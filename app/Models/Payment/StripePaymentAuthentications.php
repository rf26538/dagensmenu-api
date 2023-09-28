<?php

namespace App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class StripePaymentAuthentications extends Model
{
    
    protected $fillable = [
        'userId', 
        'restaurantId',
        'paymentIntentClientSecret',
        'authenticationResponse',
        'isAuthenticationSuccessfull',
        'paymentDetailId',
        'ip',
        'createdOn',
    ];
    
    protected $table = 'stripe_payment_authentications';
    protected $primaryKey = 'stripePaymentAuthenticationId';
    public $timestamps = false;
    protected $connection = EAT_DB_CONNECTION_NAME;
    
}

?>