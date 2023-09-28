<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = array('restaurantId', 'orderType', 'totalFoodPrice', 'deliveryPrice', 'orderStatus', 'deliveryAddressId', 'restaurantOwnerOrderAcceptedTime', 'isFutureOrder', 'userFutureOrderTime');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $primaryKey = "orderId";
    protected $hidden = ['ip'];
    public $timestamps = false;

    public function orderMenuItems() {
        return $this->hasMany('App\Models\Order\OrderMenuItem', 'orderId');
    }

    public function restaurant() {
        return $this->hasOne('App\Models\Restaurant\Advertisement', 'id', 'restaurantId');
    }

    public function userDetail() {
        return $this->hasOne('App\Models\User', 'uid', 'userId');
    }

    public function userDeliveryAddress() {
        return $this->hasOne('App\Models\Order\UserDeliveryAddress', 'deliveryAddressId', 'deliveryAddressId');
    }

    public function restaurantImage() {
        return $this->hasOne('App\Models\Restaurant\AdvertisementImages', 'adv_id', 'restaurantId');
    }

    public function orderStatusHistory() 
    {
        return $this->hasMany('App\Models\Order\OrderStatusHistory', 'orderId', 'orderId');
    }
}
