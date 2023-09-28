<?php

namespace App\Http\Controllers\Restaurant;

use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Illuminate\Support\Facades\Validator;
use App\Models\Restaurant\Working;
use App\Http\Controllers\BaseResponse;
use Exception;
use Illuminate\Support\Facades\Log;

class SaveAndGetRestaurantTiming 
{
    private $datetimeHelper;
    
    private function setDateTimeHelper()
    {
        $this->datetimeHelper = new DatetimeHelper();
    }

    public function saveOrUpdateWorking($restaurantId, $working, $workingType = WORKING_TYPE_NORMAL_TIMING)
    {
        $this->setDateTimeHelper();
        $response = [];
        try
        {
            $validator = Validator::make(
                [
                    'restaurantId' => $restaurantId, 
                    'workingType' => $workingType
                ], 
                [
                    'restaurantId' => 'required|integer|min:1',
                    'workingType' => 'required|integer'
                ]
            );
            
            if ($validator->fails())
            {
                throw new Exception(sprintf("SaveAndGetRestaurantTiming.saveOrUpdateWorking error. %s ", $validator->errors()->first()));
            }
    
            
            $carryForward = $this->getCarryForward($working);
    
            foreach($working as $key => $value) 
            {
                if (empty($value) && $value != '0')
                {
                    $working[$key] = null;
                }
            }
    
            foreach($this->datetimeHelper->weekDays as $index => $day)
            {
                $startHour = sprintf('%s%s', $day, 'StartHour');
                $endHour = sprintf('%s%s', $day, 'EndHour');
                $startMinute = sprintf('%s%s', $day, 'StartMinute');
                $endMinute = sprintf('%s%s', $day, 'EndMinute');
                
                if ((empty($working[$startHour]) && $working[$startHour] != '0') || (empty($working[$endHour]) && $working[$endHour] != '0'))
                {
                    $working[$startHour] = null;
                    $working[$endHour] = null;
                    $working[$startMinute] = 0;
                    $working[$endMinute] = 0;
                }
            }
    
            $insertData = [
                'monday' => $working['mondayStartHour'],
                'monday_e' => $working['mondayEndHour'],
                'monday_time' => $working['mondayStartMinute'],
                'monday_e_time' => $working['mondayEndMinute'],
                
                'tuesday' => $working['tuesdayStartHour'],
                'tuesday_e' => $working['tuesdayEndHour'],
                'tuesday_time' => $working['tuesdayStartMinute'],
                'tuesday_e_time' => $working['tuesdayEndMinute'],
                
                'wednesday' => $working['wednesdayStartHour'],
                'wednesday_e' => $working['wednesdayEndHour'],
                'wednesday_time' => $working['wednesdayStartMinute'],
                'wednesday_e_time' => $working['wednesdayEndMinute'],
        
                'thursday' => $working['thursdayStartHour'],
                'thursday_e' => $working['thursdayEndHour'],
                'thursday_time' => $working['thursdayStartMinute'],
                'thursday_e_time' => $working['thursdayEndMinute'],
        
                'friday' => $working['fridayStartHour'],
                'friday_e' => $working['fridayEndHour'],
                'friday_time' => $working['fridayStartMinute'],
                'friday_e_time' => $working['fridayEndMinute'],
                
                'saturday' => $working['saturdayStartHour'],
                'saturday_e' => $working['saturdayEndHour'],
                'saturday_time' => $working['saturdayStartMinute'],
                'saturday_e_time' => $working['saturdayEndMinute'],
        
                'sunday' => $working['sundayStartHour'],
                'sunday_e' => $working['sundayEndHour'],
                'sunday_time' => $working['sundayStartMinute'],
                'sunday_e_time' => $working['sundayEndMinute'],
        
                'adv_id' => $restaurantId,
                'working_type' => $workingType
            ];

            if (!empty($carryForward))
            {
                $insertData = array_merge($insertData, $carryForward);
            }
    
            $condition = ['adv_id' => $restaurantId, 'working_type' => $workingType];
    
            $working = Working::updateOrCreate($condition, $insertData);

            $response = ['update' => 0, 'create' => 0];
        
            if(!$working->wasRecentlyCreated && $working->wasChanged())
            {
                $response['update'] = 1;
            }
            
            if($working->wasRecentlyCreated)
            {
                $response['create'] = 1;
            }
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is SaveAndGetRestaurantTiming@saveOrUpdateWorking Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));      

    }

    public function fetchTimings(int $workingType, int $restaurantId)
    {
        $this->setDateTimeHelper();
        $response = [];

        try
        {
            $validator = Validator::make(
                [
                    'restaurantId' => $restaurantId, 
                    'workingType' => $workingType
                ], 
                [
                    'restaurantId' => 'required|integer|min:1',
                    'workingType' => 'required|integer'
                ]
            );
            
            if ($validator->fails())
            {
                throw new Exception(sprintf("SaveAndGetRestaurantTiming fetchTimings fetch error. %s ", $validator->errors()->first()));
            }
    
            $condition = [
                ['adv_id', $restaurantId],
                ['working_type', $workingType]
            ];
    
            $working = Working::where($condition)->first();
            
            if (!empty($working))
            {
                $timing = $this->getResultWithCarryForward($working->toArray());
    
                foreach ($timing as $key => $value)
                {
                    if (!is_null($value) && (strpos($key, 'StartHour') != false || strpos($key, 'EndHour') != false))
                    {
                        $timing[$key] = str_pad((string)$timing[$key], 2, "0", STR_PAD_LEFT);
                    }
                }
    
                $response = $timing;
            }
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is SaveAndGetRestaurantTiming@fetchTimings Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }
        return $response;
    }

    private function getCarryForward(array $data): array
    {
        $carryTime = [];

        try
        {
            if (!empty($data))
            {
                $weekDays = $this->datetimeHelper->weekDays;
                $weekDaysCount = count($weekDays);
    
                foreach($weekDays as $index => $weekDay)
                {
                    $startHourColumn = sprintf("%sStartHour", $weekDay);
                    $endHourColumn = sprintf("%sEndHour", $weekDay);
                    $startMinuteColumn = sprintf("%sStartMinute", $weekDay);
                    $endMinuteColumn = sprintf("%sEndMinute", $weekDay);
    
                    $startMinute = $data[$startMinuteColumn];
                    $endMinute = $data[$endMinuteColumn];
    
                    $nextWeekDayName = ($weekDaysCount === ($index + 1)) ? $weekDays[0] : $weekDays[$index + 1];
    
                    $weekDayEndHour = sprintf('%s_e', $weekDay);
                    $weekDayEndMinute = sprintf('%s_e_time', $weekDay);
                    
                    if (!empty($data[$startHourColumn]) && !empty($data[$endHourColumn]))
                    {
                        $startHour = intval($data[$startHourColumn]);
                        $endHour = intval($data[$endHourColumn]);
    
                        $carryStartHour = sprintf("c_%s", $nextWeekDayName);
                        $carryEndHour = sprintf("c_%s_e", $nextWeekDayName);
                        $carryStartMinute = sprintf("c_%s_time", $nextWeekDayName);
                        $carryEndMinute = sprintf("c_%s_e_time", $nextWeekDayName);
                        
                        $carryTime[$carryStartHour] = $carryTime[$carryEndHour] = $carryTime[$carryStartMinute] = $carryTime[$carryEndMinute] = null;
    
                        if ($startHour > $endHour)
                        {
                            $carryTime[$carryStartHour] = 0;
                            $carryTime[$carryStartMinute] = 0;
                            $carryTime[$carryEndHour] = $endHour;
                            $carryTime[$carryEndMinute] = $endMinute;
    
                            $carryTime[$weekDayEndHour] = 24;
                            $carryTime[$weekDayEndMinute] = 0;
                        }
                    }
                }
            }
        }
        catch(Exception $e)
        {
            Log::error(sprintf("Error found is SaveAndGetRestaurantTiming@getCarryForward Message is %s, Stack Trace %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return $carryTime;
    }

    private function getResultWithCarryForward(array $working): array
    {
        $result = [];
        if (!empty($working))
        {
            $weekDays = $this->datetimeHelper->weekDays;
            $weekDaysCount = count($weekDays);

            foreach($weekDays as $index => $weekDay)
            {
                $startHourKey = sprintf("%sStartHour", $weekDay);
                $endHourKey = sprintf("%sEndHour", $weekDay);
                $startMinuteKey = sprintf("%sStartMinute", $weekDay);
                $endMinuteKey = sprintf("%sEndMinute", $weekDay);

                $startHourColumn = $weekDay;
                $endHourColumn = sprintf("%s_e", $weekDay);
                $startMinuteColumn = sprintf("%s_time", $weekDay);
                $endMinuteColumn = sprintf("%s_e_time", $weekDay);

                $nextWeekDayName = ($weekDaysCount === ($index + 1)) ? $weekDays[0] : $weekDays[$index + 1];

                $carryEndHour = sprintf("c_%s_e", $nextWeekDayName);
                $carryEndMinute = sprintf("c_%s_e_time", $nextWeekDayName);

                $result[$startHourKey] = $working[$startHourColumn];
                $result[$startMinuteKey] = $working[$startMinuteColumn];
                $result[$endHourKey] = $working[$endHourColumn];
                $result[$endMinuteKey] = $working[$endMinuteColumn];

                if (!is_null($working[$carryEndHour]))
                {
                    $result[$endHourKey] = $working[$carryEndHour];
                    $result[$endMinuteKey] = $working[$carryEndMinute];
                }
            }
        }

        return $result;
    }
    
}
