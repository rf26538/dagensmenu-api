<?php

namespace App\Models\EatAutomatedCollection;

use Illuminate\Database\Eloquent\Model;

class VirkRestaurantInformation extends Model
{
    public $timestamps = false;
    protected $table = 'virk_restaurant_informations';
    protected $primaryKey = 'virkRestaurantInformationId';
    protected $connection = EAT_AUTOMATED_COLLECTION;
}

?>