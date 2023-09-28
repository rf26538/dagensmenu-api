<?php

namespace App\Http\Controllers\TableBooking;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Helpers\StringHelper;
use App\Shared\EatCommon\Link\Links;
use App\Models\TableBooking\TableBooking;
use App\Models\Restaurant\Advertisement;
use Illuminate\Support\Facades\Log;
use App\Shared\EatCommon\Language\TranslatorFactory;
use Illuminate\Support\Facades\Validator;
use Auth;
use Illuminate\Http\Request;
use Exception;

class TableBookingController extends Controller
{
    private $datetimeHelpers;
    private $ipHelpers;
    private $stringHelper;
    private $links;
    private $translatorFactory;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, Links $links, StringHelper  $stringHelper, TranslatorFactory $translatorFactory) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->stringHelper = $stringHelper;
        $this->links = $links;
        $this->translatorFactory = $translatorFactory::getTranslator();
    }

    public function fetchByTelephone(string $telephoneNumber)
    {
        $telephoneNumber = str_replace('+45', '', $telephoneNumber);
        $telephoneNumber = str_replace('0045', '', $telephoneNumber);
        $telephoneNumber = str_replace(' ', '', $telephoneNumber);

        $response = TableBooking::where('guestTelephoneNumber', $telephoneNumber)->whereIn('bookingStatus', [TABLE_BOOKING_STATUS_CREATED, TABLE_BOOKING_STATUS_ACCEPTED_BY_RESTAURANT])->whereDate('dateOfBooking', '>=', date("Y-m-d"))->
            orderBy('dateOfBooking', 'asc')->take(1)->first(['bookingUniqueId', 'numberOfGuests', 'dateOfBooking', 'timeOfBooking', 'guestName']);

        if ($response) {
            $response['timeOfBooking'] = date('H:i', strtotime($response['timeOfBooking']));
            $response['dateOfBooking'] = $this->datetimeHelpers->formatDateForTableBooking($response['dateOfBooking']);
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function saveTableBooking(Request $request)
    {
        $rules = array(
            'restaurantId' => 'required|integer|min:1',
            'numberOfGuests' => 'required|integer|min:1',
            'dateOfBooking' => 'required|date:Y-m-d',
            'timeOfBooking' => 'required|date_format:H:i',
            'guestName' => 'required|string',
            'guestTelephoneNumber' => 'required|integer',
            'guestEmailAddress' => 'required|regex:/^[ÆØÅæøåA-Za-z0-9._%+-]+@(?:[ÆØÅæøåA-Za-z0-9-]+\.)+[A-Za-z]{2,6}$/m',
            'guestTelephoneCode' => 'required|string'
        );

        $validator = Validator::make($request->post(), $rules);

        if ($validator->fails())
        {
            Log::critical(sprintf("Error is - %s , Table booking details - %s", $validator->errors()->first(), json_encode($request->all())));
            return response()->json(new BaseResponse(false, $this->translatorFactory->translate('Some technical issue has occurred with your request, we are fixing it. Don"t worry, Dagens Menu is always ready to help'), null));
        }

        $clientIp = $this->ipHelpers->clientIpAsLong();
        $lastHour = $this->datetimeHelpers->getCurrentUtcTimeStamp() - (24*60*60);
        $redirectUrl = [];
        $ipCondiiton = [
            ['ip', $clientIp],
            ['createdOn', '>', $lastHour]
        ];

        $ipCount = TableBooking::where($ipCondiiton)->get()->count();

        if(env("APP_ENV") != "production" || $ipCount < 5)
        {
            $restaurantId =  $request->post('restaurantId');
            $checkEnableTableBooking = Advertisement::select('enableTableBooking')->where('id', $restaurantId)->first();

            if(!empty($checkEnableTableBooking ) && $checkEnableTableBooking->enableTableBooking)
            {

                $tableBooking = new TableBooking();
                $tableBooking->restaurantId = $restaurantId;
                $tableBooking->numberOfGuests = $request->post('numberOfGuests');
                $tableBooking->dateOfBooking = $request->post('dateOfBooking');
                $tableBooking->timeOfBooking = $request->post('timeOfBooking');
                $tableBooking->guestName = $request->post('guestName');
                $tableBooking->countryCode = $request->post('guestTelephoneCode');

                $telephoneNumber = str_replace('+45', '', $request->post("guestTelephoneNumber"));
				$telephoneNumber = str_replace('0045', '', $telephoneNumber);
                $telephoneNumber = str_replace(' ', '', $telephoneNumber);

                $tableBooking->guestTelephoneNumber = $telephoneNumber;

                $tableBooking->guestEmailAddress = $request->post('guestEmailAddress');
                $tableBooking->bookingStatus = TABLE_BOOKING_STATUS_CREATED;
                $tableBooking->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $tableBooking->ip = $clientIp;

                if(Auth::check())
                {
                    $tableBooking->userId = Auth::id();
                }
                $randomHash = $this->stringHelper->generateRandomCharacters(8);
                $tableBooking->bookingUniqueId = $randomHash;
                $timestampOfBooking = strtotime($tableBooking->dateOfBooking." ".$tableBooking->timeOfBooking);
                $tableBooking->timestampOfBooking = $timestampOfBooking;
                $tableBooking->save();

                if(!empty(Auth::id()))
                {
                    $redirectUrl['redirectUrl'] = $this->links->createUrl(PAGE_USER_TABLE_BOOKINGS, array());
                }
                else
                {
                    $redirectUrl['redirectUrl'] = $this->links->createUrl(PAGE_USER_LOGIN_REGISTER, array(QUERY_LOGIN_ACTION_TYPE => LOGIN_ACTION_TYPE_TABLE_BOOKING, QUERY_LOGIN_ACTION_ID => $tableBooking->bookingUniqueId));
                }
            }
            else
            {
                // Dont show error message to hackers
				if(env("APP_ENV") != "production")
				{
					throw new Exception("Invalid booking");
				}
				else
				{
					Log::error(sprintf("Table booking Invalid booking as restaurant doesn't have table booking. Restaurant id:%s ip:%s", $restaurantId, $clientIp));
				}
            }
        }
        else
        {
            Log::emergency(sprintf("Table booking ip count is : %s for ip : %s", $ipCount, $clientIp));
        }

        return response()->json(new BaseResponse(true, null, $redirectUrl));
    }
}
