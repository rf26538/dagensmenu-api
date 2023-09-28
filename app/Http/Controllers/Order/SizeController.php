<?php

namespace App\Http\Controllers\Order;
use App\Http\Controllers\BaseResponse;
use Illuminate\Http\Request;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\Size;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Exception;
use Validator;
use Auth;

class SizeController extends Controller
{
    const MODEL = "App\Models\Order\Size";

    private $datetimeHelpers;
    private $ipHelpers;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
    }

    public function get()
    {
        $response = Size::where('isDeleted', 0)->get();
        return response()->json(new BaseResponse(true, null, $response));
    }
    public function getById(int $sizeId)
    {
        $response = Size::where([['sizeId', $sizeId], ['isDeleted', 0]])->first();
        return response()->json(new BaseResponse(true, null, $response));
    }

    public function add(Request $request)
    {
        try
        {
            $rules = array(
                'sizeName' => 'required|string',
            );
            
            $data = $request->all();
            
            $validator = Validator::make($data, $rules);

            if ($validator->fails())
            {
                throw new Exception(sprintf("SizeController add error. %s ", $validator->errors()->first()));
            }

            $sizeName = $data['sizeName'];

            $sizeData = [
                'size' => $sizeName,
                'userId' => Auth::id(),
                'createdOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                'ip' => $this->ipHelpers->clientIpAsLong()
            ];

            $sizeId = Size::insertGetId($sizeData);
            
            $isSuccess = true;
            $data['sizeName'] = $sizeName;
            $data['sizeId'] = $sizeId;
        }
        catch(Exception $e)
        {
            Log::critical($e->getMessage());
            $data = null;
            $isSuccess = false;
        }
        return response()->json(new BaseResponse($isSuccess, null, $data));

    }

    public function update(int $sizeId, Request $request)
    {
        try
        {
            $rules = array(
                'sizeName' => 'required|string'
            );
            
            $data = $request->all();
            $validator = Validator::make($data, $rules);

            if ($validator->fails())
            {
                throw new Exception(sprintf("SizeController update error. %s ", $validator->errors()->first()));
            }

            $sizeName = $data['sizeName'];

            Size::where('sizeId', $sizeId)->update(array('size' => $sizeName));
            $isSuccess = true;

        }
        catch(Exception $e)
        {
            Log::critical($e->getMessage());
            $isSuccess = false;
        }

        return response()->json(new BaseResponse($isSuccess, null, true));

    }


}
