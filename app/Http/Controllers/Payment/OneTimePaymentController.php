<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\StringHelper;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Models\Payment\OneTimePayment;
use Illuminate\Support\Facades\Log;
use App\Models\Payment\PaymentDetails;
use App\Models\Restaurant\Advertisement;
use App\Shared\EatCommon\Payment\StripeClient;
use App\Shared\EatCommon\Sms\Sms;


class OneTimePaymentController extends Controller {

    private $datetimeHelpers;
    private $ipHelpers;
    private $translatorFactory;
    private $stringHelper;
    private $stripeClient;
    private $sms;

    public function __construct(Request $request, IPHelpers $ipHelpers, DatetimeHelper $datetimeHelpers, TranslatorFactory $translatorFactory, StringHelper $stringHelper, StripeClient $stripeClient, Sms $sms)
    {
        $this->request = $request;
        $this->ipHelpers = $ipHelpers;
        $this->datetimeHelpers = $datetimeHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->stringHelper = $stringHelper;
        $this->stripe = $stripeClient;
        $this->sms = $sms;
    }

    public function save()
    {
        $isSuccess = false;
        $response = $responseMsg = null;

        try
        {
            
            $validator = Validator::make([
                'advId' => $this->request->post('advId'),
                'durationInDays' => $this->request->post('durationInDays'),
                'amount' => $this->request->post('amount'),
                
            ], [
                'advId' => 'required|int|min:1',
                'durationInDays' => 'required|int|min:1',
                'amount' => 'required|int|min:1',
            ]);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            $postData = $this->request->post();

            $oneTimePayment = new OneTimePayment();

            $oneTimePayment->userId = Auth::id();
            $oneTimePayment->adId = $postData['advId'];
            $oneTimePayment->durationInDays = $postData['durationInDays'];
            $oneTimePayment->amount = $postData['amount'];
            $oneTimePayment->uniqueId = $this->stringHelper->generateRandomCharacters(15);
            $oneTimePayment->paymentStatus = 0;
            $oneTimePayment->ip = $this->ipHelpers->clientIpAsLong();
            $oneTimePayment->createdOn = $this->datetimeHelpers->getCurrentTimeStamp();
            $oneTimePayment->save();

            $response = $oneTimePayment->uniqueId;
            $isSuccess = true;
            $responseMsg = "";
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in OneTimePaymentController@save error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }
        
        return response()->json(new BaseResponse($isSuccess, $responseMsg, $response));
    }

    public function sendSms(Request $request)
    {
        $isSuccess = false;
        $response = $responseMsg = null;

        try
        {
            $rules = array(
                'restaurantId' => 'required|int|min:1',
                'phoneNumber' => 'required|int',
                'message' => 'required',
            );
            
            $data = $request->all();
            
            $validator = Validator::make($data, $rules);

            if ($validator->fails())
            {
                throw new Exception(sprintf("OneTimePaymentController sendSms error. %s ", $validator->errors()->first()));
            }

            $restaurantId = $data['restaurantId'];
            $phoneNumber = $data['phoneNumber'];
            $message = $data['message'];

			$this->sms->SendSms(SMS_SENDER, $phoneNumber, $message);
            $isSuccess = true;
            $response = $this->translatorFactory->translate('Congratulations! Sms sent successfully to the restaurant with a payment link');
            
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in OneTimePaymentController@sendSms error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }
        return response()->json(new BaseResponse($isSuccess, $responseMsg, $response));
        
    }

