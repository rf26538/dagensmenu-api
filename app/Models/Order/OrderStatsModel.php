<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderStatsModel extends Model
{
    protected $table = 'orders_stats';
    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
}
