<?php

namespace App\Traits\Compliance;

use App\Models\Compliance\Evidence;
use App\Models\Compliance\ProjectControl;
use App\Models\Integration\IntegrationControl;
use App\Models\PolicyManagement\Campaign\Campaign;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgment;
use Illuminate\Support\Facades\Storage;

trait ComplianceHelpers
{
    public function getControlEvidences($control_id, $linked_mode = false)
    {
        $control = ProjectControl::withoutGlobalScopes()->with('control_evidences')->findOrFail($control_id);

        if ($control->automation === 'none' && $control->control_evidences()->where('type', 'control')->exists()) {
            $parent_id = $control->control_evidences()->where('type', 'control')->first()->path;
            return $this->getControlEvidences($parent_id, true);
        }

        if ($control->automation === 'document') {
            $results = [
                $control->control_document,
                ...$control->control_evidences()->where('type', 'additional')->get()
            ];
        } elseif ($control->automation === 'technical') {
            $results = [
                ...$this->getTechnicalControlEvidences($control, $linked_mode),
                ...$control->control_evidences()->where('type', 'additional')->get()
            ];
        } elseif ($control->automation === 'awareness') {
            $campaign = Campaign::withoutGlobalScopes()->where('campaign_type', 'awareness-campaign')->latest()->first();
            $results = [
                ...$control->control_evidences()->where('type', 'additional')->get()
            ];

            if ($campaign) {
                $campaign_acknowledgment_count = CampaignAcknowledgment::where('campaign_id', $campaign->id)->where('status', 'completed')->count();
                if ($campaign_acknowledgment_count > 0) {
                    $evidence = new Evidence();
                    $evidence->name = 'Native Awareness';
                    $evidence->type = 'awareness';
                    $evidence->campaignId = $campaign->id;

                    $results = [
                        $evidence,
                        ...$control->control_evidences()->where('type', 'additional')->get()
                    ];
                }
            }
        } else {
            // none
            $results = [...$control->control_evidences()->whereNotIn('type', ['additional', 'json'])->get()];
        }

        return collect($results)->map(function ($e) use ($linked_mode) {
            $e['is_linked'] = $linked_mode;
            if ($linked_mode && isset($e->type) && $e['type'] === 'additional') {
                $e['type'] = 'document';
            }
            return $e;
        });
    }

    public function getTechnicalControlEvidences($control, $is_linked = false)
    {
        return IntegrationControl::with(['integration_actions', 'integration_actions.integration_provider', 'integration_actions.integration_provider.integration'])
            ->where('primary_id', $control->primary_id)
            ->where('sub_id', $control->sub_id)
            ->firstWhere('standard_id', $control->project->of_standard->id)
            ->integration_actions()
            ->whereNotNull('is_compliant')
            ->get()
            ->map(function ($action) use ($is_linked) {
                return [
                    'id' => $action->id,
                    'type' => 'json',
                    'name' => 'JSON Evidence',
                    'is_linked' => $is_linked,
                    'title' => $action->action_name . ' on ' . $action->integration_provider->integration->name,
                    'logo_link' => $action->integration_provider->integration->logo_link,
                    'last_response' => $action->pivot->last_response,
                    'text_evidence' => $action->pivot->last_response
                ];
            })
            ->toArray();
    }

    public function getImplementedControlActions($control, $linked_mode = false)
    {
        if ($control->automation === 'none' && $control->control_evidences()->where('type', 'control')->exists()) {
            $parent_id = $control->control_evidences()->where('type', 'control')->first()->path;
            return $this->getImplementedControlActions(ProjectControl::withoutGlobalScopes()->findOrFail($parent_id), true);
        }

        $textEvidencesActions = [];
        $linkEvidencesActions = [];
        $controlEvidencesActions = []; // redundant
        $documentEvidencesActions = [];

        $documentAutomatedActions = [];
        $technicalAutomatedActions = [];
        $awarenessAutomatedActions = [];

        if ($control->automation === 'document') {
            $documentAutomatedActions[] = route('documents.export', [
                'id' => $control->control_document->document_template_id,
                'data_scope' => $control->scope->organization_id . '-' . ($control->scope->department_id ?? 0)
            ]);
            $documentAutomatedActions[] =  $control->control_document->title;
        } else if ($control->automation === 'awareness') {
            $awareness_campaign = Campaign::withoutGlobalScopes()->where('campaign_type', 'awareness-campaign')->latest()->first();
            if($awareness_campaign){
                $awarenessAutomatedActions[] = route('policy-management.campaigns.export-awareness-pdf', [
                    'id' => $awareness_campaign->id,
                    'data_scope' => $control->scope->organization_id . '-' . ($control->scope->department_id ?? 0)
                ]);
            }
            $awarenessAutomatedActions[] = 'Awareness Campaign';
        } else if ($control->automation === 'technical') {
            $technicalAutomatedActions[] = $this->getTechnicalControlEvidences($control, $linked_mode);
        } else {
            $textEvidencesActions = [...$control->control_evidences()->where('type', 'text')->get()];
            $linkEvidencesActions = [...$control->control_evidences()->where('type', 'link')->get()];
        }

        $documentEvidences = $control->control_evidences()
            ->when($control->automation === 'none', function ($query) {
                $query->where('type', 'document');
            })
            ->when($control->automation !== 'none', function ($query) {
                $query->whereIn('type', ['document', 'additional']);
            });
            
        foreach($documentEvidences->get() as $evidence){
            $evidence->route = route('compliance.implemented-controls.download-individual-evidences', [$control->id, $evidence->id]);
            $evidence->extention = pathinfo(parse_url(storage_path($evidence->path))['path'], PATHINFO_EXTENSION);

            $encryptedContents = Storage::get($evidence->path);
            $evidence->path = base64_encode(decrypt($encryptedContents));
            $documentEvidencesActions[] = $evidence;
        }

        return [
            $textEvidencesActions,
            $linkEvidencesActions,
            $controlEvidencesActions,
            $documentEvidencesActions,
            $documentAutomatedActions,
            $technicalAutomatedActions,
            $awarenessAutomatedActions,
            [$control->automation]
        ];
    }
}