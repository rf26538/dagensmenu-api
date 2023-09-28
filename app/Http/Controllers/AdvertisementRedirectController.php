<?php

namespace App\Http\Controllers;

use App\Models\Restaurant\Advertisement;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Exception;
use App\Shared\EatCommon\Amazon\AmazonS3;
use App\Models\Restaurant\AdvertisementImages;
use App\Models\Restaurant\UserImages;
use App\Models\Review\ReviewModel;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Shared\EatCommon\Helpers\StringHelper;
use App\Shared\EatCommon\Helpers\FileHandler;
use App\Models\Review\ReviewCommentModel;
use App\Models\Review\ReviewLikeModel;
use Illuminate\Support\Facades\Log;


class AdvertisementRedirectController extends Controller
{
    private $links;
    private $datetimeHelper;
    private $amazonS3;
    private $translatorFactory;
    private $stringHelper;
    private $fileHandler;

    public function __construct(DatetimeHelper $datetimeHelper, AmazonS3 $amazonS3, TranslatorFactory $translatorFactory, StringHelper $stringHelper, FileHandler $fileHandler)
    {
        $this->datetimeHelper = $datetimeHelper;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->stringHelper = $stringHelper;
        $this->fileHandler = $fileHandler;
        $this->amazonS3 = $amazonS3;
    }

    public function transferRestaurantData(Request $request)
    {
        try
        {
            $rules = array(
                'currentRestaurantId' => 'required|int|min:1',
                'redirectRestaurantId' => 'required|int|min:1'
            );
            
            $data = $request->all();
            
            $validator = Validator::make($data, $rules);

            if ($validator->fails())
            {
                throw new Exception(sprintf("AdvertisementRedirectController transferRestaurantData error. %s ", $validator->errors()->first()));
            }

            $currentRestaurantId = $data['currentRestaurantId'];
            $redirectRestaurantId = $data['redirectRestaurantId'];
            
            $currentRestaurantData = $this->getRestaurantData($currentRestaurantId);
            $redirectRestaurantData = $this->getRedirectRestaurantData($redirectRestaurantId);
            

            if(!empty($currentRestaurantData) && !empty($redirectRestaurantData))
            {

                if($redirectRestaurantData['status'] != STATUS_ACTIVE)
                {
                    throw new Exception($this->translatorFactory->translate("Restaurant is not active")); 
                }

                $newFolderName = $redirectRestaurantData['imageFolder'];
                $currentRestaurantFolderName = $currentRestaurantData['imageFolder'];
                $redirectRestaurantReviewCount = intval($redirectRestaurantData['reviewersCount']);

                if(!file_exists(MOVE_PHOTOS_IMAGES_PATH))
                {
                    mkdir(MOVE_PHOTOS_IMAGES_PATH);
                }
                
                $this->moveMenuImages($currentRestaurantData['menuImages'], $redirectRestaurantData['menuImages'], $currentRestaurantFolderName, $newFolderName, $redirectRestaurantId);
                $this->moveMenuPdf($currentRestaurantData['menuCardPDFFiles'], $redirectRestaurantData['menuCardPDFFiles'], $currentRestaurantFolderName, $newFolderName, $redirectRestaurantId);
                $this->movePhotos($currentRestaurantData['advertisement_images'], $currentRestaurantFolderName, $newFolderName, $redirectRestaurantId, $redirectRestaurantData['advertisement_images']);
                $this->moveUserImages($currentRestaurantData['advertisement_user_images'], $currentRestaurantFolderName, $newFolderName, $redirectRestaurantId);
                $this->moveReview($currentRestaurantData['advertisement_reviews'], $currentRestaurantFolderName, $newFolderName, $redirectRestaurantId, $redirectRestaurantReviewCount);

                if(file_exists(MOVE_PHOTOS_IMAGES_PATH))
                {
                    rmdir(MOVE_PHOTOS_IMAGES_PATH);
                } 
                
                Advertisement::where('id', $currentRestaurantId)->update(array('status' => RESTAURANT_REDIRECT_STATUS ,'lastInfoUpdatedOn' => $this->datetimeHelper->getCurrentUtcTimeStamp(), 'redirectRestaurantId' => $redirectRestaurantId));   
                
                $isSuccess = true;
                $response = true;
                $msg = null;
            }

        }
        catch(Exception $e)
        {
            Log::critical($e->getMessage());
            $isSuccess = false;
            $response = false;
            $msg = $e->getMessage();
        }

        return response()->json(new BaseResponse($isSuccess, $msg, $response));
    }

