<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $fillable = array('orderId', 'orderStatus');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $hidden = ['userId', 'ip'];
    public $timestamps = false;
}
