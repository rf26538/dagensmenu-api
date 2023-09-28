<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class NoLocalStorageLog extends Model
{
    protected $fillable = array('userAgent', 'createdOn', 'ip');
    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
    public $timestamps = false;
}
