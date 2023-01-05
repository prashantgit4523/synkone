<?php

namespace App\Models\DocumentAutomation;

use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\DataScope\BaseModel;
use App\Models\UserManagement\Admin;
use App\Models\ThirdPartyRisk\Vendor;
use App\Models\ThirdPartyRisk\Project;
use Illuminate\Support\Facades\Storage;
use App\Models\Compliance\ProjectControl;
use App\Models\Controls\KpiControlStatus;
use App\Helpers\SystemGeneratedDocsHelpers;
use App\Models\RiskManagement\RiskRegister;
use App\Models\GlobalSettings\GlobalSetting;
use App\Models\ThirdPartyRisk\QuestionAnswer;
use App\Models\RiskManagement\Project as RiskProject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixScore;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixImpact;
use App\Models\RiskManagement\RiskMatrix\RiskScoreLevelType;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixLikelihood;
use App\Models\Administration\OrganizationManagement\Organization;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixAcceptableScore;

class ControlDocument extends BaseModel
{
    use HasFactory;

    protected $appends = ['meta', 'is_generated', 'data_scope'];

    const MARKERS = [
        'name' => '[Organization Name]'
    ];

    const FILE_SYSTEM = 'filesystems.default';

    const DYNAMIC_MARKERS = [
        '[statement-of-applicability]' => 'getStatementOfApplicability',
        '[risk-management-report]' => 'getRiskManagementReport',
        '[kpi-dashboard]' => 'getKpiDashboard',
        '[acceptable-risk-score]' => 'getAcceptableRiskScore',
        '[probability-scale]' => 'getProbabilityScale',
        '[impact-scale]' => 'getImpactScale',
        '[overall-risk-level-categorization]' => 'getOverallRiskCategorization',
        '[overall-risk-level-calculation]' => 'getOverallRiskLevelCalculation',
        '[tpr-assessment-report]' => 'getTprAssessmentReport',
        '[impact-category-count]' => 'getImpactCategoryCount',
    ];

    protected $with = ['admin:first_name,last_name,id'];

    protected $fillable = ['title', 'admin_id', 'body', 'auto_saved_content', 'description', 'version', 'status', 'document_template_id', 'created_at'];

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDrafts($query)
    {
        return $query->where('status', 'draft');
    }

    public function getDataScopeAttribute(): ?string
    {
        if(!$this->scope)
            return null;

        return $this->scope->organization_id . '-' . ($this->scope->department_id ?? '0');
    }

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function getMetaAttribute(): ?array
    {
        $documents = SystemGeneratedDocsHelpers::getSystemGeneratedDocuments();
        $name = $this->template->name;

        if (array_key_exists($name, $documents)) {
            return $documents[$name][0];
        }

        return null;
    }

    public function getIsGeneratedAttribute(): bool
    {
        return $this->template->is_generated;
    }

    public function getCompanyLogo()
    {
        $file_system = config(self::FILE_SYSTEM);

        $logo = GlobalSetting::first()->company_logo;

        if ('assets/images/ebdaa-Logo.png' === $logo) {
            return asset($logo);
        }

        if ($file_system === 's3' && env('TENANCY_ENABLED')){
            return $logo;
        }
        
        if ($file_system === 'local') {
            if (env('TENANCY_ENABLED')) {
                return tenant_asset($logo);
            }
            return asset('/storage' . $logo);
        }

        return Storage::url('public' . $logo);
    }

    public function setUpdatedAt($value)
    {
        return null;
    }

    public function getBodyAttribute($value)
    {
        $result = $value;

        // replace organization name
        $result = str_ireplace(self::MARKERS['name'], Organization::first()->name, $result);

        //pull the markers
        $pattern = "/\[\S+\]/i";

        if (preg_match_all($pattern, $result, $matches)) {
            $document_markers = $matches[0];

            $search = [];
            $replace = [];

            foreach (array_keys(self::DYNAMIC_MARKERS) as $dynamic_marker) {
                if (
                    in_array($dynamic_marker, $document_markers)
                    && method_exists($this, self::DYNAMIC_MARKERS[$dynamic_marker])
                ) {
                    $search[] = $dynamic_marker;
                    $replace[] = '<span style="background-color: white">' . $this->{self::DYNAMIC_MARKERS[$dynamic_marker]}() . '</span>';
                }
            }

            $result = str_ireplace($search, $replace, $result);
        }

        //update aws image url
        if (config(self::FILE_SYSTEM) === 's3' && preg_match_all('/< *img[^>]*src *= *["\']?([^"\']*)/i', $result, $matches)) {
            foreach($matches[1] as $img){
                if(Str::contains($img, 'X-Amz-Signature')){
                    $image = explode('?',explode('/public',$img)[1]);
                        
                    $disk = Storage::disk('s3');
                    $imageFullLink = $disk->getAwsTemporaryUrl($disk->getDriver()->getAdapter(),'public'.$image[0], Carbon::now()->addMinutes(5), []);
                        
                    $result = str_replace($img,$imageFullLink,$result);
                }
            }
        }

        return $result;
    }

