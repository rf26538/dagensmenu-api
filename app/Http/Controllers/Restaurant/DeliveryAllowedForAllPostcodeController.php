<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant\Advertisement;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseResponse;
use Illuminate\Http\Request;

class DeliveryAllowedForAllPostcodeController extends Controller
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function isAllowed(int $restaurantId)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|int|min:1']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("DeliveryAllowedForAllPostcodeController@isAllowed error is %s", $validator->errors()->first()));
        }

        $result = Advertisement::select(['isDeliveryAllowedOutsideThePostcodes', 'areDeliveryPostcodePricesPresent'])->where('id', $restaurantId)->get()->toArray();

        $response = true;

        if (!empty($result))
        {
            if ($result[0]['areDeliveryPostcodePricesPresent'])
            {
                $response = is_null($result[0]['isDeliveryAllowedOutsideThePostcodes']) ? false : $result[0]['isDeliveryAllowedOutsideThePostcodes'];
            }
        }
        else
        {
            Log::critical(sprintf("Error in DeliveryAllowedForAllPostcodeController@isAllowed Error is restaurant is not found for restaurantId %s", $restaurantId));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }
    
    public function save(int $restaurantId)
    {
        $isSuccess = false;

        try
        {
            $validator = Validator::make([
                'restaurantId' => $restaurantId,
                'status' => $this->request->post('status')
            ], [
                'restaurantId' => 'required|int|min:1',
                'status' => 'required|int',
            ]);
    
            if ($validator->fails())
            {
                throw new Exception(sprintf("DeliveryAllowedForAllPostcodeController@save error is %s", $validator->errors()->first()));
            }
    
            Advertisement::where('id', $restaurantId)->update([
                'isDeliveryAllowedOutsideThePostcodes' => $this->request->post('status')
            ]);

            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in DeliveryAllowedForAllPostcodeController@save error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, null, true));
    }
}

?>