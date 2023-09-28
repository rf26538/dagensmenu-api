<?php

namespace App\Http\Controllers\TableBooking;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Models\Restaurant\Working;
use App\Models\TableBooking\TableBookingClosedDateModel;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use Validator;
use DB;
use Illuminate\Support\Facades\Log;

class TableBookingTimingController extends Controller
{

    public function fetchTableBookingTimingsByDate(int $restaurantId, Request $request)
    {

        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|int|min:1']);

        if ($validator->fails()) {
            throw new Exception(sprintf("TableBookingController fetchTableBookingTimingsByDate error. %s ", $validator->errors()->first()));
        }

        $queryParams = $request->query();
        $dateForTiming = $queryParams['closedDate'];

        $result = $this->getTimingForTableBookingByDate($restaurantId, $dateForTiming);
        
        return response()->json(new BaseResponse(true, null, $result));
    }
    
    public function fetchTableBookingTimingsForRestaurant(int $restaurantId, Request $request)
    {
        $isSuccess = false;
        $responseMsg = null;
        $response['timings'] = [];

        try
        {
            $queryParams = $request->query();
            $dateForTiming = $queryParams['date'];
            
            $validator = Validator::make([
                'restaurantId' => $restaurantId,
                'date' => $dateForTiming
            ], [
                'restaurantId' => 'required|int|min:1',
                'date' => 'required'
            ]);
    
            if ($validator->fails()) {
                throw new Exception(sprintf("TableBookingController fetchTableBookingTimingsForRestaurant error. %s ", $validator->errors()->first()));
            }
    
            $results = $this->getTimingForTableBookingByDate($restaurantId, $dateForTiming);
    
            $conditionForClosedDates = [['restaurantId', $restaurantId], ['isDeleted', null], ['closedDate', '=', $dateForTiming]]; 
            $closedTime = TableBookingClosedDateModel::select(DB::raw("TIME_FORMAT(closedTimeFrom, '%H:%i') as closedTimeFrom"), DB::raw("TIME_FORMAT(closedTimeTo, '%H:%i') as closedTimeTo"))->where($conditionForClosedDates)->get()->toArray();
            
            $timings = [];
    
            if(!empty($closedTime))
            {
                $timeToClosed = $this->getClosedTiming($closedTime);
                
                if(!empty($timeToClosed))
                {
                    foreach($results as $result)
                    {
                        if(in_array($result, $timeToClosed))
                        {
                            $timings[] = ["time" => $result, "status" => "closed"];
                        } 
                        else 
                        {
                            $timings[] = ["time" => $result, "status" => "open"];
                        }
                    }
                    $response['timings'] = $timings;
                    $response['closedFromAndToTime'] = $closedTime;
                } 
                else 
                {
                    $response['timings'] = [];
                }
            } 
            else 
            {
                foreach($results as $result)
                {
                    $response['timings'][] = ["time" => $result, "status" => "open"];
                }
            }

            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in TableBookingController@fetchTableBookingTimingsForRestaurant Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }
        
        return response()->json(new BaseResponse($isSuccess, $responseMsg, $response));
    }

    private function getTimingForTableBookingByDate(int $restaurantId, string $dateForTiming): array
    {
        $result = array();
        $weekNames = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
        $condition = [['adv_id', $restaurantId], ['working_type', WORKING_TYPE_NORMAL_TIMING]];
        $working = Working::where($condition)->first()->toArray();

        if(!empty($working)) 
        {
            $dateNow = date("Y-m-d");

            if ($dateNow > $dateForTiming) {
                throw new Exception("Invalid request");
            }

            $dayOfWeekIndex = date('w', strtotime($dateForTiming));
            $dayOfWeek = $weekNames[$dayOfWeekIndex];

            $dayOfWeekStartHourColumnName = $dayOfWeek;
            $dayOfWeekStartMinuteColumnName = sprintf("%s_time", $dayOfWeek);
            $dayOfWeekEndHourColumnName = sprintf("%s_e", $dayOfWeek);
            $dayOfWeekEndMinuteColumnName = sprintf("%s_e_time", $dayOfWeek);

            $startHour = intval($working[$dayOfWeekStartHourColumnName]);
            $startMinute = empty($working[$dayOfWeekStartMinuteColumnName]) ? 0 : intval($working[$dayOfWeekStartMinuteColumnName]);

            if($dateNow == $dateForTiming) 
            {
                $currentHour = date('H');
                $currentMinute = intval(date('i'));
                if($currentHour >= $startHour) 
                {
                    $startHour = $currentHour + 1;
                    if ($currentMinute > 45) 
                    {
                        $startHour = $startHour + 1;
                        $startMinute = 0;
                    } 
                    else if ($currentMinute < 15) 
                    {
                        $startMinute = 15;
                    } 
                    else if ($currentMinute < 30) 
                    {
                        $startMinute = 30;
                    } 
                    else if ($currentMinute < 45) {
                        $startMinute = 45;
                    }
                }
            }

            $endHour = intval($working[$dayOfWeekEndHourColumnName]);
            $endHour = $endHour < $startHour ? $startHour : $endHour;
            $endMinute = empty($working[$dayOfWeekEndMinuteColumnName]) ? 0 : intval($working[$dayOfWeekEndMinuteColumnName]);
            
            $result = $this->getTimeIntervals($startHour, $startMinute, $endHour, $endMinute);
        }
        return $result;
    }

    private function getClosedTiming(array $closedTimes): array
    {
        $timings = [];
    
        foreach($closedTimes as $closedTime)
        {
            $timeFrom = $closedTime['closedTimeFrom'];
            $timeTo = $closedTime['closedTimeTo'];
            
            if($timeFrom == "00:00" && $timeTo == "23:59")
            {
                $timings = [];
                break;
            }
            else
            {
                $startTime = explode(":", $timeFrom);
                $endTime = explode(":", $timeTo);

                $startHour = intval($startTime[0]);
                $startMinute = intval($startTime[1]);
                $endHour = intval($endTime[0]);
                $endMinute = intval($endTime[1]);
    
                $timeIntervals = $this->getTimeIntervals($startHour, $startMinute, $endHour, $endMinute);

                array_push($timeIntervals, $timeTo);
                $timings = array_merge($timings, $timeIntervals);
            }
        }
        return $timings;
    }

    private function getTimeIntervals(int $startHour, int $startMinute, int $endHour, int $endMinute): array
    {
        $result = [];

        while($startHour < $endHour)
        {
            while($startMinute <= 45)
            {
                array_push($result, sprintf("%02d:%02d", $startHour, $startMinute));
                $startMinute = $startMinute + 15;
            }
            $startHour++;
            $startMinute = 0;   
        }

        if ($endMinute > 0) 
        {
            while ($startMinute < $endMinute) 
            {
                array_push($result, sprintf("%02d:%02d", $startHour, $startMinute));
                $startMinute = $startMinute + 15;
            }
        }

        return $result;
    }
}


?>