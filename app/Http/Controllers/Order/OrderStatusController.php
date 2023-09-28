<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\Order;
use App\Models\Order\OrderStatusHistory;
use App\Models\Restaurant;
use App\Models\Restaurant\Advertisement;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use App\Shared\EatCommon\Payment\StripeClient;
use App\Models\Order\OrderPaymentDetailModel;
use App\Shared\EatCommon\Language\TranslatorFactory;
use Illuminate\Support\Facades\Log;
use App\Libs\Helpers\Authentication;
use Validator;
use Auth;

class OrderStatusController extends Controller
{
    private $datetimeHelpers;
    private $ipHelpers;
    private $translatorFactory;
    private $stripeClient;
    private $authentication;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, Authentication $authentication, TranslatorFactory $translatorFactory, StripeClient $stripeClient)
    {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->authentication = $authentication;
        $this->stripeClient = $stripeClient;
    }

    public function getStatusByUniqueId(String $orderUniqueId)
    {
        $this->validateOrderUniqueId($orderUniqueId);
        if (!$this->authentication->isUserAdmin()) {
            $response = Order::select('orderStatus', 'deliveryApproxTimeInSeconds', 'rejectionReasonId', 'rejectionReasonComment', 'orderType', 'isFutureOrder', 'userFutureOrderTime', 'restaurantOwnerOrderAcceptedTime')->where(['orderUniqueId' => $orderUniqueId, 'userId' => Auth::id()])->first();
        } else {
            $response = Order::select('orderStatus', 'deliveryApproxTimeInSeconds', 'rejectionReasonId', 'rejectionReasonComment', 'orderType', 'isFutureOrder', 'userFutureOrderTime', 'restaurantOwnerOrderAcceptedTime')->where(['orderUniqueId' => $orderUniqueId])->first();
        }

        if($response['isFutureOrder'] == 1 && !empty($response['restaurantOwnerOrderAcceptedTime']) && !empty($response['userFutureOrderTime']))
        {
            if($response['userFutureOrderTime'] !== $response['restaurantOwnerOrderAcceptedTime'])
            {
                $response['futureOrderAcceptedMsg'] = sprintf("%s %s. %s", $this->translatorFactory->translate('The restaurant accepts your order but due to busyness the food can only be ready'), $this->convertTimingIntoTodayAndTomorrowFormat($response['restaurantOwnerOrderAcceptedTime']), $this->translatorFactory->translate('Contact the restaurant if you want to change or cancel your order'));
            }
            else
            {
                $response['futureOrderAcceptedMsg'] = sprintf("%s %s.", $this->translatorFactory->translate('Your order has been accepted and will be ready'), $this->convertTimingIntoTodayAndTomorrowFormat($response['restaurantOwnerOrderAcceptedTime']));
            }
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function markAsAccepted(Request $request, String $orderUniqueId)
    {
        $this->validateOrderUniqueId($orderUniqueId);

        $rules = array(
            'deliveryApproxTimeInSeconds' => 'required|integer|min:1',
        );

        $validatorPost = Validator::make($request->all(), $rules);

        if ($validatorPost->fails())
        {
            throw new Exception(sprintf("OrderStatusController markAsAccepted validation error. Error %s", $validatorPost->errors()->first()));
        }

        $deliveryApproxTimeInSeconds = $request->post('deliveryApproxTimeInSeconds');

        $orderFromDb = Order::where(['orderUniqueId' => $orderUniqueId])->first();

        $this->validateUserCanUpdateOrderStatus($orderFromDb);

        if($orderFromDb->orderStatus !== ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT)
        {
            if($orderFromDb->orderStatus != ORDER_ONLINE_STATUS_ADDED && $orderFromDb->orderStatus != ORDER_ONLINE_STATUS_SEEN_BY_RESTAURANT)
            {
                throw new Exception(sprintf("OrderStatusController: Order %s can not be accepted because its current state is %s which is not ADDED", $orderUniqueId, $orderFromDb->orderStatus));
            }
    
            $orderFromDb->orderStatus = ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT;
            $orderFromDb->deliveryApproxTimeInSeconds = $deliveryApproxTimeInSeconds;
            $orderFromDb->save();
    
            $this->addOrderStatusHistory($orderFromDb->orderId, ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT);
        }

        return response()->json(new BaseResponse(true, null, true));
    }

    public function markAsSeenByRestaurant(Request $request, String $orderUniqueId)
    {
        $this->validateOrderUniqueId($orderUniqueId);

        $orderFromDb = Order::where(['orderUniqueId' => $orderUniqueId])->first();

        $this->validateUserCanUpdateOrderStatus($orderFromDb);

        if ($orderFromDb->orderStatus !== ORDER_ONLINE_STATUS_SEEN_BY_RESTAURANT)
        {
            if($orderFromDb->orderStatus != ORDER_ONLINE_STATUS_ADDED)
            {
                throw new Exception(sprintf("OrderStatusController: Order %s can not be marked as seen by restaurant because its current state is %s which is not ADDED", $orderUniqueId, $orderFromDb->orderStatus));
            }
    
            $orderFromDb->orderStatus = ORDER_ONLINE_STATUS_SEEN_BY_RESTAURANT;
            $orderFromDb->save();
    
            $this->addOrderStatusHistory($orderFromDb->orderId, ORDER_ONLINE_STATUS_SEEN_BY_RESTAURANT);
        }

        return response()->json(new BaseResponse(true, null, true));
    }

    public function markAsRejected(Request $request, String $orderUniqueId)
    {
        $this->validateOrderUniqueId($orderUniqueId);

        $rules = array(
            'rejectionReasonId' => 'required|integer|min:1',
            'rejectionReasonComment' => 'string'
        );

        $validatorPost = Validator::make($request->all(), $rules);

        if ($validatorPost->fails())
        {
            throw new Exception(sprintf("OrderStatusController markAsRejected validation error. Error : %s", $validatorPost->errors()->first()));
        }

        $rejectionReasonId = $request->post('rejectionReasonId');
        $rejectionReasonComment = $request->post('rejectionReasonComment');

        $orderFromDb = Order::where(['orderUniqueId' => $orderUniqueId])->first();

        if($orderFromDb['orderStatus'] !== ORDER_ONLINE_STATUS_REJECTED_BY_RESTAURANT)
        {
            if($orderFromDb['paymentType'] == ORDER_ONLINE_PAYMENT_TYPE_CARD_PAY)
            {
                $checkPaymentDetails = OrderPaymentDetailModel::where('orderId', $orderFromDb['orderId'])->get()->first();
    
                if($checkPaymentDetails['paymentStatus'] == ORDER_ONLINE_PAYMENT_STATUS_SUCCESSFUL)
                {
                    $paymentDetails = json_decode($checkPaymentDetails['paymentResponseData']);
    
                    $dataForRefund = [
                        'paymentIntentId' => $checkPaymentDetails['paymentIntentId'],
                        'amount' => intval($paymentDetails->amount)
                    ];
                    OrderPaymentDetailModel::where('paymentUniqueId', $checkPaymentDetails['paymentUniqueId'])->update(['refundRequestData' => json_encode($dataForRefund), 'refundRequestOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp()]);
    
                    $refundResponse = $this->stripeClient->refund($dataForRefund);
    
                    OrderPaymentDetailModel::where('paymentUniqueId', $checkPaymentDetails['paymentUniqueId'])->update(['refundResponseData' => json_encode($refundResponse), 'refundResponseOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(), 'paymentStatus' => ORDER_ONLINE_PAYMENT_STATUS_REFUNDED]);
                }
            }
    
            $this->validateUserCanUpdateOrderStatus($orderFromDb);
    
            if($orderFromDb->orderStatus != ORDER_ONLINE_STATUS_ADDED && $orderFromDb->orderStatus != ORDER_ONLINE_STATUS_SEEN_BY_RESTAURANT)
            {
                throw new Exception(sprintf("OrderStatusController: Order %s can not be rejected because its current state is %s which is not ADDED", $orderUniqueId, $orderFromDb->orderStatus));
            }
    
            $orderFromDb->orderStatus = ORDER_ONLINE_STATUS_REJECTED_BY_RESTAURANT;
            $orderFromDb->rejectionReasonId = $rejectionReasonId;
            $orderFromDb->rejectionReasonComment = $rejectionReasonComment;
            $orderFromDb->save();

            $this->addOrderStatusHistory($orderFromDb->orderId, ORDER_ONLINE_STATUS_REJECTED_BY_RESTAURANT);
        }

        return response()->json(new BaseResponse(true, null, true));
    }

    public function markAsReady(Request $request, String $orderUniqueId)
    {
        $this->validateOrderUniqueId($orderUniqueId);

        $orderFromDb = Order::where(['orderUniqueId' => $orderUniqueId])->first();

        $this->validateUserCanUpdateOrderStatus($orderFromDb);

        if ($orderFromDb->orderStatus !== ORDER_ONLINE_STATUS_FOOD_READY)
        {
            if($orderFromDb->orderStatus != ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT)
            {
                throw new Exception(sprintf("OrderStatusController: Order %s can not be marked as ready because its current state is %s which is not ACCEPTED BY RESTAURANT", $orderUniqueId, $orderFromDb->orderStatus));
            }
    
            if ($request->post('finishedBy'))
            {
                $orderFromDb->finishedBy = $request->post('finishedBy');
            }
            else
            {
                $orderFromDb->finishedBy = ORDER_ONLINE_ORDER_FINISHED_BY_AUTOMATIC_JOB;
            }
    
            $orderFromDb->orderStatus = ORDER_ONLINE_STATUS_FOOD_READY;
            $orderFromDb->save();
    
            $this->addOrderStatusHistory($orderFromDb->orderId, ORDER_ONLINE_STATUS_FOOD_READY);
        }


        return response()->json(new BaseResponse(true, null, true));
    }
    private function addOrderStatusHistory(int $orderId, int $orderStatus)
    {
        $orderStatusHistory = new OrderStatusHistory();
        $orderStatusHistory->orderId = $orderId;
        $orderStatusHistory->orderStatus = $orderStatus;
        $orderStatusHistory->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $orderStatusHistory->ip = $this->ipHelpers->clientIpAsLong();
        $orderStatusHistory->userId = Auth::id();
        $orderStatusHistory->save();
    }

    private function validateOrderUniqueId(String $orderUniqueId)
    {
        if (empty($orderUniqueId) || strlen($orderUniqueId) != 9) {
            throw new Exception(sprintf("OrderStatusController: invalid order unique id"));
        }
    }

    private function validateUserCanUpdateOrderStatus($orderFromDb)
    {
        if (empty($orderFromDb)) {
            throw new Exception(sprintf("OrderStatusController: invalid order unique id"));
        }

        if ($this->authentication->isUserAdmin()) {
            return;
        }
        $advertisementIdFromDb = Advertisement::select('id')->where('author_id', Auth::id())->first();

        if (empty($advertisementIdFromDb)) {
            throw new Exception(sprintf("OrderStatusController: user %s does not have a restaurant. Security issue", Auth::id()));
        }
        $advertisementId = $advertisementIdFromDb->id;

        if ($advertisementId != $orderFromDb->restaurantId) {
            throw new Exception(sprintf("OrderStatusController: order %s does not belong to restaurant %s. Security issue", $orderFromDb->orderId, $advertisementId));
        }
    }

    public function markFutureOrderAsAccepted(Request $request, String $orderUniqueId)
    {
        $this->validateOrderUniqueId($orderUniqueId);

        $rules = array(
            'restaurantOwnerOrderAcceptedTime' => 'required|string',
        );

        $validatorPost = Validator::make($request->all(), $rules);

        if ($validatorPost->fails())
        {
            throw new Exception(sprintf("OrderStatusController markFutureOrderAsAccepted validation error. Error %s", $validatorPost->errors()->first()));
        }

        $restaurantOwnerOrderAcceptedTime = $request->post('restaurantOwnerOrderAcceptedTime');

        $orderFromDb = Order::where(['orderUniqueId' => $orderUniqueId])->first();

        $this->validateUserCanUpdateOrderStatus($orderFromDb);

        if ($orderFromDb->orderStatus !== ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT)
        {
            if($orderFromDb->orderStatus != ORDER_ONLINE_STATUS_ADDED && $orderFromDb->orderStatus != ORDER_ONLINE_STATUS_SEEN_BY_RESTAURANT)
            {
                Log::critical(sprintf("OrderStatusController: Order %s can not be accepted because its current state is %s which is not ADDED", $orderUniqueId, $orderFromDb->orderStatus));
                throw new Exception(sprintf("OrderStatusController: Order %s can not be accepted because its current state is %s which is not ADDED", $orderUniqueId, $orderFromDb->orderStatus));
            }
    
            $orderFromDb->orderStatus = ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT;
            $orderFromDb->restaurantOwnerOrderAcceptedTime = $restaurantOwnerOrderAcceptedTime;
            $orderFromDb->save();
    
            $this->addOrderStatusHistory($orderFromDb->orderId, ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT);
        }

        return response()->json(new BaseResponse(true, null, true));
    }

    private function convertTimingIntoTodayAndTomorrowFormat(int $userOrderTime): string
    {
        $currentDayStartTimestamp = $this->datetimeHelpers->getCurrentDayTimeStamp();
        $currentDayEndTimestamp = $this->datetimeHelpers->getCurrentDayEndTimeStamp();
        $formattedTime = "";

        if($currentDayStartTimestamp < $userOrderTime && $currentDayEndTimestamp > $userOrderTime)
        {
            $formattedTime = sprintf("%s %s %s", $this->translatorFactory->translate("Today"), $this->translatorFactory->translate("at"), date("H:i", $userOrderTime));
        }
        else if($currentDayEndTimestamp < $userOrderTime)
        {
            $formattedTime = sprintf("%s %s %s", $this->translatorFactory->translate("Tomorrow"), $this->translatorFactory->translate("at"), date("H:i", $userOrderTime));
        }
        else if($currentDayStartTimestamp > $userOrderTime)
        {
            $formattedTime = sprintf("%s %s %s", $this->translatorFactory->translate("Yesterday"), $this->translatorFactory->translate("at"), date("H:i", $userOrderTime));
        }

        return $formattedTime;
    }
}