    private function getRestaurantData(int $id): array
    {
        $results = [];

        $data = Advertisement::select([
            'id as restaurantId','title','status', 'postcode', 'menuImages', 'reviewersCount', 'menuCardPDFFiles', 'imageFolder'
        ])->with(['advertisementImages' => function($query) {
            $query->select(['id', 'adv_id', 'image_name', 'image_folder']);
        }])->with(['advertisementUserImages' => function($query){
            $query->select();
        }])->with([
            'advertisementReviews.reviewComments' => function($query) {
                $query->select();
            }, 
            'advertisementReviews.reviewLikes' => function($query) {
                $query->select();
            }
        ])->where('id', $id)->first();

        if(!empty($data))
        {
            $results = $data->toArray();
        }
        return $results;
    }

    private function getRedirectRestaurantData(int $id)
    {
        $results = [];

        $data = Advertisement::select([
            'id as restaurantId','title','status', 'postcode', 'menuImages', 'reviewAverage', 'reviewersCount', 'menuCardPDFFiles', 'imageFolder'])->with(['advertisementImages' => function($query){
                $query->select(['id', 'adv_id', 'image_name', 'image_folder']);
                }])->where('id', $id)->first();
        
        if(!empty($data))
        {
            $results = $data->toArray();
        }

        return $results;
    }

    private function moveMenuImages(?string $currentRestaurantMenuImages, ?string $redirectRestaurantMenuImages, string $currentRestaurantFolderName, string $newFolderName, int $redirectRestaurantId)
    {
        if(!empty($currentRestaurantMenuImages) && empty($redirectRestaurantMenuImages))
        {
            $menuImages = explode(",", $currentRestaurantMenuImages);
            $menuImagesCount = count($menuImages);

            $imageNames = "";
            for($i=0; $i < $menuImagesCount; $i++)
            {
                $imageName = substr($menuImages[$i], 0, strpos($menuImages[$i], "-"));
                $imagePath = AmazonS3::GetWebPath(AMAZON_BUCKET, $currentRestaurantFolderName, $imageName);

                $fileName = basename($imagePath);
                $filePath = sprintf("%s%s", MOVE_PHOTOS_IMAGES_PATH, $fileName);

                if(file_put_contents($filePath, file_get_contents($imagePath)))
                {
                    $menuImageTempName = $this->stringHelper::getGuid();
                    $menuImageNewName = sprintf("%s.%s", $menuImageTempName, $this->fileHandler->GetFileExtension($filePath));
                    $menuImageNewPath = sprintf("%s%s", MOVE_PHOTOS_IMAGES_PATH, $menuImageNewName);
                    rename($filePath, $menuImageNewPath);

                    $uploadedWebPath = $this->amazonS3->UploadFile($menuImageNewPath, env('AMAZON_IMAGES_BUCKET'), $newFolderName);
                    $imageSize = getimagesize($uploadedWebPath);
            
                    $imageNames .= sprintf("%s-%s-%s,", $menuImageNewName, $imageSize[1], $imageSize[0]);

                    if(file_exists($menuImageNewPath))
                    {
                        unlink($menuImageNewPath);
                    }
                }
            }

            Advertisement::where('id', $redirectRestaurantId)->update(array('menuImages' => trim($imageNames, ","), 'lastInfoUpdatedOn' => $this->datetimeHelper->getCurrentUtcTimeStamp()));
        }
    }

    private function moveMenuPdf(?string $currentRestaurantPdf, ?string $redirectRestaurantPdf, string $currentRestaurantFolderName, string $newFolderName, int $redirectRestaurantId)
    {
        if(!empty($currentRestaurantPdf) && empty($redirectRestaurantPdf))
        {
            $pdfMenuCards = unserialize($currentRestaurantPdf);
            
            foreach($pdfMenuCards['menucardPDFFiles'] as $pdf)
            {
                $pdfPath = sprintf("%s%s", MOVE_PHOTOS_IMAGES_PATH, $pdf['pdfFileName']);

                $pdfLink = AmazonS3::GetWebPath(AMAZON_BUCKET, $currentRestaurantFolderName, $pdf['pdfFileName']);

                if(file_put_contents($pdfPath, file_get_contents($pdfLink)))
                {
                    $this->amazonS3->UploadFile($pdfPath, env('AMAZON_IMAGES_BUCKET'), $newFolderName, env('AMAZON_ACL'), $pdf['pdfFileName']);

                    $pdfMenu['menucardPDFFiles'][] = ['pdfFileName' => $pdf['pdfFileName'], 'pdfOrginalName' => $pdf['pdfOrginalName']];
                    
                    if(file_exists($pdfPath))
                    {
                        unlink($pdfPath);
                    }
                }    
            }

            Advertisement::where('id', $redirectRestaurantId)->update(array('menuCardPDFFiles' => serialize($pdfMenu), 'lastInfoUpdatedOn' => $this->datetimeHelper->getCurrentUtcTimeStamp()));   
        }
    }