    public function getAutoSavedContentAttribute($value)
    {
        $result = $value;

        //update aws image url
        if ($result && config(self::FILE_SYSTEM) === 's3' && preg_match_all('/< *img[^>]*src *= *["\']?([^"\']*)/i', $result, $matches)) {
            foreach($matches[1] as $img){
                if(Str::contains($img, 'X-Amz-Signature')){
                    $image = explode('?',explode('/public',$img)[1]);
                        
                    $disk = Storage::disk('s3');
                    $imageFullLink = $disk->getAwsTemporaryUrl($disk->getDriver()->getAdapter(),'public'.$image[0], Carbon::now()->addMinutes(5), []);
                        
                    $result = str_replace($img,$imageFullLink,$result);
                }
            }
        }

        return $result;
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }


    // these are used to render the dynamic content in automated docs
    public function getAcceptableRiskScore(): int
    {
        return RiskMatrixAcceptableScore::first()->score;
    }

    public function getProbabilityScale(): string
    {
        $header = SystemGeneratedDocsHelpers::generateTableHeaderFromArray(['Level', 'Probability']);
        $rows = [];

        RiskMatrixLikelihood::all()->each(function ($l) use (&$rows) {
            $rows[] = [$l->index + 1, $l->name];
        });

        $body = SystemGeneratedDocsHelpers::generateTableBodyFromArray($rows);

        return SystemGeneratedDocsHelpers::generateHTMLTable($header, $body);
    }

    public function getStatementOfApplicability(): string
    {
        $header = SystemGeneratedDocsHelpers::generateTableHeaderFromArray([
            'Applicable',
            'Control ID',
            'Control Name',
            'Control Description',
            'Implementation Status',
            'Justification for inclusion',
            'Justification for exclusion'
        ]);
        $rows = [];

        $applicable_text = "Selected because the control is required by relevant regulations or laws, risks have been identified for this control, and it is necessary to ensure effective information security in the organization.";
        $not_applicable_text = "This control does not apply to the organization as discovered during the risk assessment.";

        ProjectControl::each(function ($control) use ($not_applicable_text, $applicable_text, &$rows) {
            $rows[] = [$control->applicable ? 'Yes' : 'No', $control->controlId, $control->name, $control->description, $control->status, $control->applicable ? $applicable_text : '', !$control->applicable ? $not_applicable_text : ''];
        });

        $body = SystemGeneratedDocsHelpers::generateTableBodyFromArray($rows);

        return SystemGeneratedDocsHelpers::generateHTMLTable($header, $body);
    }

    public function getRiskManagementReport(): string
    {
        $header = SystemGeneratedDocsHelpers::generateTableHeaderFromArray([
            'Risk ID',
            'Name',
            'Description',
            'Affected function(s)/asset(s)',
            'Affected property(ies)',
            'Likelihood',
            'Impact',
            'Inherent Risk Score',
            'Treatment Option',
            'Control',
            'Treatment Description',
            'Risk Custodian',
            'Risk Owner',
            'Treatment Due Date',
            'Status',
            'Residual Risk Score',
            'Risk Value',
        ]);

        $project_ids = RiskProject::pluck('id')->toArray();
        $rows = [];

        if(!empty($project_ids))
        {
            $risks = RiskRegister::with('controls', 'controls.responsibleUser', 'controls.approverUser', 'owner', 'custodian')
                ->whereIn('project_id',$project_ids)
                ->get();

            $i = 0;
            foreach ($risks as $risk) {
                $mappedControl = $risk->controls()->first();

                $mappedControlResponsibeUserFullName = $mappedControl ? ($mappedControl->responsible ? $mappedControl->responsibleUser->full_name : '') : '';
                $mappedControlApproverUserFullName = $mappedControl ? ($mappedControl->approver ? $mappedControl->approverUser->full_name : '') : '';

                $risk_owner = $risk->owner ? $risk->owner->full_name : null;
                $risk_owner = $risk_owner ?: $mappedControlResponsibeUserFullName;

                $risk_custodian = $risk->custodian ? $risk->custodian->full_name : null;
                $risk_custodian = $risk_custodian ?: $mappedControlApproverUserFullName;

                $affected_functions_or_assets = implode(',',collect($risk->affected_functions_or_assets)->map(function($asset){
                    return $asset['label'];
                })->toArray());

                $rows[] = [
                    ++$i,
                    $risk->name,
                    $risk->risk_description,
                    $affected_functions_or_assets,
                    $risk->affected_properties,
                    $risk->likelihood,
                    $risk->impact,
                    $risk->inherent_score,
                    $risk->treatment_options,
                    $mappedControl ? $mappedControl->name : '',
                    $risk->treatment,
                    $risk_custodian,
                    $risk_owner,
                    $mappedControl ? $mappedControl->deadline : '',
                    $risk->status,
                    $risk->residual_score,
                    $risk->ResidualRiskScoreLevel ? $risk->ResidualRiskScoreLevel->name : '',
                ];
            }
        }

        $body = SystemGeneratedDocsHelpers::generateTableBodyFromArray($rows);
        return SystemGeneratedDocsHelpers::generateHTMLTable($header, $body);
    }

