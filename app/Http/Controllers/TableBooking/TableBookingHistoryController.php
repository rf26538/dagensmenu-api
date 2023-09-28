<?php

namespace App\Http\Controllers\TableBooking;

use App\Models\TableBooking\TableBooking;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Exception;

class TableBookingHistoryController extends Controller
{

    public function fetchBookingsForRestaurant(Request $request, int $restaurantId)
    {
        $startDate = $request->startDate;
        $endDate = $request->endDate;
       
        $validator = Validator::make(['restaurantId' => $restaurantId, 'startDate' => $startDate, 'endDate' => $endDate], ['restaurantId' => 'required|int|min:1', 'startDate' => 'required|date_format:Y-m-d', 'endDate' => 'required|date_format:Y-m-d']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("TableBookingHistoryController fetchBookingsForRestaurant error. %s ", $validator->errors()->first()));
        }

        if($endDate < $startDate)
        {
            throw new Exception('The start date must be smaller than the end date');
        }

        $condition = [
            ['restaurantId', $restaurantId]
        ];

        $results = TableBooking::select(['numberOfGuests', 'dateOfBooking', 'timeOfBooking', 'guestName',
        'guestTelephoneNumber', 'guestEmailAddress', 'bookingStatus', 'bookingUniqueId', DB::raw('FROM_UNIXTIME(createdOn, "%Y-%m-%d") as createdOn')])->where($condition)->whereBetween('dateOfBooking', [$startDate, $endDate])->orderBy('dateOfBooking', 'ASC')->get()->toArray();
        
        return response()->json(new BaseResponse(true, null, $results));
    }
}