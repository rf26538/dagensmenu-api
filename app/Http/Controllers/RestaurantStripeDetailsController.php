<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Models\Payment\RestaurantStripeDetails;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Helpers\Translate;
use Illuminate\Support\Facades\Log;

class RestaurantStripeDetailsController extends Controller
{
    private $datetimeHelper;

    public function __construct(DatetimeHelper $datetimeHelper)
    {
        $this->datetimeHelper = $datetimeHelper;
    }

    public function getRestaurantStripeDetails(Request $request)
    {
        $isSuccess = false;
        $response = [];
        $message = null;
        
        try
        {
            $adId =  $request->post('adId');
            $validator = Validator::make(['adId' => $adId], ['adId' => 'required|int|min:1']);

            if($validator->fails())
            {
                throw new Exception(sprintf("RestaurantStripeDetailsController@getRestaurantStripeDetails error. %s adId is %s", $validator->errors()->first(), $request->post('adId')));
            }

            $results = RestaurantStripeDetails::select(['id', 'cardHolderName', 'status'])->where([['restaurantId', $adId]])->get();

            if(!empty($results->toArray()))
            {
                foreach($results as $res)
                {
                    if($res['status'] == 1)
                    {
                        array_push($response, $res);
                    }
                }
                $isSuccess = true;

                if(empty($response))
                {
                    $isSuccess = true;
                    $message = Translate::msg("All cards are disabled");
                }
            }
            else
            {
                $message = Translate::msg("No card available");
            }
    }
    catch(Exception $e)
    {
        Log::critical(sprintf("Error found in RestaurantStripeDetailsController@getRestaurantStripeDetails Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
    }

        return response()->json(new BaseResponse($isSuccess, $message, $response));
    }

    public function removeStripeCard(Request $request)
    {
        try
        {
            $id =  $request->post('id');
            $validator = Validator::make(['id' => $id], ['id' => 'required|int|min:1']);

            if($validator->fails())
            {
                throw new Exception(sprintf("RestaurantStripeDetailsController@removeStripeCard error. %s ", $validator->errors()->first()));
            }

            RestaurantStripeDetails::where('id', $id)->update([
                'status' => CARD_DEACTIVATE,
                'disabledOn' => $this->datetimeHelper->getCurrentUtcTimeStamp(),
                'disabledBy' => Auth::id(),
            ]); 

        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in RestaurantStripeDetailsController@removeStripeCard Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, NULL, NULL));
    }
}

?>