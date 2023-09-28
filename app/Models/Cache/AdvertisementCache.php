<?php

namespace App\Models\Cache;

use Illuminate\Database\Eloquent\Model;

class AdvertisementCache extends Model
{
    public $timestamps = false;
    protected $table = 'advertisement_caches';
    protected $primaryKey = 'advertisementCacheId';
    protected $connection = EAT_CACHE;
    protected $fillable =  ['restaurantId', 'restaurantMenuItems', 'restaurantInformation'];
}

?>