<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class UserImages extends Model 
{
    protected $fillable = array('adId', 'imageName', 'imageFolder', 'userId', 'createdOn', 'width', 'height', 'ip', 'status');
    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $table = "restaurant_user_images";
    protected $primaryKey = "restaurantUserImageId";
    public $timestamps = false;
} 