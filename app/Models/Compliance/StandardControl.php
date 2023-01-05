<?php

namespace App\Models\Compliance;

use App\Casts\CustomCleanHtml;
use App\Models\Controls\KpiControlStatus;
use App\Models\DocumentAutomation\DocumentTemplate;
use Illuminate\Database\Eloquent\Model;

class StandardControl extends Model
{
    protected $table = 'compliance_standard_controls';
    protected $fillable = ['index', 'name', 'standard_id', 'slug', 'description', 'primary_id', 'sub_id', 'id_separator', 'required_evidence'];
    protected $appends = ['controlId', 'idSeparators'];

    protected $casts = [
        'index'    => CustomCleanHtml::class,
        'name'    => CustomCleanHtml::class,
        'description'    => CustomCleanHtml::class,
        'required_evidence'    => CustomCleanHtml::class,
        'primary_id'    => CustomCleanHtml::class,
        'sub_id'    => CustomCleanHtml::class,
    ];

    public function standard()
    {
        return $this->belongsTo(Standard::class, 'standard_id');
    }

    public function getControlIdAttribute()
    {
        $controlId = null;

        if (!is_null($this->id_separator)) {
            $separatorId = ($this->id_separator == '&nbsp;') ? ' ' : $this->id_separator;

            $controlId = $this->primary_id . $separatorId . $this->sub_id;
        } else {
            $controlId = $this->primary_id . $this->sub_id;
        }

        return $controlId;
    }

    public function kpiControlStatuses(){
        return $this->hasMany(KpiControlStatus::class, 'control_id', 'id');
    }

    public function getIdSeparatorsAttribute()
    {
        return [
            '.' => 'Dot Separated',
            '&nbsp;' => 'Space Separated',
            '-' => 'Dash Separated',
            ',' => 'Comma Separated',
        ];
    }

    /**
     * Get the non breaking space if value is space.
     *
     * @return string
     */
    public function getIdSeparatorAttribute($value)
    {
        if ($value == ' ') {
            return '&nbsp;';
        }

        return $value;
    }

    /**
     * Set the description.
     *
     * @param  string  $value
     * @return void
     */
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] =  preg_replace('/_x([0-9a-fA-F]{4})_/', '', $value);
    }

    /**
     * Set the id_separator to space if value is non-breaking space.
     *
     * @param string $value
     *
     * @return void
     */
    public function setIdSeparatorAttribute($value)
    {
        if ($value == '&nbsp;') {
            $this->attributes['id_separator'] = ' ';
        } else {
            $this->attributes['id_separator'] = $value;
        }
    }

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id', 'id');
    }
}
