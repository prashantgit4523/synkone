<?php

namespace App\Models\GlobalSettings;

use Illuminate\Database\Eloquent\Model;

class SmtpProviderAlias extends Model
{
    protected $table = 'smtp_provider_aliases';

    protected $fillable = ['smtp_provider_id', 'name', 'email', 'verificationStatus', 'selected'];
}
