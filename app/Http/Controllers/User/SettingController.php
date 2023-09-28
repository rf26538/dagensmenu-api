<?php

namespace App\Http\Controllers\User;
use App\Models\User\ChangePasswordRequestModel;
use App\Models\User;
use App\Models\Review\ReviewModel;
use App\Models\Restaurant\UserImages;
use App\Models\Review\ReviewCommentModel;
use App\Libs\Helpers\Authentication;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use App\Http\Controllers\BaseResponse;
use App\Models\Review\ReviewLikeModel;
use App\Models\Restaurant\Advertisement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Exception;
use Auth;

class SettingController extends Controller 
{
    private $datetimeHelper;

    public function __construct( DatetimeHelper $datetimeHelper)
    {
        $this->datetimeHelper = $datetimeHelper;
    }

    public function changeEmail(Request $request)
    {
        $rules = array(
            'email' => 'required|string'
        );

        $validator = Validator::make($request->post(), $rules);

        if($validator->fails())
        {
            throw new Exception(sprintf("SettingController.changeEmail error. %s ", $validator->errors()->first()));
        }

        $email = $request->post('email');

        $results = User::select('email')->where(array('email' => $email))->first();

        if($results)
        {
            return response()->json(new BaseResponse(false, null, null));
        }

        $userId = Auth::id();

        if($userId)
        {
            User::where('uid', $userId)->update(array('email' => $email));
            $result = true;
        }
        else
        {
            $result = false;
        }
        return response()->json(new BaseResponse($result, null, null));
    }

    public function deleteAccount()
    {
        $userId = Auth::id();

        if($userId)
        {
            $validator = Validator::make(['userId' => $userId], ['userId' => 'required|int|min:1']);

            if ($validator->fails())
            {
                throw new Exception(sprintf("SettingController deleteAccount error. %s ", $validator->errors()->first()));
            }
            
            ReviewModel::where('userId', $userId)->update(array('status' => DELETED));

            $results = ReviewModel::select('adId')->where(array('userId' => $userId))->groupBy('adId')->get();
            
            if(!empty($results))
            {
                foreach($results as $result)
                {
                    $this->updateReviewStats($result['adId']);
                    $this->updateFoodPriceAndServiceReviewStats($result['adId']);
                }
            }

            UserImages::where('userId', $userId)->update(array('status' => DELETED));
            ReviewCommentModel::where('userId', $userId)->update(array('status' => DELETED));
            ReviewLikeModel::where('userId', $userId)->update(array('status' => DELETED));

            $results = User::select('email')->where(array('uid' => $userId))->first();
            $email = sprintf('%s/deleted/%s', $results->email, $this->datetimeHelper->getCurrentUtcTimeStamp());

            $data = [
                'email'=> $email,
                'status' => DISABLE
            ];
            User::where('uid', $userId)->update($data);
            $result = true;
        }
        else
        {
            $result = false;
        }
        return response()->json(new BaseResponse($result, null, null));
    }

    public function changeProfileInfo(Request $request)
    {
        $userId = Auth::id();

        if(!$userId)
        {
            return response()->json(new BaseResponse(false, null, null));
        }

        $rules = array(
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'phone' => 'required|string',
            'countryCode' => 'required|string',
        );

        $validator = Validator::make($request->post(), $rules);

        if($validator->fails())
        {
            throw new Exception(sprintf("SettingController.changeProfileInfo error. %s ", $validator->errors()->first()));
        }

        $firstName = $request->post('firstName');
        $lastName = $request->post('lastName');
        $phone = $request->post('phone');
        $countryCode = $request->post('countryCode');
        $name = $firstName.' '.$lastName;
        $data = [
            'first_name'=> $firstName,
            'last_name' => $lastName,
            'name' => $name,
            'nick_name' => $name,
            'phone' => $phone,
            'countryCode' => $countryCode,
        ];

        User::where('uid', $userId)->update($data);
        return response()->json(new BaseResponse(true, null, null));
    }

    public function updateReviewStats(int $adId)
    {
        try
        {
            $overallReviewAverageAndCount = $this->getAdReviewOverallAverageCount($adId);
            
            $ratingAverage = $overallReviewAverageAndCount['ratingAverage'];
            $ratingCount = $overallReviewAverageAndCount['ratingCount'];
            
            $data = [
                'reviewAverage' => $ratingAverage,
                'reviewersCount' => $ratingCount
            ];

            Advertisement::where('id', $adId)->update($data);
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in SettingController@updateReviewStats Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }
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
            Log::critical(sprintf("Error found in SettingController@getAdReviewOverallAverageCount Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return $sqlResult;
    }

    private function updateFoodPriceAndServiceReviewStats(int $adId)
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
            Log::critical(sprintf("Error found in SettingController@updateFoodPriceAndServiceReviewStats Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
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
            Log::critical(sprintf("Error found in SettingController@getAdFoodPriceAndServiceAverageAndCount Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return $sqlResult;
    }
}
