<?php

namespace App\Models\EatAutomatedCollection;

use Illuminate\Database\Eloquent\Model;

class OurRestaurantGoogleInformation extends Model
{
    public $timestamps = false;
    protected $table = 'our_restaurant_google_informations';
    protected $primaryKey = 'ourRestaurantGoogleInformationId';
    protected $connection = EAT_AUTOMATED_COLLECTION;
}

?>