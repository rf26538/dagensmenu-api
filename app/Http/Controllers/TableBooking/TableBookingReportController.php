<?php

namespace App\Http\Controllers\TableBooking;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Models\Log\TableBookingReport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Exception;

class TableBookingReportController extends Controller
{

    private $datetimeHelpers;
    function __construct(DatetimeHelper $datetimeHelpers) {
        $this->datetimeHelpers = $datetimeHelpers;
    }

    public function getRestaurantTableBookingMonthlyReport(Request $request, int $restaurantId)
    {

        $months = !empty($request->months) ? $request->months : 12;
        $validator = Validator::make(['restaurantId' => $restaurantId, 'months' => $months], ['restaurantId' => 'required|int|min:1', 'months' => 'required|int|min:1']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("TableBookingReportController getRestaurantTableBookingMonthlyReport error. %s ", $validator->errors()->first()));
        }

        $endDate = date("Y-m", strtotime("-".$months." month"));

        $condition = [
            ['adId', $restaurantId],
            ['date', '>=', $endDate]
        ];

        $results = TableBookingReport::select([
            DB::raw("SUM(acceptedBookings) as acceptedBookings"),
            DB::raw("SUM(acceptedBookingGuests) as acceptedBookingGuests"),
            DB::raw('DATE_FORMAT(date, "%Y-%m") as yearMonth')
        ])->where($condition)->groupBy('yearMonth')->orderBy('yearMonth', 'DESC')->get()->toArray();

        for($i=0; $i<=$months; $i++)
        {
            $yearMonth = date('Y-m', strtotime("-$i month"));
	        $month = intval(date('m', strtotime($yearMonth)));
	        $year = date('Y', strtotime($yearMonth));

            if(!array_key_exists($i, $results) || $results[$i]['yearMonth'] != $yearMonth){
                $missingMonthsData[] = ["acceptedBookings" => 0, "acceptedBookingGuests" => 0, "yearMonth" => $yearMonth];
                array_splice($results, $i, 0, $missingMonthsData);
            }

            $fullMonthName = $this->datetimeHelpers->getMonthNameByMonthNumber($month);
            $danishMonth = $this->datetimeHelpers->getSmallDanishMonthNameForEnglishName($fullMonthName);
            $results[$i]['monthInHumanReadable'] = sprintf('%s %s', $danishMonth, $year);
            $missingMonthsData = NULL;
        }

        return response()->json(new BaseResponse(true, null, $results));
    }

}
