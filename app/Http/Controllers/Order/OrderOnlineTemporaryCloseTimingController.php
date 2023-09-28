<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\OrderOnlineTemporaryCloseTiming;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Http\Controllers\BaseResponse;
use Validator;
use Auth;

class OrderOnlineTemporaryCloseTimingController extends Controller
{
    private $translatorFactory;
    private $datetimeHelper;
    private $ipHelpers;

    public function __construct(DatetimeHelper $datetimeHelper, TranslatorFactory $translatorFactory, IPHelpers $ipHelpers) 
    {
        $this->datetimeHelper = $datetimeHelper;
        $this->ipHelpers = $ipHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
    }

    public function save(Request $request)
    {
        $rules = array(
            'restaurantId' => 'required|integer|min:1',
            'closedOrderType' => 'required|integer',
        );

        $validator = Validator::make($request->post(), $rules);

        if ($validator->fails())
        {
            throw new Exception(sprintf("OrderOnlineTemporaryCloseTimingController.save error. %s ", $validator->errors()->first()));
        }

        $restaurantId = $request->post('restaurantId');
        $closedOrderType = $request->post('closedOrderType');
        $intervalInMinutes  = !empty($request->post('intervalInMinutes')) ? intval($request->post('intervalInMinutes')) : false;
        $fullDayClose = !empty($request->post('fullDayClose')) ? true : false;
        $currentTime = $this->datetimeHelper->getCurrentUtcTimeStamp();

        if(!empty($intervalInMinutes))
        {
            $reopenOn = strtotime("+$intervalInMinutes minutes");
        }
        else
        {
            $reopenOn = $this->datetimeHelper->getCurrentDayEndTimeStamp();
        }
        
        $model = new OrderOnlineTemporaryCloseTiming();
        $model->restaurantId = $restaurantId;
        $model->orderType = $closedOrderType;
        $model->intervalInMinutes = $intervalInMinutes;
        $model->fullDayClose = $fullDayClose;
        $model->reopenOn = $reopenOn;
        $model->status = ORDER_ONLINE_TEMPORARY_CLOSE_STATUS_ACTIVE;
        $model->createdOn = $currentTime;
        $model->ip = $this->ipHelpers->clientIpAsLong();
        $model->userId = Auth::id();
        $model->save();

        return response()->json(new BaseResponse(true, null, true));
    }

    public function update(int $restaurantId)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|integer|min:1']);
        
        if ($validator->fails())
        {
            throw new Exception(sprintf("OrderOnlineTemporaryCloseTimingController.update error. %s ", $validatorGet->errors()->first()));
        }

        OrderOnlineTemporaryCloseTiming::where('restaurantId', $restaurantId)->update(['status' => ORDER_ONLINE_TEMPORARY_CLOSE_STATUS_DELETED]);

        return response()->json(new BaseResponse(true, null, true));
    }

}