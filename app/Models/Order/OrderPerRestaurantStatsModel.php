<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderPerRestaurantStatsModel extends Model
{
    protected $table = 'orders_per_restaurant_stats';
    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
}
