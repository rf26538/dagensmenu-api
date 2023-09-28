<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class WebsiteRegistrationStats extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'dagensmenu',
        'google',
        'facebook',
        'createdOn',
    ];

    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
    protected $table = "website_registration_stats";
}
