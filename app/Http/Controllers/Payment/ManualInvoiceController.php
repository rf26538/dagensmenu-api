<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Models\Payment\ManualInvoice;
use App\Shared\EatCommon\Helpers\IPHelpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Payment\PaymentInvoice;

class ManualInvoiceController extends Controller
{
    private $paymentInvoice;
    
    public function __construct(PaymentInvoice $paymentInvoice, IPHelpers $ipHelpers)
    {
        $this->ipHelpers = $ipHelpers;
        $this->paymentInvoice = $paymentInvoice;
    }

    public function save(Request $request)
    {
        $rules = [
            'amount' => 'required|int',
            'totalAmount' => 'required|int',
            'moms' => 'required|int',
            'advId' => 'required|int|min:1',
            'startDate' => 'required|date_format:Y-m-d',
            'endDate' => 'required|date_format:Y-m-d',
        ];

        $validator = Validator::make($request->post(), $rules);

        if ($validator->fails())
        {
            throw new Exception('ManualInvoiceController save error', $validator->errors()->first());
        }

        $invoiceNumber = $this->paymentInvoice->getMaxInvoiceNumber(); 

        $manualInvoice = new ManualInvoice();
        $manualInvoice->moms = $request->post('moms');
        $manualInvoice->advId = $request->post('advId');
        $manualInvoice->amount = $request->post('amount');
        $manualInvoice->invoiceNumber = $invoiceNumber;
        $manualInvoice->paymentEndDate = strtotime($request->post('endDate'));
        $manualInvoice->totalAmount = $request->post('totalAmount');
        $manualInvoice->description = !empty($request->post('description')) ? $request->post('description') : '';
        $manualInvoice->paymentStartDate = strtotime($request->post('startDate'));
        $manualInvoice->userId = Auth::id();
        $manualInvoice->ip = $this->ipHelpers->clientIpAsLong();
        $manualInvoice->paymentDate = time();
        $manualInvoice->createdOn = time();
        $manualInvoice->save();
        
        return response()->json(new BaseResponse(true, null, []));
    }
}

?>