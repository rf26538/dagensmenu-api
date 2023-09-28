<?php

namespace App\Http\Controllers;

use App\Models\Restaurant\Advertisement;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseResponse;
use Exception;

class AdvertisementStatusController extends Controller
{
    private $datetimeHelper;

    public function __construct(DatetimeHelper $datetimeHelper)
    {
        $this->datetimeHelper = $datetimeHelper;
    }

    public function changeStatus(int $restaurantId, int $status)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId, 'status' => $status], ['restaurantId' => 'required|integer', 'status' => 'required|integer']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("AdvertisementStatusController changeStatus error. %s ", $validator->errors()->first()));
        }

        $update = [
            'lastInfoUpdatedOn' => $this->datetimeHelper->getCurrentUtcTimeStamp(),
            'status' => $status
        ];

        Advertisement::where('id', $restaurantId)->update($update);

        return response()->json(new BaseResponse(true, null, true));
    }

}
