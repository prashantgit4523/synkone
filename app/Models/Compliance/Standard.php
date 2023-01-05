<?php

namespace App\Models\Compliance;

use App\Casts\CustomCleanHtml;
use Database\Factories\ComplianceStandardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Standard extends Model
{
    use HasFactory;

    protected $table = 'compliance_standards';
    protected $fillable = ['category_id', 'name', 'slug', 'description', 'logo', 'version', 'is_default'];
    protected $casts = [
        'name'    => CustomCleanHtml::class,
        'version'    => CustomCleanHtml::class,
    ];
    protected $appends = ['logo_link','automation'];

    public function controls()
    {
        return $this->hasMany(StandardControl::class, 'standard_id');
    }

    public function projects()
    {
        return $this->hasMany('App\Models\Compliance\Project', 'standard_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return ComplianceStandardFactory::new();
    }

    public function getAutomationAttribute()
    {
        $isAutomated = $this->controls()->whereIn('automation', ['technical', 'document'])->exists();
        return $isAutomated ? 'Automated' : 'Automation Coming Soon';
    }

    public function getLogoLinkAttribute()
    {
        return asset('assets/images/standards/' . $this->logo);
    }
}
