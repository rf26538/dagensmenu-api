<?php
namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\UserDeliveryAddress;
use Illuminate\Http\Request;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Models\Order\OrderOnlineTemporaryCloseTiming;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\StringHelper;
use Auth;

class RestaurantOrdersController extends Controller
{
    private $datetimeHelper;
    private $translatorFactory;
    private $stringHelper;

    public function __construct(DatetimeHelper $datetimeHelper, StringHelper $stringHelper, TranslatorFactory $translatorFactory)
    {
        $this->datetimeHelper = $datetimeHelper;
        $this->stringHelper = $stringHelper;
        $this->translatorFactory = $translatorFactory::getTranslator();
    }

    public function fetchOrders(int $restaurantId, Request $request)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("RestaurantOrdersController fetchOrders error %s user id - %s", $validator->errors()->first(), Auth::id()));
        }
        
        $queryParams = $request->query();
        
        $lastHour = isset($queryParams['lastHour']) ? intval($queryParams['lastHour']) : FETCH_RESTAURANT_ORDERS_FOR_LAST_HOUR;
        $lastHourForFutureOrder = isset($queryParams['lastHourForFutureOrder']) ? intval($queryParams['lastHourForFutureOrder']) : FETCH_RESTAURANT_FUTURE_ORDERS_FOR_LAST_HOUR;
        $currentTimeStamp = $this->datetimeHelper->getCurrentTimeStamp();
        $timestampForOrders = $currentTimeStamp - ($lastHour * 60 * 60);

        $days = !empty($queryParams['days']) && intval($queryParams['days']) > 0 ? intval($queryParams['days']) : 2;

        $orderTableColumns = ['orderId', 'restaurantId', 'orderType', 'paymentType', 'userId', 'deliveryAddressId', 'totalFoodPrice', 'deliveryPrice', 'orderStatus', 'orderUniqueId', 'orderNumber', 'orderMessage', 'discountPercentage', 'discountPrice', 'deliveryApproxTimeInSeconds', 'createdOn', 'userFutureOrderTime', 'restaurantOwnerOrderAcceptedTime', 'isFutureOrder'];
        $orders = Order::select($orderTableColumns)->where('restaurantId', $restaurantId)->with(
            'orderMenuItems.menuItem:menuItemId,menuItemName',
            'orderMenuItems.menuItem.categories.category:categoryId,categoryName',
            'orderMenuItems.orderMenuItemOptions.optionItem:optionItemId,optionItemName',
            'orderMenuItems.size'
        )->with(['orderStatusHistory' => function($query) {
            $query->select(['orderId', 'createdOn', 'orderStatus'])->whereIn('orderStatus', [ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT, ORDER_ONLINE_STATUS_FOOD_READY]);
        }]);
        
        if($lastHour > 0 && $lastHourForFutureOrder > 0)
        {
            // get last x hour order and future order
            $timestampForFutureOrders = $currentTimeStamp - ($lastHourForFutureOrder * 60 * 60);
            $orders->whereRaw(sprintf("((createdOn > %s AND isFutureOrder = 1) OR (createdOn > %s AND isFutureOrder IS NULL))", $timestampForFutureOrders, $timestampForOrders));
        }
        else if ($lastHour > 0)
        {
            $orders->where('createdOn', '>=', $timestampForOrders);
        }
        else
        {
            $orders->where('createdOn', '>=', strtotime(sprintf('-%s days', $days)));
        }

        $orders = $orders->orderBy('orderId','desc')->get()->toArray();

        if (!empty($orders))
        {
            $deliveryAddressIds = [];
            $userIds = [];
            foreach($orders as $order)
            {
                if (!in_array($order['deliveryAddressId'] , $deliveryAddressIds))
                {
                    $deliveryAddressIds[] = $order['deliveryAddressId'];
                }
                
                if (!in_array($order['userId'] , $userIds))
                {
                    $userIds[] = $order['userId'];
                }
            }

            $deliveryAddress = [];
            if (!empty($deliveryAddressIds))
            {
                $userDeliveryAddresses = UserDeliveryAddress::select(['deliveryAddressId', 'addressLine1', 'addressLine2', 'addressLine3', 'postcode', 'city', 'name', 'phoneNumber'])->where('isDeleted', 0)->whereIn('deliveryAddressId', $deliveryAddressIds)->get()->toArray();
                
                if (!empty($userDeliveryAddresses))
                {
                    foreach ($userDeliveryAddresses as $userAddress)
                    {
                        $deliveryAddress[$userAddress['deliveryAddressId']] = [
                            'addressLine1' => $userAddress['addressLine1'],
                            'addressLine2' => $userAddress['addressLine2'],
                            'addressLine3' => $userAddress['addressLine3'],
                            'postcode' => $userAddress['postcode'],
                            'city' => $userAddress['city'],
                            'phone' => $userAddress['phoneNumber'],
                            'name' => $userAddress['name']
                        ];
                    }
                }
            }

            $userDetails = [];
            if (!empty($userIds))
            {
                $users =  User::select(['uid', 'name', 'phone', 'image_name', 'image_folder'])->whereIn('uid', $userIds)->get()->toArray();
                if (!empty($users))
                {
                    foreach($users as $user)
                    {
                        $userDetails[$user['uid']] = $user;
                    }
                }
            }

            foreach($orders as &$order)
            {
                try
                {
                    $order['orderCreatedOn'] = $order['createdOn'];
                    $order['restaurantOwnerOrderAcceptedTimestamp'] = $order['restaurantOwnerOrderAcceptedTime'];
                    $formattedDate = $this->datetimeHelper->getDanishFormattedDate($order['createdOn']);
                    $formattedTime = $this->datetimeHelper->timeStampToFormat("G:i", $order['createdOn']);
                    $order['orderDate'] = sprintf("%s %s", $formattedDate, $formattedTime);
                    $order['timeToReadyInSeconds']  = null;
                    $allowOwnerAcceptedTime = [];
    
                    if(!empty($order['userFutureOrderTime']) && empty($order['restaurantOwnerOrderAcceptedTime']) && $order['orderStatus'] != ORDER_ONLINE_STATUS_REJECTED_BY_RESTAURANT)
                    {
                        $allowOwnerAcceptedTime[strtotime('-30 minutes', $order['userFutureOrderTime'])] = ["time" => date("H:i", strtotime('-30 minutes', $order['userFutureOrderTime'])), "textTime" => $this->convertTimingIntoTodayAndTomorrowFormat(strtotime('-30 minutes', $order['userFutureOrderTime']))];
                        $allowOwnerAcceptedTime[strtotime('-20 minutes', $order['userFutureOrderTime'])] = ["time" => date("H:i", strtotime('-20 minutes', $order['userFutureOrderTime'])), "textTime" => $this->convertTimingIntoTodayAndTomorrowFormat(strtotime('-20 minutes', $order['userFutureOrderTime']))];
                        $allowOwnerAcceptedTime[strtotime('-10 minutes', $order['userFutureOrderTime'])] = ["time" => date("H:i", strtotime('-10 minutes', $order['userFutureOrderTime'])), "textTime" => $this->convertTimingIntoTodayAndTomorrowFormat(strtotime('-10 minutes', $order['userFutureOrderTime']))];
                        $allowOwnerAcceptedTime[strtotime('10 minutes', $order['userFutureOrderTime'])] = ["time" => date("H:i", strtotime('+10 minutes', $order['userFutureOrderTime'])), "textTime" => $this->convertTimingIntoTodayAndTomorrowFormat(strtotime('+10 minutes', $order['userFutureOrderTime']))];
                        $allowOwnerAcceptedTime[strtotime('10 minutes', $order['userFutureOrderTime'])] = ["time" => date("H:i", strtotime('+20 minutes', $order['userFutureOrderTime'])), "textTime" => $this->convertTimingIntoTodayAndTomorrowFormat(strtotime('+20 minutes', $order['userFutureOrderTime']))];
                        $allowOwnerAcceptedTime[strtotime('+30 minutes', $order['userFutureOrderTime'])] = ["time" => date("H:i", strtotime('+30 minutes', $order['userFutureOrderTime'])), "textTime" => $this->convertTimingIntoTodayAndTomorrowFormat(strtotime('+30 minutes', $order['userFutureOrderTime']))];
                    }
    
                    $order['showOrderDate'] = 1;
                    
                    if($order['isFutureOrder'])
                    {
                        $order['showOrderDate'] = 0;
                    }
    
                    if(!empty($order['restaurantOwnerOrderAcceptedTime']) && ($order['restaurantOwnerOrderAcceptedTime'] - ORDER_ONLINE_FUTURE_ORDER_FOOD_READY_BUTTON_DURATION_IN_SECONDS) <= $currentTimeStamp && $order['orderStatus'] == ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT)
                    {
                        $order['isFoodReadyBtnForFutureOrder'] = 1;
                    }
    
                    $order['userFutureOrderTextTime'] = !empty($order['userFutureOrderTime']) ? $this->convertTimingIntoTodayAndTomorrowFormat($order['userFutureOrderTime'], true) : "";
                    $order['restaurantOwnerOrderAcceptedTime'] = !empty($order['restaurantOwnerOrderAcceptedTime']) ? $this->convertTimingIntoTodayAndTomorrowFormat($order['restaurantOwnerOrderAcceptedTime'], true) : "";
    
                    $order['allowOwnerToAcceptFoodOnAnotherTime'] = $allowOwnerAcceptedTime;
    
                    if (!empty($order['order_status_history']))
                    {
                        foreach($order['order_status_history'] as $orderHistory)
                        {
                            if ($orderHistory['orderStatus'] == ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT)
                            {
                                $order['timeToReadyInSeconds'] = ($orderHistory['createdOn'] + $order['deliveryApproxTimeInSeconds']) - $this->datetimeHelper->getCurrentTimeStamp();
                            }
                            else if($orderHistory['orderStatus'] == ORDER_ONLINE_STATUS_FOOD_READY)
                            {
                                $order['finishedOrderTime'] = $orderHistory['createdOn']; 
                            }
                        }
                    }
    
                    if (intval($order['deliveryAddressId']) > 0)
                    {
                        if (isset($deliveryAddress[$order['deliveryAddressId']]))
                        {
                            $order['deliveryAddress'] = $deliveryAddress[$order['deliveryAddressId']];
                        }
                    }
                    
                    if($order['orderType'] == ORDER_ONLINE_TYPE_DELIVERY && isset($deliveryAddress[$order['deliveryAddressId']]))
                    {
                        $telephoneNumber = $this->stringHelper->cleanTelephoneNumber($deliveryAddress[$order['deliveryAddressId']]['phone']);
    
                        $order['name'] = $deliveryAddress[$order['deliveryAddressId']]['name'];
                        $order['phoneNumber'] = $telephoneNumber;
                        $order['formattedPhone'] = $this->stringHelper->formatTelephoneNumber($telephoneNumber);
                    }
                    else if (isset($userDetails[$order['userId']]))
                    {
                        $userDetail = $userDetails[$order['userId']];
                        $telephoneNumber = $this->stringHelper->cleanTelephoneNumber($userDetail['phone']);
                        $order['name'] = $userDetail['name'];
                        $order['phoneNumber'] = $telephoneNumber;
                        $order['formattedPhone'] = $this->stringHelper->formatTelephoneNumber($telephoneNumber);
                    }
    
                    $order['userImage'] = $userDetail['image_name'] ?? '';
                    $order['orderMenuItems'] = [];
    
                    $orderMenuItems = $order['order_menu_items'];
    
                    if (!empty($orderMenuItems))
                    {
                        $remapOrderMenuItems = [];
                        foreach($orderMenuItems as $orderMenuItem)
                        {
                            $remapOrderMenuItems['menuItemId'] = $orderMenuItem['menuItemId'];
                            $remapOrderMenuItems['priceOfMenuItem'] = $orderMenuItem['priceOfMenuItem'];
                            $remapOrderMenuItems['quantity'] = $orderMenuItem['quantity'];
                            $remapOrderMenuItems['totalPriceWithOptions'] = $orderMenuItem['totalPriceWithOptions'];
                            $remapOrderMenuItems['menuItemName'] = !empty($orderMenuItem['menu_item']) ? $orderMenuItem['menu_item']['menuItemName'] : null;
                            $remapOrderMenuItems['sizeId'] = $orderMenuItem['sizeId'];
                            $remapOrderMenuItems['size'] = !empty($orderMenuItem['size']) ? $orderMenuItem['size']['size'] : null;
    
                            if (!empty($orderMenuItem['menu_item']['categories']) && !empty($orderMenuItem['menu_item']['categories'][0]['category']))
                            {
                                $remapOrderMenuItems['categoryId'] = $orderMenuItem['menu_item']['categories'][0]['category']['categoryId'];
                                $remapOrderMenuItems['categoryName'] = $orderMenuItem['menu_item']['categories'][0]['category']['categoryName'];
                            }
                            
                            $remapOrderMenuOptions = [];
                            if (!empty($orderMenuItem['order_menu_item_options']))
                            {
                                foreach ($orderMenuItem['order_menu_item_options'] as $orderMenuItemOption)
                                {
                                    $remapOrderMenuOptions[] = [
                                        'price' => $orderMenuItemOption['price'],
                                        'optionItemId' => $orderMenuItemOption['optionItemId'],
                                        'quantity' => $orderMenuItemOption['quantity'],
                                        'optionItemName' => !empty($orderMenuItemOption['option_item']) ? $orderMenuItemOption['option_item']['optionItemName']: null,
                                    ];
                                }
                            }
    
                            $remapOrderMenuItems['menuItemOptions'] = $remapOrderMenuOptions;
    
                            $order['orderMenuItems'][] = $remapOrderMenuItems;
                        }
                    }
                    
                    unset($order['order_menu_items'], $order['createdOn'], $order['order_status_history'], $order['deliveryApproxTimeInSeconds']);
                }
                catch(Exception $e)
                {
                    Log::critical(sprintf("Error found in RestaurantOrdersController@fetchOrders address id %s and order id %s error is %s, Stack trace is %s", $order['deliveryAddressId'], $order['orderId'] , $e->getMessage(), $e->getTraceAsString()));
                }

            }
        }

        $restaurantTemporaryClosed = OrderOnlineTemporaryCloseTiming::select(['orderType', 'fullDayClose', 'reopenOn'])->whereRaw(sprintf("(reopenOn > %s OR reopenOn = %s) AND status = %s AND restaurantId = %s", $this->datetimeHelper->getCurrentUtcTimeStamp(), 0, ORDER_ONLINE_TEMPORARY_CLOSE_STATUS_ACTIVE, $restaurantId))->get()->toArray();
        
        $temporaryClosed = [];

        if(!empty($restaurantTemporaryClosed))
        {
            if($restaurantTemporaryClosed[0]['fullDayClose'])
            {
                if($restaurantTemporaryClosed[0]['orderType'] == RESTAURANT_ORDER_ONLINE_FULL_CLOSED)
                {
                    $temporaryClosed['temporaryClosedMsg'] = $this->translatorFactory->translate("Order online is closed for whole day");
                }
                else
                {
                    $temporaryClosed['temporaryClosedMsg'] = $this->translatorFactory->translate("Delivery is closed for whole day");
                }
            }
            else
            {
                if($restaurantTemporaryClosed[0]['orderType'] == RESTAURANT_ORDER_ONLINE_FULL_CLOSED)
                {
                    $temporaryClosed['temporaryClosedMsg'] = sprintf("%s %s", $this->translatorFactory->translate("Order online is closed until"), date("H:i",$restaurantTemporaryClosed[0]['reopenOn']));
                }
                else
                {
                    $temporaryClosed['temporaryClosedMsg'] = sprintf("%s %s", $this->translatorFactory->translate("Delivery is closed until"), date("H:i",$restaurantTemporaryClosed[0]['reopenOn']));
                }
            }
            
            $temporaryClosed['isOrderOnlineTemporaryClosed'] = true;
        }
        
        $response['orders'] = $orders; 
        $response['temporaryClosedStats'] = $temporaryClosed; 

        return response()->json(new BaseResponse(true, null, $response));
    }
    
    private function convertTimingIntoTodayAndTomorrowFormat(int $userOrderTime, bool $showDateMonth = false): string
    {
        $currentDayStartTimestamp = $this->datetimeHelper->getCurrentDayTimeStamp();
        $currentDayEndTimestamp = $this->datetimeHelper->getCurrentDayEndTimeStamp();
        $formattedTime = "";

        $time = date("H:i", $userOrderTime);
        $dayMonth = date('d F,', $userOrderTime);

        $atText = $this->translatorFactory->translate("at");

        if($currentDayStartTimestamp < $userOrderTime && $currentDayEndTimestamp > $userOrderTime)
        {
            $todayText = $this->translatorFactory->translate("Today");
            $formattedTime = sprintf("%s %s", $todayText, $time);

            if ($showDateMonth)
            {
                $formattedTime = sprintf("%s %s %s. %s", $dayMonth, $todayText, $atText, $time);
            }
        }
        else if($currentDayEndTimestamp < $userOrderTime)
        {
            $tommorowText = $this->translatorFactory->translate("Tomorrow");
            $formattedTime = sprintf("%s %s", $tommorowText, $time);

            if ($showDateMonth)
            {
                $formattedTime = sprintf("%s %s %s. %s", $dayMonth, $tommorowText, $atText, $time);
            }
        }
        else if($currentDayStartTimestamp > $userOrderTime)
        {
            $yesterdayText = $this->translatorFactory->translate("Yesterday");
            $formattedTime = sprintf("%s %s", $yesterdayText, $time);

            if ($showDateMonth)
            {
                $formattedTime = sprintf("%s %s %s. %s", $dayMonth, $yesterdayText, $atText, $time);
            }
        }

        if ($showDateMonth && $formattedTime)
        {
            $monthName = date("F", $userOrderTime);
            $formattedTime = str_replace($monthName, $this->datetimeHelper->getSmallDanishMonthNameForEnglishName($monthName), $formattedTime);
        }
        
        return $formattedTime;
    }
}

?>