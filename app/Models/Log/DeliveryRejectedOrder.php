<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class DeliveryRejectedOrder extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'userId',
        'restaurantId',
        'postcode',
        'orderTotalPrice',
        'createdOn',
        'ip',
    ];

    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
    protected $table = "delivery_rejected_orders";
    protected $primaryKey = 'deliveryRejectedOrderId';
}

?>