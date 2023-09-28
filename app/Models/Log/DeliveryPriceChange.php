<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class DeliveryPriceChange extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'userId',
        'restaurantId',
        'details',
        'createdOn',
        'ip'
    ];
    protected $table = 'delivery_price_changes';
    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
    protected $primaryKey = 'delveryPriceChangeId';
}

?>