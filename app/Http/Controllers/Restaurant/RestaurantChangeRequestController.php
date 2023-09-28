<?php

namespace App\Http\Controllers\Restaurant;
use App\Http\Controllers\BaseResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Restaurant\RestaurantChangeRequestModel;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Models\Restaurant\Advertisement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Link\Links;
use Auth;
use Exception;

class RestaurantChangeRequestController extends Controller {
   
    private $ipHelpers;
    private $datetimeHelpers;
    private $translatorFactory;
    private $links;

    function __construct(TranslatorFactory $translatorFactory, DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, Links $links)
    {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->links = $links;
    }

    public function saveMenucard(Request $request)
    {
        try
        {  
            $userId = 0;

            if(!empty(Auth::id()))
            {
                $userId = Auth::id();      
            }

            $validator = Validator::make($request->all(), [
                'adId' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json(['stat'=>false, 'errors'=>$validator->errors()]);
            }

            $clientIp = $this->ipHelpers->clientIpAsLong();
            $lastHour = $this->datetimeHelpers->getCurrentUtcTimeStamp() - (24*60*60);
            $ipCondition = [
                ['ip', $clientIp],
                ['timestamp', '>', $lastHour]
            ];

            $ipCount = RestaurantChangeRequestModel::where($ipCondition)->get()->count();

            if($ipCount < QUICK_CHANGE_REQUEST_MAXIMUM_ALLOWED_FROM_ONE_IP)
            { 
                $restaurantChangeRequestModel = new RestaurantChangeRequestModel;

                $menucardPDFFiles = [];

                if(!empty($request->post('menucardPDFFiles')))
                {
                    foreach($request->post('menucardPDFFiles') as $menuCardPdf)
                    {
                        $menucardPDFFiles['menucardPDFFiles'][] = ['pdfFileName' => $menuCardPdf['fileName'], 'pdfOrginalName' => $menuCardPdf['fileOriginalName']];
                    }
            
                    $menucardPDFFiles = serialize($menucardPDFFiles);
                }

                $adId =  $request->post('adId');

                $details = $this->getRestaurantDetails($adId);
                
                $details['menuCardImages'] = $request->post('menucardImages')  > 0 ? $request->post('menucardImages') : null;
                $details['menuCardPdf'] = $menucardPDFFiles;
                $details['openingTimings'] = null;

                $restaurantChangeRequestModel->adId  =  $adId;
                $restaurantChangeRequestModel->userId = $userId;
                $restaurantChangeRequestModel->status =  RESTAURANT_CHANGE_REQUEST_STATUS_ADDED;
                $restaurantChangeRequestModel->timestamp = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $restaurantChangeRequestModel->ip = $clientIp;
                $restaurantChangeRequestModel->requestType =  RESTAURANT_CHANGE_REQUEST_TYPE_MENU_CARD;
                $restaurantChangeRequestModel->details = json_encode($details);

                $restaurantChangeRequestModel->save();
                $uid = $restaurantChangeRequestModel->id;

                $redirectUrl = $this->links->CreateUrl(PAGE_MENUCARD_QUICK_CHANGE_SUCCESS_MESSAGE);
                
                if(!Auth::id())
                {
                    $redirectUrl = $this->links->CreateUrl(PAGE_USER_LOGIN_REGISTER, array(QUERY_ADIDWITHDASH => $adId, QUERY_LOGIN_ACTION_TYPE => LOGIN_ACTION_TYPE_MENUCARD_CHANGE_REQUEST, QUERY_LOGIN_ACTION_ID => $uid));
                }

            }

            return response()->json(new BaseResponse(true, null, $redirectUrl));
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantChangeRequestController@saveMenucard Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }
    }

    public function saveTimings(Request $request)
    {
        try
        {  
            $userId = 0;

            if(!empty(Auth::id()))
            {
                $userId = Auth::id();      
            }

            $validator = Validator::make($request->all(), [
                'adId' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json(['stat'=>false, 'errors'=>$validator->errors()]);
            }

            $clientIp = $this->ipHelpers->clientIpAsLong();
            $lastHour = $this->datetimeHelpers->getCurrentUtcTimeStamp() - (24*60*60);
            $ipCondition = [
                ['ip', $clientIp],
                ['timestamp', '>', $lastHour]
            ];

            $ipCount = RestaurantChangeRequestModel::where($ipCondition)->get()->count();

            if($ipCount < QUICK_CHANGE_REQUEST_MAXIMUM_ALLOWED_FROM_ONE_IP)
            { 
                $restaurantChangeRequestModel = new RestaurantChangeRequestModel;
                $adId =  Intval($request->post('adId'));
                $details = $this->getRestaurantDetails($adId);

                $details['menuCardImages'] = null;
                $details['menuCardPdf'] = null;
                $details['openingTimings']= $request->post('restaurantOpeningTimings');
                
                $restaurantChangeRequestModel->adId  =  $adId;
                $restaurantChangeRequestModel->userId = $userId;
                $restaurantChangeRequestModel->status =  RESTAURANT_CHANGE_REQUEST_STATUS_ADDED;
                $restaurantChangeRequestModel->timestamp = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $restaurantChangeRequestModel->ip = $clientIp;
                $restaurantChangeRequestModel->requestType =  RESTAURANT_CHANGE_REQUEST_TYPE_TIMINGS;
                $restaurantChangeRequestModel->details = json_encode($details);
                $restaurantChangeRequestModel->save();
                $uid = $restaurantChangeRequestModel->id;

                $redirectUrl = $this->links->CreateUrl(PAGE_TIMINGS_QUICK_CHANGE_SUCCESS_MESSAGE);
                
                if(!Auth::id())
                {
                    $redirectUrl = $this->links->CreateUrl(PAGE_USER_LOGIN_REGISTER, array(QUERY_ADIDWITHDASH => $adId, QUERY_LOGIN_ACTION_TYPE => LOGIN_ACTION_TYPE_TIMINGS_CHANGE_REQUEST, QUERY_LOGIN_ACTION_ID => $uid));
                }

            }

            return response()->json(new BaseResponse(true, null, $redirectUrl));
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantChangeRequestController@saveTimings Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }
    }

    private function getRestaurantDetails(int $adId)
    {
        try
        {
            $validator = Validator::make(['adId' => $adId], ['adId' => 'required|integer|min:1']);
            if ($validator->fails()) {
                return response()->json(['stat'=>false, 'errors'=>$validator->errors()]);
            }

            $result = Advertisement::select(['title', 'address' , 'postcode', 'CardsSupported', 'summary', 'hasTakeaway', 'hasDelivery', 'hasSittingPlaces','deliveryPrices','sittingPlaces', 'organicLevel'])->where('id', $adId)->first();

            $details = [
                'restaurantName' => $result['title'],
                'address' => $result['address'],
                'postCode' => $result['postcode'],
                'paymentMethodsAccepted' => $result['CardsSupported'],
                'telephoneNumber' => null,
                'mobilePayNumber' => null,
                'ownersPrivateNumber' => null,
                'restaurantDescription' => $result['summary'],
                'hasTakeAway' => $result['hasTakeaway'],
                'hasDelivery' => $result['hasDelivery'],
                'isRestaurant' => $result['hasSittingPlaces'],
                'deliveryPrices' => null,
                'sittingPlaces' => $result['sittingPlaces'],
                'organicLevel' => $result['organicLevel'],
            ];

            return $details;
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantChangeRequestController@getRestaurantDetails Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }
    }
}