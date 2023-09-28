<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class Benchmark extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'url',
        'startTime',
        'endTime',
        'source',
        'payload',
        'verb',
        'createdOn',
    ];

    protected $table = 'benchmarks';
    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
    protected $primaryKey = 'benchmarkId';
}

?>