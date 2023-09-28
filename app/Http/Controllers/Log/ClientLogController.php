<?php

namespace App\Http\Controllers\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseResponse;
use Illuminate\Http\Request;
use Auth;

class ClientLogController extends Controller
{

    public function log(Request $request)
    {
        $userId = Auth::id();

        $userDetails = "";
        if($userId)
        {
            $userDetails = sprintf("UserId is - %s ,", $userId);
        }

        Log::critical(sprintf("%s Message is- %s , Object is - %s", $userDetails, $request->post('message'), $request->post('customObject')));

        return response()->json(new BaseResponse(true, null, true));

    }
}
