<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Models\Log\WebsiteRegistrationStats;
use Validator;
use Auth;

class WebsiteRegistrationStatController extends Controller
{
    
    public function fetchAll()
    {   
        try
        {
            $condition = [
                ['createdOn', '!=', NULL] 
            ];
            $result = WebsiteRegistrationStats::select(['*'])->where($condition)->limit(MAXIMUM_DAYS_ALLOWED_TO_STATS_CHART)->orderBy('createdOn', 'desc')->get();
            return response()->json(new BaseResponse(true, null, $result));
        }
        catch(Exception $e)
        {
            MAE::$Logger->error(sprintf('Error in WebsiteRegistrationStatController  Message is %s. Stack Trace is %s',$e->getMessage(), $e->getTraceAsString()));
        } 
    }
}
