<?php

namespace App\Models\PolicyManagement;

use App\Casts\CustomCleanHtml;
use  App\Models\DataScope\BaseModel;
use App\Models\Compliance\ProjectControl;
use App\Models\DocumentAutomation\ControlDocument;
use App\Models\DocumentAutomation\DocumentTemplate;

class Policy extends BaseModel
{
    protected $table = 'policy_policies';
    protected $fillable = ['display_name', 'type', 'path', 'version', 'description'];
    protected $appends = ['document_status','can_edit_control_document'];

    protected $casts = [
        'display_name'    => CustomCleanHtml::class,
        'version'    => CustomCleanHtml::class,
        'description'    => CustomCleanHtml::class,
        'created_at' => 'datetime:jS F y',
        'updated_at' => 'datetime:jS F y'
    ];

    public function getDocumentStatusAttribute()
    {
        if ($this->type !== 'automated' || !$this->version) {
            return '';
        }
        $point = explode('.', $this->version)[1];
        if (intval($point) === 0) {
            return 'published';
        }
        return 'draft';
    }

    public function document_template()
    {
        return $this->hasOne(DocumentTemplate::class, 'id', 'path');
    }

    public function latest_control_document()
    {
        return $this->hasOne(ControlDocument::class, 'document_template_id', 'path')->latestOfMany();
    }

    public function getCanEditControlDocumentAttribute()
    {
        $control = ProjectControl::query()
            ->where('automation', 'document')
            ->where('document_template_id', (int) $this->path)
            ->whereNotNull('responsible')
            ->where('responsible', auth()->user()->id)
            ->first();
            
        return $control && $control->count() ? true : false;
    }
}
