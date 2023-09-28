<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderOnlineTemporaryCloseTiming extends Model
{
    protected $fillable = array('restaurantId', 'orderType', 'intervalInMinutes', 'fullDayClose', 'reopenOn');
    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
    protected $table = 'order_online_temporary_close_timings';
    protected $primaryKey = "orderOnlineTemporaryCloseTimingId";
    protected $hidden = ['userId', 'createdOn', 'status', 'ip'];
    public $timestamps = false;
}
