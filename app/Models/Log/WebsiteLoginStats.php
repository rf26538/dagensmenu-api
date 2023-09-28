<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class WebsiteLoginStats extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'totalLogin',
        'uniqueLogin',
        'createdOn'
    ];

    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
    protected $table = "website_login_stats";
}
