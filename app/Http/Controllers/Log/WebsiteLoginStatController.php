<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Models\Log\WebsiteLoginStats;
use Validator;
use Auth;

class WebsiteLoginStatController extends Controller
{

    public function fetchAll()
    {
        try
        {
            $condition = [
                ['createdOn', '!=', NULL] 
            ];
            $result = WebsiteLoginStats::select(['*'])->where($condition)->limit(MAXIMUM_DAYS_ALLOWED_TO_STATS_CHART)->orderBy('timestamp', 'desc')->get();
            return response()->json(new BaseResponse(true, null, $result));
        }
        catch(Exception $e)
        {
            MAE::$Logger->error(sprintf('Error in WebsiteLoginStatController  Message is %s. Stack Trace is %s',$e->getMessage(), $e->getTraceAsString()));
        } 
    }
}
