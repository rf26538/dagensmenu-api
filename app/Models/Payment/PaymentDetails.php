<?php

namespace App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class PaymentDetails extends Model
{
    protected $fillable = [
        'order_id', 
        'ad_id',
        'user_id',
        'amount',
        'moms',
        'request_data',
        'response_data',
        'payment_status',
        'payment_type',
        'payment_request_date',
        'payment_response_date',
        'payment_start_date',
        'payment_end_date',
        'profile_type',
        'durationInDays',
        'claimed_restaurant_id',
        'stripe_invoice_id',
        'meta', 
        'invoiceNumber',
        'paymentType',
        'paymentIntentClientSecretId'
    ];

    public $timestamps = false;
    protected $table = 'payment_details';
    protected $primaryKey = 'payment_id';
    

    public function restaurant() 
    {
        return $this->hasOne('App\Models\Restaurant\Advertisement', 'id', 'ad_id');
    }
}

?>