<?php

namespace App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class StripePaymentFailedNotifications extends Model
{
    protected $fillable = [
        'restaurantId',
        'totalNotificationSent',
        'failureReason',
        'paymentFailedUniqueId',
        'paymentType',
        'isActive',
        'createdOn',
    ];

    protected $table = 'payment_failed_notifications';
    protected $primaryKey = 'paymentFailedNotificationId';
    public $timestamps = false;
    protected $connection = EAT_DB_CONNECTION_NAME;
}

?>