<?php

namespace App\Http\Controllers\Integration;

use App\CustomProviders\Interfaces\IAssetProvider;
use App\Http\Controllers\Controller;
use App\Mail\Integration\Disconnect;
use App\Models\Compliance\Project;
use App\Models\Compliance\ProjectControl;
use App\Models\Compliance\StandardControl;
use App\Models\Integration\Integration;
use App\Models\Integration\IntegrationAction;
use App\Models\Integration\IntegrationCategory;
use App\Models\Integration\IntegrationControl;
use App\Models\Integration\IntegrationProvider;
use App\Models\RiskManagement\RiskRegister;
use App\Models\UserManagement\Admin;
use App\Utils\RegularFunctions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class IntegrationController extends Controller
{
    /**
     * @var IntegrationCategory
     */
    private $category;
    /**
     * @var Integration
     */
    private $integration;

    /**
     * @param IntegrationCategory $category
     * @param Integration $integration
     */
    public function __construct(IntegrationCategory $category, Integration $integration)
    {
        $this->category = $category;
        $this->integration = $integration;
    }

    public function index()
    {
        $categories = $this->category->with(['integrations' => function ($query) {
            $query->with(['provider' => function ($query1) {
                $query1->select('id', 'name', 'previous_scopes_count', 'protocol', 'required_fields')->get();
            }])->select('id', 'category_id', 'name', 'logo', 'description', 'connected', 'slug', 'provider_id');
        }])->orderBy('order_number')->select(['id', 'name'])->get();

        $categories->prepend(new Collection([
            'id' => 0,
            'name' => 'All Categories',
            'integrations' => $this->integration->with(['provider' => function ($query) {
                $query->select('id', 'name', 'previous_scopes_count', 'protocol', 'required_fields')->get();
            }])->select('id', 'category_id', 'name', 'logo', 'description', 'connected', 'slug', 'provider_id')->get()
        ]));

        return inertia('integrations/Integrations', compact('categories'));
    }

    public function disconnect(Request $request)
    {
        $service = Integration::with(['provider', 'provider.integration_actions', 'provider.integration_controls'])->findOrFail($request->id);

        DB::transaction(function () use (&$service) {
            if ($service->provider->protocol === 'custom') {
                $required_fields = json_decode($service->provider->required_fields, true);

                foreach ($required_fields['fields'] as $key => $field) {
                    if (array_key_exists('value', $required_fields['fields'][$key])) {
                        $required_fields['fields'][$key]['value'] = '';
                    }
                }

                $service->provider->update(['required_fields' => $required_fields]);
            }

            // remove token details
            $service->provider->update(['accessToken' => null, 'refreshToken' => null, 'subscriptionId' => null, 'tokenExpires' => null]);

            // delete the assets (if any)
            $service->provider->assets()->delete();

            //reset manual user auth_method to Manual when office 365, google cloud identity and okta is disconnected
            if (in_array($service->slug, ['office-365', 'google-cloud-identity', 'okta'])) {
                Admin::where('is_manual_user', 1)->update(['auth_method' => 'Manual', 'is_sso_auth' => 0]);
            }

            // reset the intermediate table values
            $action_ids = $service->provider->integration_actions()->pluck('id')->toArray();

            DB::table('integration_action_integration_control')
                ->whereIn('integration_action_id', $action_ids)
                ->update([
                    'is_compliant' => null,
                    'last_response' => null
                ]);

            // disconnect the integration
            $service->update(['connected' => 0]);

            // grab all the controls tied to that disconnected provider
            $service->provider->integration_controls()->each(function ($control) {
                $should_implement = $control
                    ->integration_actions()
                    ->wherePivot('is_compliant', '<>', null)
                    ->wherePivot('is_compliant', false)
                    ->doesntExist()
                    && $control
                    ->integration_actions()
                    ->wherePivot('is_compliant', '<>', null)
                    ->wherePivot('is_compliant', true)
                    ->exists();

                $standardControl = StandardControl::query()
                    ->where('standard_id', $control->standard_id)
                    ->where('primary_id', $control->primary_id)
                    ->where('sub_id', $control->sub_id)->first();

                $automationType = $standardControl->document_template_id ? 'document' : 'none';

                StandardControl::query()
                    ->where('standard_id', $control->standard_id)
                    ->where('primary_id', $control->primary_id)
                    ->where('sub_id', $control->sub_id)->update([
                        'automation' => $should_implement ? 'technical' : $automationType
                    ]);

                $projectControls = ProjectControl::query()
                    ->whereHas('project.of_standard', fn ($q) => $q->where('id', $control->standard_id))
                    ->where('primary_id', $control->primary_id)
                    ->where('sub_id', $control->sub_id)
                    ->with('evidences')
                    ->where('manual_override', 0)
                    ->where('automation', 'technical')
                    ->get();

                // remove linked controls
                foreach ($projectControls as $projectControl) {
                    if ($projectControl->childLinkedControls()->count()) {
                        $ids = $projectControl->childLinkedControls()->pluck('id');
                        ProjectControl::whereIn('id', $ids)->update([
                            'status' => $should_implement ? 'Implemented' : 'Not Implemented'
                        ]);
                    }
                }

                // added because the projectControl observer won't listen to mass updates
                RiskRegister::whereHas('controls', function ($q) use ($projectControls) {
                    $q
                        ->whereIn('control_id', $projectControls->pluck('id'))
                        ->where('status', 'Not Implemented');
                })->each(function ($mappedRisk) {
                    $mappedRisk->update([
                        'status' => 'Open',
                        'treatment_options' => 'Mitigate',
                        'residual_score' => $mappedRisk->inherent_score,
                    ]);
                });

                ProjectControl::query()
                    ->whereIn('id', $projectControls->pluck('id'))
                    ->each(function ($control) use ($should_implement, $automationType) {
                        $control->update([
                            'status' => $should_implement ? 'Implemented' : 'Not Implemented',
                            'automation' => $should_implement ? 'technical' : $automationType,
                            'responsible' => !$should_implement ? null : $control->responsible ?? $control->project?->admin_id,
                            'approver' => null,
                            'manual_override' => 0,
                            'is_editable' => !$should_implement
                        ]);
                    });
            });
        });

        //        //Notify project creator of the fact that controls in the project are no longer automated and manual action is required.
        //        $affectedProjects = array_unique(array_merge(...$projectControlUniqueIds));
        //
        //        $projects = Project::whereIn('id', $affectedProjects)->with('admin')->get();
        //        $admins = collect();
        //        foreach ($projects as $project) {
        //            $admin = $project->admin;
        //            if (!$admin) {
        //                continue;
        //            }
        //
        //            if ($admins->where('id', $admin->id)->count() === 0) {
        //                $adminProjects = collect();
        //                $adminProjects->push($project);
        //                $admin->setAttribute('projects', $adminProjects);
        //                $admins->push($admin);
        //            } else {
        //                $admins->where('id', $admin->id)->first()->projects->push($project);
        //            }
        //        }
        //
        //        foreach ($admins as $admin) {
        //            Mail::to($admin->email)->send(new Disconnect($admin, $service));
        //        }
        //
        //        callArtisanCommand('technical-control:api-map');
        callArtisanCommand('kpi_controls:update');

        return redirect()->route('integrations.index')->withSuccess("{$service->name} service is now disconnected.");
    }

    public function checkErrorCode(Request $request)
    {
        if ($request->code === 'cancel') {
            $msg = 'You declined to consent to access the app.';
        } elseif ($request->code === 'invalidUrl') {
            $msg = 'Response url is invalid. Please try again.';
        } else {
            $msg = 'Invalid error code.';
        }

        return redirect()->route('integrations.index')->withError($msg);
    }

    public function authenticate($id, Request $request)
    {
        $provider = IntegrationProvider::findOrFail($id);
        $required_fields = json_decode($provider->required_fields, true);

        $validation = [];
        foreach ($required_fields['fields'] as $field) {
            $validation[$field['name']] = $field['validator'];
        }

        $request->validate($validation);

        $class = config('integrations.' . $provider->integration->slug);
        $handler = new $class();

        $authenticated = $handler->attempt($request->toArray());

        if ($authenticated) {
            if ($handler instanceof IAssetProvider) {
                $provider
                    ->integration
                    ->category()
                    ->update(['updated_at' => null]);

                // executing shell script without waiting for it
                callArtisanCommand('assets:fetch');
            }

            return redirect()->back()->withSuccess(sprintf('%s was connected successfully.', $provider->integration->name));
        }

        return redirect()->back()->withError(sprintf('Authenticating with %s failed.', $provider->integration->name));
    }
}
