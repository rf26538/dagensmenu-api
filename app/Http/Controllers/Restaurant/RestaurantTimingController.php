<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Models\Restaurant\Advertisement;
use Illuminate\Http\Request;
use App\Models\Restaurant\Working;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Models\Order\OrderOnlineTemporaryCloseTiming;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Http\Controllers\Restaurant\SaveAndGetRestaurantTiming;
use Illuminate\Support\Facades\Validator;
use Exception;
use Log;

class RestaurantTimingController extends Controller
{
    public $datetimeHelper;
    public $translatorFactory;

    // Note:- 
    // If you are loading any new classes in constructor 
    // Make sure that class needs to be added in OrderContoller@checkRestaurantIsOpen

    public function __construct(DatetimeHelper $datetimeHelper, TranslatorFactory $translatorFactory)
    {
        $this->datetimeHelper = $datetimeHelper;
        $this->translatorFactory = $translatorFactory::getTranslator();  
    }

    public function fetchTimings(int $workingType, int $restaurantId)
    {
        $response = [];

        $saveAndGetRestaurantTiming = new SaveAndGetRestaurantTiming();
        $response['working'] = $saveAndGetRestaurantTiming->fetchTimings($workingType, $restaurantId);
        
        $advertisement = Advertisement::select(['id','title','city'])->where('id', $restaurantId)->first();

        if (!empty($advertisement))
        {
            $restaurant['title'] = $advertisement->title;
            $restaurant['city']  = $advertisement->city;
            $response['restaurant'] = $restaurant;
        }

        return response()->json(new BaseResponse(true, null, $response));      
    }

    public function saveTimings(int $workingType, int $restaurantId, Request $request)
    {

        $saveAndGetRestaurantTiming = new SaveAndGetRestaurantTiming();
        $response = $saveAndGetRestaurantTiming->saveOrUpdateWorking($restaurantId, $request->post(), $workingType);
        
        return response()->json(new BaseResponse(true, null, $response));      
    }    

    public function getTakeawayAndDeliveryStatus(int $restaurantId, Request $request)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1|int']);

        if ($validator->fails())
        {
            throw new Exception("RestaurantTimingController getTakeawayAndDeliveryStatus error %s", $validator->errors()->first());
        }

        $queryParams = $request->query();
        $workingType = !empty($queryParams['workingType']) ? $queryParams['workingType'] : 0;

        $response = [];
        $advertisement = Advertisement::select(['hasTakeaway', 'hasDelivery', 'enableFutureOrder'])->where('id', $restaurantId)->first()->toArray();

