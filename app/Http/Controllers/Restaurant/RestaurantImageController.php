<?php

namespace App\Http\Controllers\Restaurant;
use App\Http\Controllers\BaseResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Shared\EatCommon\Amazon\AmazonS3;
use App\Shared\EatCommon\Helpers\FileHandler;
use App\Shared\EatCommon\Image\ImageHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Restaurant\UserImages;
use App\Models\Restaurant\Advertisement;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Illuminate\Support\Facades\Auth;
use Exception;
class RestaurantImageController extends Controller
{
    private $fileHandler;
    private $imageHandler;
    private $amazonS3;
    private $ipHelpers;
    private $dateTimeHelpers;
    private $translatorFactory;
    private $request;

    public function __construct(FileHandler $fileHandler, ImageHandler $imageHandler, AmazonS3 $amazonS3, TranslatorFactory $translatorFactory, IPHelpers $ipHelpers, DatetimeHelper $dateTimeHelpers, Request $request)
    {
        $this->fileHandler = $fileHandler;
        $this->imageHandler = $imageHandler;
        $this->amazonS3 = $amazonS3;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->dateTimeHelpers = $dateTimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->request = $request;
    }

    public function uploadImage(Request $request)
    {  
        $isSuccess = false;
        $response = false;

        try
        {
            $fileInputFieldName = $request->post('fileControlName');
            $uploadedImage = $request->file($fileInputFieldName);
            $restaurantId = intval($request->post('restaurantId'));
            $folderName = $request->post('imageFolderName');

            $rules = array(
                $fileInputFieldName => 'mimes:jpeg,jpg,png,gif,webp|required|max:50000' // max 50000kb
            );
            
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails())
            {
                Log::critical(sprintf("RestaurantImageController file upload validation failed. %s ", $validator->errors()->first()));
                $response = new BaseResponse(true, null, null);
                return response()->json($response);
            }

            $isSuccess = true;
            
            if(!empty($restaurantId))
            {
    
                $advertisementDetails = Advertisement::select('imageFolder', 'status')->where('id', $restaurantId)->first();
                
                if(!empty($advertisementDetails))
                {
                    if($advertisementDetails['status'] == DELETED)
                    {
                        return response()->json(new BaseResponse(true, null, true));
                    }
                    else
                    {
                        $imageFolderName = $advertisementDetails['imageFolder'];
                    }
                }
                else
                {
                    throw new Exception(sprintf("Restaurant does not exist. %s ", $restaurantId));
                }   
            } 
            else if(!empty($folderName))
            {
                $imageFolderName = $folderName;
            }
            else
            {
                $imageFolderName = $this->imageHandler->CreateNewFolderForAdvertisementImage();
            }
            
            $response = $this->uploadImageToAmazon($uploadedImage, $imageFolderName, $restaurantId, false, null, null);
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in RestaurantImageController@uploadImage Message is %s, Stack Trace is %s", $e->getMessage(), $e->getTraceAsString()));
            $isSuccess = false;
        }

        $respMessage = '';
        if (!$isSuccess)
        {
            $respMessage = $this->translatorFactory->translate("ImageUploadError");
        }

