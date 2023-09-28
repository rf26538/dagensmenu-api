<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class DuplicateAdvertisement extends Model
{
    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
    protected $table = 'duplicate_advertisements';
    public $timestamps = false;
}

?>