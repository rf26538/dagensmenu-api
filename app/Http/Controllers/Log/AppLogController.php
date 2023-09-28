<?php

namespace App\Http\Controllers\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseResponse;
use Illuminate\Http\Request;

class AppLogController extends Controller
{
    public function log(Request $request)
    {
        $errors = ["error", "critical", "info", "warning"];
        $errorLogType = $request->post('type') ?? "error";
        $message = $request->post('message') ?? "";

        if (in_array($errorLogType, $errors) && $message)
        {
            Log::channel('appLogs')->$errorLogType($message);   
        }

        return response()->json(new BaseResponse(true, null, true));
    }
}
