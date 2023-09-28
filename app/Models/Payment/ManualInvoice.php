<?php

namespace App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class ManualInvoice extends Model
{
    protected $table = 'manual_invoices';
    protected $primaryKey = 'manualInvoiceId';
    protected $fillable = [
        'invoiceNumber',
        'moms',
        'amount',
        'totalAmount',
        'userId',
        'adId',
        'paymentDate',
        'paymentStartDate',
        'ip',
        'createdOn',
        'paymentEndDate',
        'description',
        'notificationSent'
    ];
    
    public $timestamps = false;

    public function restaurant() 
    {
        return $this->hasOne('App\Models\Restaurant\Advertisement', 'id', 'advId');
    }
}

?>