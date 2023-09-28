<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Models\Order\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Auth;
use App\Shared\EatCommon\Link\Links;
use App\Shared\EatCommon\Image\ImageHandler;

class UserOrderController extends Controller {

    private $datetimeHelpers;
    private $links;
    private $imageHandler;

    function __construct(DatetimeHelper $datetimeHelpers, Links $links, ImageHandler $imageHandler) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->links = $links;
        $this->imageHandler = $imageHandler;
    }

    public function history(Request $request)
    {
        $queryParams = $request->query();
        $limit = !empty($queryParams['limit']) ? (int)$queryParams['limit'] : 4;
        $currentPage = !empty($queryParams['page']) && (int)$queryParams['page'] > 0 ? (int)$queryParams['page'] : 1;
        $offset = (($currentPage - 1) * $limit);

        $condition = [
            ['userId',  Auth::id()],
            ['createdOn',  '>=', strtotime('-1 year')]
        ];

        $orderQuery = Order::where($condition)->with([
            'restaurant:id,title,extra,city,postcode,postcodeUrl,cityUrl,urlTitle,serviceDomainName,orderOnlineUrl',
            'restaurantImage' => function($query) {
                $query->select(['adv_id','image_name', 'image_folder'])->where('is_Primary_Image', 1);
            },
        ])->with(
            'orderMenuItems.menuItem:menuItemId,menuItemName',
            'orderMenuItems.orderMenuItemOptions.optionItem:optionItemId,optionItemName',
            'orderMenuItems.size'
        );

        $orders = $orderQuery->offset($offset)->limit($limit)->orderBy('orderId','desc')->get();

        if ($currentPage < 1) {
            $currentPage = 1;
        }

        $pagination = [
            'currentPage' => $currentPage,
            'itemPerPage' => $limit
        ];

        $response = ['pagination' => $pagination, 'data' => []];

        if (!empty($orders)) {
            foreach ($orders as $order) {

                $data = [
                    "orderId" => $order->orderId,
                    "restaurantId" => $order->restaurantId,
                    "orderType" => $order->orderType,
                    "totalFoodPrice" => $order->totalFoodPrice,
                    "deliveryPrice" => $order->deliveryPrice,
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

                $formattedDate = $this->datetimeHelpers->getDanishFormattedDate($order->createdOn);
                $formattedTime = $this->datetimeHelpers->timeStampToFormat("G:i", $order->createdOn);
                $data['orderDateTime'] = sprintf("%s %s", $formattedDate, $formattedTime);

                if (!empty($order->restaurantImage)) {
                    $data["restaurantImageWebPath"] = $this->imageHandler->GetImagePathAfterResizing($order->restaurantImage->image_folder, $order->restaurantImage->image_name, 200, 200, false);
                }

                $restaurant = $order->restaurant;

                if (!empty($restaurant)) {

                    $restaurantUrl = $this->links->menuLink($restaurant->id, $restaurant->url, $restaurant->urlTitle, $restaurant->serviveDomainName, $restaurant->cityUrl);
                    $restaurantReviewUrl = $this->links->writeReviewLink($restaurant->id, $restaurant->urlTitle, $restaurant->cityUrl);
                    $restaurantOrderOnlineUrl = $this->links->orderOnlineLink($restaurant->id, $restaurant->orderOnlineUrl);

                    if(!empty($restaurant->orderOnlineUrl))
                    {
                        $orderOnlineUrl = sprintf("%s?orderUniqueId=%s", $restaurantOrderOnlineUrl, $order->orderUniqueId);
                    } else {
                        $orderOnlineUrl = sprintf("%s&orderUniqueId=%s", $restaurantOrderOnlineUrl, $order->orderUniqueId);
                    }

                    $data['restaurantId'] = $restaurant->id;
                    $data['restaurantName'] = $restaurant->title;
                    $data['restaurantUrl'] = $restaurantUrl;
                    $data['restaurantReviewUrl'] = $restaurantReviewUrl;
                    $data['postcodeUrl'] = $this->links->postcodeLink($restaurant->postcode);
                    $data['orderOnlineUrl'] = $orderOnlineUrl;

                    $restaurantAddress = '';
                    if (!empty($restaurant->extra)) {
                        $extraDecoded = json_decode($restaurant->extra, true);
                        $restaurantAddress = !empty($extraDecoded['address']) ? $extraDecoded['address'].' ' : '';
                    }

                    $restaurantCity = !empty($restaurant->city) ? $restaurant->city : '';
                    $restaurantPostcode = !empty($restaurant->postcode) ? ', '.$restaurant->postcode : '';

                    $data['restaurantAddress'] = sprintf('%s%s%s', $restaurantAddress, $restaurantCity, $restaurantPostcode);
                }


                $orderMenuItems = $order->orderMenuItems;

                if (!empty($orderMenuItems)) {
                    $menuItems = [];
                    foreach ($orderMenuItems as $orderMenuItem) {

                        $menuItem = [
                            'orderId' => $orderMenuItem->orderId,
                            'menuItemId' => $orderMenuItem->menuItemId,
                            'sizeId' => $orderMenuItem->sizeId,
                            'orderMenuItemId' => $orderMenuItem->orderMenuItemId,
                            'priceOfMenuItem' => $orderMenuItem->priceOfMenuItem,
                            'quantity' => $orderMenuItem->quantity,
                            'totalPriceWithOptions' => $orderMenuItem->totalPriceWithOptions,
                            'menuItemName' => !empty($orderMenuItem->menuItem) ? $orderMenuItem->menuItem->menuItemName : null,
                            'size' => !empty($orderMenuItem->size) ? $orderMenuItem->size->size : null,
                        ];

                        $orderMenuItemOptions = $orderMenuItem['orderMenuItemOptions'];

                        if (!empty($orderMenuItemOptions)) {
                            $menuItemOptions = [];
                            foreach ($orderMenuItemOptions as $orderMenuItemOption) {
                                $menuItemOption = [
                                    "orderMenuItemOptionId" => $orderMenuItemOption->orderMenuItemOptionId,
                                    "orderMenuItemId" => $orderMenuItemOption->orderMenuItemId,
                                    "optionId" => $orderMenuItemOption->optionId,
                                    "optionItemId" => $orderMenuItemOption->optionItemId,
                                    "price" => $orderMenuItemOption->price,
                                    "quantity" => $orderMenuItemOption->quantity,
                                    "optionItemName" => !empty($orderMenuItemOption->optionItem) ? $orderMenuItemOption->optionItem->optionItemName : null,
                                ];

                                $menuItemOptions[] = $menuItemOption;
                            }
                            $menuItem['menuItemOptions'] = $menuItemOptions;
                        }

                        $menuItems[] = $menuItem;
                    }

                    $data['menuItems'] = $menuItems;
                }

                $response['data'][] = $data;
            }

        }

        return response()->json(new BaseResponse(true, null, $response));
    }
}

?>
