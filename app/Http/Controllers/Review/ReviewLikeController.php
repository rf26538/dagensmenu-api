<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\BaseResponse;
use App\Models\Review\ReviewLikeModel;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Auth;
use Exception;

class ReviewLikeController extends Controller
{


    public function getUserLikedReview()
    {
        try
        {
            $userId = Auth::id();
            $response = [];

            if($userId)
            {
                $response = ReviewLikeModel::where([['userId', $userId], ['status', STATUS_ACTIVE]])->get();
            }
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewLikeController@getUserLikedReview Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

}