    private function movePhotos(?array $advertisementImages, string $currentRestaurantFolderName, string $newFolderName, int $redirectRestaurantId, array $redirectAdvertisementImages)
    {
        if(!empty($advertisementImages))
        {
            $isPrimaryImageValue = 1;

            if(!empty($redirectAdvertisementImages))
            {
                $isPrimaryImageValue = 0;
            }

            foreach($advertisementImages as $advertisementImage)
            {
                $photoPath = sprintf("%s%s", MOVE_PHOTOS_IMAGES_PATH, $advertisementImage['image_name']);

                $photoLink = AmazonS3::GetWebPath(AMAZON_BUCKET, $currentRestaurantFolderName, $advertisementImage['image_name']);

                if(file_put_contents($photoPath, file_get_contents($photoLink)))
                {
                    $photoName = $this->stringHelper::getGuid();
                    $newPhotoName = sprintf("%s.%s", $photoName, $this->fileHandler->GetFileExtension($photoPath));
                    $newPhotoPath = sprintf("%s%s", MOVE_PHOTOS_IMAGES_PATH, $newPhotoName);
                    rename($photoPath, $newPhotoPath);

                    $uploadedPhoto = $this->amazonS3->UploadFile($newPhotoPath, env('AMAZON_IMAGES_BUCKET'), $newFolderName);
                    $photoSize = getimagesize($uploadedPhoto);

                    $advertisementImages = new AdvertisementImages();

                    $advertisementImages->adv_id = $redirectRestaurantId;
                    $advertisementImages->image_name = $newPhotoName;
                    $advertisementImages->image_folder = $newFolderName;
                    $advertisementImages->width = $photoSize[0];
                    $advertisementImages->height = $photoSize[1];
                    $advertisementImages->creation_date = $this->datetimeHelper->getCurrentUtcTimeStamp();
                    $advertisementImages->is_Primary_Image = $isPrimaryImageValue;
                    
                    $advertisementImages->save();
                    $isPrimaryImageValue = 0;

                    if(file_exists($newPhotoPath))
                    {
                        unlink($newPhotoPath);
                    }
                }
            }
        }
    }

    private function moveUserImages(?array $userImages, string $currentRestaurantFolderName, string $newFolderName, int $redirectRestaurantId)
    {
        if(!empty($userImages))
        {
            foreach($userImages as $userImage)
            {
                $userImagePath = sprintf("%s%s", MOVE_PHOTOS_IMAGES_PATH, $userImage['imageName']);

                $userImageLink = AmazonS3::GetWebPath(AMAZON_BUCKET, $currentRestaurantFolderName, $userImage['imageName']);

                if(file_put_contents($userImagePath, file_get_contents($userImageLink)))
                {
                    $userPhotoTempName = $this->stringHelper::getGuid();
                    $userPhotoName = sprintf("%s.%s", $userPhotoTempName, $this->fileHandler->GetFileExtension($userImagePath));
                    $newUserImagePath = sprintf("%s%s", MOVE_PHOTOS_IMAGES_PATH, $userPhotoName);
                    rename($userImagePath, $newUserImagePath);
                    
                    $uploadedPhoto = $this->amazonS3->UploadFile($newUserImagePath, env('AMAZON_IMAGES_BUCKET'), $newFolderName);
                    $userPhotoSize = getimagesize($uploadedPhoto);

                    $userImages = new UserImages();

                    $userImages->adId = $redirectRestaurantId;
                    $userImages->imageName = $userPhotoName;
                    $userImages->imageFolder = $newFolderName;
                    $userImages->userId = $userImage['userId'];
                    $userImages->createdOn = $userImage['createdOn'];
                    $userImages->width = $userPhotoSize[0];
                    $userImages->height = $userPhotoSize[1];
                    $userImages->ip = $userImage['ip'];
                    $userImages->status = $userImage['status'];

                    $userImages->save();

                    if(file_exists($newUserImagePath))
                    {
                        unlink($newUserImagePath);
                    }                    
                }
            }
        }
    }

