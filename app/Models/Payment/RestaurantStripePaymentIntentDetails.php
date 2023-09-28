<?php

namespace App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class RestaurantStripePaymentIntentDetails extends Model
{
    protected $fillable = [
        'userId', 
        'restaurantId',
        'stripeCustomerId',
        'paymentIntentClientSecretId',
        'paymentIntentId',
        'createCustomerRequest',
        'createCustomerResponse',
        'createPaymentIntentRequest',
        'createPaymentIntentResponse',
        'registerCardSuccessResponse',
        'registerCardFailureResponse',
        'status',
        'ip',
        'createdOn',
    ];

    protected $table = 'restaurant_stripe_payment_intent_details';
    protected $primaryKey = 'restaurantStripePaymentIntentDetailsId';
    public $timestamps = false;
    protected $connection = EAT_DB_CONNECTION_NAME;
}

?>