        if (!empty($advertisement))
        {
            if ($advertisement['hasTakeaway'] == 0 && $advertisement['hasDelivery'] == 0)
            {
                $workingTypeName = $this->getWorkingTypeName(WORKING_TYPE_NORMAL_TIMING);

                $response[] = [
                    'open' => false, 
                    'message' => sprintf("%s %s.", $workingTypeName, $this->translatorFactory->translate('is closed')), 
                    'time' => null,
                    'workingType' => WORKING_TYPE_NORMAL_TIMING
                ];
            }
            else
            {
                $whereIn = [WORKING_TYPE_NORMAL_TIMING];

                if ($advertisement['hasTakeaway'] == 1)
                {
                    $whereIn[] = WORKING_TYPE_TAKEAWAY_TIMINGS;
                }

                if ($advertisement['hasDelivery'] == 1)
                {
                    $whereIn[] = WORKING_TYPE_DELIVERY_TIMINGS;
                }

                $condition[] = ['adv_id', $restaurantId];
                if ($workingType > 0 && in_array($workingType , $whereIn))
                {
                    $whereIn = [];
                    $condition[] = ['working_type', $workingType];
                }
        
                $working = Working::where($condition);
                
                if (!empty($whereIn))
                {
                    $working->whereIn('working_type', $whereIn);
                }
        
                $workings = $working->get()->toArray();


                
                if (!empty($workings))
                {
                    $workings = $this->removeTimingWhenAllWeekDaysAreZero($workings);

                    $enableFutureOrder = $advertisement['enableFutureOrder'];

                    foreach($workings as $workingRow)
                    {
                        $futureOrderTiming = [];
                        $workingRow['enableFutureOrder'] = $enableFutureOrder;
                        $calculateTiming = $this->calculateTiming($workingRow);

                        if($enableFutureOrder)
                        {
                            $futureOrderTiming = $this->getFutureOrderTimings($workingRow);
                        }
 
                        unset($calculateTiming['openingHours']);
                        $calculateTiming['futureOrderTiming'] = $futureOrderTiming;
                        $response['status'][] = $calculateTiming;
                    }
                    $response['isFutureOrderEnable'] = $enableFutureOrder;
                }
            }
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    private function removeTimingWhenAllWeekDaysAreZero(array $workings): array
    {
        $data = [];
        $weekDays = $this->datetimeHelper->weekDays;
        $weekDaysCount = count($weekDays);

        foreach($workings as $workingRow)
        {
            $counter = 0;

            if ($workingRow['working_type'] != WORKING_TYPE_NORMAL_TIMING)
            {
                foreach($weekDays as $index => $weekDay)
                {
                    $startHourColumn = $weekDay;
                    $endHourColumn = sprintf("%s_e", $weekDay);
                    $startMinuteColumn = sprintf("%s_time", $weekDay);
                    $endMinuteColumn = sprintf("%s_e_time", $weekDay);

                    if ($workingRow[$startHourColumn] === 0 && $workingRow[$endHourColumn] === 0 && $workingRow[$startMinuteColumn] === 0 && $workingRow[$endMinuteColumn] === 0)
                    {
                        ++$counter;
                    }
                }
            }
            
            if ($counter != $weekDaysCount)
            {
                $data[] = $workingRow;
            }
        }

        return $data;
    }

    public function calculateTiming(array $working): array
    {   
        try
        {
            $currentDay = strtolower(date('l'));
            $currentHrs = date('H:i');
            $currentTime = strtotime($currentHrs);
            $message = '';
            $isOpened = false;
            $timeDiff = null;
            $futureOrderMsg = "";
            $futureOrderMsgForBasket = "";
            $defaultFutureOrderMsg = $this->translatorFactory->translate("Possibility of pre-ordering");
            $defaultFutureOrderMsgForBasket = $this->translatorFactory->translate("Pre-order your food online. Choose a time");

            $startHourColumn    = $currentDay;
            $endHourColumn      = sprintf("%s_e", $currentDay);
            $startMinuteColumn  = sprintf("%s_time", $currentDay);
            $endMinuteColumn    = sprintf("%s_e_time", $currentDay);

            $nextDayName = strtolower(date("l", strtotime("+1 days")));
            
            $carryNextDay = $this->getArrayOfColumn($nextDayName, 'carry');
            $carryCurrentDay = $this->getArrayOfColumn($currentDay, 'carry');
            
            $carryCurrentDayEndHour = $working[$carryCurrentDay['carryEndHourColumn']];
            $carryCurrentDayEndMinute = $working[$carryCurrentDay['carryEndMinuteColumn']];
            $carryCurrentDayEndHourAndMinute = sprintf('%02d:%02d', $carryCurrentDayEndHour, $carryCurrentDayEndMinute);
            $carryCurrentDayEndHourAndMinuteTime = 0;

            if (!is_null($carryCurrentDayEndHour))
            {
                $carryCurrentDayEndHourAndMinuteTime = strtotime($carryCurrentDayEndHourAndMinute);
            }

            $carryNextDayEndHour = $working[$carryNextDay['carryEndHourColumn']];
            $carryNextDayEndMinute = $working[$carryNextDay['carryEndMinuteColumn']];

            $carryNextDayEndHourAndMinute = sprintf('%02d:%02d', $carryNextDayEndHour, $carryNextDayEndMinute);

            $todayStartHour = $working[$startHourColumn];
            $todayEndHour = $working[$endHourColumn];
            $todayStartMinute = intval($working[$startMinuteColumn]);
            $todayEndMinute = intval($working[$endMinuteColumn]);

            $todayStartHourAndMinute = sprintf('%02d:%02d', $todayStartHour, $todayStartMinute);
            $todayEndHourAndMinute = sprintf('%02d:%02d', $todayEndHour, $todayEndMinute);

            $workingTypeName = $this->getWorkingTypeName($working['working_type']);

            if($carryCurrentDayEndHourAndMinuteTime > 0 && $currentTime < $carryCurrentDayEndHourAndMinuteTime)
            {
                $isOpened = true;
                $timeDiff =  $this->datetimeHelper->timeDifference($currentHrs, $carryCurrentDayEndHourAndMinute);
                $timeText = $this->getOpeningHoursMessage($timeDiff);
                $message = sprintf('%s %s %s.', $workingTypeName, $this->translatorFactory->translate('is open for the next') , $timeText);
            }
            else if (is_null($todayStartHour) || is_null($todayEndHour))
            {
                $nextOpenedDay = $this->getRestaurantNextOpenedDay($working);
                if (!empty($nextOpenedDay))
                {
                    $timeMessage = $this->getTimingMessage($working);
                    $futureOrderMsg = $timeMessage['futureOrderMsg'];
                    $futureOrderMsgForBasket = $timeMessage['futureOrderMsgForBasket'];
                    $message = sprintf("%s %s%s.", $workingTypeName, $this->translatorFactory->translate('is closed today'), $timeMessage['message']);
                }
                else
                {
                    $message = sprintf("%s %s.", $workingTypeName, $this->translatorFactory->translate('is closed'));
                }
            }
            else
            {
                // When both hour is 0 then we will consider them as they are working 24hrs.
                if ($todayStartHour === 0 && $todayEndHour === 0)
                {
                    $isOpened = true;
                }
                else
                {
                    $todayStartTime = strtotime($todayStartHourAndMinute);
                    $todayEndTime = strtotime($todayEndHourAndMinute);
                    $nextClosingTimeAndMsg = false; 

                    if ($currentTime > $todayStartTime && $todayEndHour === 0)
                    {
                        $isOpened = true;
                        $nextClosingTimeAndMsg = true;
                    }
                    else if ($currentTime < $todayStartTime)
                    {
                        $timeDiff = $this->datetimeHelper->timeDifference($currentHrs, $todayStartHourAndMinute);
                        $timeText = $this->getOpeningHoursMessage($timeDiff);
                        $futureOrderMsg = $defaultFutureOrderMsg;
                        $futureOrderMsgForBasket = $defaultFutureOrderMsgForBasket;
                        $message = sprintf('%s %s %s', $workingTypeName, $this->translatorFactory->translate('will be open in'), $timeText);
                    }
                    else if ($currentTime > $todayEndTime)
                    {
                        $timeMessage = $this->getTimingMessage($working);
                        $futureOrderMsg = $timeMessage['futureOrderMsg'];
                        $futureOrderMsgForBasket = $timeMessage['futureOrderMsgForBasket'];

                        $message = sprintf("%s %s%s", $workingTypeName, $this->translatorFactory->translate('is closed right now'), $timeMessage['message']);
                    }
                    else if ($currentTime < $todayEndTime)
                    {
                        $isOpened = true;
                        $nextClosingTimeAndMsg = true;
                    }

                    if ($nextClosingTimeAndMsg)
                    {
                        $timeDiff =  $this->datetimeHelper->timeDifference($currentHrs, $todayEndHourAndMinute);
                        
                        if (!is_null($carryNextDayEndHour))
                        {
                            $timeDiff = $this->datetimeHelper->addTime(sprintf("%02d:%02d", $timeDiff['hour'], $timeDiff['minute']), $carryNextDayEndHourAndMinute);
                        }

                        $timeText = $this->getOpeningHoursMessage($timeDiff);
                        $message = sprintf('%s %s %s.', $workingTypeName, $this->translatorFactory->translate('is open for the next') , $timeText);
                    }
                }
            }
            
            $restaurantTemporaryClosed = OrderOnlineTemporaryCloseTiming::select(['orderType', 'fullDayClose', 'reopenOn'])->whereRaw(sprintf("(reopenOn > %s OR reopenOn = %s) AND status = %s AND restaurantId = %s", $this->datetimeHelper->getCurrentUtcTimeStamp(), 0, ORDER_ONLINE_TEMPORARY_CLOSE_STATUS_ACTIVE, $working['adv_id']))->get()->toArray();

            if(!empty($restaurantTemporaryClosed) && ($restaurantTemporaryClosed[0]['orderType'] == RESTAURANT_ORDER_ONLINE_FULL_CLOSED || $restaurantTemporaryClosed[0]['orderType'] == $working['working_type']))
            {
                $isOpened = false;
                $timeDiff = "";
                $todayStartHourAndMinute = "";
                $carryCurrentDayEndHourAndMinute = "";

                if($restaurantTemporaryClosed[0]['fullDayClose'])
                {
                    if($working['working_type'] == WORKING_TYPE_TAKEAWAY_TIMINGS)
                    {
                        $message = $this->translatorFactory->translate('We do not offer takeaway today');
                    }
                    else
                    {
                        $message = $this->translatorFactory->translate('We do not offer delivery today.'); 
                    }
                }
                else
                {
                    if($restaurantTemporaryClosed[0]['orderType'] == RESTAURANT_ORDER_ONLINE_DELIVERY_CLOSED)
                    {
                        $message = sprintf("%s %s", $this->translatorFactory->translate('Due to busyness we can not offer delivery the next'), $this->getRemainingTime($restaurantTemporaryClosed[0]['reopenOn'])); 
                    }
                    else if($restaurantTemporaryClosed[0]['orderType'] == RESTAURANT_ORDER_ONLINE_FULL_CLOSED)
                    {
                        $message = sprintf("%s %s", $this->translatorFactory->translate('Due to busyness, we will not receive any more orders next time'), $this->getRemainingTime($restaurantTemporaryClosed[0]['reopenOn'])); 
                    }

                    $todayStartHourAndMinute = date("H:i", $restaurantTemporaryClosed[0]['reopenOn']);

                }
            }

            $returnData = [
                'open' => $isOpened, 
                'message' => $message, 
                'time' => $timeDiff,
                'openingHours' => [
                    'startTime' => $todayStartHourAndMinute,
                    'endTime' => $todayEndHourAndMinute,
                    'carryCurrentDayEndHourAndMinute' => $carryCurrentDayEndHourAndMinute,     
                ],
                'workingType' => $working['working_type']
            ];

            if(isset($working['enableFutureOrder']) && $working['enableFutureOrder'])
            {
                $futureOrderMsg = empty($futureOrderMsg) ? $defaultFutureOrderMsg : $futureOrderMsg;
                $futureOrderMsgForBasket = empty($futureOrderMsgForBasket) ? $defaultFutureOrderMsgForBasket : $futureOrderMsgForBasket;
                $returnData['futureOrderMsg'] = $futureOrderMsg;
                $returnData['futureOrderMsgForBasket'] = $futureOrderMsgForBasket;
            }

            return $returnData;
        }
        catch (Exception $e)
        {
            throw new Exception("Error in RestaurantTimingController calulateTiming is %s", $e->getMessage());
        }
    }

    private function getRemainingTime(int $remainingTime)
    {

        $currentTime =  $this->datetimeHelper->getCurrentUtcTimeStamp();
        $timeDiff = $remainingTime - $currentTime;
    
        $timeLeft = sprintf("%s %s", ceil(abs($timeDiff / 60)), " min.");
        
        return $timeLeft;
    }

    private function getFutureOrderTimings(array $working): array
    {
        $futureOrderTiming = [];
        
        if(!empty($working))
        {
            $nextDate = date("Y-m-d", strtotime("+1 days"));
            $nextDayName = strtolower(date("l", strtotime("+1 days")));
            $todayTiming = $this->calculateTiming($working);
            $nextDayTiming = $this->getWeekDaysTiming($nextDayName, $working);
            $todayStartTime = $todayTiming['openingHours']['startTime'];
            $todayEndTime = $todayTiming['openingHours']['endTime'];
            $todayCarryTime = $todayTiming['openingHours']['carryCurrentDayEndHourAndMinute']; 

            $nextDayStartTime = $nextDayTiming['startTime'];
            $nextDayEndTime = $nextDayTiming['endTime'];
            $nextDayCarryTime = $nextDayTiming['carryCurrentDayEndHourAndMinute'];
            
            $todayFutureOrderTimings = $this->calculateFutureOrderTimings($todayStartTime, $todayEndTime, $todayCarryTime, 'I dag');
            $nextDayFutureTimings = $this->calculateFutureOrderTimings($nextDayStartTime, $nextDayEndTime, $nextDayCarryTime, 'I morgen', $nextDate);
            
            $futureOrderTiming = array_merge($todayFutureOrderTimings, $nextDayFutureTimings);
        }

        return $futureOrderTiming;
    }

    private function calculateFutureOrderTimings(string $todayStartTime, string $todayEndTime, string $todayCarryTime, string $text, string $date = null): array
    {
        $timings = [];
        
        $currentTime = strtotime(date('H:i'));

        if($todayEndTime == "24:00")
        {
            $todayEndTime = "23:59";
        }

        if($todayCarryTime != "00:00")
        {
            if(empty($date))
            {
                $todayStartTimestamp = $this->datetimeHelper->getCurrentDayTimeStamp();
                $todayCarryTimestamp = strtotime($todayCarryTime);
                
                if($todayStartTimestamp < $todayCarryTimestamp && $currentTime < $todayCarryTimestamp)
                {
                    for($i = $this->changeTimingIntoMultipleOfFifteen($currentTime + ORDER_ONLINE_FUTURE_ORDER_DURATION_IN_SECONDS); $i <= $todayCarryTimestamp; $i += ORDER_ONLINE_FUTURE_ORDER_TIME_INTERVALS)
                    {
                        $timings[][$i] = sprintf("%s %s", $text, date("H:i", $i));
                    }
                }
            }
            else
            {
                $startTime = strtotime($date . "00:00");
                $todayCarryTimestamp = strtotime($date . $todayCarryTime);
                
                for($i = $startTime; $i <= $todayCarryTimestamp; $i += ORDER_ONLINE_FUTURE_ORDER_TIME_INTERVALS)
                {
                    $timings[][$i] = sprintf("%s %s", $text, date("H:i", $i));
                }
            }
        }
        
        if(!empty($todayStartTime) && !empty($todayEndTime) && $todayEndTime !== "00:00")
        {
            if(!empty($date))
            {
                $todayStartTimestamp = strtotime($date . $todayStartTime);
                $todayEndTime = strtotime($date . $todayEndTime);
            }
            else
            {
                $todayStartTimestamp = strtotime($todayStartTime);
                $todayEndTime = strtotime($todayEndTime);
            }


            if(($todayStartTimestamp - $currentTime) <= ORDER_ONLINE_FUTURE_ORDER_DURATION_IN_SECONDS)
            {
                $startTime = $currentTime + ORDER_ONLINE_FUTURE_ORDER_DURATION_IN_SECONDS;
                
                for($i = $this->changeTimingIntoMultipleOfFifteen($startTime); $i <= $todayEndTime; $i += ORDER_ONLINE_FUTURE_ORDER_TIME_INTERVALS)
                {
                    $timings[][$i] = sprintf("%s %s", $text, date("H:i", $i));
                }

            }
            else
            {
                for($i = ($this->changeTimingIntoMultipleOfFifteen($todayStartTimestamp) + ORDER_ONLINE_FUTURE_ORDER_OPENING_TIMING_DELAY_IN_SECONDS); $i <= $todayEndTime; $i += ORDER_ONLINE_FUTURE_ORDER_TIME_INTERVALS)
                {
                    $timings[][$i] = sprintf("%s %s", $text, date("H:i", $i));
                }
            }
        }
        
        return $timings;
    }

    private function changeTimingIntoMultipleOfFifteen(int $timestamp): int
    {
        $hour = date("H", $timestamp);
        $minute = date("i", $timestamp);
        $date = date("Y-m-d", $timestamp);

        if($minute > 0 && $minute < 15)
        {
            $minute = 15;
        }
        else if($minute > 15 && $minute < 30)
        {
            $minute = 30;
        }
        else if($minute > 30 && $minute < 45)
        {
            $minute = 45;
        }
        else if($minute > 45)
        {
            $minute = 45;
        }

        $timeToMultipleOfFivteen = strtotime(sprintf("%s %s:%s", $date, $hour, $minute));

        return $timeToMultipleOfFivteen;
    }

    private function getWeekDaysTiming(string $weekDay, array $working ): array
    {
        $currentDay = strtolower($weekDay);

        $startHourColumn    = $currentDay;
        $endHourColumn      = sprintf("%s_e", $currentDay);
        $startMinuteColumn  = sprintf("%s_time", $currentDay);
        $endMinuteColumn    = sprintf("%s_e_time", $currentDay);

        $nextDayName = strtolower(date("l", strtotime("+1 days")));
        
        $carryNextDay = $this->getArrayOfColumn($nextDayName, 'carry');
        $carryCurrentDay = $this->getArrayOfColumn($currentDay, 'carry');
        
        $carryCurrentDayEndHour = $working[$carryCurrentDay['carryEndHourColumn']];
        $carryCurrentDayEndMinute = $working[$carryCurrentDay['carryEndMinuteColumn']];
        $carryCurrentDayEndHourAndMinute = sprintf('%02d:%02d', $carryCurrentDayEndHour, $carryCurrentDayEndMinute);
        $carryCurrentDayEndHourAndMinuteTime = 0;

        if (!is_null($carryCurrentDayEndHour))
        {
            $carryCurrentDayEndHourAndMinuteTime = strtotime($carryCurrentDayEndHourAndMinute);
        }

        $carryNextDayEndHour = $working[$carryNextDay['carryEndHourColumn']];
        $carryNextDayEndMinute = $working[$carryNextDay['carryEndMinuteColumn']];

        $carryNextDayEndHourAndMinute = sprintf('%02d:%02d', $carryNextDayEndHour, $carryNextDayEndMinute);

        $todayStartHour = $working[$startHourColumn];
        $todayEndHour = $working[$endHourColumn];
        $todayStartMinute = intval($working[$startMinuteColumn]);
        $todayEndMinute = intval($working[$endMinuteColumn]);

        $todayStartHourAndMinute = sprintf('%02d:%02d', $todayStartHour, $todayStartMinute);
        $todayEndHourAndMinute = sprintf('%02d:%02d', $todayEndHour, $todayEndMinute);

        return [
            'startTime' => $todayStartHourAndMinute,
            'endTime' => $todayEndHourAndMinute,
            'carryCurrentDayEndHourAndMinute' => $carryCurrentDayEndHourAndMinute  
        ];
    }

    private function getTimingMessage($working): array
    {
        $message = '';
        $nextOpenTime = 0;
        $currentDay = strtolower(date('l'));
        $nextOpenedDay = $this->getRestaurantNextOpenedDay($working);
        $workingTypeName = $this->getWorkingTypeName($working['working_type']);
        $futureOrderMsg = "";
        $futureOrderMsgForBasket = "";
        $defaultFutureOrderMsg = $this->translatorFactory->translate("Possibility of pre-ordering");
        $defaultFutureOrderMsgForBasket = $this->translatorFactory->translate("Pre-order your food online. Choose a time");

        if (!empty($nextOpenedDay))
        {
            $nextOpenTime = sprintf('%02d:%02d', $nextOpenedDay['startHour'], $nextOpenedDay['startMinute']); 
            if ($nextOpenedDay['currentDayDifference'] > 1)
            {
                $message = sprintf(", %s %s", $this->translatorFactory->translate('But we will open on'), $this->translatorFactory->translate($nextOpenedDay['day']));
            }
            else
            {
                $message = sprintf(", %s %s", $this->translatorFactory->translate('But we will open'), $this->translatorFactory->translate('tomorrow'));
                $futureOrderMsg = $defaultFutureOrderMsg;
                $futureOrderMsgForBasket = $defaultFutureOrderMsgForBasket;
            }
            
            if ($message && $nextOpenTime != '00:00')
            {
                $timeTwelveHrFormat = date('H:i', strtotime($nextOpenTime));
                $message = sprintf("%s %s %s %s", $message, $this->translatorFactory->translate('from'),  $this->translatorFactory->translate('time'), $timeTwelveHrFormat);
            }
        }
        else
        {
            $message = sprintf(", %s %s %s", $workingTypeName, $this->translatorFactory->translate('will be open from next'), $this->translatorFactory->translate($currentDay));
        }

        return [
            'message' => $message,
            'nextOpenTime' => $nextOpenTime,
            'futureOrderMsg' => $futureOrderMsg,
            'futureOrderMsgForBasket' => $futureOrderMsgForBasket
        ];
    }

    private function getRestaurantNextOpenedDay(array $working): array
    {
        $currentDay = strtolower(date('l'));
        for($i = 1; $i <= 7; $i++)
        {
            $dayName = strtolower(date("l", strtotime("+$i days")));
            $startHourColumn = $dayName;
            $endHourColumn = sprintf("%s_e", $dayName);
            $startMinuteColumn  = sprintf("%s_time", $dayName);
            $endMinuteColumn    = sprintf("%s_e_time", $dayName);

            if (array_key_exists($startHourColumn, $working) && array_key_exists($endHourColumn, $working) && !is_null($working[$startHourColumn]) && !is_null($working[$endHourColumn]) && $currentDay != $dayName)
            {
                return [
                    'startHour' => $working[$startHourColumn],
                    'endHour' => $working[$endHourColumn],
                    'startMinute' => $working[$startMinuteColumn],
                    'endMinute' => $working[$endMinuteColumn],
                    'day' => $dayName, 
                    'currentDayDifference' => $i
                ];
            }
        }

        return [];
    }

    private function getWorkingTypeName(int $workingType): string
    {
        $workingTypeName = [
            WORKING_TYPE_NORMAL_TIMING => $this->translatorFactory->translate('The restaurant'),
            WORKING_TYPE_TAKEAWAY_TIMINGS => $this->translatorFactory->translate('TakeAwayOtherName'),
            WORKING_TYPE_DELIVERY_TIMINGS => $this->translatorFactory->translate('DeliveryPrices')
        ];

        if (!array_key_exists($workingType, $workingTypeName))
        {
            throw new Exception('Invalid working type');
        }
        else
        {
            return $workingTypeName[$workingType];
        }
    }

    private function getOpeningHoursMessage(array $timeDiff): string
    {
        $message = '';
        if ($timeDiff['hour'] > 0)
        {
            $hourText = $timeDiff['hour'] == 1 ?  $this->translatorFactory->translate('hour') :  $this->translatorFactory->translate('hours');
            $message = sprintf('%s %s', $timeDiff['hour'], $hourText);
        }

        if ($timeDiff['minute'] > 0)
        {
            $minuteText = $timeDiff['minute'] == 1 ?  $this->translatorFactory->translate('minute') :  $this->translatorFactory->translate('minutes');
            $message = sprintf('%s %s %s', $message, $timeDiff['minute'], $minuteText);
        }

        return $message;
    }

    private function getArrayOfColumn(string $dayName, string $columnName): array
    {
        $result = [];

        if ($columnName == 'carry')
        {
            $result = [
                'carryEndHourColumn' => sprintf("c_%s_e", $dayName),
                'carryEndMinuteColumn' => sprintf("c_%s_e_time", $dayName),
                'carryStartHour' => sprintf("c_%s", $dayName),
                'carryStartMinute' => sprintf("c_%s_time", $dayName)
            ];
        }

        return $result;
    }
}

?>