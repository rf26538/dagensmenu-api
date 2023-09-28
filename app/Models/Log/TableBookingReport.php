<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class TableBookingReport extends Model
{
    protected $connection = EAT_LOG_DB_CONNECTION_NAME;
	protected $table = "table_booking_restaurants_stats";
    public $timestamps = false;
}
