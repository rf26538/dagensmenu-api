<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Payment\PaymentDetails;
use App\Models\Payment\ManualInvoice;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\Request;
use App\Shared\EatCommon\Helpers\StringHelper;

class InvoiceController extends Controller
{
    private $stringHelper;

    public function __construct(StringHelper $stringHelper)
    {
        $this->stringHelper = $stringHelper;
    }

    public function fetchInvoices(int $restaurantId)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

        if ($validator->fails())
        {
            throw new Exception('InvoiceController fetchInvoice error %s.', $validator->errors()->first());
        }

        $lastTwoYearTimestamp = strtotime('-2 year');

        $paymentDetailsColumns = ['payment_id as invoiceId', 'amount',  DB::raw("0 as totalAmount"), 'payment_response_date as paymentDate', DB::raw(sprintf("%s as invoiceType", PAYMENT_INVOICE_AUTOMATIC))];
        $paymentDetails = PaymentDetails::select($paymentDetailsColumns)->where([['ad_id', $restaurantId], ['payment_response_date', '>=', $lastTwoYearTimestamp]])->whereIn('payment_status', [PAYMENT_STATUS_TICKET_CREATED_CAPTURE_SUCCESSFUL, PAYMENT_STATUS_AUTOMATED_CAPTURE_SUCCESSFUL, PAYMENT_STATUS_TICKET_SUCCESS]);   
        
        $manualInvoiceColumns = ['manualInvoiceId as invoiceId', 'amount', 'totalAmount', 'paymentDate', DB::raw(sprintf("%s as invoiceType", PAYMENT_INVOICE_MANUAL))];
        $manualInvoice = ManualInvoice::select($manualInvoiceColumns)->where([['advId', $restaurantId], ['paymentDate', '>=', $lastTwoYearTimestamp]])->union($paymentDetails)->orderBy('paymentDate', 'desc')->get()->toArray();
        
        if (!empty($manualInvoice))
        {
            foreach ($manualInvoice as &$invoice)
            {
                $invoice['paymentDate'] = date('d-m-Y', $invoice['paymentDate']);
                $invoice['amount'] = sprintf("%s %s ( plus moms )", PRICE_CURRENCY, $invoice['amount'] / PAYMENT_CURRENCY_MULTIPLIER);
            }
        }

        $response = $manualInvoice;

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function fetchInvoice(int $paymentInvoiceId, Request $request)
    {
        $rules = [
            'paymentInvoiceId' => 'required|min:1',
            'type' => 'required|min:1'
        ];

        $queryParams = $request->query();
        $type = !empty($queryParams['type']) ? $queryParams['type'] : 0;

        $validator = Validator::make(['paymentInvoiceId' => $paymentInvoiceId, 'type' => $type], $rules);

        if ($validator->fails())
        {
            throw new Exception('InvoiceContoller fetchInvoice error', $validator->errors()->first());
        }

        if (!in_array($type, [PAYMENT_INVOICE_MANUAL, PAYMENT_INVOICE_AUTOMATIC]))
        {
            throw new Exception('Invalid type');
        }

        setlocale(LC_MONETARY, 'da_DK');

        $response = [];

        if ($type == PAYMENT_INVOICE_MANUAL)
        {
            $selectColumns = ['manualInvoiceId as invoiceId', 'invoiceNumber', 'paymentDate', 'paymentStartDate', 'paymentEndDate', 'moms', 'amount', 'totalAmount', 'description', 'advId'];
            $response = ManualInvoice::with('restaurant:id,extra,companyName,companyCvr')->select($selectColumns)->where('manualInvoiceId', $paymentInvoiceId)->get()->toArray();
        }
        else if ($type == PAYMENT_INVOICE_AUTOMATIC)
        {
            $selectColumns = ['payment_id as invoiceId', 'invoiceNumber', 'payment_response_date as paymentDate', 'payment_start_date as paymentStartDate', 'payment_end_date as paymentEndDate', 'moms', 'amount', 'ad_id'];
            $response = PaymentDetails::with('restaurant:id,extra,companyName,companyCvr')->select($selectColumns)->where('payment_id', $paymentInvoiceId)->get()->toArray();
        }

        if (!empty($response))
        {
            $response = $response[0];
            $response['paymentDate'] = date('d-m-Y', $response['paymentDate']);
            $response['paymentStartDate'] = date('d-m-Y', $response['paymentStartDate']);
            $response['paymentEndDate'] = date('d-m-Y', $response['paymentEndDate']);

            if (empty($response['totalAmount']))
            {
                $totalAmount = ($response['amount'] / CURRENCY_MULTIPLIER) + ($response['moms'] / CURRENCY_MULTIPLIER);
            
                $response['totalAmount'] = money_format('%.2n', $totalAmount);
            } 
            else
            {
                $totalAmount = $response['totalAmount'] / CURRENCY_MULTIPLIER;
                $response['totalAmount'] = money_format('%.2n', $totalAmount);
            }

            if (empty($response['description']))
            {
                $response['description'] = sprintf('%s %s', 'Annonce på ', DOMAIN_NAME);
            }

            if (!empty($response['restaurant']))
            {
                $response['restaurant']['extra'] = json_decode($response['restaurant']['extra']);
            }

            $amount = $response['amount'] / CURRENCY_MULTIPLIER;
            $response['amount'] = money_format('%.2n', $amount);

            $moms = $response['moms'] / CURRENCY_MULTIPLIER;
            $response['moms'] = money_format('%.2n', $moms);
        }

        return response()->json(new BaseResponse(true, null, $response));
    }
}

?>