    public function getImpactScale(): string
    {
        $header = SystemGeneratedDocsHelpers::generateTableHeaderFromArray(['Level', 'Impact Level']);
        $rows = [];

        RiskMatrixImpact::all()->each(function ($l) use (&$rows) {
            $rows[] = [$l->index + 1, $l->name];
        });

        $body = SystemGeneratedDocsHelpers::generateTableBodyFromArray($rows);

        return SystemGeneratedDocsHelpers::generateHTMLTable($header, $body);
    }


    public function getOverallRiskLevelCalculation(): string
    {
        $riskMatrixLikelihoods = RiskMatrixLikelihood::orderBy('id', 'desc')->select('id', 'name', 'index')->get();
        $riskMatrixImpacts = RiskMatrixImpact::orderBy('id')->select('id', 'name', 'index')->get();
        $riskMatrixScores = RiskMatrixScore::orderBy('likelihood_index', 'desc')
            ->orderBy('impact_index', 'asc')->select(['id', 'score', 'impact_index', 'likelihood_index'])->get()->split(count($riskMatrixLikelihoods));
        $riskScoreLevelTypes = RiskScoreLevelType::with(['levels' => function ($query) {
            $query->orderBy('max_score', 'desc');
            $query->select('id', 'name', 'max_score', 'color', 'level_type');
        }])->where('is_active', '1')->select(['id', 'level', 'is_active'])->first();

        $body = '';
        $riskMatrixLikelihoods->each(function ($l, $lk) use (&$body, $riskMatrixScores, $riskMatrixImpacts, $riskScoreLevelTypes) {
            $body .= sprintf("
                    <tr>
                        <td>%s</td>", $l->name);    // For Probability names
            $riskMatrixImpacts->each(function ($i, $ik) use ($lk, &$body, $riskMatrixScores, $riskScoreLevelTypes) {
                $color = '';
                $riskScoreLevelTypes->levels->each(function ($t) use ($lk, $ik, $riskMatrixScores, &$color) {
                    if ($riskMatrixScores[$lk][$ik]->score <= $t->max_score)
                        $color = $t->color;
                });

                if ($color == '')
                    $color = $riskScoreLevelTypes->levels[count($riskScoreLevelTypes->levels) - 1]->color;

                $body .= sprintf("
                                    <td style='background-color: %s;'>%d</td>
                            ", $color, $riskMatrixScores[$lk][$ik]->score);
            });
            $body .= sprintf("
                    </tr>");
        });

        // For Impact names
        $body .= sprintf("
                    <tr>
                        <td>&nbsp;</td>");
        $riskMatrixImpacts->each(function ($i) use (&$body) {
            $body .= sprintf("
                                    <td>%s</td>
                            ", $i->name);
        });
        $body .= sprintf("
                    </tr>");

        return sprintf("
            <div style='position: relative; margin: 0 0 40px 40px;'>
                <div style='display: flex; align-items:center;'>
                    <h4 style='position:absolute; left:-60px; -webkit-transform: rotate(-90deg);top: 20px;'>Probability</h4>
                    <table style='text-align:center;width:100%%;'>
                        <tbody>
                        %s
                        </tbody>
                    </table>
                    <h4 style='position:absolute; bottom:-60px; left:60%%;'>Impact</h4>
                </div>
            </div>
        ", $body);
    }

    public function getOverallRiskCategorization(): string
    {
        $riskMatrixMaxScore = RiskMatrixScore::max('score');
        $riskScoreLevelTypes = RiskScoreLevelType::with(['levels' => function ($query) {
            $query->select('id', 'name', 'max_score', 'color', 'level_type');
        }])->where('is_active', '1')->select(['id', 'level', 'is_active'])->first();

        $body = '';
        $min = 0;
        $max = 0;

        $riskScoreLevelTypes->levels->each(function ($l) use (&$body, &$min, &$max, $riskMatrixMaxScore) {
            if ($l->max_score == null)
                $max = $riskMatrixMaxScore;
            else
                $max = $l->max_score;

            $body .= sprintf("
                    <tr>
                        <td style='background-color: %s;'>%s</td>
                        <td>%d - %d</td>
                    </tr>", $l->color, $l->name, $min, $max);

            $min = $l->max_score + 1;
        });

        return sprintf("
            <table style='text-align:center;width:100%%;'>
                <thead>
                    <tr>
                        <th>Risk Level</th>
                        <th>Risk Value Range</th>
                    </tr>     
                </thead>
                <tbody>
                %s
                </tbody>
            </table>
        ", $body);
    }

    public function getImpactCategoryCount()
    {
        $impact_value = RiskScoreLevelType::where('is_active',1)->first();
        $values = collect([
            '3'=>'three',
            '4'=>'four',
            '5'=>'five',
            '6'=>'six',
        ]);
        $impact_value_text= $values->get($impact_value->level);
        return "&nbsp;$impact_value_text";
    }

    public function getTprAssessmentReport(): string
    {
        $latestThreeProjects = Project::orderBy('id', 'desc')
            ->take(3)
            ->get()
            ->map(function($row) {
                if(!empty($row->projectVendor) && !is_null($row->projectVendor->vendor))
                {
                    return $row;
                }
            })
            ->pluck('id');

        $vendors = Project\ProjectVendor::whereHas('projects', function($query) use($latestThreeProjects)
        {
            $query->whereIn('id', $latestThreeProjects);
        })->orderByDesc('score')->get();

        $latestAnsweredProject = QuestionAnswer::latest('id')->pluck('project_id')->first();
        $project = Project::with(['questionnaire', 'vendor:id,name,score'])->find($latestAnsweredProject);
        $data = [];
        if($project)
        {
            $appTimezone = GlobalSetting::query()->first('timezone')->timezone;
            $project['launch_date'] = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $project->launch_date, 'UTC')->setTimezone($appTimezone);
            $project['due_date'] = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $project->due_date, 'UTC')->setTimezone($appTimezone);
            $data = $project->questionnaire?->questions()
                ?->with(['single_answer' => function ($q) use ($project) {
                    $q->where('project_id', $project->id)->latest();
                }])->get();

            if(empty($data)){
                $data = [];
            }
        }

        $levels = [
            [
                'level' => 1,
                'color' => '#ff0000',
                'name' => 'Level 1',
                'count' => $vendors->where('level', 1)->count()
            ],
            [
                'level' => 2,
                'color' => '#ffc000',
                'name' => 'Level 2',
                'count' => $vendors->where('level', 2)->count()
            ],
            [
                'level' => 3,
                'color' => '#ffff00',
                'name' => 'Level 3',
                'count' => $vendors->where('level', 3)->count()
            ],
            [
                'level' => 4,
                'color' => '#92d050',
                'name' => 'Level 4',
                'count' => $vendors->where('level', 4)->count()
            ],
            [
                'level' => 5,
                'color' => '#00b050',
                'name' => 'Level 5',
                'count' => $vendors->where('level', 5)->count()
            ],
        ];

        $top_vendors = $vendors;
        return view('third-party-risks.control-document-trp-assessment', compact('levels', 'top_vendors', 'project', 'data'))->render();
    }

    public function getKpiDashboard()
    {
        $rows = [];
        $header = SystemGeneratedDocsHelpers::generateTableHeaderFromArray([
            'Control Id',
            'Name',
            'Description',
            'Current Target (%)',
            'Achieved (%)',
            'Status'
        ]);

        KpiControlStatus::with('kpi_mapping.control')->each(function ($kpi_control) use (&$html, &$rows) {
            $kpi_control_mapping = $kpi_control->kpi_mapping;
            $control = $kpi_control_mapping->control;
            $control_id = "$control->primary_id" . "$control->id_separator" . "$control->sub_id";

            $target = null;
            $status = null;

            if ($kpi_control_mapping->targets) {
                $target = json_decode($kpi_control_mapping->targets, true);
                $target = $target[date('Y')];
            }

            if ($target) {
                if ($kpi_control->per >= $target) {
                    $status = "Passed";
                } else {
                    $status = "Failed";
                }
            }

            $rows[] = [$control_id, $control->name, $kpi_control_mapping->description, $target ?: "N/A", $kpi_control->per, $status ?: "N/A"];
        });

        $body = SystemGeneratedDocsHelpers::generateTableBodyFromArray($rows);

        return SystemGeneratedDocsHelpers::generateHTMLTable($header, $body);
    }
}
