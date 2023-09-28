<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Restaurant\Advertisement;
use App\Models\Payment\RestaurantStripeDetails;
use App\Models\Payment\RestaurantStripePaymentIntentDetails;
use App\Models\Payment\StripePaymentFailedNotifications;
use App\Models\Payment\PaymentDetails;
use App\Models\Payment\StripePaymentAuthentications;
use App\Models\Restaurant\RestaurantSubscription;
use App\Shared\EatCommon\Payment\StripeClient;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Shared\EatCommon\Payment\CreatePaymentIntentChargeResultPOPO;
use Illuminate\Support\Facades\Log;

class StripePaymentIntentController extends Controller
{
    private $stripeClient;
    private $datetimeHelpers;
    private $ipHelpers;
    private $translatorFactory;

    public function __construct(StripeClient $stripeClient, IPHelpers $ipHelpers, DatetimeHelper $datetimeHelpers, TranslatorFactory $translatorFactory)
    {
        $this->stripeClient = $stripeClient;
        $this->ipHelpers = $ipHelpers;
        $this->datetimeHelpers = $datetimeHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
    }

    public function createPaymentIntent(int $restaurantId)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

        if ($validator->fails())
        {
            throw new Exception('StripePaymentIntentController createPaymentIntent error %s.', $validator->errors()->first());
        }

        $condition = [
            ['restaurantId', $restaurantId],
            ['status', PAYMENT_STATUS_ACTIVE]
        ];

        $getCustomerId = RestaurantStripeDetails::select(['stripeCustomerId'])->where( $condition)->first();
        $paymentIntent = '';
        $email = '';
        $createCustomerResponse = '';

        if(empty($getCustomerId))
        {
            $getCustomerId = RestaurantStripePaymentIntentDetails::select(['stripeCustomerId'])->where($condition)->first();
        }

        if(!empty($getCustomerId))
        {
            $customerId = $getCustomerId['stripeCustomerId'];
            $paymentIntent = $this->stripeClient->createPaymentIntent($customerId);
        }
        else
        {
            $result = Advertisement::select(['author_id', 'id'])->where('id', $restaurantId)->first();
        
            $userEmail = User::select(['email'])->where('uid', $result['author_id'])->first();
            $email = $userEmail['email'];
            $createCustomerResponse = $this->stripeClient->createCustomer($email); 
            $customerId = $createCustomerResponse['id'];

            $paymentIntent = $this->stripeClient->createPaymentIntent($customerId);
        }
       
        
        $paymentIntentClientSecretId = $paymentIntent['client_secret'];
        $paymentIntentCustomerId = $paymentIntent['customer'];
        $ip = $this->ipHelpers->clientIpAsLong();
        $createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $userId = Auth::id();

        $dataToSave = [
            'userId' => $userId,
            'restaurantId' => $restaurantId, 
            'stripeCustomerId' => $paymentIntentCustomerId,
            'paymentIntentClientSecretId' => $paymentIntentClientSecretId,
            'paymentIntentId' => $paymentIntent['id'],
            'createCustomerRequest' => $email,
            'createCustomerResponse' => $createCustomerResponse,
            'createPaymentIntentRequest' => $customerId,
            'createPaymentIntentResponse' =>$paymentIntent,
            'status' => PAYMENT_STATUS_ACTIVE,  
            'createdOn' => $createdOn, 
            'ip' => $ip
        ];

        RestaurantStripePaymentIntentDetails::insertGetId($dataToSave);