    public function paymentIntent(string $uniqueId, Request $request)
    {
        $isSuccess = false;
        $response = $responseMsg = null;

        try
        {
            $validator = Validator::make([
                'uniqueId' => $this->request->post('uniqueId'),
            ], [
                'uniqueId' => 'required|string',
            ]);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }
            
            $oneTimePayment = OneTimePayment::select('adId', 'amount', 'durationInDays')->where('uniqueId', $uniqueId)->first();
            if(!empty($oneTimePayment)) 
            {
                $advertisement = Advertisement::select(['title', 'extra', 'postcode', 'city', 'country', 'summary'])->where('id', $oneTimePayment->adId)->first();
            }
            if(empty($advertisement->country))
            {
                $advertisement->country = 'DK';
            }
            $intentExtraData = [
                'title' =>   $advertisement->title,
                'address' => $advertisement['extra'],
                'postCode' => $advertisement->postcode,
                'city' => $advertisement->city,
                'country' => $advertisement->country,
                'amount' => $oneTimePayment->amount * PAYMENT_CURRENCY_MULTIPLIER,
                'description' => $advertisement['summary'],
            ];
            
            $intent = $this->stripe->createOneTimePaymentIntent($intentExtraData);
            $isSuccess = true;
            $response = $intent;
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in OneTimePaymentController@paymentIntent error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse($isSuccess, $responseMsg, $response));
        
    }

    public function fetchRestaurantName(string $uniqueId, Request $request) 
    {
        $isSuccess = false;
        $response = $responseMsg = null;

        try
        {
            $validator = Validator::make([
                'uniqueId' => $this->request->post('uniqueId'),
            ], [
                'uniqueId' => 'required|string',
            ]);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            $oneTimePayment = OneTimePayment::select('adId', 'paymentStatus', 'durationInDays')->where('uniqueId', $uniqueId)->first();
            if(!empty($oneTimePayment)) 
            {
                $advertisement = Advertisement::select(['title'])->where('id', $oneTimePayment->adId)->first();
            }
            $isSuccess = true;
            $response = $advertisement;
            
        }
        catch(Exception $e)
        {

            Log::critical(sprintf("Error found in OneTimePaymentController@fetchRestaurantName error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }
        return response()->json(new BaseResponse($isSuccess, $responseMsg, $response));
    }

    public function transaction(string $uniqueId, Request $request)
    {
        $isSuccess = false;
        $response = $responseMsg = null;

        try
        {

            $validator = Validator::make([
                'uniqueId' => $this->request->post('uniqueId'),
            ], [
                'uniqueId' => 'required|string',
            ]);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            $paymentResponse = $request->all();
            $stripePaymentResponse = json_encode($paymentResponse);
            $stripeResponse = $paymentResponse['stripeSource']['paymentIntent'];
            $paymentStatus = '';
            $stripeFailedReason = '';
            $paymentStatusMsg = '';

            $amount = $paymentResponse['stripeSource']['paymentIntent']['amount'];
            $net = $amount/1.25;
            $moms = $amount - $net;
            $amount = $net;

            if(!empty($paymentResponse) && isset($stripeResponse))
            {
                if($stripeResponse['status'] == PAYMENT_INTENT_STATUS_SUCCEEDED)
                {
                    $paymentStatus = PAYMENT_STATUS_AUTOMATED_CAPTURE_SUCCESSFUL;
                    $paymentStatusMsg = $this->translatorFactory->translate('Congratulations! Your payment has been done successfully');
                }
                else
                {
                    $paymentStatus = PAYMENT_STATUS_AUTOMATED_CAPTURE_UNSUCCESSFUL_FOR_COLLECT_PAYMENT;
                    $stripeFailedReason = $stripeResponse['status'];
                    $paymentStatusMsg = $this->translatorFactory->translate('Your payment is failed. Please try again');
                }

                $oneTimePayment = OneTimePayment::select('adId', 'paymentStatus', 'durationInDays')->where('uniqueId', $uniqueId)->first();
                if(!empty($oneTimePayment)) 
                {
                    $advertisement = Advertisement::select(['author_id', 'id', 'profileType'])->where('id', $oneTimePayment->adId)->first();
                }

                $paymentDataSave = [
                    'order_id' => uniqid(),
                    'ad_id' => $advertisement->id,
                    'user_id' => $advertisement->author_id,
                    'amount' => floatval($amount),
                    'moms' => floatval($moms),
                    'payment_status' => $paymentStatus,
                    'payment_type' => PAYMENT_TYPE_ADVERTISEMENT_SUBSCRIPTION,
                    'payment_response_date' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                    'payment_request_date' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                    'payment_start_date' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                    'payment_end_date' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                    'profile_type' => $advertisement->profileType,
                    'durationInDays' => $oneTimePayment->durationInDays,
                    'failedReason' => $stripeFailedReason,
                    'stripePaymentType' => PAYMENT_STRIPE_INTENT_TYPE,
                    'paymentIntentClientSecretId' => $stripeResponse['client_secret'],
                    'request_data' => $stripePaymentResponse,
                    'response_data' => $stripePaymentResponse,

                ];
                $updateData = [
                    'paymentIntentId' => $stripeResponse['id'],
                    'stripePaymentMethod' => $stripeResponse['capture_method'],
                    'paymentStatus' => $paymentStatus,
                    'lastPaymentSuccessfulOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                    'updatedOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                ];

                PaymentDetails::insertGetId($paymentDataSave);
                OneTimePayment::where('uniqueId', $uniqueId)->update($updateData);
                
                $isSuccess = true;
                $response = $paymentStatusMsg;
            }
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in OneTimePaymentController@transaction error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }
        return response()->json(new BaseResponse($isSuccess, $responseMsg, $response));
    }

}