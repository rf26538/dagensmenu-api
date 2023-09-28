<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Model;
class PlaceModel extends Model{
    protected $table = "Places";
    public $timestamps = false;
    protected $connection = LOCATION_DB_CONNECTION_NAME;

}