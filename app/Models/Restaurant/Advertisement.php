<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    protected $fillable = array('creation_date', 'updation_date', 'author_id', 'menuCardImage', 'organicLevel',
    'enableFutureOrder', 'isAutoPrintEnabled', 'lastInfoUpdatedOn', 'menuImages', 'createdBy', 'ownerName', 'hasSittingPlaces',
    'reviewersCount', 'menuCardPDFFiles', 'redirectRestaurantId', 'postcode', 'placesId', 'menuCardPdfCollection',
    'isDeliveyPriceAllowedForAllPostcodes', 'areDeliveryPostcodePricesPresent', 'companyCVR', 'CardsSupported',
    'isTestingRestaurant', 'isPromotionalRestaurant', 'title', 'urlTitle', 'companyName', 'summary', 'sittingPlaces',
    'extra', 'phoneNumber', 'address', 'status', 'advertisement_type', 'imageFolder', 'city', 'regionId','serviceDomainName', 'service',
    'hasDelivery', 'hasTakeaway', 'foodTypes', 'registrationStep', 'cityUrl');
    
    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $table = "advertisement";
    protected $primaryKey = "id";
    protected $hidden  = ["geoPoint"];
    public $timestamps = false;

    public function advertisementFrontImage() {
        return $this->hasMany('App\Models\Restaurant\AdvertisementImages', 'adv_id', 'id');
    }

    public function advertisementMenuCardUpdatedBy()
    {
        return $this->hasOne('App\Models\User', 'uid', 'menuCardUpdatedBy');
    }

    public function postcodePopulation()
    {
        return $this->hasOne('App\Models\Location\PostcodePopulation', 'postcode', 'postcode');
    }

    public function virkRestaurantInformation()
    {
        return $this->hasOne('App\Models\EatAutomatedCollection\VirkRestaurantInformation', 'ourRestaurantId', 'id');
    }

    public function ourRestaurantGoogleInformation()
    {
        return $this->hasOne('App\Models\EatAutomatedCollection\OurRestaurantGoogleInformation', 'ourRestaurantId', 'id');
    }
    
    public function advertisementImages() {
        return $this->hasMany('App\Models\Restaurant\AdvertisementImages', 'adv_id', 'restaurantId');
    }

    public function advertisementUserImages() {
        return $this->hasMany('App\Models\Restaurant\UserImages', 'adId', 'restaurantId');
    }

    public function advertisementReviews() {
        return $this->hasMany('App\Models\Review\ReviewModel', 'adId', 'restaurantId');
    }

    public function deliveryPrices()
    {
        return $this->hasMany('App\Models\Order\DeliveryPrice', 'restaurantId', 'id');
    }

    public function restaurantSubscription()
    {
        return $this->hasOne('App\Models\Restaurant\RestaurantSubscription', 'restaurantId', 'id');
    }
    
    public function advertisementUserName()
    {
        return $this->hasOne('App\Models\User', 'uid', 'author_id');
    }
    
    public function advertisementFeatures()
    {
        return $this->hasMany('App\Models\Restaurant\AdvertisementFeatures', 'advId', 'id');
    }

    public function working()
    {
        return $this->hasMany('App\Models\Restaurant\Working', 'adv_id', 'id');
    }
}
