<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderMenuItemOption extends Model
{
    protected $fillable = array('orderMenuItemId', 'optionId', 'optionItemId', 'price', 'quantity', 'totalPriceOfQuantities');

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $primaryKey = "orderMenuItemOptionId";
    public $timestamps = false;

    public function optionItem() {
        return $this->hasOne('App\Models\Order\OptionItem', 'optionItemId', 'optionItemId');
    }
}
