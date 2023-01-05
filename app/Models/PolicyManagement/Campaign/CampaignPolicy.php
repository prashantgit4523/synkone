<?php

namespace App\Models\PolicyManagement\Campaign;

use App\Models\PolicyManagement\Policy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CampaignPolicy extends Model
{
    protected $table = 'policy_campaign_policies';
    protected $fillable = ['policy_id', 'display_name', 'type', 'path', 'version', 'description'];
    protected $appends = ['ext', 'data_scope', 'base_pdf'];

    public function getExtAttribute()
    {
        if ($this->type == "doculink") {
            return "";
        }

        $ext = pathinfo(parse_url(storage_path($this->path))['path'], PATHINFO_EXTENSION);

        return $ext;
    }

    public function getPathAttribute($value){
        if(config('filesystems.default') == 's3'){
            if($this->type=='doculink' || $this->type=='automated'){
                return $value;
            }
            else{
                $disk = Storage::disk('s3');
                return $disk->getAwsTemporaryUrl($disk->getDriver()->getAdapter(), 'public/'.$value, Carbon::now()->addMinutes(25), []);
            }
        }
        else{
            return $value;
        }
       
    }

    public function getDataScopeAttribute(): ?string
    {
        if($this->type === 'automated'){
            $policy = Policy::findOrFail($this->policy_id)->scope;

            return $policy->organization_id . '-' . ($policy->department_id ?? '0');
        }

        return null;
    }


    public function getBasePdfAttribute(): ?string
    {
        $ext = pathinfo(parse_url(storage_path($this->getRawOriginal('path')))['path'], PATHINFO_EXTENSION);

        if($ext==="pdf" && env('APP_ENV_REGION')=="KSA"){
            $file=Storage::get('public/'.$this->getRawOriginal('path'));
            $encoded_file=base64_encode($file);
            return $encoded_file;
        }
        else{
            return null;
        }
        
    }
}
