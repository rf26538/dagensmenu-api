<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\OrderPaymentDetailModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Validator;
class OrderPaymentDetailController extends Controller
{
    private $datetimeHelpers;

    public function __construct(DatetimeHelper $datetimeHelper)
    {
        $this->datetimeHelpers = $datetimeHelper;
    }


    public function markAsFailed(Request $request)
    {
        try
        {
            $rules = array(
                'paymentUniqueId' => 'required',
                'paymentResponseData' => 'required',
            );

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails())
            {
                throw new Exception(sprintf("OrderPaymentDetailController.markAsFailed error. %s ", $validator->errors()->first()));
            }

            $data = [
                'paymentResponseData' => $request->post('paymentResponseData'),
                'paymentResponseOn' => $this->datetimeHelpers->getCurrentTimeStamp(),
                'paymentStatus' => ORDER_ONLINE_PAYMENT_STATUS_FAILED
            ];

            OrderPaymentDetailModel::where('paymentUniqueId', $request->post('paymentUniqueId'))->update($data);
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in OrderPaymentDetailController@markAsFailed error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, true));
    }

    public function updatePaymentRequestedOn(Request $request)
    {
        try
        {
            $rules = array(
                'paymentUniqueId' => 'required',
                'paymentRequestData' => 'required',
            );

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails())
            {
                throw new Exception(sprintf("OrderPaymentDetailController.updatePaymentRequestedOn error. %s ", $validator->errors()->first()));
            }

            $data = $request->all();

            $data = [
                'paymentRequestData' => json_encode($data['paymentRequestData']),
                'paymentRequestOn' => $this->datetimeHelpers->getCurrentTimeStamp(),
                'paymentStatus' => ORDER_ONLINE_PAYMENT_STATUS_SENT
            ];

            OrderPaymentDetailModel::where('paymentUniqueId', $request->post('paymentUniqueId'))->update($data);

        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in OrderPaymentDetailController@updatePaymentRequestedOn error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, true));
    }


}
