<?php

namespace App\Models\EatAutomatedCollection;

use Illuminate\Database\Eloquent\Model;

class OrganicRestaurantInformation extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'restaurantCreationIgnoredReason',
        'restaurantCreationIgnoredOn', 
        'restaurantCreationIgnoredBy',
        'organicRestaurantName',
        'dagensmenuIdUpdatedBy' 
    ];
    protected $table = 'organic_restaurant_informations';
    protected $primaryKey = 'organicRestaurantInformationId ';
    protected $connection = EAT_AUTOMATED_COLLECTION;

    public function userDetail() 
    {
        return $this->hasOne('App\Models\User', 'uid', 'restaurantCreationIgnoredBy');
    }
}

?>