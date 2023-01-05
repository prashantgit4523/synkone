<?php

namespace App\Models\GlobalSettings;

use Illuminate\Database\Eloquent\Model;

class SmtpProvider extends Model
{
    protected $table = 'smtp_providers';

    protected $fillable = ['access_token', 'refresh_token', 'token_expires', 'connected', 'from_address', 'from_name'];
}
