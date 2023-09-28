<?php

namespace App\Models\TableBooking;
use Illuminate\Database\Eloquent\Model;

class TableBookingClosedDateModel extends Model 
{
    protected $fillable = array('restaurantId', 'closedDate', 'closedTimeFrom', 'closedTimeTo', 'createdOn', 'ip', 'userId', 'reason', 'isDeleted');
    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $table = "table_booking_closed_dates";
    protected $primaryKey = "tableBookingclosedDateId";
    public $timestamps = false;
}
