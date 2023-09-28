<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model 
{
    protected $table = "features";
    public $timestamps = false;
    protected $connection = EAT_DB_CONNECTION_NAME;
}