<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class RestaurantSubscription extends Model
{
    protected $fillable = ['advertisementSubscriptionPlan'];
    protected $hidden = ['ip', 'createdOn', 'id', 'userId'];
    public $timestamps = false;
}
