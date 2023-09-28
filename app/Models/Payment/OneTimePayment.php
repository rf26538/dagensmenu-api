<?php
namespace App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class OneTimePayment extends Model {

    protected $fillable = [
        'userId',
        'adId',
        'paymentIntentId',
        'stripePaymentMethod',
        'amount',
        'durationInDays',
        'uniqueId',
        'paymentStatus',
        'lastPaymentSuccessfulOn',
        'ip',
        'createdOn',
        'updatedOn'
    ];

    protected $hidden = ['userId', 'ip', 'paymentStatus', 'createdOn', 'updatedOn'];

    protected $connection = EAT_DB_CONNECTION_NAME;
    public $timestamps = false;
    protected $table = 'one_time_payments';
    protected $primaryKey = 'oneTimePaymentId';
    
}