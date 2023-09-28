<?php

namespace App\Http\Controllers\Review;

use Illuminate\Http\Request;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Illuminate\Support\Facades\Validator;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use App\Models\Review\ReviewModel;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Models\Restaurant\Advertisement;
use App\Shared\EatCommon\Helpers\StringHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Link\Links;

class ReviewController extends Controller
{
    private $ipHelpers;
    private $datetimeHelpers;
    private $stringHelper;
    private $translatorFactory;
    private $links;

    public function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, StringHelper $stringHelper, TranslatorFactory $translatorFactory, Links $links)
    {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->stringHelper = $stringHelper;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->links = $links;
    }

    public function saveReply(Request $request, int $reviewId)
    {
        $rules = [
            'reviewReply' => 'required|string',
            'reviewId' => 'required|int|min:1'
        ];

        $validator = Validator::make($request->post(), $rules);
        if($validator->fails())
        {
            throw new Exception(sprintf('ReviewController saveReply error %s', $validator->errors()->first()));
        }
        $reviewReply = $request->post('reviewReply');
        $reviewTimestamp = $this->datetimeHelpers->getCurrentUtcTimeStamp();

        ReviewModel::where('id', $reviewId)->update(
            ['reviewReply' => $reviewReply, 'replyTimeStamp' => $reviewTimestamp]
        );

        $formattedDate = $this->datetimeHelpers->getDanishFormattedDate($reviewTimestamp);
        $data['replyTime'] = $formattedDate;
        $data['restaurantReply'] = $reviewReply;
        
        return response()->json(new BaseResponse(true, null, $data));
    }

    public function deleteReply(int $reviewId)
    {
        $validator = Validator::make(['reviewId' => $reviewId], ['reviewId' => 'required|int|min:1']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("ReviewController deleteReply error. %s ", $validator->errors()->first()));
        }
        
        $reviewReply = null;
        $reviewTimestamp = null;

        ReviewModel::where('id', $reviewId)->update(
            ['reviewReply' => $reviewReply, 'replyTimeStamp' => $reviewTimestamp]
        );
        return response()->json(new BaseResponse(true, null, true));
    }

    public function save(Request $request)
    {
        $response = false;
        $reviewId = "";
        $msg = "";
        try
        {
            $rules = [
                'reviewerName' => 'required|string',
                'adId' => 'required|int|min:1'
            ];
    
            $validator = Validator::make($request->post(), $rules);
            
            if($validator->fails())
            {
                throw new Exception(sprintf('ReviewController save error %s', $validator->errors()->first()));
            }

            $reviewerName = $request->post('reviewerName');
            $reviewerComment = $request->post('reviewerComment');
            $reviewTitle = $request->post('reviewTitle');
            $rating = floatval($request->post('rating'));
            $adId = $request->post('adId');
            $clientIp = $this->ipHelpers->clientIpAsLong(); 
            $videoFolderName = $request->post('videoFolderName');
            $reviewImages = $request->post('reviewImages');
            $reviewTypeId = !empty($request->post('typeId')) ?  $request->post('typeId') : null;
            $reviewReferenceId = !empty($request->post('typeReferenceId')) ? $request->post('typeReferenceId') : null;
            $userId = !empty(Auth::id()) ? Auth::id() : null;
            $videoFileDetails = null;

            if(!empty($videoFolderName))
            {
                $videoDetails = [
                    'videoFolderName' => $request->post('videoFolderName'),
                    'videoOriginalFileName' => $request->post('videoOriginalFileName'),
                    'videoTranscodedFileName' => $request->post('videoTranscodedFileName'),
                    'videoThumbnailFilePattern' => $request->post('videoThumbnailFilePattern')
                ];

                $videoFileDetails = serialize($videoDetails);
            }

            $isReviewValid = $this->isReviewValid($adId, $clientIp, $userId);

            if($isReviewValid)
            {
                $images = null;
                if(!empty($reviewImages)) 
                {        
                    $images = [];

                    foreach($reviewImages as $reviewImageObject) 
                    {
                        $imageDetails = sprintf("%s-%s-%s", $reviewImageObject["fileName"], $reviewImageObject["fileHeight"], $reviewImageObject["fileWidth"]);
                        array_push($images, $imageDetails);
                    }

                    $images = implode(",", $images);
                }

                $reviewModel = new ReviewModel();

                $reviewModel->adId = $adId;
                $reviewModel->status = STATUS_ACTIVE;
                $reviewModel->adType = AD_FULL;
                $reviewModel->timestamp = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $reviewModel->reviewerComment = $reviewerComment;
                $reviewModel->reviewUniqueId = $this->stringHelper::getGuid();
                $reviewModel->reviewTitle = $reviewTitle;
                $reviewModel->reviewerName = $reviewerName;
                $reviewModel->reviewImages = $images;
                $reviewModel->videoFileDetails = $videoFileDetails;
                $reviewModel->rating = $rating; 
                $reviewModel->typeReferenceId = $reviewReferenceId;
                $reviewModel->typeId = $reviewTypeId; 
                $reviewModel->userId = $userId;
                $reviewModel->ip = $clientIp;

                $reviewModel->save();
                $reviewId = $reviewModel->reviewUniqueId;

                if($rating >= MINIMUM_REVIEWS_STAR_COUNT)
                {
                    $this->updateAdOverAllReviewStats(intval($adId));
                }

                Advertisement::where('id', $adId)->update(['updation_date' => $this->datetimeHelpers->getCurrentUtcTimeStamp()]);
            }

            if(!$isReviewValid)
            {
                if($userId)
                {
                    $msg = $this->links->createUrl(PAGE_USER_REVIEWS);   
                }
                else
                {
                    $msg = SITE_BASE_URL;
                }
            }

            $response = true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewController@save Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($response, $msg, $reviewId));

    }

    public function update(Request $request, string $reviewUniqueId)
    {
        try
        {
            $rules = [
                'adId' => 'required|int|min:1',
                'reviewUniqueId' => 'required|string',
            ];
    
            $validator = Validator::make($request->post(), $rules);
            
            if($validator->fails())
            {
                throw new Exception(sprintf('ReviewController update error %s', $validator->errors()->first()));
            }
    
            $foodRating = $request->post('foodRating');
            $serviceRating = $request->post('serviceRating');
            $priceRating = $request->post('priceRating');
            $adId = $request->post('adId');
            $reviewUniqueId = $request->post('reviewUniqueId');

            ReviewModel::where('reviewUniqueId', $reviewUniqueId)->update([
                'foodRating' => $foodRating,
                'serviceRating' => $serviceRating,
                'priceRating' => $priceRating
            ]);

            $this->updateAdFoodPriceAndServiceReviewStats(intval($adId));
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewController@update Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, true));
    }

    private function isReviewValid(int $adId, int $ip, ?int $userId) : bool
    {
        if(!ENVIRONMENT_PRODUCTION && Auth::id() && Auth::user()->type == USER_SUPER_ADMIN)
        {
            return true;
        }
        
        $hasIpSentReviewForThisAd = $this->hasIpSentReviewForThisAd($adId, $ip);
        

        if(!$hasIpSentReviewForThisAd)
        {
            if($userId != null)
            {
                $hasUserSentReviewForThisAd = $this->hasUserSentReviewForThisAd($adId, $userId);

                if($hasUserSentReviewForThisAd)
                {
                    return false;
                }
                else
                {
                    Log::critical(sprintf("Review not added. userId %s has already added review for adId %s", $userId, $adId));
                }
            }

            $reviewCountFromIp = $this->reviewCountFromIp($ip);

            if($reviewCountFromIp < MAXIMUM_REVIEW_ALLOWED)
            {
                return true;
            }

            Log::critical(sprintf("Review not added. Ip %s found %s times", $ip, $reviewCountFromIp));

        }
        else
        {
            Log::critical(sprintf("Review not added. Ip %s has already added review for adId %s", $ip, $adId));

        }

        return false;
    }

    private function hasIpSentReviewForThisAd(int $adId, int $ip): bool
    {
        try 
        {
            $reviewCount = ReviewModel::select(DB::raw('count(*) as countOfReviews'))->where([['adId', $adId], ['ip', $ip], ['status', STATUS_ACTIVE]])->get()->first();
            
            return $reviewCount['countOfReviews'] == 0 ? false : true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewController@hasIpSentReviewForThisAd Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return false;
    }

    private function hasUserSentReviewForThisAd(int $userId, int $adId)
    {
        try 
        {
            $reviewCount = ReviewModel::select(DB::raw('count(*) as countOfReviews'))->where([['adId', $adId], ['userId', $userId], ['status', STATUS_ACTIVE]])->get()->first();
            
            return $reviewCount['countOfReviews'] == 0 ? false : true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewController@hasUserSentReviewForThisAd Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return false;
    }

    private function reviewCountFromIp(int $ip): int
    {
        $count = 0;
        try 
        {
            $reviewCount = ReviewModel::select(DB::raw('count(*) as countOfReviews'))->where([['ip', $ip], ['status', STATUS_ACTIVE]])->get()->first();

            $count = $reviewCount['countOfReviews'];
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewController@hasIpSentReviewForThisAd Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return $count;
    }

    private function updateAdOverAllReviewStats(int $adId)
    {
        try
        {
            $overallReviewAverageAndCount = $this->getAdReviewOverallAverageCount($adId);

            if(!empty($overallReviewAverageAndCount))
            {
                $ratingAverage = $overallReviewAverageAndCount['ratingAverage'];
                $ratingCount = $overallReviewAverageAndCount['ratingCount'];

                if(!empty($ratingAverage)  && !empty($ratingCount))
                {
                    $data = [
                        'reviewAverage' => $ratingAverage,
                        'reviewersCount' => $ratingCount
                    ];

                    Advertisement::where('id', $adId)->update($data);
                }
            }
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewController@updateAdOverAllReviewStats Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }
    }

    private function updateAdFoodPriceAndServiceReviewStats(int $adId)
    {
        try
        {
            $adFoodPriceAndServiceAverageAndCount = $this->getAdFoodPriceAndServiceAverageAndCount($adId);

            if(!empty($adFoodPriceAndServiceAverageAndCount))
            {
                $foodRatingAverage = $adFoodPriceAndServiceAverageAndCount['foodRatingAverage'];
                $foodRatingCount = $adFoodPriceAndServiceAverageAndCount['foodRatingCount'];
                $serviceRatingAverage = $adFoodPriceAndServiceAverageAndCount['serviceRatingAverage']; 
                $serviceRatingCount = $adFoodPriceAndServiceAverageAndCount['serviceRatingCount']; 
                $priceRatingAverage = $adFoodPriceAndServiceAverageAndCount['priceRatingAverage']; 
                $priceRatingCount = $adFoodPriceAndServiceAverageAndCount['priceRatingCount'];

                $data = [
                    'foodRatingAverage' => $foodRatingAverage,
                    'foodRatingCount' => $foodRatingCount,
                    'serviceRatingAverage' => $serviceRatingAverage,
                    'serviceRatingCount' => $serviceRatingCount,
                    'priceRatingAverage' => $priceRatingAverage,
                    'priceRatingCount' => $priceRatingCount
                ];

                Advertisement::where('id', $adId)->update($data);
                
            }
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewController@updateAdOverAllReviewStats Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }
    }

    private function getAdFoodPriceAndServiceAverageAndCount(int $advId)
    {
        $sqlResult = [];
        try
        {
            $sqlResult = ReviewModel::select(DB::raw(
                "AVG(CASE WHEN (foodRating = 0 OR foodRating IS NULL OR foodRating < 4) THEN NULL ELSE foodRating END) as foodRatingAverage, SUM(CASE WHEN (foodRating = 0 OR foodRating IS NULL OR foodRating < 4)  THEN 0 ELSE 1 END) as foodRatingCount,
                AVG(CASE WHEN (serviceRating = 0 OR serviceRating IS NULL OR serviceRating < 4) THEN NULL ELSE serviceRating END) as serviceRatingAverage, SUM(CASE WHEN (serviceRating = 0 OR serviceRating IS NULL OR serviceRating < 4) THEN 0 ELSE 1 END) as serviceRatingCount,
                AVG(CASE WHEN (priceRating = 0 OR priceRating IS NULL OR priceRating < 4) THEN NULL ELSE priceRating END) as priceRatingAverage, SUM(CASE WHEN (priceRating = 0 OR priceRating IS NULL OR priceRating < 4) THEN 0 ELSE 1 END) as priceRatingCount")
                )->where([['adId', $advId], ['status', STATUS_ACTIVE], ['rating', '>=', MINIMUM_REVIEWS_STAR_COUNT]])->get()->first();
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewController@getAdReviewAverageAndCount Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return $sqlResult;
    }

    private function getAdReviewOverallAverageCount(int $advId)
    {
        $sqlResult = [];
        try
        {
            $sqlResult = ReviewModel::select(DB::raw("AVG(rating) as ratingAverage, COUNT(rating) as ratingCount"))->where([['adId', $advId], ['status', STATUS_ACTIVE], ['rating', '>=', MINIMUM_REVIEWS_STAR_COUNT]])->get()->first();
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewController@getAdReviewOverallAverageCount Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return $sqlResult;
    }
}