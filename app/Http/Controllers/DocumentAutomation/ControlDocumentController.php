<?php

namespace App\Http\Controllers\DocumentAutomation;

use App\Models\DataScope\DataScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Utils\RegularFunctions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Compliance\Evidence;
use Illuminate\Support\Facades\File;
use App\Models\PolicyManagement\Policy;
use Illuminate\Support\Facades\Storage;
use App\Models\Compliance\ProjectControl;
use Illuminate\Support\Facades\Validator;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use App\Models\DocumentAutomation\ControlDocument;
use App\Models\DocumentAutomation\DocumentTemplate;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgment;
use Illuminate\Support\Facades\Auth;

class ControlDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Inertia\Response
     * @throws \Exception
     */
    public function show($id, Request $request)
    {
        if(!str_contains(url()->previous(), 'documents') || str_contains(url()->previous(), '/show/tasks'))
        {
            \Session::put('backUrl', url()->previous());
        }
        
        if(\Session::has('backUrl')) {
            $fromUrl = \Session::get('backUrl');
        }
        else {
            $fromUrl = url()->previous();
        }
        $request->validate([
            'control' => 'required_without:policy|exists:compliance_project_controls,id',
            'policy' => 'required_without:control|exists:policy_policies,id'
        ]);

        if($request->filled('control')){
            $model = ProjectControl::withoutGlobalScope(new DataScope())->findOrFail($request->input('control'));
        }else{
            $model = Policy::withoutGlobalScope(new DataScope())->findOrFail($request->input('policy'));
        }

        $target_data_scope = $this->getDataScopeFromModel($model);

        if($request->control){
            $request->validate([
                'control' => 'bail|required|exists:compliance_project_controls,id'
            ]);
        }
        
        $control = ProjectControl::query()
            ->when($request->filled('control'), function ($q) use ($request){
                $q->withoutGlobalScope(new DataScope())->where('id', $request->input('control'));
            })
            ->where('automation', 'document')
            ->where('document_template_id', $id)
            ->where('responsible', auth()->user()->id)
            ->whereHas('scope', function ($q) use ($target_data_scope) {
                $q
                    ->where('organization_id', $target_data_scope['organization_id'])
                    ->where('department_id', $target_data_scope['department_id']);
            })
            ->first();

        if(!$control || $control->template?->is_generated){
            return redirect()->back()->withError('You aren\'t allowed to access the document, because you\'re not a responsible.');
        }

        return Inertia::render('document-automation/documents/Show', [
            'document_template_id' => $id,
            'target_data_scope' => $target_data_scope['value'],
            'from' => $fromUrl
        ]);
    }

    public function getJsonData(Request $request, $id)
    {
        $document_template = DocumentTemplate::where('is_generated', false)->findOrFail($id);
        $control_document = $document_template->latest;
        
        if ($request->input('version')) {
            $version = (int)$request->version == $request->version ? intval($request->version) : $request->version;
            $control_document = $document_template->versions()->firstWhere('version', $version);
        }

        return response()->json([
            'control_document' => $control_document,
            'versions' => $document_template->versions->pluck('version')
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $template = DocumentTemplate::findOrFail($id);

        if ($template->versions()->where('status', 'published')->doesntExist()) {
            ProjectControl::query()
                ->where('automation', 'document')
                ->where('document_template_id', $id)
                ->update([
                    'approver' => null,
                    'responsible' => null,
                    'automation' => 'none',
                    'current_cycle' => 1,
                    'frequency' => null,
                    'deadline' => null
                ]);

            // delete the policy
            Policy::query()
                ->where('type', 'automated')
                ->where('path', $id)
                ->delete();

            //delete the versions
            $template->versions()->delete();

            return redirect()->back()->withSuccess('Document deleted successfully!');
        }

        return redirect()->back()->withError('You can\'t delete this document template because it\'s already published');
    }

    public function draft(Request $request, $document_template_id)
    {
        $request->validate([
            'body' => 'required',
            'description' => 'required',
            'title' => 'required'
        ]);

        $control_document = ControlDocument::where('document_template_id', $document_template_id)
            ->where('version', $request->selectedVersion)
            ->first();

        $document_template = DocumentTemplate::query()->findOrFail($document_template_id);
        $last_version = $document_template->versions->last();
        $previous_version = $last_version ? $last_version->version : 0.1;

        $int_and_decimal = explode('.', $previous_version);
        $int = $int_and_decimal[0];
        $decimal = $int_and_decimal[1];
        $new_decimal = intval($decimal) + 1;
        $current_version = "$int.$new_decimal";

        ControlDocument::create([
            'admin_id' => Auth()->user()->id,
            'document_template_id' => $document_template_id,
            'body' => mb_convert_encoding($request->body, 'HTML-ENTITIES', 'UTF-8'),
            'description' => $request->description,
            'status' => 'draft',
            'version' => $current_version,
            'title' => $request->title
        ]);

        //remove autosaved content of control document
        if ($control_document) {
            $control_document->update([
                'auto_saved_content' => null,
            ]);
        }

        $policy = Policy::query()->where('path', $document_template_id)->firstOrFail();
        $policy->update([
            'display_name' => $request->input('title'),
            'version' => $current_version
        ]);

        $project_controls = ProjectControl::where('automation', 'document')
            ->where('document_template_id', $document_template_id)
            ->get();

        if ($project_controls) {
            DB::transaction(function () use ($project_controls) {
                foreach ($project_controls as $project_control) {
                    $project_control->responsible = Auth()->user()->id;
                    $project_control->deadline = now()->addDays(7)->format('Y-m-d');
                    $project_control->save();
                }
            });
        }

        Log::info("User has saved a draft version of the document template.", ['document_template_id' => $document_template->id]);
        return redirect()->back()->withSuccess('Document draft was saved successfully.');
    }

    public function publish(Request $request, $document_template_id)
    {
        if(!str_contains(url()->previous(), 'documents'))
        {
            \Session::put('backUrl', url()->previous());
        }
        
        $request->validate([
            'body' => 'required',
            'description' => 'required',
            'title' => 'required'
        ]);

        $control_document = ControlDocument::where('document_template_id', $document_template_id)
            ->where('version', $request->selectedVersion)
            ->first();
        
        $document_template = DocumentTemplate::query()->findOrFail($document_template_id);
        $last_version = $document_template->versions->last();
        $previous_version = $last_version ? $last_version->version : 0.1;

        $int_and_decimal = explode('.', $previous_version);
        $int = $int_and_decimal[0];
        $new_int = intval($int) + 1;
        $current_version = "$new_int.0";

        ControlDocument::create([
            'admin_id' => Auth()->user()->id,
            'document_template_id' => $document_template_id,
            'body' => mb_convert_encoding($request->body, 'HTML-ENTITIES', 'UTF-8'),
            'description' => $request->description,
            'status' => 'published',
            'version' => $current_version,
            'title' => $request->title
        ]);

        //remove autosaved content of control document
        if ($control_document) {
            $control_document->update([
                'auto_saved_content' => null,
            ]);
        }
     
        $policy = Policy::query()->where('path', $document_template_id)->firstOrFail();
        
        $policy->update([
            'display_name' => $request->input('title'),
            'version' => $current_version
        ]);

        ProjectControl::query()
            ->where('automation', 'document')
            ->where('document_template_id', $document_template_id)
            ->where('status', 'Implemented')
            ->each(function ($control) {
                $control->update([
                    'current_cycle' => $control->current_cycle + 1
                ]);
            });
      
        $project_controls = ProjectControl::where('automation', 'document')
            ->where('document_template_id', $document_template_id)
            ->where('status', '!=', 'Implemented')
            ->get();

        if ($project_controls) {
            DB::transaction(function () use ($project_controls) {
                foreach ($project_controls as $project_control) {
                    $project_control->status = "Implemented";
                    $project_control->responsible = Auth()->user()->id;
                    $project_control->deadline = date('Y-m-d');
                    $project_control->frequency = "Annually";
                    $project_control->current_cycle = $project_control->current_cycle + 1;
                    $project_control->is_editable = false;
                    $project_control->save();
                }
            });
        }

        Log::info("User has published a version of the document template.", ['document_template_id' => $document_template->id]);

        return redirect()->back()->withSuccess('Document was published successfully.');
    }

    private function evidenceValidation($documentTemplateId) {
        $allowedRoles = [
            "Global Admin",
            "Compliance Administrator",
            "Auditor"
        ];
        if (Auth::guard('admin')->user()->hasAnyRole($allowedRoles)) {
            return true;
        }

        $directUsers = ProjectControl::withoutGlobalScope(DataScope::class)
                    ->select('id', 'responsible', 'approver')
                    ->where('document_template_id', $documentTemplateId)
                    ->get();

        $linkedControlProjects = Evidence::withoutGlobalScope(DataScope::class)
                    ->select('project_control_id')
                    ->whereIn('path', $directUsers->pluck('id')->toArray())
                    ->get();

        $linkedControlUsers = ProjectControl::withoutGlobalScope(DataScope::class)
            ->select('id', 'responsible', 'approver')
            ->whereIn('id', $linkedControlProjects->pluck('project_control_id')->toArray())
            ->get();

        $finalUsers = array_unique(
            array_merge(
                $directUsers->where('responsible', '!=', null)->pluck('responsible')->toArray(),
                $directUsers->where('approver', '!=', null)->pluck('approver')->toArray(),
                $linkedControlUsers->where('responsible', '!=', null)->pluck('responsible')->toArray(),
                $linkedControlUsers->where('approver', '!=', null)->pluck('approver')->toArray()
            ));

        return in_array(Auth::guard('admin')->user()->id, $finalUsers);
    }

    public function export(Request $request, $id)
    {
        $result = $this->evidenceValidation($id);

        if (!$result) {
            abort(404);
        }
        $landscape_only = ['Risk Management Report', 'Statement of Applicability'];

        if (!auth('admin')->check()) {
            $campaign_acknowledgment = CampaignAcknowledgment::query()
                ->where('token', $request->token)
                ->where('status', 'pending')
                ->first();


            if (
                !$campaign_acknowledgment
                || $campaign_acknowledgment->policy->version !== $request->version
            ) {
                abort(403);
            }
        }

        $document_template = DocumentTemplate::query()->findOrFail($id);

        if ($request->filled('version')) {
            $control_document = $document_template->versions()->where('version', $request->version)->firstOrFail();
        } else {
            $control_document = $document_template->latest;
        }

        $name = $control_document->version ? $control_document->title . '-' . $control_document->version : $control_document->title . '-' . date("d-m-Y");
        $document_name = $name . '.pdf';
        $pdf = PDF::loadView('document-automation.pdf-export', ['control_document' => $control_document])
            ->setOptions([
                'enable-local-file-access' => true,
                'load-error-handling' => 'ignore',
                'footer-center' => 'Classification: Internal',
                'footer-right' => '[page]',
                'enable-javascript' => true,
                'javascript-delay' => 2000,
                'enable-smart-shrinking' => true,
                'orientation' => $control_document->is_generated && in_array($control_document->title, $landscape_only) ? 'Landscape' : 'Portrait'
            ]);

        if ($request->has('download') && $request->download) {
            Log::info('User has downloaded a control document export.');

            return $pdf->download($document_name);
        }

        return $pdf->inline($document_name);
    }

    public function autoSave(Request $request)
    {
        $documentTemplateId = $request->id;
        $selectedVersion = $request->selectedVersion;

        $controlDocument = ControlDocument::where('document_template_id', $documentTemplateId)
            ->where('version', $selectedVersion)
            ->first();

        if ($controlDocument) {
            $updated = $controlDocument->update([
                'auto_saved_content' => $request->body
            ]);
        } else {
            $updated = false;
        }

        return response()->json([
            'success' => $updated
        ]);
    }

    public function removeAutoSavedContent(Request $request)
    {
        $document_template_id = $request->id;
        $selected_version = $request->selectedVersion;

        $control_document = ControlDocument::where('document_template_id', $document_template_id)
            ->where('version', $selected_version)
            ->first();

        if ($control_document) {
            $updated = $control_document->update([
                'auto_saved_content' => null,
            ]);
        } else {
            $updated = false;
        }

        return response()->json([
            'success' => $updated
        ]);
    }

    public function uploadImage(Request $request){
        $validator = Validator::make($request->all(), [
            'file' => 'required|max:10240|mimes:jpeg,png,jpg,gif'
        ], [
            'file.max' => 'The upload max filesize is 10MB. Please upload file less than 10MB.'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['error'=> $validator->errors()->first()]);
        }

        $document_template_id = intval($request->id);

        if ($request->hasFile('file')) {
            $file = $request->file;
            $fileName = uniqid().''.time(). '.' .File::extension($file->getClientOriginalName());
            
            $filePath = 'public/froala-editor-images/'.$document_template_id;
            
            // Store the file
            $file = Storage::putFileAs($filePath, $file, $fileName,'public');
            
            $pathArray = explode('/', $file);
            $pathArray[0] = '';

            $image = implode('/', $pathArray);
            
            if (config('filesystems.default') === 's3') {
                $disk = Storage::disk('s3');
                $imageFullLink = $disk->getAwsTemporaryUrl($disk->getDriver()->getAdapter(),'public'.$image, Carbon::now()->addMinutes(5), []);

                return ["link" => $imageFullLink];
            }

            if (config('filesystems.default') === 'local') {
                if (env('TENANCY_ENABLED')) {
                    return ["link" => tenant_asset($image)];
                }
                return ["link" => asset('/storage' . $image)];
            }
            
            return ["link" => Storage::url('public' . $image)];
        }
        return false;
    }

    /**
     * @throws \Exception
     */
    public function getDataScopeFromModel(Model $model): array
    {
        $data_scope = $model->scope;

        return [
            'organization_id' => $data_scope->organization_id,
            'department_id' => $data_scope->department_id,
            'value' => $data_scope->organization_id . '-' . ($data_scope->department_id ?? '0')
        ];
    }
}
