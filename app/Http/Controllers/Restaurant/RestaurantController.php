<?php

namespace App\Http\Controllers\Restaurant;

use App\Shared\EatCommon\Amazon\AmazonS3;
use App\Libs\Helpers\Authentication;
use App\Models\Restaurant\AdvertisementImages;
use App\Models\Restaurant\AdvertisementFeatures;
use App\Models\Restaurant\Service;
use App\Models\Restaurant\Feature;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Restaurant\Advertisement;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Http\Controllers\BaseResponse;
use App\Shared\EatCommon\Image\ImageHandler;
use App\Models\Location\PlaceModel;
use App\Http\Controllers\Restaurant\SaveAndGetRestaurantTiming;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Shared\EatCommon\Location\GeoLocationExtractorApi;
use App\Shared\EatCommon\Link\Links;
use App\Models\User;
use Auth;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;

use function PHPSTORM_META\type;

class RestaurantController extends Controller
{
    private $datetimeHelpers;
    private $ipHelpers;
    private $amazonS3;
    private $imageHandler;
    private $links;
    private $translatorFactory;
    private $geoLocationExtractionApi;

    function __construct(TranslatorFactory $translatorFactory, DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, AmazonS3 $amazonS3, ImageHandler $imageHandler, Links $links, GeoLocationExtractorApi $geoLocationExtractionApi) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->amazonS3 = $amazonS3;
        $this->imageHandler = $imageHandler;
        $this->links = $links;
        $this->geoLocationExtractionApi = $geoLocationExtractionApi;
        $this->translatorFactory = $translatorFactory::getTranslator();
        
    }

    public function index()
    {
        //
    }

    public function fetch(Request $request, int $adId)
    {
        $validatorGet = Validator::make(['adId' => $adId], ['adId' => 'required|integer|min:1']);
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("RestaurantController fetch error. %s ", $validatorGet->errors()->first()));
        }

        $result = Advertisement::select(['title', 'maxDaysForTableBooking', 'enableFutureOrder', 'hasTakeaway', 'hasDelivery'])->where('id', $adId)->first();
        
        if(empty($result['maxDaysForTableBooking']))
        {
            $result['maxDaysForTableBooking'] = DEFAULT_MAXIMUM_DAYS_FOR_TABLE_BOOKING;
        }

        if(is_null($result['enableFutureOrder']))
        {
            $result['enableFutureOrder'] = 0;
        }

        return response()->json(new BaseResponse(true, null, $result));

    }


    public function fetchMenuCardImage(Request $request, int $adId)
    {
        $authentication = new Authentication();
        $authentication->doesRestaurantBelongsToUser($adId);
        $validatorGet = Validator::make(['adId' => $adId], ['adId' => 'required|integer|min:1']);
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("RestaurantController menu card fetch error. %s ", $validatorGet->errors()->first()));
        }

        $result = array();
        $imageFolder = "";
        $menuImageFolder = Advertisement::select('menuImages')->where('id', $adId)->first();
        if(!empty($menuImageFolder))
        {
            $imageCsv = $menuImageFolder["menuImages"];

            $resultForImages = AdvertisementImages::select('image_folder')->where('adv_id', $adId)->first();
            if(!empty($resultForImages))
            {
                $imageFolder = $resultForImages["image_folder"];
            }
            if(!empty($imageCsv))
            {
                $splitMenucards = explode(",", $imageCsv);

                foreach ($splitMenucards as $splitMenucard)
                {
                    $splitMenucardImage = explode("-", $splitMenucard);
                    if(!empty($splitMenucardImage))
                    {
                        $menuCardImageDetails = array();
                        $menuCardImageDetails["imageName"] = $splitMenucardImage[0];
                        $menuCardImageDetails["imageFolder"] = $imageFolder;
                        $menuCardImageDetails["imageHeight"] = $splitMenucardImage[1];
                        $menuCardImageDetails["imageWidth"] = $splitMenucardImage[2];
                        $menuCardImageDetails["imageWebPath"] = $this->amazonS3::GetWebPath(env('AMAZON_IMAGES_BUCKET'), $imageFolder, $menuCardImageDetails["imageName"]);
                        array_push($result, $menuCardImageDetails);
                    }
                }
            }
        }
        return response()->json(new BaseResponse(true, null, $result));
    }

    public function fetchRestaurantImages(Request $request, int $adId)
    {
        $authentication = new Authentication();
        $authentication->doesRestaurantBelongsToUser($adId);
        $validatorGet = Validator::make(['adId' => $adId], ['adId' => 'required|integer|min:1']);
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("RestaurantController images fetch error. %s ", $validatorGet->errors()->first()));
        }

        $result = array();
        $resultForImages = AdvertisementImages::select('image_name', 'image_folder', 'width', 'height')->where('adv_id', $adId)->orderBy('id', 'asc')->get();

        if(!empty($resultForImages))
        {
            foreach ($resultForImages as &$resultForImage)
            {
                $resultForImage->imageWebPath = $this->amazonS3::GetWebPath(env('AMAZON_IMAGES_BUCKET'), $resultForImage->image_folder, $resultForImage->image_name);
            }

        }
        return response()->json(new BaseResponse(true, null, $resultForImages));
    }

    public function fetchOrderOnlinePaymentDetails(Request $request, int $adId)
    {
        $validatorGet = Validator::make(['adId' => $adId], ['adId' => 'required|integer|min:1']);
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("RestaurantController fetchOrderOnlinePaymentDetails error. %s ", $validatorGet->errors()->first()));
        }

        $result = Advertisement::select('mobilePayQRImage', 'extra', 'orderOnlinePaymentMobilePay', 'orderOnlinePaymentCash', 'orderOnlinePaymentCard')->where('id', $adId)->first();
        $mobilePayQRImage = $result->mobilePayQRImage;
        $extra = $result->extra;
        $mobilePayQRImageWebPath = null;
        if($mobilePayQRImage)
        {
            $imageFolderObject = AdvertisementImages::select('image_folder')->where('adv_id', $adId)->first();
            $imageFolder = $imageFolderObject->image_folder;
            $mobilePayQRImageWebPath = $this->amazonS3::GetWebPath(env('AMAZON_IMAGES_BUCKET'), $imageFolder, $mobilePayQRImage);
        }

        $mobilePayNumber = null;
        if(!empty($extra))
        {
            $extraDecoded = json_decode($extra);
            if(!empty($extraDecoded) && !empty($extraDecoded->mobilePayNumber))
            {
                $mobilePayNumber = $extraDecoded->mobilePayNumber;
            }
        }

        $orderOnlinePaymentMobilePay = $result["orderOnlinePaymentMobilePay"];
        $orderOnlinePaymentCash = $result["orderOnlinePaymentCash"];
        $orderOnlinePaymentCard = intval($result["orderOnlinePaymentCard"]);
        $result = array();
        $result["mobilePayQRImageWebPath"] = $mobilePayQRImageWebPath;
        $result["mobilePayNumber"] = $mobilePayNumber;
        $result["orderOnlinePaymentMobilePay"] = $orderOnlinePaymentMobilePay;
        $result["orderOnlinePaymentCash"] = $orderOnlinePaymentCash;
        $result["orderOnlinePaymentCard"] = $orderOnlinePaymentCard;
        return response()->json(new BaseResponse(true, null, $result));
    }

    public function fetchRestaurantDetails()
    {
        $userId =  Auth::id();
        $validator = Validator::make(['uid' => $userId], ['uid' => 'required|integer']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("RestaurantController fetchRestaurantDetails error. %s ", $validator->errors()->first()));
        }

        $condition = [
            ['author_id', $userId],
            ['status',  STATUS_ACTIVE]
        ];
        
        $results = Advertisement::select([
            'id as restaurantId','title','extra','city','postcode','enableTableBooking','enableOrderOnline', 'isAutoPrintEnabled'])->with(['advertisementFrontImage' => function($query){
            $query->select(['id', 'adv_id', 'image_name', 'image_folder'])->where('is_Primary_Image', 1);
        }])->where($condition)->orderBy('updation_date', 'DESC')->first();

        if(!empty($results))
        {

            $results = $results->toArray();
            $extra = json_decode($results['extra'], true);                       
            $address = $extra['address'];
            $results['address'] = $address;
            unset($results['extra']);

            if(!empty($results['advertisement_front_image']))
            {
                $imageFolder = $results['advertisement_front_image'][0]['image_folder'];
                $imageName = $results['advertisement_front_image'][0]['image_name'];

                $imageWebPath = $this->amazonS3::GetWebPath(env('AMAZON_IMAGES_BUCKET'), $imageFolder, $imageName);
                $results['frontImage'] = $imageWebPath;
                unset($results['advertisement_front_image']);
            }

        }
        else
        {
            $results[] = ['restaurantId' => null, 'title' => null, 'city' => null, 'postcode' => null, 'enableTableBooking' => null, 'enableOrderOnline' => null, 'address' => null, 'frontImage' => null];
        }
        
       return response()->json(new BaseResponse(true, null, $results));
    }

    public function destroy($id)
    {
        //
    }

    public function updateIsAutoPrintEnabledStatus(int $restaurantId, int $autoPrintStatus) 
    {
        $isSuccess = false;

        try 
        {
            $validator = Validator::make(
                [
                    'restaurantId' => $restaurantId, 
                    'autoPrintStatus' => $autoPrintStatus
                ], 
                [
                    'restaurantId' => 'required|integer',
                    'autoPrintStatus' => 'required'
                ]
            );
    
            if ($validator->fails())
            {
                throw new Exception(sprintf("RestaurantController@updateIsAutoPrintEnabledStatus validation error %s ", $validator->errors()->first()));
            }
            
            Advertisement::where('id', $restaurantId)->update(['isAutoPrintEnabled' => $autoPrintStatus]);

            $isSuccess = true;
        }
        catch(Exception $e) 
        {
            Log::error(sprintf("Error found is RestaurantController@updateIsAutoPrintEnabledStatus Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }


        return response()->json(new BaseResponse($isSuccess, null, null));
    }

    public function getLoggedInUserRestaurant()
    {
        $isSuccess = false;
        $response = [];
        try
        {   
            $advertisement = Advertisement::select(
                ['id', 'title', 'urlTitle', 'summary', 'extra', 'phoneNumber', 'address', 'author_id', 'status', 'service', 'imageFolder',
                'serviceDomainName', 'city', 'postcode', 'companyName', 'companyCVR', 'menuImages', 'CardsSupported', 'sittingPlaces', 'smiley',
                'hasTakeaway', 'hasDelivery', 'hasSittingPlaces', 'menuCardPDFFileName', 'organicLevel', 'ownerName', 'registrationStep', 'menuCardPDFFiles']
            )->where('author_id', Auth::id())->first();

            if(!empty($advertisement))
            {
                $saveAndGetRestaurantTiming = new SaveAndGetRestaurantTiming();
                $advertisement->advertisementFrontImage;
                $advertisement->advertisementFeatures;
                $advertisement['working'] = $saveAndGetRestaurantTiming->fetchTimings(WORKING_TYPE_NORMAL_TIMING, $advertisement->id);
                $advertisement['extra'] = json_decode($advertisement['extra']);

                if(!empty($advertisement['CardsSupported']))
                {
                    $advertisement['CardsSupportedSeperated'] = str_getcsv($advertisement['CardsSupported']);
                }

                if(!empty($advertisement->advertisementFrontImage))
                {
                    foreach($advertisement->advertisementFrontImage as &$image)
                    {
                        $image['webPath'] = $this->amazonS3::GetWebPath(env('AMAZON_IMAGES_BUCKET'), $image['image_folder'], $image['image_name']);
                    }
                }
                $menuCardImages= [];
                if(!empty($advertisement['menuImages']))
                {
                    $menuImagesEntries = str_getcsv(trim($advertisement['menuImages'], ','));

                    foreach ($menuImagesEntries as $menuImagesEntry)
                    {
                        $explodedImageAttributes = explode('-', $menuImagesEntry);
                        $imageWebPath = $this->amazonS3::GetWebPath(env('AMAZON_IMAGES_BUCKET'), $advertisement['imageFolder'], $explodedImageAttributes[0]);
                        $menuCardImages[] = ['webPath' => $imageWebPath, 'imageName' => $explodedImageAttributes[0], 'fileHeight' => $explodedImageAttributes[1], 'width' => $explodedImageAttributes[2]];
                    }
                }
                $advertisement['menuCardImagesWebPath'] = $menuCardImages;

                $advertisement['menuCardPDFWebpath'] = null;    
                if(!empty($advertisement['menuCardPDFFiles']))
                {
                    $pdfFiles = unserialize($advertisement['menuCardPDFFiles']);
                    
                    if(!empty($advertisement['menuCardPDFFiles']))
                    {
                        foreach($pdfFiles['menucardPDFFiles'] as &$pdfFile)
                        {
                            $pdfFile['pdfFileWebPath'] = $this->amazonS3::GetWebPath(env('AMAZON_BUCKET'), $advertisement['imageFolder'], $pdfFile['pdfFileName']);
                        }

                        $advertisement['menuCardPDFFiles'] = $pdfFiles['menucardPDFFiles'];
                    }
                }
                
                $response = $advertisement;
                $isSuccess = true;
            }
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantController@getLoggedInUserRestaurant Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }
        return response()->json(new BaseResponse($isSuccess, null, $response));

    }

    public function saveRestaurantData(Request $request)
    {
        $isSuccess = false;
        $response = [];
        
        try
        {
            $rules = [
                'restaurantName' => 'required|string',
                'streetAndHouseNumber' => 'required|string',
                'postcode' => 'required|int',
                'city' => 'required|string',
                'restaurantInfo' => 'required|string',
            ];

            $userId = Auth::id();
            
            if(empty($userId))
            {
                $rules['email'] = 'required|string';
                $rules['password'] = 'required|string';
            }
    
            $validator = Validator::make($request->post(), $rules);            

            if($validator->fails())
            {
                throw new Exception(sprintf('RestaurantController saveRestaurantData error %s', $validator->errors()->first()));
            }

            Input::merge(array_map('trim', Input::except(['menuCardImage', 'menuCardPdfCollection', 'restaurantImage'])));

            
            $restaurantId = !empty($request->post('restaurantId')) ? $request->post('restaurantId') : null;
            $serviceName = 'Restaurant';
            $name = $request->post('restaurantName');
            $telephone = $request->post('orderTelephoneNumber');
            
            $service = Service::where('name', $serviceName)->first();
            $serviceDomainName = $service['serviceDomainName'];
            
            $places = PlaceModel::where('postcode', $request->post('postcode'))->get();
            
            $user = [];

            if(empty($userId))
            {
                $email = $request->post('email');
                $password = $request->post('password');
    
                $user = $this->createUser($name, $telephone, $email, $password);
                $userId = $user['uid'];
            }
            else
            {
                $user['uid'] = Auth::id();
                $user['rememberMe'] = true;
                $user['autoLoginHash'] = Auth::user()->auto_login_hash;
            }
            
            if (!empty($request->post('streetAndHouseNumber'))) 
            {
                $extra = array(
                    'address' => $request->post('streetAndHouseNumber'),
                    'telephone' => $telephone,
                    'ownerProvateTelephone' => $request->post('ownerTelephoneNumber'),
                    'mobilePayNumber' => $request->post('mobilePayNumber')
                );
            }

            $locationString = sprintf("%s+%s+denmark", ($request->post('streetAndHouseNumber')), ($request->post('city')));
            $locationString = str_replace(" ", "+", $locationString);

            $pointParameter = null;

            if(ENVIRONMENT_PRODUCTION)
            {
                $geoLocationResult = $this->geoLocationExtractionApi->GetGeoLocation($locationString);
            
                if(!empty($geoLocationResult))
                {
                    $pointParameter = 'POINT(' . $geoLocationResult->lat . ", " . $geoLocationResult->lng . ')';
                }
            }
            

            $data = [
                'title' => $name,
                'urlTitle' => $name,
                'companyName' => $name,
                'summary' => $this->removeLinkFromText($request->post('restaurantInfo')),
                'extra' => json_encode($extra),
                'phoneNumber' => intval($telephone),
                'address' => $request->post('streetAndHouseNumber'),
                'registrationStep' => RESTAURANT_CREATION_FIRST_TAB,
                'author_id' => $userId,
                'status' => RESTAURANT_STATUS_INACTIVE,
                'advertisement_type' => MASSAGE_AD_TYPE,
                'imageFolder' => $this->imageHandler->CreateNewFolderForAdvertisementImage(),
                'city' => $request->post('city'),
                'cityUrl' => $request->post('city'),
                'regionId' => 0,
                'postcode' => intval($request->post('postcode')), 
                'placesId' => $places[0]['PlacesId'], 
                'companyCVR' => $request->post('companyCvr'),
                'createdBy' => $userId,
                'service' => $serviceName,
                'geoPoint' => DB::raw($pointParameter),
                'serviceDomainName' => $serviceDomainName, 
                'lastInfoUpdatedOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                'creation_date' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                'updation_date' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
            ];
                                    

            Log::error(sprintf("User id - %s , data - %s", $userId, json_encode($data)));

            $restaurantId = Advertisement::insertGetId($data);
        
            if(!empty($request->post('restaurantTypes')))
            {
                $this->saveFeatures($request->post('restaurantTypes'), $restaurantId);
            }

            $response = [
                'adId' => $restaurantId,
                'userDetails' => $user,
                'imageFolder' => $data['imageFolder']
            ];

            $this->updateAdvFoodTypes($restaurantId);

            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantController@saveRestaurantData Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, null, $response));
    }

    public function updateRestaurantData(Request $request)
    {
        $isSuccess = false;
        $response = [];
        
        try
        {
            $rules = [
                'restaurantId' => 'required|int|min:1'
            ];

            $validator = Validator::make($request->post(), $rules);            

            if($validator->fails())
            {
                throw new Exception(sprintf('RestaurantController updateRestaurantData error %s', $validator->errors()->first()));
            }

            Input::merge(array_map('trim', Input::except(['menuCardImage', 'menuCardPdfCollection', 'restaurantImage'])));

            
            $restaurantId = $request->post('restaurantId');
            $serviceName = 'Restaurant';
            $name = $request->post('restaurantName');
            $telephone = $request->post('orderTelephoneNumber');
            
            $service = Service::where('name', $serviceName)->first();
            $serviceDomainName = $service['serviceDomainName'];
            
            $places = PlaceModel::where('postcode', $request->post('postcode'))->get();
            
            $userId = Auth::id();

            if (!empty($request->post('streetAndHouseNumber'))) 
            {
                $extra = array(
                    'address' => $request->post('streetAndHouseNumber'),
                    'telephone' => $telephone,
                    'ownerProvateTelephone' => $request->post('ownerTelephoneNumber'),
                    'mobilePayNumber' => $request->post('mobilePayNumber')
                );
            }

            $data = [
                'title' => $name,
                'urlTitle' => $name,
                'companyName' => $name,
                'summary' => $this->removeLinkFromText($request->post('restaurantInfo')),
                'extra' => json_encode($extra),
                'phoneNumber' => intval($telephone),
                'address' => $request->post('streetAndHouseNumber'),
                'registrationStep' => RESTAURANT_CREATION_FIRST_TAB,
                'author_id' => $userId,
                'status' => RESTAURANT_STATUS_INACTIVE,
                'advertisement_type' => MASSAGE_AD_TYPE,
                'city' => $request->post('city'),
                'cityUrl' => $request->post('city'),
                'regionId' => 0,
                'postcode' => intval($request->post('postcode')), 
                'placesId' => $places[0]['PlacesId'], 
                'companyCVR' => $request->post('companyCvr'),
                'createdBy' => $userId,
                'service' => $serviceName,
                'serviceDomainName' => $serviceDomainName,
                'lastInfoUpdatedOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                'updation_date' => $this->datetimeHelpers->getCurrentUtcTimeStamp()
            ];
                        
            if(!empty($request->post('menuCardImage'))) 
            {
                $menuImages = '';
                foreach ($request->post('menuCardImage') as $menuImageObject) 
                {
                    $menuImages .= sprintf("%s-%s-%s,", $menuImageObject["fileName"], $menuImageObject["fileHeight"], $menuImageObject["fileWidth"]);
                }

                $data['menuImages'] =  trim($menuImages, ",");
            }

            if(!empty($request->post('menuCardPdfCollection')))
            {
                foreach($request->post('menuCardPdfCollection') as $menuCardPdf)
                {
                    $menucardPDFFiles['menucardPDFFiles'][] = ['pdfFileName' => $menuCardPdf['fileName'], 'pdfOrginalName' => $menuCardPdf['fileOriginalName']];
                }
        
                $data['menuCardPDFFiles'] = serialize($menucardPDFFiles);
            }

            if(!empty($request->post('cardsSupported')))
            {
                $data['CardsSupported'] = $request->post('cardsSupported');
            }

            if(!empty($request->post('sittingPlaces')))
            {
                $data['sittingPlaces'] = intval($request->post('sittingPlaces'));
            }

            if(!empty($request->post('organicLevel')))
            {
                $data['organicLevel'] = intval($request->post('organicLevel'));
            }

            if(!empty($request->post('ownerName')))
            {
                $data['ownerName'] = $request->post('ownerName');
            }
            
            if(!empty($request->post('hasSittingPlaces')))
            {
                $data['hasSittingPlaces'] = $request->post('hasSittingPlaces') == "true" ? 1 : 0;
            }
            
            if(!empty($request->post('hasDelivery')))
            {
                $data['hasDelivery'] = $request->post('hasDelivery') == "true" ? 1 : 0;
            }
            
            if(!empty($request->post('hasTakeaway')))
            {
                $data['hasTakeaway'] = $request->post('hasTakeaway') == "true" ? 1 : 0;
            }

            if(isset($data['menuImages']) || !empty($request->post('restaurantImage')) || isset($data['menuCardPDFFiles']) || isset($data['CardsSupported']))
            {
                $data['registrationStep'] = RESTAURANT_CREATION_SECOND_TAB;
            }
            
            if($data['hasTakeaway'] || $data['hasDelivery'] || $data['hasSittingPlaces'] || isset($data['ownerName']) || isset($data['organicLevel']) || isset($data['sittingPlaces']))
            {
                $data['registrationStep'] = RESTAURANT_CREATION_THIRD_TAB;
            }

            Log::error(sprintf("User id - %s , data - %s", $userId, json_encode($data)));

            Advertisement::where('id', $restaurantId)->update($data);
            
            if(!empty($request->post('restaurantImage')))
            {
                $this->saveRestaurantImages($request->post('restaurantImage'), $restaurantId);
            }

            if(!empty($request->post('restaurantTypes')))
            {
                $this->saveFeatures($request->post('restaurantTypes'), $restaurantId);
            }


            $saveAndGetRestaurantTiming = new SaveAndGetRestaurantTiming();
            $saveAndGetRestaurantTiming->saveOrUpdateWorking($restaurantId, $request->all());

            $response = [
                'adId' => $restaurantId
            ];

            $this->updateAdvFoodTypes($restaurantId);

            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantController@updateRestaurantData Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, null, $response));
    }

    private function createUser(string $nickName, int $telephone, string $email, string $password)
    {
        $userDetail['uid'] = Auth::id();
        $userDetail['rememberMe'] = true;

        try
        {
            if($userDetail['uid'])
            {
                $data = [
                    'nick_name' => $nickName,
                    'company_name' => $nickName,
                    'type' => USER_NOT_ADMIN,
                    'phone' => $telephone
                ];
    
                User::where('uid', $userDetail['uid'])->update($data);
                
                $userDetail['autoLoginHash'] = Auth::user()->auto_login_hash;

                Log::error(sprintf("User updated with uid- %s ", $userDetail['uid']));

            }
            else
            {
                $user = new User();
                
                $user->phone = $telephone;
                $user->name = $nickName;
                $user->company_name = $nickName;	
                $user->status = INACTIVE;
                $user->type = USER_NOT_ADMIN;
                $user->ip = $this->ipHelpers->clientIpAsLong();
                $user->email = $email;
                $user->source_type = USER_REGISTRATION_SOURCE_TYPE_DIRECT; 
                $user->password = md5($password);
                $user->auto_login_hash = md5(mt_rand());
                $user->created_on = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $user->save();
    
                $userDetail['autoLoginHash'] = $user->auto_login_hash;
                $userDetail['uid'] = $user->uid;

                Log::error(sprintf("User created with email- %s ", $email));
            }
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantController@createUser Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }
        
        return $userDetail;
    }

    private function saveRestaurantImages(?array $images, int $restaurantId)
    {
        try
        {
            if(!empty($images))
            {
                $isPrimary = 1;
    
                AdvertisementImages::where('adv_id', $restaurantId)->delete();
    
                foreach ($images as $imageObject) 
                {
                    $data = [
                        'adv_id' => $restaurantId,
                        'image_name' => $imageObject['fileName'],
                        'image_folder' => $imageObject['imageFolderName'],
                        'is_Primary_Image' => $isPrimary,
                        'creation_date' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                        'height' => $imageObject['fileHeight'],
                        'width' => $imageObject['fileWidth']
                    ];

                    AdvertisementImages::insert($data);
                    $isPrimary = 0;			
                }
            }
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantController@saveRestaurantImages Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));

        }
    }

    private function removeLinkFromText(String $text) : String 
    {
        $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@";
        return preg_replace($regex, ' ', $text);
    }

    private function saveFeatures(?string $types, $restaurantId)
    {
        try
        {
            if($types)
            {
                $restaurantTypes = str_getcsv($types);

                AdvertisementFeatures::where('advId', $restaurantId)->delete();
    
                foreach ($restaurantTypes as $restaurantType) 
                {
                    AdvertisementFeatures::insert(['advId' => $restaurantId, 'featureId' => $restaurantType]);
                }
            }
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantController@saveFeatures Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }
    }

    private function updateAdvFoodTypes(int $adId): void
	{
        try
        {
            $typeIds = [FEATURE_TYPE_HEALTHY_FOOD_CATEGORIES, FEATURE_TYPE_CUISINE, FEATURE_TYPE_RESTAURANT_TYPE];

            $result = DB::table('advertisement_features')->join('features', 'advertisement_features.featureId',  '=',  'features.featureId')->where('advId', $adId)->whereIn('typeId', $typeIds)->get()->toArray();

            if (!empty($result))
            {
                $ignore = [];
    
                foreach($result as $index => $row)
                {
                    $count = $ignore[$row->advId] ?? 0;
    
                    if ($row->typeId === FEATURE_TYPE_HEALTHY_FOOD_CATEGORIES)
                    {   
                        $ignore[$row->advId] = ++$count;
    
                        if ($count > 1)
                        {
                            unset($result[$index]);
                        }
                    }
                }
            }
    
            if (!empty($result))
            {
                if (count($result))
                {				
                    $foodTypes = [];
    
                    foreach($result as $advertisementFoodType)
                    {
                        $foodTypes[] = [
                            'rank' => $advertisementFoodType->rank,
                            'advId' => $advertisementFoodType->advId,
                            'typeId' => $advertisementFoodType->typeId,
                            'featureId' => $advertisementFoodType->featureId,
                            'featureName' => $advertisementFoodType->featureName,
                        ];
                    }

                    if (!empty($foodTypes))
                    {
                        Advertisement::where('id', $adId)->update(['foodTypes' => json_encode($foodTypes)]);
                    }
                }	
            }
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantController@updateAdvFoodTypes Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }
	}

    public function verifyEmailForRestaurantCreation(Request $request)
    {
        $response  = [];
        $msg = "";
        try
        {
            $rules = array(
                'email' => 'required|string',
            );

            $validator = Validator::make($request->post(), $rules);

            if($validator->fails())
            {
                throw new Exception(sprintf("FeedbackController.saveFeedback error. %s ", $validator->errors()->first()));
            }

            $email = $request->post('email');
            $result = DB::table('user')->join('advertisement', 'user.uid', '=', 'advertisement.author_id')->where('email', $email)->get();

            if(!empty($result->toArray()))
            {
                $msg = sprintf('%s %s %s', $this->translatorFactory->translate('User with email') , $email, $this->translatorFactory->translate('already exists, please use some other email'));
            }
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is RestaurantController@verifyEmailAlreadyHaveARestaurantOrNot Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }
        
        return response()->json(new BaseResponse(true, $msg, $response));
    }
    
}