    private function moveReview(?array $advertisementReviews, string $currentRestaurantFolderName, string $newFolderName, int $redirectRestaurantId, int $redirectRestaurantReviewCount)
    {
        if(!empty($advertisementReviews))
        {
            $reviewCount = count($advertisementReviews);
            $totalReview = $reviewCount + $redirectRestaurantReviewCount;

            foreach($advertisementReviews as $advertisementReview)
            {
                $reviewImageNames = "";

                if(!empty($advertisementReview['reviewImages']))
                {
                    $explodeReviewImage = explode(",", trim($advertisementReview['reviewImages'], ","));
                    $reviewImageCount = count($explodeReviewImage);

                    for($i=0; $i < $reviewImageCount; $i++)
                    {

                        $reviewImageName = substr($explodeReviewImage[$i], 0, strpos($explodeReviewImage[$i], "-"));
                        $reviewImagePath = AmazonS3::GetWebPath(AMAZON_BUCKET, $currentRestaurantFolderName, $reviewImageName);

                        $reviewFileName = basename($reviewImagePath);
                        
                        $reviewFilePath = sprintf("%s%s", MOVE_PHOTOS_IMAGES_PATH, $reviewFileName);

                        if(file_put_contents($reviewFilePath, file_get_contents($reviewImagePath)))
                        {
                            $reviewImageTempName = $this->stringHelper::getGuid();
                            $reviewImageName = sprintf("%s.%s", $reviewImageTempName, $this->fileHandler->GetFileExtension($reviewFilePath));
                            $reviewNewImagePath = sprintf("%s%s", MOVE_PHOTOS_IMAGES_PATH, $reviewImageName);
                            rename($reviewFilePath, $reviewNewImagePath);

                            $uploadedReviewWebPath = $this->amazonS3->UploadFile($reviewNewImagePath, env('AMAZON_IMAGES_BUCKET'), $newFolderName);
                            $reviewImageSize = getimagesize($uploadedReviewWebPath);

                            $reviewImageNames .= sprintf("%s-%s-%s,", $reviewImageName , $reviewImageSize[1], $reviewImageSize[0]);
                            
                            if(file_exists($reviewNewImagePath))
                            {
                                unlink($reviewNewImagePath);
                            }
                        }
                    }
                }

                $reviewToSave = [
                    'status' => $advertisementReview['status'],
                    'adId' => $redirectRestaurantId,
                    'timestamp' => $advertisementReview['timestamp'],
                    'rating' => $advertisementReview['rating'],
                    'reviewerName' => $advertisementReview['reviewerName'],
                    'reviewerComment' => $advertisementReview['reviewerComment'],
                    'adType' => $advertisementReview['adType'],
                    'ip' => $advertisementReview['ip'],
                    'reviewImages' => trim($reviewImageNames, ","),
                    'foodRating' => $advertisementReview['foodRating'],
                    'serviceRating' => $advertisementReview['serviceRating'],
                    'priceRating' => $advertisementReview['priceRating'],
                    'videoFileDetails' => $advertisementReview['videoFileDetails'],
                    'reviewTitle' => $advertisementReview['reviewTitle'],
                    'userId' => $advertisementReview['userId'],
                    'reviewUniqueId' => $advertisementReview['reviewUniqueId'],
                    'reviewLikes' => $advertisementReview['reviewLikes'],
                    'reviewReply' => $advertisementReview['reviewLikes'],
                    'replyTimeStamp' => $advertisementReview['replyTimeStamp'],
                    'reviewMailSentToRestaurantOwner' => $advertisementReview['reviewMailSentToRestaurantOwner'],
                ];

                $reviewId = ReviewModel::insertGetId($reviewToSave);

                ReviewModel::where('id', $advertisementReview['id'])->update(['redirectReviewId' => $reviewId]);

                if(!empty($advertisementReview['review_comments']))
                {
                    foreach($advertisementReview['review_comments'] as $reviewComment)
                    {
                        $reviewCommentModel = new ReviewCommentModel();

                        $reviewCommentModel->reviewId = $reviewId; 
                        $reviewCommentModel->comment = $reviewComment['comment'];
                        $reviewCommentModel->userId = $reviewComment['userId'];
                        $reviewCommentModel->timestamp = $reviewComment['timestamp'];
                        $reviewCommentModel->status = $reviewComment['status'];
                        $reviewCommentModel->ip = $reviewComment['ip'];

                        $reviewCommentModel->save();
                    }
                }

                if(!empty($advertisementReview['review_likes']))
                {
                    foreach($advertisementReview['review_likes'] as $reviewLike)
                    {
                        $reviewLikeModel = new ReviewLikeModel();

                        $reviewLikeModel->reviewId = $reviewId;
                        $reviewLikeModel->status = $reviewLike['status'];
                        $reviewLikeModel->userId = $reviewLike['userId'];
                        $reviewLikeModel->timestamp = $reviewLike['userId'];
                        $reviewLikeModel->ip = $reviewLike['userId'];

                        $reviewLikeModel->save();
                    }
                }
            }

            Advertisement::where('id', $redirectRestaurantId)->update(array('reviewersCount' => $totalReview, 'lastInfoUpdatedOn' => $this->datetimeHelper->getCurrentUtcTimeStamp()));   
        }
    }

}
