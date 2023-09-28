<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class DeliveryPrice extends Model
{
    protected $primaryKey = 'deliveryPriceId';
    protected $table = "delivery_prices";
    protected $fillable = [
        'deliveryPrice',
        'userId',
        'price',
        'restaurantId',
        'postcodeStart',
        'postcodeEnd',
        'createdOn',
        'updatedOn',
        'status',
        'ip',
    ];
    protected $hidden = ['userId', 'ip', 'status', 'createdOn', 'updatedOn'];

    protected $connection = EAT_DB_CONNECTION_NAME;
    public $timestamps = false;
}

?>