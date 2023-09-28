<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Models\Log\NoLocalStorageLog;
use Validator;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Auth;

class NoLocalStorageController extends Controller
{
    private $datetimeHelpers;
    private $ipHelpers;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
    }

    public function add()
    {
        $ip = $this->ipHelpers->clientIpAsLong();
        $noLocalStorageFromDb = NoLocalStorageLog::where([['ip', $ip]])->first();
        if(empty($noLocalStorageFromDb)){
            $noLocalStorageLog = new NoLocalStorageLog();
            $userAgent = null;
            if(array_key_exists("HTTP_USER_AGENT", $_SERVER) && !empty($_SERVER['HTTP_USER_AGENT']))
            {
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
            }
            $noLocalStorageLog->userAgent = $userAgent;
            $noLocalStorageLog->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
            $noLocalStorageLog->ip = $this->ipHelpers->clientIpAsLong();
            $noLocalStorageLog->save();
        }

        return response()->json(new BaseResponse(true, null, true));
    }
}
