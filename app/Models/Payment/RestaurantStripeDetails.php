<?php

namespace App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class RestaurantStripeDetails extends Model
{
    
    protected $fillable = [
        'userId', 
        'restaurantId',
        'stripeCustomerId',
        'paymentIntentClientSecretId',
        'paymentMethod',
        'paymentType',
        'status',
        'ip',
        'createdOn',
    ];
    
    protected $table = 'restaurant_stripe_details';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $connection = EAT_DB_CONNECTION_NAME;
    
}

?>