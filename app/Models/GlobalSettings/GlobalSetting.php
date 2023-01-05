<?php

namespace App\Models\GlobalSettings;

use App\Casts\CustomCleanHtml;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GlobalSetting extends Model
{
    protected $table = 'account_global_settings';
    protected $guarded = ['id'];

    protected $casts = [
        'display_name' => CustomCleanHtml::class,
        'primary_color' => CustomCleanHtml::class,
        'secondary_color' => CustomCleanHtml::class,
        'default_text_color' => CustomCleanHtml::class,
    ];

    public function getCompanyLogoAttribute($value)
    {
        if(config('filesystems.default') == 's3'){
        
            if($value=='assets/images/ebdaa-Logo.png'){
                $url= $value;
            }
            else{
                
                // $disk = Storage::disk('s3');
                // $url = $disk->getAwsTemporaryUrl($disk->getDriver()->getAdapter(), 'public'.$value, Carbon::now()->addMinutes(5), []);

                // fix to revert
                $disk = Storage::disk('s3-public');
                $url = $disk->url($value);

            }
            return $url;
        } else {
            return $value;
        }
    }

    public function getFaviconAttribute($value)
    {
        if (config('filesystems.default') == 's3') {
            if ($value == 'assets/images/cyberarrow-favicon.png' || $value == 'assets/images/ebdaa-Logo.png') {
                $url = $value;
            } else {
                $disk = Storage::disk('s3');
                $url = $disk->getAwsTemporaryUrl($disk->getDriver()->getAdapter(), 'public' . $value, Carbon::now()->addMinutes(5), []);
            }
            return $url;
        } else {
            return $value;
        }
    }
}
