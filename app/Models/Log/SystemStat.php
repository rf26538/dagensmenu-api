<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class SystemStat extends Model
{
    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
    protected $table = 'system_stats';
    public $timestamp = false;
}

?>