<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Sms\Sms;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Models\Order\PhoneNumberVerification;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use App\Libs\Helpers\Authentication;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Validator;
use Auth;

class PhoneNumberVerificationController extends Controller
{
    private $sms;
    private $datetimeHelpers;
    private $ipHelpers;
    private $authentication;
    private $translatorFactory;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, Sms $sms, TranslatorFactory $translatorFactory, Authentication $authentication)
    {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->authentication = $authentication;
        $this->ipHelpers = $ipHelpers;
        $this->sms = $sms;
    }

    public function getPhoneNumberVerificationStatus(int $phoneNumber)
    {
        try
        {
            $validator = Validator::make(['phoneNumber' => $phoneNumber], ['phoneNumber' => 'required|int|min:1']);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            $result = PhoneNumberVerification::where([['phoneNumber', $phoneNumber], ['userId', Auth::id()], ['isVerified', PHONE_VERIFIED]])->get()->toArray();

            $response = false;

            if(!empty($result))
            {
                $response = true;
            }
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in PhoneNumberVerificationController@getPhoneNumberVerificationStatus error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function sendPhoneNumberVerificationSmsToUser(int $phoneNumber, string $countryCode)
    {
        try
        {
            $validator = Validator::make([
                'phoneNumber' => $phoneNumber,
                'countryCode' => $countryCode
            ],
                [
                    'phoneNumber' => 'required|int|min:1',
                    'countryCode' => 'required|string',
                ]
            );

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            if (!$this->authentication->isUserAdmin())
            {
                $previousDayTime = strtotime('-1 day');
                $currentTime = $this->datetimeHelpers->getCurrentUtcTimeStamp();

                $phoneVerificationIpCondition = [
                    ['ip', $this->ipHelpers->clientIpAsLong()]
                ];

                $phoneVerificationPhoneCondition = [
                    ['phoneNumber', $phoneNumber],
                    ['userId', Auth::id()]
                ];

                $phoneVerificationFromOneIp = PhoneNumberVerification::select('phoneNumberVerificationId')->where($phoneVerificationIpCondition)->whereBetween('createdOn', [$previousDayTime, $currentTime])->get()->toArray();
                $phoneVerificationFromOnePhoneNumber = PhoneNumberVerification::select('phoneNumberVerificationId')->where($phoneVerificationPhoneCondition)->whereBetween('createdOn', [$previousDayTime, $currentTime])->get()->toArray();

                if(count($phoneVerificationFromOneIp) >= MAXIMUM_PHONE_VERIFICATION_SMS_SEND_FROM_ONE_IP)
                {
                    Log::critical(sprintf("Sms send from %s IP more than %s times in the last 24 hrs", $this->ipHelpers->clientIpAsLong(), MAXIMUM_PHONE_VERIFICATION_SMS_SEND_FROM_ONE_IP));
                    throw new Exception("Sms not send because of multiple sms sent with in the last 24 hrs");
                }

                if (count($phoneVerificationFromOnePhoneNumber) >= MAXIMUM_PHONE_VERIFICATION_SMS_SEND_FROM_ONE_PHONE_NUMBER)
                {
                    Log::critical(sprintf("Sms send from %s phone number more than %s times in the last 24 hrs", $phoneNumber, MAXIMUM_PHONE_VERIFICATION_SMS_SEND_FROM_ONE_PHONE_NUMBER));
                    throw new Exception("Sms not send because of multiple sms sent with in the last 24 hrs");
                }

            }

            $code = random_int(100000, 999999);
            $msg = sprintf(" %s %s %s%s %s Dagensmenu.dk", $this->translatorFactory->translate('Your phone number verification code is'), $code, PHP_EOL, PHP_EOL, $this->translatorFactory->translate('Sincerely'));
            $this->sms->SendSms(SMS_SENDER, $phoneNumber, $msg, $countryCode);

            $model = new PhoneNumberVerification();

            $model->userId = Auth::id();
            $model->phoneNumber = $phoneNumber;
            $model->isVerified = false;
            $model->verificationCode = $code;
            $model->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
            $model->ip = $this->ipHelpers->clientIpAsLong();
            $model->save();
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in PhoneNumberVerificationController@sendPhoneNumberVerificationSmsToUser error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, true));
    }

    public function verifyPhoneNumberVerificationCode(int $phoneNumber, int $verificationCode)
    {
        try
        {
            $validator = Validator::make([
                'phoneNumber' => $phoneNumber,
                'verificationCode' => $verificationCode
            ], [
                'phoneNumber' => 'required|int',
                'verificationCode' => 'required|int'
            ]);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            $result = PhoneNumberVerification::where([['phoneNumber', $phoneNumber], ['userId', Auth::id()], ['verificationCode', $verificationCode]])->get()->toArray();

            if($result)
            {
                $response = true;
                PhoneNumberVerification::where([['phoneNumber', $phoneNumber], ['userId', Auth::id()], ['verificationCode', $verificationCode]])->update(['isVerified' => true]);
            }
            else
            {
                $response = false;
            }
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in PhoneNumberVerificationController@verifyPhoneNumberVerificationCode error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }
}
