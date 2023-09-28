<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\BaseResponse;
use App\Models\Review\ReviewCommentModel;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Auth;
use Exception;

class ReviewCommentController extends Controller
{
    private $datetimeHelpers;

    function __construct(DatetimeHelper $datetimeHelpers)
    {
        $this->datetimeHelpers = $datetimeHelpers;
    }

    public function deleteIndividualReviewComment(int $reviewCommentId)
    {
        try
        {
            $response = [];
            
            $validatorGet = Validator::make(['reviewCommentId' => $reviewCommentId], ['reviewCommentId' => 'required|integer|min:1']);

            if ($validatorGet->fails())
            {
                return response()->json( $response);
            }

            $currentTime = $this->datetimeHelpers->getCurrentUtcTimeStamp();
            $data = [ 
                'status'=> DELETED,
                'modifiedOn' => $currentTime
            ];
            $response = ReviewCommentModel::where('reviewCommentId', $reviewCommentId)->update($data);
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in ReviewCommentController@deleteIndividualReviewComment Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

}