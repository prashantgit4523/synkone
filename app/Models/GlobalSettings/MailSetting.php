<?php

namespace App\Models\GlobalSettings;

use App\Casts\CustomCleanHtml;
use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $table = 'mail_settings';
    protected $guarded = ['id'];

    protected $casts = [
        'mail_driver' => CustomCleanHtml::class,
        'mail_host' => CustomCleanHtml::class,
        'mail_from_address' => CustomCleanHtml::class,
        'mail_from_name' => CustomCleanHtml::class,
    ];

    public function getMailPasswordAttribute($value)
    {
        try{
            return $value ? decrypt($value) : '';
        }catch(\Exception $e){
            \Log::error('Not able to decrypt the password due to APP_KEY change.');
            return '';
        }
    }
}
