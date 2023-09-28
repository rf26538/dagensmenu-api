<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Model;
class PostcodePopulation extends Model
{
    protected $table = "postcode_populations";
    protected $primaryKey = "postcodePopulationId";
    public $timestamps = false;
    protected $connection = LOCATION_DB_CONNECTION_NAME;
}