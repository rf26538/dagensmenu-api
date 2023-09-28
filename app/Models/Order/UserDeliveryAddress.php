<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class UserDeliveryAddress extends Model
{
    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $primaryKey = "deliveryAddressId";
    protected $hidden = ['userId', 'createdOn', 'isDeleted', 'ip'];
    public $timestamps = false;

    public static $rules = [
    ];
}
