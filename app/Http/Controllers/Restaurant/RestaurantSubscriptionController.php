<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\BaseResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Restaurant\Advertisement;
use App\Models\Restaurant\RestaurantSubscription;
use App\Models\Restaurant\RestaurantStripeDetail;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Language\TranslatorFactory;
use Illuminate\Support\Facades\Log;
use Auth;
use Exception;
use App\Models\Payment\PaymentDetails;

class RestaurantSubscriptionController extends Controller
{
    private $datetimeHelpers;
    private $ipHelpers;
    private $translatorFactory;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, TranslatorFactory $translatorFactory) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
    }

    public function index()
    {
        //
    }

    public function fetch(Request $request)
    {
        $this->validate($request, [
            'ad-id' => 'required|integer'
        ]);


        $adId = $request->input('ad-id');
        $row = RestaurantSubscription::where('restaurantId', $adId)->first();
        $row['advertisementDetails'] = Advertisement::select('title', 'advertisementTrialPeriodExpiryDate', 'tableBookingTrialExpiryDate')->where('id', $adId)->first();

        $row['hasStripeDetails'] = RestaurantStripeDetail::where('restaurantId', $adId)->where('status', 1)->count();
        $row['title'] = $row['advertisementDetails']->title;
        $row['advertisementTrialExpiry'] = null;
        $row['tableBookingTrialExpiry'] = null;

        if(!empty($row['advertisementDetails']->advertisementTrialPeriodExpiryDate))
        {
            $row['advertisementTrialExpiry'] = gmdate("d M Y", $row['advertisementDetails']->advertisementTrialPeriodExpiryDate);
        }
        if(!empty($row['advertisementDetails']->tableBookingTrialExpiryDate))
        {
            $row['tableBookingTrialExpiry'] = gmdate("d M Y", $row['advertisementDetails']->tableBookingTrialExpiryDate);
        }


        return $row;
    }

    public function updateTableBookingPayment(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'ad-id' => 'required|integer',
            'tableBookingPaymentType' => 'required',
            'tableBookingPaymentDays' => 'required_if:tableBookingPaymentType,3|integer',
            'tableBookingPaymentPrice' => 'required|numeric',
            'tableBookingPaymentTaxType' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['stat'=>false, 'errors'=>$validator->errors()]);
        }

        $today = time();
        $adId = $request->input('ad-id');

        $data['tableBookingPaymentType'] = $request->input('tableBookingPaymentType');
        $data['tableBookingPaymentDays'] = (!empty($request->input('tableBookingPaymentDays')) ? (int) $request->input('tableBookingPaymentDays') : null);
        $data['tableBookingPaymentPrice'] = (!empty($request->input('tableBookingPaymentPrice')) ? (float) $request->input('tableBookingPaymentPrice') : null);
        $data['tableBookingPaymentTaxType'] = $request->input('tableBookingPaymentTaxType');
        
        $data['restaurantId'] = $request->input('ad-id');
        $data['userId'] = Auth::id();
        $data['isActive'] = 1;
        $data['ip'] = $this->ipHelpers->clientIpAsLong();
        $data['createdOn'] = $today;

        RestaurantSubscription::updateOrInsert(['restaurantId'=>$adId], $data);

        $restaurantSubscription = RestaurantSubscription::where('restaurantId', $adId)->first();

        $advertisement = Advertisement::find($adId);
        if(empty($advertisement->tableBookingTrialStartDate))
        {
            // No trial period exists, start taking the payments
            $advertisement->tableBookingExpiryDate = $today;
            $advertisement->tableBookingStartDate = $today;
            $advertisement->enableTableBooking = 1;
            $advertisement->save();
        }
        else
        {
            // Trial period exists, add expiry so payments can not be taken straight away. Add expiry and wait for trial period to be over
            $advertisement->tableBookingExpiryDate = $today;
            $advertisement->enableTableBooking = 1;
            $advertisement->save();
        }
        return response()->json(['stat'=>true]);
    }

    public function updateTableBookingTrial(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ad-id' => 'required|integer',
            'tableBookingTrialType' => 'required',
            'tableBookingTrialValue' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['stat'=>false, 'errors'=>$validator->errors()]);
        }

        $today = time();
        $adId = $request->input('ad-id');

        $data['tableBookingTrialPeriodType'] = $request->input('tableBookingTrialType');
        $data['tableBookingTrialPeriodValue'] = (!empty($request->input('tableBookingTrialValue')) ? (int) $request->input('tableBookingTrialValue') : null);
        
        $data['restaurantId'] = $request->input('ad-id');
        $data['userId'] = Auth::id();
        $data['isActive'] = 1;
        $data['ip'] = $this->ipHelpers->clientIpAsLong();
        $data['createdOn'] = $today;

        RestaurantSubscription::updateOrInsert(['restaurantId'=>$adId], $data);

        $tableBookingTrialStartDate = $today;
        $tableBookingTrialExpiryDate = null;
        /* Check if table booking trial period is by date */
        if($data['tableBookingTrialPeriodType'] == 3)
        {
            $tableBookingTrialExpiryDate = $today + $data['tableBookingTrialPeriodValue'] * (24 * 60 * 60);
        }

        $advertisement = Advertisement::find($adId);
        $advertisement->tableBookingTrialStartDate = $tableBookingTrialStartDate;
        $advertisement->tableBookingTrialExpiryDate = $tableBookingTrialExpiryDate;
        $advertisement->tableBookingStartDate = null;
        $advertisement->tableBookingExpiryDate = null;
        $advertisement->enableTableBooking = 1;
        $advertisement->save();

        return response()->json(['stat'=>true]);
    }

    public function updateAdvertisementPayment(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'ad-id' => 'required|integer',
            'advertisementProfileType' => 'required|integer',
            'advertisementPaymentDays' => 'required|integer',
            'advertisementPaymentPrice' => 'required|numeric',
            'advertisementPaymentTaxType' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['stat'=>false, 'errors'=>$validator->errors()]);
        }

        $today = time();
        $adId = $request->input('ad-id');
        $data['advertisementDurationInDays'] = (!empty($request->input('advertisementPaymentDays')) ? (int) $request->input('advertisementPaymentDays') : null);
        $data['advertisementPrice'] = (!empty($request->input('advertisementPaymentDays')) ? (float) $request->input('advertisementPaymentPrice') : null);
        $data['advertisementTaxType'] = $request->input('advertisementPaymentTaxType');
        $data['advertisementProfileType'] = $request->input('advertisementProfileType');

        $data['restaurantId'] = $request->input('ad-id');
        $data['userId'] = Auth::id();
        $data['isActive'] = 1;
        $data['ip'] = $this->ipHelpers->clientIpAsLong();
        $data['createdOn'] = $today;

        RestaurantSubscription::updateOrInsert(['restaurantId'=>$adId], $data);

        $advertisement = Advertisement::find($adId);
        if(is_null($advertisement->advertisementTrialPeriodExpiryDate) and is_null($advertisement->advertisementTrialPeriodExpiryDate))
        {
            // No trial period. Ending the trial period so payment can be taken straight away
            $advertisement->advertisementTrialPeriodStartDate = $today;
            $advertisement->advertisementTrialPeriodExpiryDate = $today;
            $advertisement->advertisementPaymentStartDate = null;
            $advertisement->advertisementPaymentExpiryDate = null;
            $advertisement->profileType = $request->input('advertisementProfileType');
            $advertisement->save();    
        }
        return response()->json(['stat'=>true]);
    }

    public function updateAdvertisementTrialPeriod(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'ad-id' => 'required|integer',
            'advertisementProfileType' => 'required|integer',
            'advertisementTrialPeriodDays' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['stat'=>false, 'errors'=>$validator->errors()]);
        }

        $today = time();
        $adId = $request->input('ad-id');

        $data['advertisementTrialPeriod'] = (!empty($request->input('advertisementTrialPeriodDays')) ? (int) $request->input('advertisementTrialPeriodDays') : null);

        $data['restaurantId'] = $request->input('ad-id');
        $data['userId'] = Auth::id();
        $data['isActive'] = 1;
        $data['ip'] = $this->ipHelpers->clientIpAsLong();
        $data['advertisementProfileType'] = $request->input('advertisementProfileType');
        $data['createdOn'] = $today;

        RestaurantSubscription::updateOrInsert(['restaurantId'=>$adId], $data);

        $advertisement = Advertisement::find($adId);
        $advertisementExpiry = $today + $data['advertisementTrialPeriod'] * (24 * 60 * 60);
        $advertisement->advertisementTrialPeriodStartDate = $today;
        $advertisement->advertisementTrialPeriodExpiryDate = $advertisementExpiry;
        $advertisement->advertisementPaymentStartDate = null;
        $advertisement->advertisementPaymentExpiryDate = null;
        $advertisement->profileType = $request->input('advertisementProfileType');
        $advertisement->save();

        return response()->json(['stat'=>true]);
    }

    public function updatePaymentSubscriptionStatus(Request $request)
    {

        $rules = [
            'ad-id' => 'required|integer|min:1',
            'isActive' => 'required|integer|between:0,1',
            'paymentStatus' => 'required|integer|between:0,1',
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new Exception(sprintf("RestaurantSubscriptionController.updatePaymentSubscriptionStatus %s.", $validator->errors()->first()));
        }

        $adId = $request->input('ad-id');
        $isActiveValue = $request->input('isActive');
        $paymentStatus = $request->input('paymentStatus');
        $msg = '';
        $stat = true;

        if($isActiveValue == PAYMENT_STATUS_INACTIVE)
        {
            if($paymentStatus == DO_NOT_TAKE_OLD_PAYMENT)
            {
                $paymentExpiryDate = Advertisement::select('advertisementPaymentExpiryDate')->where('id', $adId)->first();
                $durationDays = RestaurantSubscription::select('advertisementDurationInDays')->where('restaurantId', $adId)->first();
                if(!empty($paymentExpiryDate->advertisementPaymentExpiryDate) && !empty($durationDays->advertisementDurationInDays))
                {   
                    Advertisement::where(['id'=>$adId])->update(['advertisementPaymentExpiryDate' => $this->datetimeHelpers->getCurrentUtcTimeStamp()]);
                }
            }

            RestaurantSubscription::where(['restaurantId'=>$adId])->update(['isActive'=> PAYMENT_STATUS_ACTIVE ]);
            $msg = $this->translatorFactory->translate("Subscription Active Successfully");
        }
        else if($isActiveValue == PAYMENT_STATUS_ACTIVE)
        {
            RestaurantSubscription::where(['restaurantId'=>$adId])->update(['isActive'=> PAYMENT_STATUS_INACTIVE ]);

            $msg = $this->translatorFactory->translate('Subscription Inactive Successfully');
        }
        
        return response()->json(new BaseResponse($stat, null, $msg));
    }

    public function fetchDueSubscriptionAmount(int $restaurantId)
    {   

        $isSuccess = false;
        $finalAmount = 0;

        try
        {
            $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

            if ($validator->fails())
            {
                throw new Exception('fetchDueSubscriptionAmount fetchDueSubscriptionAmount error %s.', $validator->errors()->first());
            }
            
            $currentTimeStamp = $this->datetimeHelpers->getCurrentUtcTimeStamp();
            $condition = [
                ['isActive', STATUS_ACTIVE],
                ['restaurantId', $restaurantId]
            ];

            $restaurantSubscription = RestaurantSubscription::select('*')->where($condition)->first();

            if(!empty($restaurantSubscription))
            {
                $tax = 0;
                $subscriptionAmount = 0;

                if($restaurantSubscription['advertisementTaxType'] == TAX_ON_AMOUNT) 
                {
                    $tax = (TAX_PERCENT/100 * $restaurantSubscription['advertisementPrice']);
                }
                $subscriptionAmount = $restaurantSubscription['advertisementPrice'] + $tax;

                $advertisement = Advertisement::select(['id','profileType', 'advertisementPaymentStartDate', 'advertisementPaymentExpiryDate'])->where([
                        ['id', $restaurantId],
                        ['status', STATUS_ACTIVE],
                    ])->first();

                if(is_null($advertisement['advertisementPaymentExpiryDate']) || $advertisement['advertisementPaymentExpiryDate'] <= $currentTimeStamp)
                {
                    $finalAmount = $subscriptionAmount;
                }
                else
                {
                    $finalAmount = RESTAURANT_CARD_REGISTER_PAYMENT_AMOUNT;
                } 
                
            }
            else
            {
                $finalAmount = RESTAURANT_CARD_REGISTER_PAYMENT_AMOUNT;
            } 
            
            $isSuccess = true;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in RestaurantSubscriptionController@fetchDueSubscriptionAmount error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        $response = number_format((float)($finalAmount), 2);

        return response()->json(new BaseResponse($isSuccess, null, $response));
    }

    public function updateAdvertisementPlan(Request $request, int $restaurantId)
    {
        $rules = [
            'restaurantId' => 'required|integer',
            'restaurantSubscriptionPlan' => 'required|integer',
        ];

        $requestAll = $request->all();
        $requestAll['restaurantId'] = $restaurantId;
        $validator = Validator::make($requestAll, $rules); 

        if ($validator->fails()) {
            throw new Exception(sprintf("RestaurantSubscriptionController@updateAdvertisementPlan %s.", $validator->errors()->first()));
        }

        $restaurantSubscriptionPlan = $request->input('restaurantSubscriptionPlan');

        $stat = true;
        $planPrice = 0;

        switch($restaurantSubscriptionPlan)
        {
            case RESTAURANT_PAYMENT_PLAN_ONE:
                    $planPrice = RESTAURANT_PAYMENT_PLAN_ONE_PRICE;
                break;
            case RESTAURANT_PAYMENT_PLAN_TWO:
                    $planPrice = RESTAURANT_PAYMENT_PLAN_TWO_PRICE;     
                break;
            case RESTAURANT_PAYMENT_PLAN_THREE:
                    $planPrice = RESTAURANT_PAYMENT_PLAN_THREE_PRICE;
                break;
            default:
        }

        $userId = Auth::id();
        $today = time();
        $ip = $this->ipHelpers->clientIpAsLong();

        $updateOrInsertData = [
            'advertisementTaxType' => TAX_ON_AMOUNT,
            'advertisementDurationInDays' => RESTAURANT_PAYMENT_DAYS,
            'advertisementPrice' => $planPrice,
            'userId' => $userId,
            'ip' => $ip,
            'advertisementSubscriptionPlan' => $restaurantSubscriptionPlan,
            'createdOn' => $today,
            'restaurantId' => $restaurantId,
            'isActive' => STATUS_ACTIVE

        ];  

        RestaurantSubscription::updateOrInsert([
            'restaurantId' => $restaurantId
        ], $updateOrInsertData);

        $condition = [
            ['restaurantId', $restaurantId],
            ['status', PAYMENT_STATUS_ACTIVE]
        ];

        $response = [];
        $restaurantStripeDetails = RestaurantStripeDetail::where($condition)->get()->toArray();

        if (!empty($restaurantStripeDetails))
        {
            $condition = [
                ['ad_id', $restaurantId],
                ['payment_status', PAYMENT_STATUS_AUTOMATED_CAPTURE_SUCCESSFUL]
            ];

            $lastPaymentSuccess = Paymentdetails::where($condition)->get()->toarray();

            if(!empty($lastPaymentSuccess))
            {
                $response['message'] = sprintf('You have successfully subscribied to the plan %s', $restaurantSubscriptionPlan);
            }   
        }
        else
        {
            $response['redirectUrl'] = sprintf("%s/%s?ad-id=%s", SITE_BASE_URL, PAGE_NEW_STRIPE_PAYMENT_CARD, $restaurantId);
        }

        return response()->json(new BaseResponse($stat, null, $response));    
    }

    public function fetchAdvertisementSubscriptionPlan(int $restaurantId)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

        if ($validator->fails())
        {
            throw new Exception('RestaurantSubscriptionController@fetchAdvertisementSubscriptionPlan error %s.', $validator->errors()->first());
        }

        $condition = [
            ['restaurantId', $restaurantId]
        ];

        $fetchSubscription = RestaurantSubscription::where($condition)->whereNotNull('advertisementSubscriptionPlan')->first();
        $response = [];

        if(!empty($fetchSubscription))
        {
            $msg = '';

            if($fetchSubscription['advertisementSubscriptionPlan'] == RESTAURANT_PAYMENT_PLAN_ONE)
            {
                $msg = "Subscribed to Plan One";
            }
            elseif($fetchSubscription['advertisementSubscriptionPlan'] == RESTAURANT_PAYMENT_PLAN_TWO)
            {
                $msg = "Subscribed to Plan Two";
            }  
            elseif($fetchSubscription['advertisementSubscriptionPlan'] == RESTAURANT_PAYMENT_PLAN_THREE)
            {
                $msg = "Subscribed to Plan Three";
            }

            $response['msg'] = $msg;
            $response['subscribedPlan'] = $fetchSubscription['advertisementSubscriptionPlan'];
        }
        return response()->json(new BaseResponse(true, null, $response));
    }

    public function destroy($id)
    {
        //
    }
}
