<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;
class FeedbackModel extends Model{
    protected $table = "feedbacks";
    public $timestamps = false;
    protected $fillable = ['userType','name', 'email', 'countryCode','phone','restaurantName', 'postCode', 'adId', 'message', 'ip', 'createdOn', 'status', 'replyContent', 'replyOn', 'replyBy', 'replyType', 'replyInfo', 'restaurantTelephoneNumber', 'feedbackRestaurantId', 'feedbackImages', 'feedbackPdfs', 'restaurantOpeningTimings' ];
    protected $connection = EAT_DB_CONNECTION_NAME;

    public function userDetail() 
    {
        return $this->hasOne('App\Models\User', 'uid', 'replyBy');
    }
    
}