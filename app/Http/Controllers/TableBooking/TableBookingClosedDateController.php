<?php

namespace App\Http\Controllers\TableBooking;
use Illuminate\Http\Request;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Models\TableBooking\TableBookingClosedDateModel;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use App\Shared\EatCommon\Helpers\IPHelpers;
use Validator;
use Auth;

class TableBookingClosedDateController extends Controller
{
    private $datetimeHelpers;
    private $translatorFactory;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, TranslatorFactory $translatorFactory) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;   
        $this->translatorFactory = $translatorFactory::getTranslator();
    }

    public function fetchTableBookingClosedDates(int $restaurantId, Request $request)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|int|min:1']);
        
        if($validator->fails())
        {
            throw new Exception(sprintf("TableBookingClosedDateController fetchTableBookingClosedDates error. %s ", $validator->errors()->first()));
        }

        $queryParams = $request->query();
        $limit = !empty($queryParams['limit']) ? (int)$queryParams['limit']: 10;
        $currentPage = !empty($queryParams['page']) ? (int)$queryParams['page']: 1;
        $offset = (($currentPage - 1) * $limit);
        $currentDate = date("Y-m-d");

        $condition = [['restaurantId', $restaurantId], ['isDeleted', null], ['closedDate', '>=', $currentDate]]; 

        $closedDatesQuery = TableBookingClosedDateModel::select('tableBookingclosedDateId', 'closedDate', 'closedTimeFrom', 'closedTimeTo', 'reason')->where($condition);
        $closedDates = $closedDatesQuery->offset($offset)->limit($limit)->orderBy('closedDate', 'asc')->get();
        
        if($currentPage < 1) {
            $currentPage = 1;
        }

        $pagination = [
            'currentPage' => $currentPage,
            'itemPerPage' => $limit
        ];

        $response = ['pagination' => $pagination, 'data' => []];

        if(!empty($closedDates)) {
            foreach($closedDates as $closedDate) {
                $data = [
                    "tableBookingclosedDateId" => $closedDate->tableBookingclosedDateId,
                    "humanReadableDate" => $this->datetimeHelpers->getDanishFormattedDate(strtotime($closedDate->closedDate)),
                    "closedDate" => $closedDate->closedDate,
                    "closedTimeFrom" => date("H:i", strtotime($closedDate->closedTimeFrom)),
                    "closedTimeTo" => date("H:i", strtotime($closedDate->closedTimeTo)),
                    "reasonId" => $closedDate->reason,
                    "reason" => $this->getReasonText($closedDate->reason)
                ];

                $response['data'][] = $data;
            }
        }
        $response['currentDate'] = date("Y-m-d");
        $response['currentTime'] = date("H:i");    
        
        return response()->json(new BaseResponse(true, null, $response));
    }

    public function saveTableBookingClosedDate(Request $request, int $restaurantId)
    {
        $rules = [
            'restaurantId' => 'required|int|min:1',
            'closedDate' => 'required|date_format:Y-m-d',
            'closedTimeFrom' => 'required|date_format:H:i',
            'closedTimeTo' => 'required|date_format:H:i',
            'reason' => 'required|int|min:1'
        ];
        $validator = Validator::make($request->post(), $rules);

        if($validator->fails())
        {
            throw new Exception(sprintf("TableBookingClosedDateController saveTableBookingClosedDate error. %s ", $validator->errors()->first()));
        }

        $closedDate = $request->post('closedDate');
        $closedTimeFrom = $request->post('closedTimeFrom');
        $closedTimeTo = $request->post('closedTimeTo');
        $reason = $request->post('reason');
        $restaurantId = $request->post('restaurantId');
        $createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $ip = $this->ipHelpers->clientIpAsLong();
        $userId = Auth::id();
       
        $dateAndTime = $this->checkTableBookingTimeAlreadyExists($closedDate, $closedTimeFrom, $closedTimeTo, $restaurantId);
        $messageForUser = null;
        $isSuccess = true;
        $data = null;

        if($dateAndTime)
        {
            $messageForUser = $this->translatorFactory->translate("The time already exists, Select another time");
            $isSuccess = false;
        }
        else
        {
            $dataToSave = [
                'closedTimeFrom' => $closedTimeFrom, 
                'restaurantId' => $restaurantId, 
                'closedTimeTo' => $closedTimeTo, 
                'closedDate' => $closedDate, 
                'createdOn' => $createdOn, 
                'userId' => $userId, 
                'reason' => $reason,
                'ip' => $ip
            ];
    
            $tableBookingclosedDateId = TableBookingClosedDateModel::insertGetId($dataToSave);
            $data['tableBookingclosedDateId'] = $tableBookingclosedDateId;
            $data['closedDate'] = $closedDate;
            $data['humanReadableDate'] = $this->datetimeHelpers->getDanishFormattedDate(strtotime($closedDate));
            $data['closedTimeFrom'] = date("H:i", strtotime($closedTimeFrom));
            $data['closedTimeTo'] = date("H:i", strtotime($closedTimeTo)); 
            $data['reason'] = $this->getReasonText($reason);
            $data['reasonId'] = $reason;
        }
 
        return response()->json(new BaseResponse($isSuccess, $messageForUser, $data));
    }

    public function editTableBookingClosedDate(Request $request, int $tableBookingclosedDateId)
    {
        $rules = [
            'tableBookingclosedDateId' => 'required|int|min:1',
            'restaurantId' => 'required|int|min:1',
            'closedDate' => 'required|date_format:Y-m-d',
            'closedTimeFrom' => 'required|date_format:H:i',
            'closedTimeTo' => 'required|date_format:H:i',
            'reason' => 'required|int|min:1',
        ];

        $validator = Validator::make($request->post(), $rules);

        if($validator->fails())
        {
            throw new Exception(sprintf("TableBookingClosedDateController editTableBookingClosedDate error. %s ", $validator->errors()->first()));
        }

        $tableBookingclosedDateId = $request->post('tableBookingclosedDateId');
        $closedDate = $request->post('closedDate');
        $closedTimeFrom = $request->post('closedTimeFrom');
        $closedTimeTo = $request->post('closedTimeTo');
        $reason = $request->post('reason');
        $restaurantId = $request->post('restaurantId');
        $updatedOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();

        $dataToUpdate = [
            'closedDate'=> $closedDate,
            'closedTimeFrom'=> $closedTimeFrom,
            'closedTimeTo'=> $closedTimeTo,
            'reason'=> $reason,
            'updatedOn'=> $updatedOn
        ];

        TableBookingClosedDateModel::where('tableBookingclosedDateId', $tableBookingclosedDateId)->update($dataToUpdate);

        $data['tableBookingclosedDateId'] = $tableBookingclosedDateId;
        $data['humanReadableDate'] = $this->datetimeHelpers->getDanishFormattedDate(strtotime($closedDate));
        $data['closedDate'] = $closedDate;
        $data['closedTimeFrom'] = date("H:i", strtotime($closedTimeFrom)); ;
        $data['closedTimeTo'] = date("H:i", strtotime($closedTimeTo)); ;
        $data['reason'] = $this->getReasonText($reason);
        $data['reasonId'] = $reason;

        return response()->json(new BaseResponse(true, null, $data));
    }

    public function deleteTableBookingClosedDate(int $tableBookingclosedDateId)
    {
        $validator = Validator::make(['tableBookingclosedDateId' => $tableBookingclosedDateId], ['tableBookingclosedDateId' => 'required|int|min:1']);

        if($validator->fails())
        {
            throw new Exception(sprintf("TableBookingClosedDateController deleteTableBookingClosedDate error. %s ", $validator->errors()->first()));
        }
        $updatedOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();

        TableBookingClosedDateModel::where('tableBookingclosedDateId', $tableBookingclosedDateId)->update(array('isDeleted' => 1, 'updatedOn' => $updatedOn));
        
        return response()->json(new BaseResponse(true, null, null));
    }

    public function getReason()
    {
        $reasons[TABLE_BOOKING_CLOSED_REASON_FULLY_BOOKED] = $this->getReasonText(TABLE_BOOKING_CLOSED_REASON_FULLY_BOOKED);
        $reasons[TABLE_BOOKING_CLOSED_REASON_HOLIDAY] = $this->getReasonText(TABLE_BOOKING_CLOSED_REASON_HOLIDAY);
        $reasons[TABLE_BOOKING_CLOSED_REASON_VACATION] = $this->getReasonText(TABLE_BOOKING_CLOSED_REASON_VACATION);
        $reasons[TABLE_BOOKING_CLOSED_REASON_NO_STAFF] = $this->getReasonText(TABLE_BOOKING_CLOSED_REASON_NO_STAFF);
        $reasons[TABLE_BOOKING_CLOSED_REASON_OTHER_REASON] = $this->getReasonText(TABLE_BOOKING_CLOSED_REASON_OTHER_REASON);
    
        return response()->json(new BaseResponse(true, null, $reasons));
    }

    private function getReasonText(int $reasonId): string
    {
        switch($reasonId) {
            case TABLE_BOOKING_CLOSED_REASON_HOLIDAY:
                return $this->translatorFactory->translate('Holiday');
            break;
            case TABLE_BOOKING_CLOSED_REASON_VACATION:
                return $this->translatorFactory->translate('Vacation');
            break;
            case TABLE_BOOKING_CLOSED_REASON_FULLY_BOOKED:
                return $this->translatorFactory->translate('Full booked');
            break;
            case TABLE_BOOKING_CLOSED_REASON_NO_STAFF:
                return $this->translatorFactory->translate('No staff');
            break;
            case TABLE_BOOKING_CLOSED_REASON_OTHER_REASON:
                return $this->translatorFactory->translate('Other reasons');
            break;
            default:
            return "";
        }
    }

    private function checkTableBookingTimeAlreadyExists(string $date, string $timeFrom, string $timeTo, int $restaurantId): bool
    {
        $condition = [['isDeleted', null], ['closedDate', '=', $date], ['restaurantId', '=', $restaurantId]]; 
        $tableBookingClosedDates = TableBookingClosedDateModel::select('closedDate', 'closedTimeFrom', 'closedTimeTo')->where($condition)->get()->toArray();

        if(!empty($tableBookingClosedDates))
        {
            foreach($tableBookingClosedDates as $tableBookingClosedDate)
            {
                $closedTimeFrom = date("H:i", strtotime($tableBookingClosedDate['closedTimeFrom']));
                $closedTimeTo = date("H:i", strtotime($tableBookingClosedDate['closedTimeTo']));
        
                if($closedTimeFrom == "00:00" && $closedTimeTo == "23:59")
                {
                    return true;
                } 
                
                if ($closedTimeFrom == $timeFrom && $closedTimeTo == $timeTo)
                {
                    return true;
                } 
            }
        } 
        return false;
    }
}
