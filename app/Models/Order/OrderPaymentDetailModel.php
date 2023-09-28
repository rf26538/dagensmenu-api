<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderPaymentDetailModel extends Model {

    protected $fillable = [
        'orderId',
        'userId',
        'restaurantId',
        'orderDetails',
        'paymentUniqueId',
        'paymentRequestData',
        'paymentResponseData',
        'refundRequestData',
        'refundResponseData',
        'paymentStatus',
        'paymentRequestOn',
        'paymentResponseOn',
        'refundRequestOn',
        'refundResponseOn',
        'paymentIntentId',
        'ip',
        'createdOn'
    ];
    protected $table = 'order_payment_details';
    protected $hidden = ['ip', 'createdOn'];
    public $timestamps = false;
    public $primaryKey = 'orderPaymentDetailId';

    protected $connection = EAT_ORDER_DB_CONNECTION_NAME;
}
?>
