<?php

namespace App\Models\EatAutomatedCollection;

use Illuminate\Database\Eloquent\Model;

class GoogleRestaurantInformation extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'comment',
        'commentBy',
        'commentOn',
        'restaurantCreationIgnoredOn', 
        'restaurantCreationIgnoredBy', 
    ];
    protected $table = 'google_restaurant_informations';
    protected $primaryKey = 'googleRestaurantId';
    protected $connection = EAT_AUTOMATED_COLLECTION;

    public function userDetail() 
    {
        return $this->hasOne('App\Models\User', 'uid', 'commentBy');
    }
}

?>