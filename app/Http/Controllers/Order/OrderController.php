<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\Order;
use App\Models\Order\OrderMenuItem;
use App\Models\Order\OrderPaymentDetailModel;
use App\Models\Order\OrderMenuItemOption;
use App\Models\Order\OrderStatusHistory;
use App\Models\Order\UserDeliveryAddress;
use App\Models\User;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use App\Libs\Helpers\Authentication;
use App\Shared\EatCommon\Helpers\StringHelper;
use Validator;
use Auth;
use App\Http\Controllers\Restaurant\RestaurantTimingController;
use App\Models\Order\MenuItem;
use App\Models\Restaurant\Advertisement;
use App\Models\Restaurant\Working;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Shared\EatCommon\Payment\StripeClient;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class OrderController extends Controller
{
    private $datetimeHelpers;
    private $ipHelpers;
    private $authentication;
    private $stringHelper;
    private $translatorFactory;
    private $restaurantTimingController;
    private $log;
    private $stripe;
    private $stripeClient;
    private $stripePaymentIntent;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, Authentication $authentication, StringHelper $stringHelper, TranslatorFactory $translatorFactory, RestaurantTimingController $restaurantTimingController, Log $log, Stripe $stripe, PaymentIntent $stripePaymentIntent, StripeClient $stripeClient) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->authentication = $authentication;
        $this->stringHelper = $stringHelper;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->restaurantTimingController = $restaurantTimingController;
        $this->stripeClient = $stripeClient;
        $this->log = $log;
        $this->stripe = $stripe;
        $this->stripePaymentIntent = $stripePaymentIntent;
    }

    public function placeOrder(Request $request){
        $rules = array(
            'restaurantId' => 'required|integer|min:1',
            'totalFoodPrice' => 'required|integer|min:1',
            'cardProcessingCharge' => 'integer',
            'deliveryPrice' => 'required|integer|min:0',
            'orderType' => 'required|integer|min:1|max:2',
            'paymentType' => 'required|integer|min:1|max:3',
            'menuItems.*.menuItemId' => 'required|integer|min:1',
            'menuItems.*.sizeId' => 'required|integer|min:0',
            'menuItems.*.count' => 'required|integer|min:1',
            'menuItems.*.priceOfMenuItem' => 'required|integer|min:1',
            'menuItems.*.totalPriceWithOptions' => 'required|integer|min:1',
            'menuItems.*.options.*.optionId' => 'integer|min:1',
            'menuItems.*.options.*.optionItems.*.optionItemId' => 'integer|min:1',
            'menuItems.*.options.*.optionItems.*.optionItemPrice' => 'integer|min:0'
        );

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("OrderController.placeOrder error. %s ", $validator->errors()->first()));
        }

        $currentTimeStamp = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $isFutureOrder = !empty($request->post('isFutureOrder') && $request->post('isFutureOrder') == 1) ? true : false;
        $restaurantId = $request->post('restaurantId');
        $deliveryAddressId = null;

        $advertisement = Advertisement::select(['hasTakeaway', 'hasDelivery', 'isTestingRestaurant'])->where('id', $restaurantId)->first();

        if($request->post('orderType') == ORDER_ONLINE_TYPE_DELIVERY)
        {
            if($advertisement['hasDelivery'])
            {
                $deliveryAddressId = $request->post('deliveryAddressId');
            }
            else
            {
                return response()->json(new BaseResponse(false, $this->translatorFactory->translate("Sorry, the order cannot be placed because restaurant don't have delivery"), null));
            }
        }

        $restaurantTiming = $this->checkRestaurantIsOpen($restaurantId, $request->post('orderType'));
        $isSuccess = false;
        $responseMsg = null;
        $responseObject = array();

        if ((!empty($restaurantTiming) && $restaurantTiming['open'] == true) || $isFutureOrder)
        {
            $clientIp = $this->ipHelpers->clientIpAsLong();
            $userOrderTime = !empty($request->post('userOrderTime')) ? intval($request->post('userOrderTime')) : null;
            $nextDayDate = date("Y-m-d", strtotime("+1 days"));
            $nextDayEndTimestamp = strtotime(sprintf("%s %s", $nextDayDate, "23:59"));

            if (!$this->authentication->isUserAdmin())
            {
                $orderFrom = strtotime('-1 day');
                $orderTo = time();

                $orderIpCondition = [
                    ['ip', $clientIp]
                ];

                $ordersFromOneIp = Order::select('orderId')->where($orderIpCondition)->whereBetween('createdOn', [$orderFrom, $orderTo])->get()->toArray();

                if ((is_null($advertisement['isTestingRestaurant']) || $advertisement['isTestingRestaurant'] != 1) && count($ordersFromOneIp) >= MAXIMUM_ORDER_ALLOWED_FROM_ONE_IP)
                {
                    $this->log::critical(sprintf("Order placed from %s IP more than %s times in the last 24 hrs", $clientIp, MAXIMUM_ORDER_ALLOWED_FROM_ONE_IP));
                    throw new Exception("Order rejected because of multiple orders placed with in the last 24 hrs");
                }
            }

            if($isFutureOrder && ($userOrderTime < ($currentTimeStamp + ORDER_ONLINE_FUTURE_ORDER_CHECK_ORDER_TIME_IN_SECONDS) || $userOrderTime > $nextDayEndTimestamp))
            {
                $this->log::critical("Invalid time");
                return response()->json(new BaseResponse(false, $this->translatorFactory->translate('Order has been rejected because future time has passed'), []));
            }

            $calulatedOrderPrice = $this->recalculateOrderPrice($request->post());
            if ($calulatedOrderPrice > 0)
            {
                $calulatedOrderPrice = $calulatedOrderPrice / CURRENCY_MULTIPLIER;
                $totalFoodPrice = $request->post('totalFoodPrice') / CURRENCY_MULTIPLIER;

                if (($calulatedOrderPrice - $totalFoodPrice) > TOTAL_FOOD_PRICE_DIFFERENCE)
                {
                    $this->log::critical(sprintf("Restaurant Id - %s, Delivery Price - %s, menuItems are - %s, discountPrice is - %s, discountPercentage is - %s, cardProcessingCharge is - %s", $restaurantId, $request->post('deliveryPrice'), json_encode($request->post('menuItems')), $request->post('discountPrice'), $request->post('discountPercentage'), $request->post('cardProcessingCharge')));
                    return response()->json(new BaseResponse(false, $this->translatorFactory->translate('Order cannot be placed because prices of items that you have selected have changed, please try again'), ORDER_ONLINE_ORDER_REJECTED_BECAUSE_OF_PRICE_MISMATCH));
                }
            }

            if($request->post('isPaymentSuccessfull'))
            {
                $getPaymentDetails = OrderPaymentDetailModel::where('paymentUniqueId', $request->post('paymentUniqueId'))->get()->first();

                if($getPaymentDetails['paymentStatus'] == ORDER_ONLINE_PAYMENT_STATUS_SENT)
                {
                    $paymentDetails =  $request->post('paymentDetails');

                    $data = [
                        'paymentResponseData' => json_encode($paymentDetails),
                        'paymentResponseOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                        'paymentIntentId' => $paymentDetails['id'],
                        'paymentStatus' => ORDER_ONLINE_PAYMENT_STATUS_SUCCESSFUL
                    ];

                    $verifyPayment = $this->stripeClient->retrivePaymentDetails($paymentDetails['id']);

                    if($verifyPayment['status'] == "succeeded" && intval($paymentDetails['amount']) == $verifyPayment['amount'])
                    {
                        OrderPaymentDetailModel::where('paymentUniqueId', $request->post('paymentUniqueId'))->update($data);
                    }
                    else
                    {
                        $data['paymentStatus'] = ORDER_ONLINE_PAYMENT_STATUS_FAILED_FROM_PROVIDER;

                        OrderPaymentDetailModel::where('paymentUniqueId', $request->post('paymentUniqueId'))->update($data);
                        throw new Exception("Ordre afvist på grund af betaling er ikke gennemført");
                    }
                }
                else
                {
                    throw new Exception("Order rejected because of payment is not done");
                }
            }

            $currentDayTimeStamp = $this->datetimeHelpers->getCurrentDayTimeStamp();
            $todayOrderCount = Order::select('orderId')->where(['restaurantId' => $restaurantId ])->where('createdOn', '>', $currentDayTimeStamp)->count();
            $orderNumber = $todayOrderCount + 1;
            $order = new Order();
            $order->restaurantId = $restaurantId;
            $order->orderType = $request->post('orderType');
            $order->totalFoodPrice = $request->post('totalFoodPrice');
            $order->deliveryPrice = $request->post('deliveryPrice');
            $order->orderStatus = ORDER_ONLINE_STATUS_ADDED;
            $order->deliveryAddressId = $deliveryAddressId;
            $order->paymentType = $request->post('paymentType');
            $order->orderNumber = $orderNumber;
            $order->orderMessage = $request->post('orderMessage');
            $order->discountPrice = $request->post('discountPrice');
            $order->discountPercentage = $request->post('discountPercentage');
            $order->cardProcessingCharge = intval($request->post('cardProcessingCharge'));

            if($isFutureOrder)
            {
                $order->isFutureOrder = 1;
                $order->userFutureOrderTime = $userOrderTime;
            }

            $order->userId = Auth::id();
            $order->createdOn = $currentTimeStamp;
            $order->ip = $clientIp;
            $orderUniqueId = $this->stringHelper->generateRandomCharacters(9);
            $order->orderUniqueId = $orderUniqueId;
            $order->save();
            $orderId = $order->orderId;

            OrderPaymentDetailModel::where('paymentUniqueId', $request->post('paymentUniqueId'))->update(['orderId' => $orderId]);

            $menuItems = $request->post('menuItems');

            foreach ($menuItems as $menuItem)
            {
                $orderMenuItem = new OrderMenuItem();
                $orderMenuItem->orderId = $orderId;
                $orderMenuItem->menuItemId = $menuItem["menuItemId"];
                if($menuItem["sizeId"] > 0)
                {
                    $orderMenuItem->sizeId = $menuItem["sizeId"];
                }
                $orderMenuItem->priceOfMenuItem = $menuItem["priceOfMenuItem"];
                $orderMenuItem->totalPriceWithOptions = $menuItem["totalPriceWithOptions"];
                $orderMenuItem->quantity = $menuItem["count"];
                $orderMenuItem->save();

                $menuItemId = $orderMenuItem->orderMenuItemId;
                if(array_key_exists("options", $menuItem) && $menuItem["options"] && count($menuItem["options"]) > 0)
                {
                    foreach ($menuItem["options"] as $option)
                    {
                        $optionId = $option["optionId"];
                        foreach($option["optionItems"] as $optionItem)
                        {
                            $orderMenuItemOption = new OrderMenuItemOption();
                            $orderMenuItemOption->orderMenuItemId = $menuItemId;
                            $orderMenuItemOption->optionId = $optionId;
                            $orderMenuItemOption->optionItemId = $optionItem["optionItemId"];
                            $orderMenuItemOption->price = $optionItem["optionItemPrice"];
                            $orderMenuItemOption->quantity = empty($optionItem["optionQty"]) ? null : $optionItem["optionQty"];
                            $orderMenuItemOption->totalPriceOfQuantities = empty($optionItem["totalPriceOfQuantities"]) ? null : $optionItem["totalPriceOfQuantities"];
                            $orderMenuItemOption->save();
                        }
                    }
                }
            }

            $orderStatusHistory = new OrderStatusHistory();
            $orderStatusHistory->orderId = $orderId;
            $orderStatusHistory->orderStatus = ORDER_ONLINE_STATUS_ADDED;
            $orderStatusHistory->createdOn = $currentTimeStamp;
            $orderStatusHistory->ip = $clientIp;
            $orderStatusHistory->userId = Auth::id();
            $orderStatusHistory->save();

            $responseObject['orderUniqueId'] = $orderUniqueId;
            $responseObject['orderNumber'] = $orderNumber;
            $isSuccess = true;
        }
        else
        {
            $responseMsg = sprintf('%s %s', $this->translatorFactory->translate('Order is rejected because'), $restaurantTiming['message']);
        }

        return response()->json(new BaseResponse($isSuccess, $responseMsg, $responseObject));
    }

    private function recalculateOrderPrice(array $orderData): int
    {
        $menuItems = $orderData['menuItems'];
        $menuItemIds = $optionIds = $sizeIds = $optionItemIds = [];

        foreach($menuItems as $menuItem)
        {
            if (!in_array(intval($menuItem['menuItemId']), $menuItemIds))
            {
                $menuItemIds[] = intval($menuItem['menuItemId']);
            }

            if ($menuItem['sizeId'] > 0 && !in_array($menuItem['sizeId'], $sizeIds))
            {
                $sizeIds[] = intval($menuItem['sizeId']);
            }

            if (!empty($menuItem['options']))
            {
                foreach ($menuItem["options"] as $option)
                {
                    if (!in_array(intval($option['optionId']), $optionIds))
                    {
                        $optionIds[] = intval($option["optionId"]);
                    }

                    foreach($option["optionItems"] as $optionItem)
                    {
                        if (!in_array(intval($optionItem['optionItemId']), $optionItemIds))
                        {
                            $optionItemIds[] = intval($optionItem['optionItemId']);
                        }
                    }
                }
            }
        }

        $adv = Advertisement::select(['id', 'orderOnlineDiscountPercentage'])->where('id',  $orderData['restaurantId'])->get()->toArray();
        $orderOnlineDiscountPercentage = 0;
        if (!empty($adv))
        {
            $orderOnlineDiscountPercentage = intval($adv[0]['orderOnlineDiscountPercentage']);
        }

        $order = MenuItem::with([
            'options' => function($query) use($optionIds) {
                $query->select(['menuItemId', 'optionId', 'addPriceToMenuItem'])->whereIn('optionId', $optionIds);
            },
            'options.optionItems' => function($query) use($optionItemIds) {
                $query->select(['optionId', 'optionItemId', 'price'])->whereIn('optionItemId', $optionItemIds);
            },
            'options.optionItems.sizes' => function($query) use($sizeIds) {
                $query->select(['optionItemId', 'sizeId', 'price'])->whereIn('sizeId', $sizeIds);
            }
        ])->with(['sizes' => function($query) use($sizeIds) {
            $query->select(['menuItemId', 'sizeId', 'price'])->whereIn('sizeId', $sizeIds);
        }])->select(['menuItemId', 'price'])->where('restaurantId', $orderData['restaurantId'])->whereIn('menuItemId', $menuItemIds)->get()->toArray();

        $totalOrderPrice = 0;

        foreach($menuItems as $menuItem)
        {
            $totalOptionPrice = 0;
            $menuItemPrice = $this->getMenuItemPrice($order, $menuItem['menuItemId'], intval($menuItem['sizeId'])) * $menuItem['count'];

            if (!empty($menuItem['options']))
            {
                foreach ($menuItem["options"] as $option)
                {
                    $optionItemPrice = $this->getOptionItemPrice($order, $menuItem['menuItemId'], intval($menuItem['sizeId']), $option);
                    $totalOptionPrice += ($optionItemPrice * $menuItem['count']);
                }
            }

            $totalOrderPrice += ($menuItemPrice + $totalOptionPrice);
        }

        if ($orderOnlineDiscountPercentage > 0)
        {
            $orderPrice = $totalOrderPrice / CURRENCY_MULTIPLIER;
            $totalDiscountPrice = ($orderPrice * $orderOnlineDiscountPercentage) / 100;
            $totalOrderPrice = ($orderPrice - $totalDiscountPrice) * CURRENCY_MULTIPLIER;
        }

        return $totalOrderPrice;
    }

    private function getMenuItemPrice(array $menuItems, int $menuItemId, int $sizeId): int
    {
        $menuItemPrice = 0;

        if (!empty($menuItems))
        {
            foreach($menuItems as $menuItem)
            {
                if ($menuItem['menuItemId'] === $menuItemId)
                {
                    if (!empty($menuItem['sizes']) && $sizeId > 0)
                    {
                        foreach($menuItem['sizes'] as $size)
                        {
                            if ($size['sizeId'] === $sizeId)
                            {
                                $menuItemPrice = $size['price'];
                                break;
                            }
                        }
                    }
                    else
                    {
                        $menuItemPrice = $menuItem['price'];
                    }

                    break;
                }
            }
        }

        return $menuItemPrice;
    }

    private function getOptionItemPrice(array $menuItems, int $menuItemId, int $sizeId, array $optionData): int
    {
        $totalOptionPrice = 0;

        if (!empty($menuItems))
        {
            foreach($menuItems as $menuItem)
            {
                if ($menuItem['menuItemId'] === $menuItemId && !empty($optionData) && !empty($menuItem['options']))
                {
                    foreach($menuItem['options'] as $menuItemOptions)
                    {
                        if ($menuItemOptions['optionId'] === intval($optionData['optionId']) && $menuItemOptions['addPriceToMenuItem'] == 1 && !empty($optionData['optionItems']))
                        {
                            $totalOptionItemPrice = 0;

                            foreach($optionData['optionItems'] as $optionItem)
                            {
                                foreach($menuItemOptions['option_items'] as $orderOptionItem)
                                {
                                    if ($optionItem['optionItemId'] == intval($orderOptionItem['optionItemId']))
                                    {
                                        if (!empty($orderOptionItem['sizes']) && $sizeId > 0)
                                        {
                                            foreach($orderOptionItem['sizes'] as $optionItemSize)
                                            {
                                                if ($optionItemSize['sizeId'] == intval($sizeId))
                                                {
                                                    $totalOptionItemPrice += $optionItemSize['price'];
                                                }
                                            }
                                        }
                                        else
                                        {
                                            $totalOptionItemPrice += $orderOptionItem['price'];
                                        }
                                        break;
                                    }
                                }
                            }

                            $totalOptionPrice = $totalOptionItemPrice;
                        }
                    }
                    break;
                }
            }
        }

        return $totalOptionPrice;
    }

    private function getWorkingType(int $orderType): int
    {
        $workingTypeMapping = [
            ORDER_ONLINE_TYPE_DELIVERY => WORKING_TYPE_DELIVERY_TIMINGS,
            ORDER_ONLINE_TYPE_TAKE_AWAY => WORKING_TYPE_TAKEAWAY_TIMINGS,
        ];

        if (array_key_exists($orderType, $workingTypeMapping))
        {
            return $workingTypeMapping[$orderType];
        }

        return 0;
    }

    private function checkRestaurantIsOpen(int $restaurantId, int $orderType): array
    {
        $workingType = $this->getWorkingType($orderType);
        $whereIn = [WORKING_TYPE_NORMAL_TIMING, $workingType];

        $restaurantTimingObj = new $this->restaurantTimingController(new DatetimeHelper, new TranslatorFactory);
        $working = Working::where([['adv_id', $restaurantId]])->whereIn('working_type', $whereIn)->get()->toArray();
        $restaurantTimingData = [
            'open' => false,
            'message' => sprintf('%s %s', $this->translatorFactory->translate('Restaurant'), $this->translatorFactory->translate('is closed right now'))
        ];

        if (!empty($working))
        {
            if (count($working) == 2)
            {
                foreach($working as $row)
                {
                    if ($row['working_type'] === $workingType)
                    {
                        $restaurantTimingData = $restaurantTimingObj->calculateTiming($row);
                        break;
                    }
                }
            }
            else
            {
                $restaurantTimingData = $restaurantTimingObj->calculateTiming($working[0]);
            }
        }

        return $restaurantTimingData;
    }

    public function getOrderDetail(string $orderUniqueId) {
        $condition = [
            ['userId',  Auth::id()],
            ['orderUniqueId', $orderUniqueId]
        ];

        $order = Order::where($condition)->with([
            'orderMenuItems',
            'orderMenuItems.orderMenuItemOptions',
        ])->first();

        $response = [];
        if (!empty($order)) {

            $response = [
                "orderId" => $order->orderId,
                "restaurantId" => $order->restaurantId,
                "orderType" => $order->orderType,
                "deliveryPrice" => $order->deliveryPrice,
                "totalFoodPrice" => $order->totalFoodPrice,
                "orderStatus" => $order->orderStatus,
                "deliveryAddressId" => $order->deliveryAddressId,
                "orderUniqueId" => $order->orderUniqueId,
                "deliveryApproxTimeInSeconds" => $order->deliveryApproxTimeInSeconds,
                "rejectionReasonId" => $order->rejectionReasonId,
                "rejectionReasonComment" => $order->rejectionReasonComment,
                "paymentType" => $order->paymentType,
                "orderNumber" => $order->orderNumber,
                "orderMessage" => $order->orderMessage,
            ];

            if (!empty($order->orderMenuItems)) {
                $orderMenuItems = [];
                foreach ($order->orderMenuItems as $orderMenuItem) {

                    $menuItem = [
                        'orderId' => $orderMenuItem->orderId,
                        'menuItemId' => $orderMenuItem->menuItemId,
                        'sizeId' => $orderMenuItem->sizeId,
                        'orderMenuItemId' => $orderMenuItem->orderMenuItemId,
                        'priceOfMenuItem' => $orderMenuItem->priceOfMenuItem,
                        'quantity' => $orderMenuItem->quantity,
                    ];

                    if (!empty($orderMenuItem->orderMenuItemOptions)) {
                        $menuItemOptions = [];

                        foreach ($orderMenuItem->orderMenuItemOptions as $orderMenuItemOption) {

                            $menuItemOption = [
                                "orderMenuItemOptionId" => $orderMenuItemOption->orderMenuItemOptionId,
                                "orderMenuItemId" => $orderMenuItemOption->orderMenuItemId,
                                "optionId" => $orderMenuItemOption->optionId,
                                "optionItemId" => $orderMenuItemOption->optionItemId,
                                "price" => $orderMenuItemOption->price,
                                "quantity" => $orderMenuItemOption->quantity
                            ];

                            $menuItemOptions[] = $menuItemOption;
                        }

                        $menuItem['menuItemOptions'] = $menuItemOptions;
                    }

                    $orderMenuItems[] = $menuItem;
                }

                $response['menuItems'] = $orderMenuItems;
            }
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function getStripeClientSecretKey(Request $request)
    {
        $validator = Validator::make($request->post(), ['orderTotalPrice' => 'required|int']);
        if ($validator->fails())
        {
            throw new Exception(sprintf("OrderController.getStripeClientSecretKey error. %s ", $validator->errors()->first()));
        }

        $this->stripe::setApiKey(STRIPE_SECRET_KEY);

        $orderObject = serialize($request->post('orderObject'));
        $orderTotalPrice = $request->post('orderTotalPrice');
        $restaurantId = $request->post('adId');
        $paymentUniqueId = $this->stringHelper->generateRandomCharacters(10);

        $orderPaymentDetailModel = new OrderPaymentDetailModel();

        $orderPaymentDetailModel->paymentUniqueId = $paymentUniqueId;
        $orderPaymentDetailModel->userId = Auth::id();
        $orderPaymentDetailModel->restaurantId = $restaurantId;
        $orderPaymentDetailModel->orderDetails = $orderObject;
        $orderPaymentDetailModel->totalFoodPrice = $orderTotalPrice;
        $orderPaymentDetailModel->paymentStatus = ORDER_ONLINE_PAYMENT_STATUS_STARTED;
        $orderPaymentDetailModel->ip = $this->ipHelpers->clientIpAsLong();
        $orderPaymentDetailModel->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $orderPaymentDetailModel->save();

        $intent = $this->stripePaymentIntent::create([
            'amount' => $orderTotalPrice,
            'currency' => PRICE_CURRENCY,
            'metadata' => ['integration_check' => 'accept_a_payment'],
        ]);

        $response = [
            'clientSecretKey' => $intent->client_secret,
            'publisherKey' => STRIPE_PUBLISHABLE_KEY,
            'paymentUniqueId' => $paymentUniqueId
        ];

        $user = User::select(['uid', 'name', 'email'])->where('uid', Auth::id())->first();
        if (!empty($user))
        {
            $response['billingDetails'] = [
                'name' => $user->name,
                'email' => $user->email,
            ];
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function hasNewOrder()
    {
        $userId = Auth::id();

        $advertisements = Advertisement::select('id')->where('author_id', $userId)->get()->toArray();
        $response['hasNewOrder'] = 0;

        if (!empty($advertisements))
        {
            $currentTimestamp = $this->datetimeHelpers->getCurrentTimeStamp();
            $lastHourTimestamp = $currentTimestamp - FETCH_RESTAURANT_LAST_NEW_ORDERS_TIME_IN_SECONDS;

            $restaurantId = $advertisements[0]['id'];

            $ordersCount = Order::select('orderId')->where([
                ['orderStatus', ORDER_ONLINE_STATUS_ADDED],
                ['restaurantId', $restaurantId]
            ])->whereBetween('createdOn', [$lastHourTimestamp, $currentTimestamp])->count();

            $response['hasNewOrder'] = $ordersCount;

            if ($ordersCount > 0)
            {
                $response['orderOnlineUrl'] = sprintf("%s/%s?ad-id=%s", SITE_BASE_URL, PAGE_RESTAURANT_ORDERS, $restaurantId);
            }
        }

        return response()->json(new BaseResponse(true, null, $response));
    }
}
