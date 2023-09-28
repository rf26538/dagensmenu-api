<?php

namespace App\Models\TableBooking;

use Illuminate\Database\Eloquent\Model;

class TableBooking extends Model
{
    protected $fillable = ['restaurantId', 'numberOfGuests', 'dateOfBooking', 'timeOfBooking', 'guestName', 'guestTelephoneNumber', 'guestEmailAddress', 'bookingStatus', 'createdOn', 'ip', 'bookingUniqueId', 'timestampOfBooking'];
    protected $connection = EAT_DB_CONNECTION_NAME;
    protected $table = "table_bookings";
    protected $primaryKey = "tableBookingId";
    public $timestamps = false;
}
