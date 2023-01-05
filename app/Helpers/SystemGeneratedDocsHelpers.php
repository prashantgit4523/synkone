<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Compliance\ProjectControl;
use App\Models\Controls\KpiControlStatus;
use App\Models\DocumentAutomation\DocumentTemplate;
use App\Models\RiskManagement\Project;
use App\Models\ThirdPartyRisk\Project as ThirdPartyRiskProject;

use function route;

class SystemGeneratedDocsHelpers
{

    public static function getSystemGeneratedDocuments(
        $explicit_data_scope = false,
        $organization_id = null,
        $department_id = null
    ): array
    {
        return [
            'Statement of Applicability' => [
                null,
                fn() => true
            ],
            'Risk Management Report' => [
                [
                    'suffix' => 'please create a new risk management project.',
                    'route' => route('risks.projects.projects-create'),
                    'allowed_roles' => ['Risk Administrator']
                ],
                fn() => Project::query()
                        ->when($explicit_data_scope, function ($q) use ($department_id, $organization_id) {
                            $q->whereHas('scope', function ($q) use ($department_id, $organization_id) {
                                $q
                                    ->where('organization_id', $organization_id)
                                    ->where('department_id', $department_id);
                            });
                        })
                        ->count() > 0
            ],
            'Third-Party Risk Assessment Report' => [
                [
                    'suffix' => 'please create a new third-party risk project.',
                    'route' => route('third-party-risk.projects.index'),
                    'allowed_roles' => ['Third Party Risk Administrator']
                ],
                fn() => ThirdPartyRiskProject::query()
                        ->when($explicit_data_scope, function ($q) use ($department_id, $organization_id) {
                            $q->whereHas('scope', function ($q) use ($department_id, $organization_id) {
                                $q
                                    ->where('organization_id', $organization_id)
                                    ->where('department_id', $department_id);
                            });
                        })
                        ->count() > 0
            ],
            'Performance Evaluation Report' => [
                [
                    'suffix' => 'please use the KPI module.',
                    'route' => route('kpi.index')
                ],
                fn() => KpiControlStatus::count() > 0
            ],
            'Risk Management Methodology' => [
                null,
                fn() => true
            ]
        ];
    }

    public static function checkSystemGeneratedDocuments(
        $explicit_data_scope = false,
        $organization_id = null,
        $department_id = null
    ): void
    {
        foreach (self::getSystemGeneratedDocuments($explicit_data_scope, $organization_id, $department_id) as $name => $value) {
            $document = self::getDocument($name);

            if (!$document) {
                continue;
            }

            if ($value[1]()) {
                ProjectControl::query()
                    ->where('document_template_id', $document->id)
                    ->where('applicable', true)
                    ->where('automation', 'document')
                    ->where('status', '!=', 'Implemented')
                    ->when($explicit_data_scope, function ($q) use ($department_id, $organization_id) {
                        $q->whereHas('scope', function ($q) use ($department_id, $organization_id) {
                            $q
                                ->where('organization_id', $organization_id)
                                ->where('department_id', $department_id);
                        });
                    })
                    ->each(fn($control) => $control->update([
                        'status' => 'Implemented',
                        'deadline' => date('Y-m-d'),
                        'frequency' => 'Annually',
                        'responsible' => Auth::id() ?? $control->project?->admin_id,
                        'is_editable' => false
                    ]));
            } else {
                ProjectControl::query()
                    ->where('document_template_id', $document->id)
                    ->where('applicable', true)
                    ->where('automation', 'document')
                    ->when($explicit_data_scope, function ($q) use ($department_id, $organization_id) {
                        $q->whereHas('scope', function ($q) use ($department_id, $organization_id) {
                            $q
                                ->where('organization_id', $organization_id)
                                ->where('department_id', $department_id);
                        });
                    })
                    ->each(fn($control) => $control->update([
                        'status' => 'Not Implemented',
                        'responsible' => self::getResponsible($name),
                        'is_editable' => true
                    ]));
            }
        }
    }

    private static function getResponsible($document): ?int
    {
        $user = Auth::user();

        if($user) {
            // if the user is global admin, assign him to everything
            // or else, check the required roles + Compliance Administrator role
            $values = self::getSystemGeneratedDocuments()[$document][0];

            if($user->hasRole('Global Admin') || (array_key_exists('allowed_roles', $values) && $user->hasAllRoles(['Compliance Administrator', ...$values['allowed_roles']]))){
                return $user->id;
            }
        }

        return null;
    }

    private static function getDocument(string $name): ?Model
    {
        return DocumentTemplate::query()
            ->where('is_generated', true)
            ->firstWhere('name', $name);
    }

    public static function generateTableHeaderFromArray(array $columns): string
    {
        return '<thead><tr>' . implode("\n", array_map(fn($column) => '<th>' . $column . '</th>', $columns)) . '</tr></thead>';
    }

    public static function generateHTMLTable(string $header, string $body): string
    {
        return sprintf('
            <table style="width: 100%%;">
                %s
                %s
            </table>
        ', $header, $body);
    }

    public static function generateTableBodyFromArray(array $rows): string
    {
        $body = '<tbody>';
        $rowsArray = [];

        foreach ($rows as $row) {
            $html = '<tr>';
            $html .= implode("\n", array_map(fn($v) => '<td>' . (is_array($v) ? implode(', ', array_map(fn($e) => $e['value'], $v)) : $v ) . '</td>', $row));
            $html .= '</tr>';

            $rowsArray[] = $html;
        }

        $body .= implode("\n", $rowsArray);

        return $body . '</tbody>';
    }

    public static function setKPIControlsNotImplemented(): void
    {
        $document = DocumentTemplate::query()
            ->firstWhere('name', 'Performance Evaluation Report');

        if ($document) {
            ProjectControl::query()
                ->where('document_template_id', $document->id)
                ->where('automation', 'document')
                ->where('applicable', true)
                ->each(fn($control) => $control->update([
                    'status' => 'Not Implemented',
                    'responsible' => Auth::id() ?? $control->project?->admin_id
                ]));
        }
    }
}
