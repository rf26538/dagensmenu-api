<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderMenuItem extends Model
{
    protected $fillable = array('orderId', 'menuItemId', 'priceOfMenuItem', 'totalPriceWithOptions');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $primaryKey = "orderMenuItemId";
    public $timestamps = false;

    public function orderMenuItemOptions() {
        return $this->hasMany('App\Models\Order\OrderMenuItemOption', 'orderMenuItemId');
    }

    public function menuItem() {
        return $this->hasOne('App\Models\Order\MenuItem', 'menuItemId', 'menuItemId');
    }

    public function size() {
        return $this->hasOne('App\Models\Order\Size', 'sizeId', 'sizeId');
    }
}
