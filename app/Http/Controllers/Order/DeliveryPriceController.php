<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseResponse;
use App\Models\Log\DeliveryPriceChange;
use App\Models\Order\DeliveryPrice;
use App\Models\Restaurant\Advertisement;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use Illuminate\Support\Facades\Auth;


class DeliveryPriceController extends Controller
{
    private $request;
    private $datetimeHelper;
    private $iPHelpers;

    public function __construct(Request $request, DatetimeHelper $datetimeHelper, IPHelpers $iPHelpers)
    {
        $this->request = $request;
        $this->datetimeHelper = $datetimeHelper;
        $this->iPHelpers = $iPHelpers;
    }

    public function fetchAll(int $restaurantId)
    {
        $results = ['restaurant' => [], 'deliveryPrices' => []];
        $isSuccess = false;

        try
        {
            $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|int|min:1']);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            $restaurantWithDeliveryPrices = Advertisement::select(['id', 'title', 'isDeliveryAllowedOutsideThePostcodes'])->with(['deliveryPrices' => function($query) {
                $query->whereNull('status');
            }])->where('id', $restaurantId)->get()->toArray();

            if (!empty($restaurantWithDeliveryPrices))
            {
                $results['restaurant'] = $restaurantWithDeliveryPrices[0];
                $results['deliveryPrices'] = $restaurantWithDeliveryPrices[0]['delivery_prices'];

                if (!empty($results['deliveryPrices']))
                {
                    foreach($results['deliveryPrices'] as &$row)
                    {
                        $row['postcodeStart'] = intval($row['postcodeStart']);
                        $row['postcodeEnd'] = intval($row['postcodeEnd']);
                    }
                }

                unset($results['restaurant']['delivery_prices']);
                unset($results['restaurant']['id']);
            }

            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found in DeliveryPriceController@fetchAll error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, null, $results));
    }

    public function save() 
    {
        $isSuccess = false;
        $response = $responseMsg = null;

        try
        {
            $validator = Validator::make([
                'restaurantId' => $this->request->post('restaurantId'),
                'deliveryPrice' => $this->request->post('deliveryPrice'),
                'postcodeStart' => $this->request->post('postcodeStart'),
                'postcodeEnd' => $this->request->post('postcodeEnd'),
            ], [
                'restaurantId' => 'required|int|min:1',
                'deliveryPrice' => 'required|int|min:1',
                'postcodeStart' => 'required|int|min:1',
                'postcodeEnd' => 'int|gte:postcodeStart',
            ]);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            $postData = $this->request->post();


            $getDeliveryPriceFromPostcode = DeliveryPrice::where('restaurantId', $postData['restaurantId'])->whereRaw(sprintf("(
                (postcodeStart <= %s && postcodeEnd >= %s) || (postcodeStart <= %s && postcodeEnd >= %s) || (postcodeStart <= %s && postcodeEnd >= %s)
            )", $postData['postcodeStart'], $postData['postcodeStart'], $postData['postcodeEnd'], $postData['postcodeEnd'], $postData['postcodeStart'], $postData['postcodeEnd']))->whereNull('status')->get()->toArray();

            if (empty($getDeliveryPriceFromPostcode))
            {
                $deliveryPrice = new DeliveryPrice();
                $deliveryPrice->restaurantId = $postData['restaurantId'];
                $deliveryPrice->price = $postData['deliveryPrice'];
                $deliveryPrice->postcodeStart = $postData['postcodeStart'];
                $deliveryPrice->postcodeEnd = $postData['postcodeEnd'];
                $deliveryPrice->createdOn = $this->datetimeHelper->getCurrentTimeStamp();
                $deliveryPrice->userId = Auth::id();
                $deliveryPrice->ip = $this->iPHelpers->clientIpAsLong();
                $deliveryPrice->save();
    
                $advertisement = Advertisement::find($postData['restaurantId']);
                $advertisement->areDeliveryPostcodePricesPresent = ORDER_ONLINE_DELIVERY_PRICES_ARE_PRESENT;
                $advertisement->save();
    
                $response = $deliveryPrice->deliveryPriceId;
                $isSuccess = true;
            }
            else
            {
                $responseMsg = "Delivery price already exists.";
            }
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in DeliveryPriceController@save error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, $responseMsg, $response));
    }

    public function update(int $deliveryPriceId) 
    {
        $isSuccess = false;
        $response = null;

        try
        {
            $validator = Validator::make([
                'deliveryPriceId' => $deliveryPriceId,
                'restaurantId' => $this->request->post('restaurantId'),
                'deliveryPrice' => $this->request->post('deliveryPrice'),
                'postcodeStart' => $this->request->post('postcodeStart'),
                'postcodeEnd' => $this->request->post('postcodeEnd'),
            ], [
                'deliveryPriceId' => 'required|int|min:1',
                'restaurantId' => 'required|int|min:1',
                'deliveryPrice' => 'required|int|min:1',
                'postcodeStart' => 'required|int|min:1',
                'postcodeEnd' => 'int|gte:postcodeStart',
            ]);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            $this->saveDeliveryPriceChangeInfo($this->request->post('restaurantId'));

            $deliveryPrice = DeliveryPrice::find($deliveryPriceId);
            $deliveryPrice->price = $this->request->post('deliveryPrice');
            $deliveryPrice->postcodeStart = $this->request->post('postcodeStart');
            $deliveryPrice->postcodeEnd = $this->request->post('postcodeEnd');
            $deliveryPrice->updatedOn = $this->datetimeHelper->getCurrentTimeStamp();
            $deliveryPrice->save();

            $response = $deliveryPrice->deliveryPriceId;
            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in DeliveryPriceController@update error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, null, $response));
    }

    public function delete(int $restaurantId, int $deliveryPriceId) 
    {
        $isSuccess = false;
        $response = null;

        try
        {
            $validator = Validator::make([
                'deliveryPriceId' => $deliveryPriceId
            ], [
                'deliveryPriceId' => 'required|int|min:1'
            ]);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            $this->saveDeliveryPriceChangeInfo($restaurantId);

            $deliveryPrice = DeliveryPrice::find($deliveryPriceId);
            $deliveryPrice->status = ORDER_ONLINE_DELIVERY_PRICE_STATUS_DELETED;
            $deliveryPrice->save();
            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in DeliveryPriceController@delete error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, null, $response));
    }

    private function saveDeliveryPriceChangeInfo(int $restaurantId)
    {
        $deliveryPriceChange = new DeliveryPriceChange();

        $deliveryPrices = DeliveryPrice::where('restaurantId', $restaurantId)->get()->toArray();

        if (!empty($deliveryPrices))
        {
            $deliveryPriceChange->details = json_encode($deliveryPrices);
            $deliveryPriceChange->userId = Auth::id();
            $deliveryPriceChange->restaurantId = $restaurantId;
            $deliveryPriceChange->ip = $this->iPHelpers->clientIpAsLong();
            $deliveryPriceChange->createdOn = $this->datetimeHelper->getCurrentTimeStamp();
            $deliveryPriceChange->save();
        }
    }
}

?>