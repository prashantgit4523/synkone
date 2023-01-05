<?php

namespace App\Models\PolicyManagement\Campaign;

use App\Casts\CustomCleanHtml;
use App\Models\Compliance\Evidence;
use App\Models\Compliance\ProjectControl;
use App\Models\DataScope\BaseModel;
use App\Models\DataScope\Scopable;
use Illuminate\Support\Facades\Log;

class Campaign extends BaseModel
{
    protected $table = 'policy_campaigns';
    protected $fillable = ['name', 'owner_id', 'launch_date', 'auto_enroll_users', 'due_date', 'timezone', 'campaign_type', 'acknowledgement_email_sent'];

    protected $appends = ['owner_name', 'owner_department_name'];

    protected $casts = [
        'name'    => CustomCleanHtml::class,
        'launch_date'    => CustomCleanHtml::class,
        'auto_enroll_users'    => CustomCleanHtml::class,
        'due_date'    => CustomCleanHtml::class,
        'timezone'    => CustomCleanHtml::class,
        'acknowledgement_email_sent'    => CustomCleanHtml::class,
    ];

    /**
     * The policies that belong to the campaign.
     */
    public function policies()
    {
        return $this->hasMany('App\Models\PolicyManagement\Campaign\CampaignPolicy', 'campaign_id');
    }

    /**
     * The groups that belong to the campaign.
     */
    public function groups()
    {
        return $this->hasMany('App\Models\PolicyManagement\Campaign\CampaignGroup', 'campaign_id');
    }

    /**
     * The users that belong to the campaign.
     */
    public function users()
    {
        return $this->hasManyThrough(
            CampaignGroupUser::class,
            CampaignGroup::class,
            'campaign_id', // Foreign key on the intermediate table...
            'group_id' // Foreign key on the final table...
        );
    }

    /**
     * The acknowledgements that belong to the campaign.
     */
    public function acknowledgements()
    {
        return $this->hasMany('App\Models\PolicyManagement\Campaign\CampaignAcknowledgment', 'campaign_id');
    }

    /**
     * gets campaing activities.
     */
    public function activities()
    {
        return $this->hasMany('App\Models\PolicyManagement\Campaign\CampaignActivity', 'campaign_id');
    }

    /**
     * Get campaign owner.
     */
    public function owner()
    {
        return $this->belongsTo('App\Models\UserManagement\Admin', 'owner_id');
    }

    public function department()
    {
        return $this->morphOne(Scopable::class, 'scopable');
    }

    public function getOwnerNameAttribute(){
        return $this->owner?->fullName;
    }

    public function getOwnerDepartmentNameAttribute(){
        return $this->owner?->department_name;
    }

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($campaign) {
            Log::info('A policy campaign was deleted', ['id' => $campaign->id, 'type' => $campaign->campaign_type]);
            //revert all awareness control to normal
            if($campaign->campaign_type == 'awareness-campaign'){
                $allProjectControls = ProjectControl::withoutGlobalScopes()->where('automation','awareness')->get();
                foreach($allProjectControls as $eachControl){
                    $eachControl->update([
                        'status'      => 'Not Implemented',
                        'deadline'    => now()->addDays(7)->format('Y-m-d'),
                        'is_editable' => true,
                        'frequency'   => 'One-Time'
                    ]);
                }
                $control_ids = ProjectControl::withoutGlobalScopes()->where('automation','awareness')->pluck('id')->toArray();
                Evidence::withoutGlobalScopes()->whereIn('project_control_id', $control_ids)->delete();
            }
        });
    }
}
