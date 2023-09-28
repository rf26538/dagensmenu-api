<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class AdvertisementFeatures extends Model 
{
    protected $table = "advertisement_features";
    public $timestamps = false;
    protected $fillable = ['advId', 'featureId'];
    protected $connection = EAT_DB_CONNECTION_NAME;
}