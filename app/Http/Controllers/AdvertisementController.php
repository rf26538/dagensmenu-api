<?php

namespace App\Http\Controllers;

use App\Models\Restaurant\Advertisement;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\JWTAuth;
use DB;
use App\Http\Controllers\BaseResponse;
use App\Models\EatAutomatedCollection\OurRestaurantGoogleInformation;
use App\Shared\EatCommon\Link\Links;
use Exception;
use App\Shared\EatCommon\Language\TranslatorFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Shared\EatCommon\Amazon\AmazonS3;
use App\Helpers\Translate;
use App\Models\Restaurant\AdvertisementImages;

class AdvertisementController extends Controller
{
    private $links;
    private $translatorFactory;
    private $datetimeHelper;
    private $amazonS3;

    public function __construct(Links $links, TranslatorFactory $translatorFactory, DatetimeHelper $datetimeHelper, AmazonS3 $amazonS3)
    {
        $this->links = $links;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->datetimeHelper = $datetimeHelper;
        $this->amazonS3 = $amazonS3;
    }


    public function get($id)
    {
//        $this->validate($request, [
//            'email'    => 'required|email|max:255',
//            'password' => 'required',
//        ]);
//
        $result = DB::table('advertisement')->select('title')->where('id', $id)->first();
        //$result = DB::connection('mysql_eat_order')->table('categories')->select('categoryName', 'restaurantId', 'userId')->where('categoryId', $id)->first();
        return response()->json($result);
    }


