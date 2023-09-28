<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class Service extends Model 
{
    protected $table = "service";
    public $timestamps = false;
    protected $connection = EAT_DB_CONNECTION_NAME;
}