        return response()->json(new BaseResponse(true, null, $paymentIntentClientSecretId));
    }

    public function cardRegisterSuccessfulResponse(int $restaurantId, Request $request)
    {
        $isSuccess = false;
        $response = [];
        $defaultErrorMessage = $this->translatorFactory->translate('Your card is not registered with us due to some technical error, please try again');

        try
        {
            $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

            if ($validator->fails())
            {
                throw new Exception('StripePaymentIntentController cardRegisterSuccessfulResponse error %s.', $validator->errors()->first());
            }

            $cardRegisterDetails = $request->all();
            $paymentMethod = '';
            $registerCardSuccessResponse = json_encode($cardRegisterDetails);
            $cardHolderName = '';
            $currentTimeStamp = $this->datetimeHelpers->getCurrentUtcTimeStamp();
            $taxCalculation = [
                'tax' => '0',
                'amount' => '0'
            ];

            if(!empty($cardRegisterDetails) && isset($cardRegisterDetails['stripeSource']) && isset($cardRegisterDetails['stripeSource']['setupIntent']))
            {
                $paymentMethod = $cardRegisterDetails['stripeSource']['setupIntent']['payment_method'];
                $cardHolderName = $cardRegisterDetails['cardHolderName'];
                
                $condition = [
                    ['restaurantId', $restaurantId],
                    ['status', STATUS_ACTIVE]
                ];
                
                $restaurantSubscription = Advertisement::select(
                [
                    'id',
                    'profileType', 
                    'advertisementPaymentStartDate', 
                    'advertisementPaymentExpiryDate'
                ])->with(['restaurantSubscription' => function($query){
                    $query->where('isActive', RESTAURANT_STATUS_ACTIVE);
                    $query->whereNotNull('advertisementDurationInDays');
                }])->where([
                    ['id', $restaurantId],
                    ['status', RESTAURANT_STATUS_ACTIVE]
                ])->first();
                
                if(!empty($restaurantSubscription))
                {
                    $restaurantSubscription =  $restaurantSubscription->toArray();
                }

                $subscriptionAmount = RESTAURANT_CARD_REGISTER_PAYMENT_AMOUNT;
                $advertisementDurationInDays = 0;
                $advertisementPaymentExpiryDate = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $advertisementProfileType = 0;
                $isCalculateTaxOnAmount = true;
                $lastFailedAmount = [];
                $updateAdvertisementPaymentDate = false;

                if(!empty($restaurantSubscription['restaurant_subscription']))
                {
                    $advertisementPaymentExpiryDate = $restaurantSubscription['advertisementPaymentExpiryDate'];

                    if($advertisementPaymentExpiryDate <= $this->datetimeHelpers->getCurrentUtcTimeStamp())
                    {
                        $subscriptionAmount = $restaurantSubscription['restaurant_subscription']['advertisementPrice'];
                    }
                    else
                    {
                        $subscriptionAmount = RESTAURANT_CARD_REGISTER_PAYMENT_AMOUNT;
                    }

                    $advertisementDurationInDays = $restaurantSubscription['restaurant_subscription']['advertisementDurationInDays'];
                    $advertisementTaxType = $restaurantSubscription['restaurant_subscription']['advertisementTaxType'];
                    $advertisementProfileType = $restaurantSubscription['profileType'];
                    $updateAdvertisementPaymentDate = true;
                }
                
                if($isCalculateTaxOnAmount)
                {
                    if($subscriptionAmount == RESTAURANT_CARD_REGISTER_PAYMENT_AMOUNT)
                    {
                        $advertisementTaxType = TAX_IN_AMOUNT;
                    }

                    $taxCalculation = $this->calculateTax($subscriptionAmount, $advertisementTaxType);
                    $subscriptionAmount = $taxCalculation['stripeAmount'];

                }

                $lastRestaurantStripePaymentIntentDetails = RestaurantStripePaymentIntentDetails::select(['restaurantStripePaymentIntentDetailsId', 'stripeCustomerId'])->where($condition)->orderBy('restaurantStripePaymentIntentDetailsId', 'desc')->first();
                
                //stripe payment intent charge 
                $createSubscriptionCharge = $this->stripeClient->createPaymentIntentCharge($paymentMethod, $subscriptionAmount, $lastRestaurantStripePaymentIntentDetails->stripeCustomerId);

                $stripeChargeResp = $createSubscriptionCharge->getIntentStripeRawObject();
                $stripeChargeRespStatus = $stripeChargeResp['status'];
                $stripeChargeRespInvoiceId = $stripeChargeResp['invoice'];
                $stripePaymentIntentId = $stripeChargeResp['client_secret'];
                $stripePaymentAmount = $stripeChargeResp['amount'];
                $stripePaymentType = PAYMENT_STRIPE_INTENT_TYPE;

                $stripeRequest = array(
                    'stripeCustomerId' => $stripeChargeResp['customer'],
                    'stripeSource' => $stripeChargeResp['source'],
                    'stripeAmount' => $stripeChargeResp['amount']
                );
                
                $restaurantStripeDetailsSaveData = [
                    'userId' => Auth::id(),
                    'restaurantId' => $restaurantId, 
                    'stripeCustomerId' => $lastRestaurantStripePaymentIntentDetails->stripeCustomerId,
                    'paymentMethod' => $paymentMethod,
                    'paymentType' => PAYMENT_STRIPE_INTENT_TYPE,
                    'cardHolderName' => $cardHolderName,
                    'status' => STATUS_ACTIVE,
                    'ip' => $this->ipHelpers->clientIpAsLong(),
                    'createdOn' => $currentTimeStamp,
                ];

                if($stripeChargeRespStatus == PAYMENT_INTENT_STATUS_SUCCEEDED)
                {
                    RestaurantStripePaymentIntentDetails::where('restaurantStripePaymentIntentDetailsId', $lastRestaurantStripePaymentIntentDetails->restaurantStripePaymentIntentDetailsId)->update(array('registerCardSuccessResponse' => $registerCardSuccessResponse));
                
                    $exitsStripeDetails  = RestaurantStripeDetails::where($condition)->count();

                    if($exitsStripeDetails > 0)
                    {
                        RestaurantStripeDetails::where('restaurantId', $restaurantId)->update(array('status' => '0'));
                    }


                    $paymentStartDate = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                    $paymentEndDate = $paymentStartDate + ($advertisementDurationInDays * 24 * 60 * 60);

                    RestaurantStripeDetails::insertGetId($restaurantStripeDetailsSaveData); 

                    $getActiveFailedNotifications = StripePaymentFailedNotifications::where(array('restaurantId' => $restaurantId, 'isActive' => PAYMENT_STATUS_ACTIVE))->count();
                
                    if($getActiveFailedNotifications > 0)
                    {
                        StripePaymentFailedNotifications::where('restaurantId', $restaurantId)->update(array('isActive' => '0', 'totalNotificationSent' => '2'));
                    }

                    if(!empty($lastFailedAmount) && $lastFailedAmount['payment_status'] == PAYMENT_STATUS_AUTOMATED_CAPTURE_UNSUCCESSFUL_FOR_COLLECT_PAYMENT)
                    {
                        PaymentDetails::Where('payment_id', $lastFailedAmount['payment_id'])->update(array('payment_status' => PAYMENT_STATUS_AUTOMATED_CAPTURE_SUCCESSFUL, 'request_data' => $stripeChargeResp));
                    }
                    else
                    {

                        // insert in payment_details
                        $paymentDetailsSaveData = [
                            'ad_id' => $restaurantId,
                            'amount' => floatval($taxCalculation['amount']),
                            'moms' => floatval($taxCalculation['tax']),
                            'payment_status' => PAYMENT_STATUS_AUTOMATED_CAPTURE_SUCCESSFUL,
                            'payment_type' => PAYMENT_TYPE_ADVERTISEMENT_SUBSCRIPTION,
                            'durationInDays' => $advertisementDurationInDays,
                            'payment_start_date' => $paymentStartDate,
                            'payment_end_date' => $paymentEndDate,
                            'profile_type' => intval($advertisementProfileType),
                            'user_id' => Auth::id(),
                            'payment_request_date' => $currentTimeStamp,
                            'payment_response_date' => $currentTimeStamp,
                            'request_data' => serialize($stripeRequest),
                            'response_data'=> serialize($stripeChargeResp),
                            'stripe_invoice_id' => $stripeChargeRespInvoiceId,
                            'paymentIntentClientSecretId' => $stripePaymentIntentId,
                            'stripePaymentType' => $stripePaymentType,
                            'order_id' =>  uniqid(),
                        ];

                        PaymentDetails::insertGetId($paymentDetailsSaveData); 
                    }
                    
                    /* Update advertisement expiry date if subscription amount due */
                    $defaultStripeAmount = RESTAURANT_CARD_REGISTER_PAYMENT_AMOUNT * PAYMENT_CURRENCY_MULTIPLIER;
                    
                    if($updateAdvertisementPaymentDate && $stripePaymentAmount > $defaultStripeAmount)
                    {
                        Advertisement::where('id', $restaurantId)->update(array('advertisementPaymentStartDate' => $paymentStartDate, 'advertisementPaymentExpiryDate'=> $paymentEndDate));
                    }
                    $isSuccess = true;
                    $response['successMsg'] = $this->translatorFactory->translate("Congratulations! your payment is successfully taken and your card is registered with us");
                    $response['stripeStatus'] = $stripeChargeRespStatus; 

                }
                else if($stripeChargeRespStatus == STRIPE_PAYMENT_INTENT_FAILURE_REQUIRES_SOURCE)
                {
                    $exitsStripeDetails  = RestaurantStripeDetails::where($condition)->count();

                    if($exitsStripeDetails > 0)
                    {
                        RestaurantStripeDetails::where('restaurantId', $restaurantId)->update(array('status' => '0'));
                    }
                    
                    RestaurantStripeDetails::insertGetId($restaurantStripeDetailsSaveData);
                    $isSuccess = true;
                    $response['successMsg'] = $this->translatorFactory->translate("Your Card is registered successfully");
                    $response['stripeStatus'] = $stripeChargeRespStatus;
                    $response['stripePaymentIntentId'] = $stripePaymentIntentId;
                    $response['paymentMethod'] = $paymentMethod;
                }
                else
                {
                    $isSuccess = false;
                    $response['successMsg'] = $this->translatorFactory->translate('Your card is not registered with us due to some technical error, please try again');
                }
            } 
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in StripePaymentIntentController@cardRegisterSuccessfulResponse error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        $respMessage = '';
        if(!$isSuccess)
        {
            $respMessage = $defaultErrorMessage; 
        }
        return response()->json(new BaseResponse($isSuccess, $respMessage, $response));
    }

    public function cardRegisterFailureResponse(int $restaurantId, Request $request)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

        if ($validator->fails())
        {
            throw new Exception('StripePaymentIntentController cardRegisterSuccessfullResponse error %s.', $validator->errors()->first());
        }
        
        $cardRegisterDetails = $request->all();
        $paymentMethod = '';
        $registerCardSuccessResponse = json_encode($cardRegisterDetails);
        $successMsg = '';
        if(!empty($cardRegisterDetails) && isset($cardRegisterDetails['stripeSource']))
        {
            $condition = [
                ['restaurantId', $restaurantId],
                ['status', PAYMENT_STATUS_ACTIVE]
            ];
            
            $lastRestaurantStripePaymentIntentDetails = RestaurantStripePaymentIntentDetails::select(['restaurantStripePaymentIntentDetailsId', 'stripeCustomerId'])->where($condition)->orderBy('restaurantStripePaymentIntentDetailsId', 'desc')->first();
            
            RestaurantStripePaymentIntentDetails::where('restaurantStripePaymentIntentDetailsId', $lastRestaurantStripePaymentIntentDetails->restaurantStripePaymentIntentDetailsId)->update(array('registerCardFailureResponse' => $registerCardSuccessResponse));
            
            $successMsg = $this->translatorFactory->translate("Your card is not registered with us due to some technical error, please try again");
        }

        return response()->json(new BaseResponse(true, null, $successMsg));
    }

    public function fetchDetailsToAuthenticateFailedPayment(int $restaurantId, Request $request)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

        if ($validator->fails())
        {
            throw new Exception('StripePaymentIntentController fetchDetailsToAuthenticateFailedPayment error %s.', $validator->errors()->first());
        }

        $condition = [
            ['restaurantId', $restaurantId],
            ['status', PAYMENT_STATUS_ACTIVE]
        ];
        
        $dataNeedForAuthenticate = [];
        $restaurantStripeDetails = RestaurantStripeDetails::select(['paymentMethod'])->where($condition)->first();
        if(!empty($restaurantStripeDetails))
        {
            $dataNeedForAuthenticate['paymentMethod'] = $restaurantStripeDetails['paymentMethod'];
        }
        $paymentDetails = PaymentDetails::select(['paymentIntentClientSecretId'])->where(array('ad_id'=> $restaurantId, 'stripePaymentType' => PAYMENT_STRIPE_INTENT_TYPE, 'payment_status' => PAYMENT_STATUS_AUTOMATED_CAPTURE_UNSUCCESSFUL_FOR_COLLECT_PAYMENT))->orderBy('payment_id', 'DESC')->first();
        
        if(!empty($paymentDetails))
        {
            $dataNeedForAuthenticate['paymentIntentClientSecretId'] = $paymentDetails['paymentIntentClientSecretId'];
        }
        return response()->json(new BaseResponse(true, null, $dataNeedForAuthenticate));
    }

    public function saveSuccessAuthenticatePaymentDetails(int $restaurantId, Request $request)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

        if ($validator->fails())
        {
            throw new Exception('StripePaymentIntentController fetchDetailsToAuthenticateFailedPayment error %s.', $validator->errors()->first());
        }
        
        $cardAuthenticateSuccessDetails = $request->all();
        $clientSecretPaymentIntnetId = '';
        $paymentDetailId = '';
        $successMsg = '';
        $saveAuthenticationSuccessResponse = json_encode($cardAuthenticateSuccessDetails);
        

        if(!empty($cardAuthenticateSuccessDetails) && isset($cardAuthenticateSuccessDetails['authenticateSource']) && isset($cardAuthenticateSuccessDetails['authenticateSource']['paymentIntent']['client_secret']))
        {
            $clientSecretPaymentIntnetId = $cardAuthenticateSuccessDetails['authenticateSource']['paymentIntent']['client_secret'];
            $paymentDetailId = $cardAuthenticateSuccessDetails['authenticateSource']['paymentIntent']['id'];
          
            $advertisement = Advertisement::select(['advertisementPaymentExpiryDate'])->where('id', $restaurantId)->first();
            $restaurantSubscription = RestaurantSubscription::select(['advertisementDurationInDays', 'advertisementProfileType'])->where('restaurantId', $restaurantId)->first();
            
            if(!empty($advertisement && $restaurantSubscription))
            {
                $paymentStartDate = $advertisement['advertisementPaymentExpiryDate'];
                $paymentEndDate = $paymentStartDate + ($restaurantSubscription['advertisementDurationInDays'] * 24 * 60 * 60);

                Advertisement::Where('id', $restaurantId)->update(array('advertisementPaymentStartDate' => $paymentStartDate, 'advertisementPaymentExpiryDate' => $paymentEndDate, 'profileType' => $restaurantSubscription['advertisementProfileType']));

                PaymentDetails::Where('paymentIntentClientSecretId', $clientSecretPaymentIntnetId)->update(array('payment_status' => PAYMENT_STATUS_AUTOMATED_CAPTURE_SUCCESSFUL));

                StripePaymentFailedNotifications::where('restaurantId', $restaurantId)->update(array('isActive' => '0', 'totalNotificationSent' => '2'));

                $ip = $this->ipHelpers->clientIpAsLong();
                $createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();

                $authenticateDetails = [
                    'userId' =>Auth::id(),
                    'restaurantId' => $restaurantId,
                    'paymentIntentClientSecret' => $clientSecretPaymentIntnetId,
                    'authenticationResponse' => $saveAuthenticationSuccessResponse,
                    'isAuthenticationSuccessfull' => 1,
                    'paymentDetailId' => $paymentDetailId,
                    'ip' => $ip,
                    'createdOn' => $createdOn
                ];

                StripePaymentAuthentications::insertGetId($authenticateDetails);
                $successMsg = $this->translatorFactory->translate("Congratulation! Your payment is authenticated successfully");
            }
            
            return response()->json(new BaseResponse(true, null, $successMsg));
        }
        
    }

    public function saveFailedAuthenticatePaymentDetails(int $restaurantId, Request $request)
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

        if ($validator->fails())
        {
            throw new Exception('StripePaymentIntentController fetchDetailsToAuthenticateFailedPayment error %s.', $validator->errors()->first());
        }
        
        $cardAuthenticateFailedDetails = $request->all();
        $clientSecretPaymentIntnetId = '';
        $paymentDetailId = '';
        $successMsg = '';
        $saveAuthenticationSuccessResponse = json_encode($cardAuthenticateFailedDetails);
        

        if(!empty($cardAuthenticateFailedDetails) && isset($cardAuthenticateFailedDetails['authenticateSource']))
        {
            
            $clientSecretPaymentIntnetId = $cardAuthenticateFailedDetails['authenticateSource']['error']['payment_intent']['client_secret'];
            $paymentDetailId = $cardAuthenticateFailedDetails['authenticateSource']['error']['payment_intent']['id'];
            $ip = $this->ipHelpers->clientIpAsLong();
            $createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();

            $authenticateDetails = [
                'userId' =>Auth::id(),
                'restaurantId' => $restaurantId,
                'paymentIntentClientSecret' => $clientSecretPaymentIntnetId,
                'authenticationResponse' => $saveAuthenticationSuccessResponse,
                'isAuthenticationSuccessfull' => 0,
                'paymentDetailId' => $paymentDetailId,
                'ip' => $ip,
                'createdOn' => $createdOn
            ];

            StripePaymentAuthentications::insertGetId($authenticateDetails);
            $successMsg = $this->translatorFactory->translate($cardAuthenticateFailedDetails['authenticateSource']['error']['message']);
        }

        return response()->json(new BaseResponse(true, null, $successMsg));
    }

    public function addInstantPayment(int $restaurantId, Request $request)
    {
        $isSuccess = false;

        try
        {
            $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|min:1']);

            if ($validator->fails())
            {
                throw new Exception('StripePaymentIntentController addInstantPayment error %s.', $validator->errors()->first());
            }

            $cardAuthenticateSuccessDetails = $request->all();
            $clientSecretPaymentIntnetId = '';
            $successMsg = '';
            $saveAuthenticationSuccessResponse = json_encode($cardAuthenticateSuccessDetails);
            $currentTimeStamp = $this->datetimeHelpers->getCurrentUtcTimeStamp();

            if(!empty($cardAuthenticateSuccessDetails) && isset($cardAuthenticateSuccessDetails['authenticateSource']) && isset($cardAuthenticateSuccessDetails['authenticateSource']['paymentIntent']['client_secret']))
            {
                $clientSecretPaymentIntnetId = $cardAuthenticateSuccessDetails['authenticateSource']['paymentIntent']['client_secret'];

                $restaurantSubscription = Advertisement::select(
                    [
                        'id',
                        'profileType', 
                        'advertisementPaymentStartDate', 
                        'advertisementPaymentExpiryDate'
                    ])->with(['restaurantSubscription' => function($query){
                        $query->where('isActive', STATUS_ACTIVE);
                        $query->whereNotNull('advertisementDurationInDays');
                    }])->whereNotNull(array('advertisementPaymentStartDate', 'advertisementPaymentExpiryDate'))->where([
                        ['id', $restaurantId],
                        ['status', STATUS_ACTIVE],
                        ['advertisementPaymentExpiryDate', '<=', $currentTimeStamp]
                    ])->first();
                    
                    if(!empty($restaurantSubscription))
                    {
                        $restaurantSubscription =  $restaurantSubscription->toArray();
                    }

                $advertisementTaxType = TAX_IN_AMOUNT;
                $advertisementDurationInDays = 0;
                $advertisementProfileType = 0;
                $paymentStartDate = $currentTimeStamp;
                $paymentEndDate = $currentTimeStamp;
                $paymentRequestDate = $currentTimeStamp;
                $paymentResponseDate = $currentTimeStamp;
                $stripePaymentType = PAYMENT_STRIPE_INTENT_TYPE;
                $advertisementPrice = RESTAURANT_CARD_REGISTER_PAYMENT_AMOUNT;

                $defaultStripeAmount = RESTAURANT_CARD_REGISTER_PAYMENT_AMOUNT * PAYMENT_CURRENCY_MULTIPLIER;
                $chargeStripeAmount = $cardAuthenticateSuccessDetails['authenticateSource']['paymentIntent']['amount'];

                if(!empty($restaurantSubscription))
                {
                    $advertisementTaxType = $restaurantSubscription['restaurant_subscription']['advertisementTaxType'];
                    $advertisementDurationInDays = $restaurantSubscription['restaurant_subscription']['advertisementDurationInDays'];
                    $paymentStartDate = $restaurantSubscription['advertisementPaymentExpiryDate'];
                    $paymentEndDate = $paymentStartDate + ($advertisementDurationInDays * 24 * 60 * 60);
                    $advertisementPrice = $restaurantSubscription['restaurant_subscription']['advertisementPrice'];
                }
                
                // insert in payment_details
                # Calculate tax
                $taxCalculation = $this->calculateTax($advertisementPrice, $advertisementTaxType);

                $paymentDetailsSaveData = [
                    'ad_id' => $restaurantId,
                    'amount' => floatval($taxCalculation['amount']),
                    'moms' => floatval($taxCalculation['tax']),
                    'payment_status' => PAYMENT_STATUS_AUTOMATED_CAPTURE_SUCCESSFUL,
                    'payment_type' => PAYMENT_TYPE_ADVERTISEMENT_SUBSCRIPTION,
                    'durationInDays' => $advertisementDurationInDays,
                    'payment_start_date' => $paymentStartDate,
                    'payment_end_date' => $paymentEndDate,
                    'profile_type' => intval($advertisementProfileType),
                    'user_id' => Auth::id(),
                    'payment_request_date' => $paymentRequestDate,
                    'payment_response_date' => $paymentResponseDate,
                    'request_data' => $saveAuthenticationSuccessResponse,
                    'paymentIntentClientSecretId' => $clientSecretPaymentIntnetId,
                    'stripePaymentType' => $stripePaymentType,
                    'order_id' =>  uniqid(),
                ];

                PaymentDetails::insertGetId($paymentDetailsSaveData);

                if($chargeStripeAmount > $defaultStripeAmount)
                {
                    Advertisement::where('id', $restaurantId)->update(array('advertisementPaymentStartDate' => $paymentStartDate, 'advertisementPaymentExpiryDate'=> $paymentEndDate));
                }
               
                $successMsg = $this->translatorFactory->translate("Congratulations! your payment is successfully taken and your card is registered with us");
                $isSuccess = true;

            }
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in StripePaymentIntentController@addInstantPayment error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
            $successMsg = '';
        }

        return response()->json(new BaseResponse($isSuccess, null, $successMsg));
        
    }

    private function calculateTax(int $amount, int $taxType): array
    {
        $finalAmount = [];
        if($taxType == TAX_ON_AMOUNT)
        {
            $tax = (TAX_PERCENT/100 * $amount);
            $totalAmount = $amount + $tax;
            $finalAmount['stripeAmount'] = $totalAmount *  PAYMENT_CURRENCY_MULTIPLIER;
            $finalAmount['amount'] = $amount * PAYMENT_CURRENCY_MULTIPLIER;
            $finalAmount['tax'] = $tax * PAYMENT_CURRENCY_MULTIPLIER;
        }
        else
        {
            $net = $amount/1.25;
            $tax = $amount - $net;
            $calculatedAmount = $net;

            $finalAmount['stripeAmount'] = $amount * PAYMENT_CURRENCY_MULTIPLIER;
            $finalAmount['amount'] = $calculatedAmount * PAYMENT_CURRENCY_MULTIPLIER;
            $finalAmount['tax'] = $tax * PAYMENT_CURRENCY_MULTIPLIER;
        }
        return $finalAmount;        
    }
}