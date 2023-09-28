<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Models\Log\DeliveryRejectedOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Link\Links;
use Exception;


class DeliveryRejectedOrderController extends Controller
{
    private $request;
    private $datetimeHelper;
    private $iPHelpers;
    private $links;

    public function __construct(Request $request, DatetimeHelper $datetimeHelper, IPHelpers $iPHelpers, Links $links)
    {
        $this->request = $request;
        $this->datetimeHelper = $datetimeHelper;
        $this->iPHelpers = $iPHelpers;
        $this->links = $links;
    }

    public function save()
    {
        $isSuccess = true;

        try
        {
            $validator = Validator::make([
                'postcode' => $this->request->post('postcode'),
                'restaurantId' => $this->request->post('restaurantId'),
                'orderTotalPrice' => $this->request->post('orderTotalPrice'),
            ], [
                'postcode' => 'required|int|min:1',
                'restaurantId' => 'required|int|min:1',
                'orderTotalPrice' => 'required|int|min:1'
            ]);
    
            if ($validator->fails())
            {
                throw new Exception(sprintf("DeliveryRejectedOrder@save error is %s", $validator->errors()->first()));
            }

            $deliveryRejectedOrder = new DeliveryRejectedOrder();
            $deliveryRejectedOrder->userId = Auth::id();
            $deliveryRejectedOrder->postcode  = $this->request->post('postcode');
            $deliveryRejectedOrder->restaurantId  = $this->request->post('restaurantId');
            $deliveryRejectedOrder->orderTotalPrice  = $this->request->post('orderTotalPrice');
            $deliveryRejectedOrder->createdOn = $this->datetimeHelper->getCurrentTimeStamp();
            $deliveryRejectedOrder->ip = $this->iPHelpers->clientIpAsLong();
            $deliveryRejectedOrder->save();

            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in DeliveryRejectedOrder@save error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, null, true));
    }

    public function fetchAll()
    {
        $results = DeliveryRejectedOrder::select([
            'delivery_rejected_orders.deliveryRejectedOrderId',
            'delivery_rejected_orders.restaurantId',
            'delivery_rejected_orders.postcode',
            'delivery_rejected_orders.orderTotalPrice',
            'delivery_rejected_orders.createdOn',
            'a.title AS restaurantName',
            'a.url',
            'a.urlTitle',
            'a.postcode',
            'a.serviceDomainName',
            'a.cityUrl'
        ])->leftJoin('eat.advertisement AS a', 'a.id', '=', 'delivery_rejected_orders.restaurantId')->where('a.status', STATUS_ACTIVE)->orderBy('delivery_rejected_orders.createdOn', 'DESC')->get()->toArray();
    

        if (!empty($results))
        {
            foreach($results as &$result)
            {
                $result['orderTotalPrice'] = $result['orderTotalPrice'] / CURRENCY_MULTIPLIER;
                $result['createdOn'] = $this->datetimeHelper->getDanishFormattedDateTime($result['createdOn']);
                $result['restaurantUrl'] = $this->links->menuLink($result['restaurantId'], $result['url'], $result['urlTitle'], $result['serviceDomainName'], $result['cityUrl']);
                
                unset($result['restaurantId']);
                unset($result['url']); 
                unset($result['cityUrl']);
                unset($result['urlTitle']); 
                unset($result['serviceDomainName']); 
            }
        }


        return response()->json(new BaseResponse(true, null, $results));
    }
}

?>