        return response()->json(new BaseResponse($isSuccess, $respMessage, $response));
    }

    public function uploadMultipleImage(Request $request)
    {
        try
        {
            $data = $request->all();
            $fileInputFieldName = $request->post('fileControlName');
            $images = $request->file($fileInputFieldName);
            $restaurantId = intval($request->post('restaurantId'));
            $imageFolder = $request->post('imageFolderName');
            $saveRestaurantUserImage = empty($request->post('saveRestaurantUserImage')) ? false : $request->post('saveRestaurantUserImage');
            $saveFooterLinkImage =  empty($request->post('saveFooterLinkImage')) ? false : $request->post('saveFooterLinkImage');
            $clientIp = $this->ipHelpers->clientIpAsLong();
            $userId = $respMessage = null;
            $isSuccess = false;
            $response = [];
           
            $validator = Validator::make(
                $data, [
                    sprintf("%s.*", $fileInputFieldName) => 'required|mimes:jpg,jpeg,png,webp'
                ],[
                    sprintf("%s.*.mimes", $fileInputFieldName) => $this->translatorFactory->translate('Only jpg, jpeg and png files are allowed')
                ]
            );

            if ($validator->fails())
            {
                Log::critical(sprintf("UploadMultipleImages file upload validation failed. %s ", $validator->errors()->first()));
                $response['error'] = $validator->errors()->first();
            }
            else
            {
                if(!empty($restaurantId))
                {
                    $advertisementDetails = Advertisement::select('imageFolder', 'status')->where('id', $restaurantId)->first();
                    
                    if(!empty($advertisementDetails))
                    {
                        if($advertisementDetails['status'] == DELETED)
                        {
                            return response()->json(new BaseResponse(true, null, true));
                        }
                        else
                        {
                            $folderName = $advertisementDetails['imageFolder'];
                        }
                    }
                    else
                    {
                        throw new Exception(sprintf("Restaurant does not exist. %s ", $restaurantId));
                    }
                }
                else if(!empty($imageFolder))
                {
                    $folderName = $imageFolder;
                }
                else
                {
                    $folderName = $this->imageHandler->CreateNewFolderForAdvertisementImage();
                }

                if(Auth::check())
                {
                    $userDetails = Auth::user();
                    $userId = intval($userDetails->uid);
                    $userImageCount = $this->getCountFromUserId($userId, $restaurantId);
                }
                else 
                {
                    $userImageCount = $this->getUserImageCountFromIp($clientIp, $restaurantId);
                }

                $selectedImage = count($images);
                $totalImages = intval($selectedImage + $userImageCount);

                
                if($saveRestaurantUserImage == true && ($userImageCount > MAXIMUM_USER_IMAGE_UPLOAD_COUNT || $totalImages > MAXIMUM_USER_IMAGE_UPLOAD_COUNT))
                {
                    $msg = "";
                    
                    if($userImageCount >= MAXIMUM_USER_IMAGE_UPLOAD_COUNT)
                    {
                        $msg = sprintf("%s %s %s %s %s",$this->translatorFactory->translate("Maximum"), MAXIMUM_USER_IMAGE_UPLOAD_COUNT, $this->translatorFactory->translate("images are allowed and you have already uploaded") , MAXIMUM_USER_IMAGE_UPLOAD_COUNT, $this->translatorFactory->translate("images"));
                    }
                    else if($totalImages > MAXIMUM_USER_IMAGE_UPLOAD_COUNT)
                    {
                        $image = $this->translatorFactory->translate("images");

                        if($userImageCount == 1)
                        {
                            $image = $this->translatorFactory->translate("image");
                        }

                        $msg = sprintf("%s %s %s, %s %s %s",$this->translatorFactory->translate("You have already uploaded"), $userImageCount, $image, strtolower($this->translatorFactory->translate("Only")) , MAXIMUM_USER_IMAGE_UPLOAD_COUNT, $this->translatorFactory->translate("images allowed"));
                    }

                    Log::critical($msg);
                    $response['error'] = $msg;
                    $response['saveRestaurantUserImage'] = $saveRestaurantUserImage;
                }
                else if($saveFooterLinkImage)
                {
                    $folderName = 'footerLinkImages';
                    
                    $response['imageUploaded'] = $this->uploadImages($images, $folderName, $restaurantId, $saveRestaurantUserImage, $clientIp, $userId);
                    $response['folderName'] = $folderName;
                    $isSuccess = true;
                }
                else if(!empty($images))
                {
                    $response['imageUploaded'] = $this->uploadImages($images, $folderName, $restaurantId, $saveRestaurantUserImage, $clientIp, $userId);
                    $response['folderName'] = $folderName;
                    $isSuccess = true;
                }

                if (!empty($response['imageUploaded']))
                {
                    $response['restaurantId'] = $restaurantId;
                }

                if (empty($response['imageUploaded']))
                {
                    $isSuccess = false;
                }
            }
        }
        catch(Exception $e)
        {   
            $restaurantId = intval($request->post('restaurantId')) ?? 0;
            $userId = $this->getUserId();
            $errorMessage = sprintf("Error found in RestaurantImageController@uploadMultipleImage RestaurantId %s, UserId %s, Message is %s, Stack Trace is %s", $restaurantId, $userId, $e->getMessage(), $e->getTraceAsString());

            Log::critical($errorMessage, [
                'response' => $this->request->all()
            ]);
        }
        
        if (!$isSuccess)
        {
            $respMessage = $this->translatorFactory->translate("ImageUploadError");
        }

        return response()->json(new BaseResponse($isSuccess, $respMessage, $response));
    }

    private function getUserId(): int
    {
        $userId = 0;

        if(Auth::check())
        {
            $userDetails = Auth::user();
            $userId = intval($userDetails->uid);
        }

        return $userId;
    }

    private function uploadImages(array $images, string $folderName, int $restaurantId, ?bool $saveRestaurantUserImage, ?int $clientIp, ?int $userId): array
    {
        $response = [];
       
        foreach($images as $image)
        {
            if (method_exists($image, 'getPathName'))
            {
                try
                {
                    $response[] = $this->uploadImageToAmazon($image->getPathName(), $folderName, $restaurantId, $saveRestaurantUserImage, $clientIp, $userId);
                }
                catch(Exception $e)
                {
                    $this->uploadFailedImage($image->getPathName(), $image->getClientOriginalName());
                    Log::critical(sprintf('Error found in uploadImageToAmazon Method where restaurant id is : %s and user id is : %s. Message is %s, Stack Trace is %s', $restaurantId, $userId, $e->getMessage(), $e->getTraceAsString()));
                    // throw $e;
                }
            }
            else
            {
                $errorMessage = "Error found getPathName Method not found in the Image object.";

                Log::critical(sprintf('%s Image object is', $errorMessage), [
                    'request' => $this->request->all(),
                    'files' => $_FILES
                ]);

                // throw new Exception($errorMessage);
            }
        }

        return $response;
    }

    private function uploadImageToAmazon(string $tempName, string $folderName, int $restaurantId, bool $saveRestaurantUserImage, ?int $clientIp, ?int $userId): array
    {
        $createdOn = $this->dateTimeHelpers->getCurrentUtcTimeStamp();
        $this->imageHandler->doImageValidation($tempName);
        $fileName = $this->imageHandler->moveImageToAdvertisementImageFolder($tempName, $folderName);
        $newFilePath = sprintf("%s%s%s%s", TEMP_IMAGES_PATH, $folderName, DIRECTORY_SEPARATOR, $fileName);
        $newFolderPath = sprintf("%s%s", TEMP_IMAGES_PATH, $folderName);
        $imageSize = getimagesize($newFilePath);
        $width = $imageSize[0];
        $height = $imageSize[1];
        
        $imageWebPath = $this->amazonS3->UploadFile($newFilePath, env('AMAZON_IMAGES_BUCKET'), $folderName);
        $this->fileHandler->deleteDirAndItsContent($newFolderPath);

        $userImageId = null;
        
        if($saveRestaurantUserImage)
        {
            $userImageId = $this->addUserImages($restaurantId, $fileName, $folderName, $userId, $createdOn, $width, $height, $clientIp, STATUS_ACTIVE);
        }

        $result = array('imageFolderName'=> $folderName, 'imageWebPath'=> $imageWebPath, 'imageName'=> $fileName, 'imageHeight'=> $height, 'imageWidth'=> $width);
        

        if($userId > 0)
        {
            Advertisement::where('id', $restaurantId)->update(['updation_date' => $createdOn]);
        }
        else
        {
            $result['userImageId'] = $userImageId;
        }

        return $result;
    }

    private function getUserImageCountFromIp(int $clientIp, int $restaurantId): int
    {
        $condition = ['ip' => $clientIp, 'adId' => $restaurantId];

        $ipCount = UserImages::where($condition)->get()->count();
        return $ipCount;
    }

    private function addUserImages(int $adId, string $imageName, string $folderName, ?int $userId, int $createdOn, int $width, int $height, int $ip, int $status): int
    {
        $dataToSave = [
            'adId' => $adId,
            'imageName' => $imageName,
            'imageFolder' => $folderName,
            'userId' => $userId,
            'createdOn' => $createdOn,
            'width' => $width,
            'height' => $height,
            'ip' => $ip,
            'status' => $status
        ];

        $userImageId = UserImages::insertGetId($dataToSave);

        return $userImageId;
    }

    private function getCountFromUserId(?int $userId, int $restaurantId): int
    {
        $userImageCount = 0;
        if(intval($userId) > 0)
        {
            $condition = ['userId' => $userId, 'adId' => $restaurantId];

            $userImageCount = UserImages::where($condition)->get()->count();
        }
        return $userImageCount;
    }

    public function uploadFailedImage(string $fileToUpload, string $fileOriginalName)
    {
        try
        {
            $folderName = '../FailedUploadedImages';

            if (!file_exists($folderName)) 
            {
                mkdir($folderName);
            }

            $targetFile = sprintf('%s/%s',$folderName, $fileOriginalName);
            move_uploaded_file($fileToUpload, $targetFile);
            
        }
        catch(Exception $e)
        {
            Log::critical(sprintf('Error found in uploadFailedImage method while uploading failed image to local folder. Image name is : %s. Message is %s, Stack Trace is %s', $fileOriginalName, $e->getMessage(), $e->getTraceAsString()));
        }
        
    }
}