    public function changeIsPromotionalRestaurantStatus(int $restaurantId, int $status)
    {
        $message = null;
        $isSuccess = false;

        try
        {
            $validator = Validator::make([
                'restaurantId' => $restaurantId,
                'status' => $status
            ], 
            [
                'restaurantId' => 'required|int|min:1',
                'status' => 'required|min:0|max:1'
            ]);
           
            if($validator->fails())
            {
                throw new Exception(sprintf("Validation failed in AdvertisementController@changeIsPromotionalRestaurantStatus %s ", $validator->errors()->first()));
            }

            $advertisement = Advertisement::find($restaurantId);

            if ($advertisement)
            {
                $advertisement->isPromotionalRestaurant = $status;
                $advertisement->lastInfoUpdatedOn = $this->datetimeHelper->getCurrentUtcTimeStamp();
                $advertisement->save();
            }

            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in AdvertisementController@changeIsPromotionalRestaurantStatus Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, $message, null));
    }

    public function getIsPromotionalRestaurant(int $restaurantId)
    {
        $message = null;
        $isSuccess = false;
        $response = 0;

        try
        {
            $validator = Validator::make([
                'restaurantId' => $restaurantId,
            ], 
            [
                'restaurantId' => 'required|int|min:1',
            ]);
           
            if($validator->fails())
            {
                throw new Exception(sprintf("Validation failed in AdvertisementController@getIsPromotionalRestaurant %s ", $validator->errors()->first()));
            }

            $advertisement = Advertisement::find($restaurantId);

            if ($advertisement->isPromotionalRestaurant)
            {
                $response = $advertisement->isPromotionalRestaurant;
            }

            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in AdvertisementController@changeIsPromotionalRestaurantStatus Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, $message, $response));
    }

    public function enableFutureOrder(Request $request, int $restaurantId)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|int|min:1']);

        if($validator->fails())
        {
            throw new Exception(sprintf("AdvertisementController enableFutureOrder error. %s ", $validator->errors()->first()));
        }

        $data = $request->all();

        $updatedOn = $this->datetimeHelper->getCurrentUtcTimeStamp();
        $enableFutureOrder = $data['enableFutureOrder'];
        
        Advertisement::where('id', $restaurantId)->update(array('enableFutureOrder' => $enableFutureOrder, 'lastInfoUpdatedOn' => $updatedOn));
        
        return response()->json(new BaseResponse(true, null, null));
    }

    public function authenticationCheck()
    {
        return "user has been authenticated";
    }

    public function getClosedRestaurantOnVirkSmileyAndGoogleInformations() 
    {
        $results = Advertisement::from('advertisement AS a')->select([
            'a.id', 'a.title', 'a.id',
            'a.url',
            'a.urlTitle',
            'a.extra',
            'a.city',
            'a.smileyUrl',
            'a.postcode',
            'a.serviceDomainName',
            'a.cityUrl',
            'vri.virkRestaurantInformationId', 
            'vri.isRestaurantClosedPercentage', 
            'vri.virkRawResponse', 
            'orgi.ourRestaurantGoogleInformationId',
            'a.smileyAbsenceTimestamp', 
            'vri.companyStopDate', 'orgi.isRestaurantPermanentlyClosedOnGoogle'
        ])->leftJoin(
            'eat_automated_collection.virk_restaurant_informations AS vri', 'vri.ourRestaurantId', '=', 'a.id'
        )->leftJoin(
            'eat_automated_collection.our_restaurant_google_informations AS orgi', 'orgi.ourRestaurantId', '=', 'a.id'
        )->where('a.status', STATUS_ACTIVE)->whereNull('a.restaurantClosedOn')->whereRaw('(a.smileyAbsenceTimestamp IS NOT NULL OR vri.companyStopDate IS NOT NULL OR vri.isRestaurantClosedPercentage IS NOT NULL) AND orgi.isRestaurantPermanentlyClosedOnGoogle = 1 AND orgi.isRestaurantVerified IS NULL')->orderByDesc('vri.isRestaurantClosedPercentage')->get()->toArray();

        if (!empty($results))
        {
            $closedText = $this->translatorFactory->translate('Closed');
            $openedText = $this->translatorFactory->translate('Opened');

            foreach($results as &$result)
            {
                $data['advId'] = $result['id'];
                $data['title'] = $result['title'];
                $data['smileyUrl'] = $result['smileyUrl'];

                $extra = json_decode($result['extra'], true);

                $data['restaurantTelephoneNumber'] = $extra['telephone'] ?? '';

                if (!empty($extra['address']))
                {
                    $data['title'] = sprintf("%s, %s %s %s", $result['title'], $extra['address'], $result['city'], $result['postcode']);
                }

                $isRestaurantClosedOnVirk = !is_null($result['companyStopDate']) || !is_null($result['isRestaurantClosedPercentage']) ? 1 : 0;
                $isRestaurantClosedOnSmiley = !is_null($result['smileyAbsenceTimestamp']) ? 1 : 0;
                $isRestaurantClosedOnGoogle = ($result['isRestaurantPermanentlyClosedOnGoogle'] == 1) ? 1 : 0;
            
                $data['isRestaurantClosedOnVirk'] = $isRestaurantClosedOnVirk ? sprintf('%s (%s%%)', $closedText, $result['isRestaurantClosedPercentage']) : $openedText;
                $data['isRestaurantClosedOnSmiley'] = $isRestaurantClosedOnSmiley ? $closedText : $openedText;
                $data['isRestaurantClosedOnGoogle'] = $isRestaurantClosedOnGoogle ? $closedText : $openedText;

                $data['restaurantUrl'] = $this->links->menuLink($result['id'], $result['url'], $result['urlTitle'], $result['serviceDomainName'], $result['cityUrl']);

                if ($isRestaurantClosedOnGoogle)
                {
                    $data['googleRestaurantUrl'] = sprintf("https://www.google.com/search?q=%s", $data['title']);
                }

                $data['position'] = intval(sprintf("%s%s%s%03d", $isRestaurantClosedOnVirk, $isRestaurantClosedOnSmiley, $isRestaurantClosedOnGoogle, intval($result['isRestaurantClosedPercentage'])));
                $data['virkRestaurants'] = [];

                if ($isRestaurantClosedOnVirk && !is_null($result['isRestaurantClosedPercentage']) && $result['isRestaurantClosedPercentage'] < 100)
                {
                    $virkRawResponse = json_decode($result['virkRawResponse'], true);
                    if (isset($virkRawResponse['hits']['total']) && isset($virkRawResponse['hits']['hits']) && $virkRawResponse['hits']['total'] > 0)
                    {
                        $virkResponseData = $virkRawResponse['hits']['hits'][0];
                        if (!empty($virkResponseData) && isset($virkResponseData['_source']['Vrvirksomhed']) && isset($virkResponseData['_source']['Vrvirksomhed']['navne']))
		                {
                            $virkRestaurants = $virkResponseData['_source']['Vrvirksomhed']['navne'];
                            if (!empty($virkRestaurants))
                            {
                                foreach($virkRestaurants as $virkRestaurant)
                                {
                                    $data['virkRestaurants'][] = [
                                        'restaurantName' => $virkRestaurant['navn'],
                                        'companyStartDate' => $virkRestaurant['periode']['gyldigFra'] ?? '',
                                        'companyStopDate' => $virkRestaurant['periode']['gyldigTil'] ?? '',
                                    ];
                                }
                            }
                        }
                    }
                }

                $result = $data;
            }

            usort($results, function($a, $b) {
                return $b['position'] - $a['position'];
            });
        }

        return response()->json(new BaseResponse(true, null, $results));
    }

    public function changeRestaurantCloseStatus(int $restaurantId, int $status)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId, 'status' => $status], ['restaurantId' => 'required|integer', 'status' => 'required|integer']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("AdvertisementController closeRestaurant error. %s ", $validator->errors()->first()));
        }

        $update = [
            'restaurantClosedOn' => $this->datetimeHelper->getCurrentUtcTimeStamp(),
            'restaurantClosedBy' => Auth::id(), 
            'status' => CLOSED
        ];

        $message = $this->translatorFactory->translate("Restaurant closed successfully");
        
        if ($status == 0)
        {
            $update = [
                'restaurantClosedOn' => null,
                'restaurantClosedBy' => null, 
                'status' => STATUS_ACTIVE
            ];
            
            $message = $this->translatorFactory->translate("Restaurant opened successfully");
        }

        Advertisement::where('id', $restaurantId)->update($update);

        return response()->json(new BaseResponse(true, $message, true));
    }

    public function updateOurRestaurantGoogleInformationIsVerified(Request $request)
    {
        $restaurantId =  $request->post('restaurantId');

        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|int|min:1']);

        if($validator->fails())
        {
            throw new Exception(sprintf("AdvertisementController updateGoogleRestaurantStatus error. %s ", $validator->errors()->first()));
        }

        OurRestaurantGoogleInformation::where('ourRestaurantId', $restaurantId)->update([
            'isRestaurantVerified' => 1
        ]);

        return response()->json(new BaseResponse(true, null, null));
    }

    public function getRestaurantsByKeyword(Request $request)
    {
        $isSuccess = false;
        $response = false;
        try
        {
            $val =  $request->post('keyword');

            $validator = Validator::make(['val' => $val], ['val' => 'required']);

            if($validator->fails())
            {
                throw new Exception(sprintf("AdvertisementController getRestaurantsByKeyword error. %s ", $validator->errors()->first()));
            }
            
            $condition = "";
            
            $email = (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $val)) ? false : true;

            if($email)
            {
                $condition = [
                    ['email', $val],
                    ['status', STATUS_ACTIVE]
                ]; 
            }
            
            if($condition)
            {
                $result = User::select('uid')->where($condition)->first();
                if (!empty($result))
                {
                    $condition = [
                        ['author_id', $result['uid']]
                    ]; 
                }
            }
            elseif(is_numeric($val))
            {
                if(strlen($val) >= 8)
                {
                    $condition = [
                        ['phoneNumber', $val]
                    ]; 
                }
                else
                {
                    $condition = [
                        ['id', $val]
                    ]; 
                }
            }
            else
            {
                $condition = [
                    ['title', 'LIKE', "%{$val}%"]
                ]; 
            }

            $response = Advertisement::select(['id', 'title', 'phoneNumber', 'summary', 'extra', 'address','author_id', 'status','service', 'advertisementPaymentExpiryDate'])->where($condition)->first();
            if($response)
            {
                $restaurantSecondaryPhoneNumber = "";
                $restaurantDetails = json_decode($response->extra);

                if(isset($restaurantDetails->ownerProvateTelephone))
                {
                    $restaurantSecondaryPhoneNumber = $restaurantDetails->ownerProvateTelephone;
                }
                
                $response->restaurantSecondaryPhone = $restaurantSecondaryPhoneNumber;

                $status = "";
                if($response->status == RESTAURANT_STATUS_ACTIVE) 
                {
                    $status = Translate::msg("Approved Restaurant");
                } 
                elseif($response->status == RESTAURANT_STATUS_INACTIVE) 
                {
                    $status = Translate::msg("Restaurant status pending");
                } 
                elseif($response->status == DISABLE) 
                {
                    $status = Translate::msg("Restaurant disabled");
                }

                $response->restaurantStatus = $status;
                $response->nickName = $response->advertisementUserName->nick_name;
                $imgResult = AdvertisementImages::where([['adv_id', $response->id]])->first();
            
                if(!empty($imgResult))
                {
                    $imageFolder = $imgResult->image_folder;
                    $imageName = $imgResult->image_name;
                    $response->imageWebPath = $this->amazonS3::GetWebPath(env('AMAZON_IMAGES_BUCKET'), $imageFolder, $imageName);
                }
                else
                {                   
                    $response->imageWebPath = $this->amazonS3::GetWebPath(AMAZON_BUCKET, S3_STATIC_IMAGES_FOLDER_NAME, DEFAULT_RESTAURANT_IMAGE);
                }

                $response->expireDate = $this->datetimeHelper->getDanishFormattedDate($response->advertisementPaymentExpiryDate);
                $isSuccess = true;
            }

            return response()->json(new BaseResponse($isSuccess, null, $response));
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in AdvertisementController@getRestaurantsByKeyword Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

    }

    public function fetchRestaurantByInformation(Request $request)
    {
        $resp = [];
        
        try
        {
            $requestAll = $request->all();
    
            $telephoneNumber = $requestAll['phoneNumber'] ?? '';
            $restaurantName =  $requestAll['title'] ?? '';
            $restaurantPostcode =  $requestAll['postcode'] ?? '';
            $restaurantOwnerPhoneNumber =  $requestAll['restaurantOwnerPhoneNumber'] ?? '';

            $advertisement = Advertisement::select('id', 'imageFolder');

            $condition = [];

            $phoneNumber = !empty($restaurantOwnerPhoneNumber) ? $restaurantOwnerPhoneNumber : $telephoneNumber ;

            if ($phoneNumber)
            {
                $advertisement->whereRaw(sprintf("JSON_EXTRACT(extra, '$.ownerProvateTelephone') = '%s' OR phoneNumber = %s", $phoneNumber, $phoneNumber));
            }

            if(!$phoneNumber && $restaurantPostcode && $restaurantName)
            { 
                $condition = [
                    ['postcode', intval($restaurantPostcode)],
                    ['title', 'LIKE', "%{$restaurantName}%"]
                ];
            }

            $resp =  $advertisement->where($condition)->get()->first();
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in AdvertisementController@fetchRestaurantByInformation Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $resp));
    }
}