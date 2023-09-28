<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class RestaurantChangeRequestModel extends Model 
{
    protected $table = "restaurant_change_request";
    protected $fillable = [
        'adId', 'userId', 'details', 'status', 'ip', 'timestamp', 'requestType'
    ];
    public $timestamps = false;
    protected $connection = EAT_DB_CONNECTION_NAME;
}