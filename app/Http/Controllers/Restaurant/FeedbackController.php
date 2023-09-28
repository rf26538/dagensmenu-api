<?php

namespace App\Http\Controllers\Restaurant;
use App\Models\Restaurant\FeedbackModel;
use App\Models\Location\PlaceModel;
use App\Models\Restaurant\Advertisement;
use App\Libs\Helpers\Authentication;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Shared\EatCommon\Link\Links;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseResponse;
use Exception;
use Auth;
use App\Shared\EatCommon\Sms\Sms;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendFeedbackReplyMail;

class FeedbackController extends Controller {
    private $ipHelpers;
    private $datetimeHelpers;
    private $translatorFactory;
    private $sms;

    function __construct(TranslatorFactory $translatorFactory, Links $links, DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, Sms $sms)
    {
        $this->links = $links;
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->sms = $sms;
    }

    public function saveFeedback(Request $request)
    {
        $clientIp = $this->ipHelpers->clientIpAsLong();
        $lastHour = $this->datetimeHelpers->getCurrentUtcTimeStamp() - (24*60*60);
        $ipCondition = [
            ['ip', $clientIp],
            ['createdOn', '>', $lastHour]
        ];

        $ipCount = FeedbackModel::where($ipCondition)->get()->count();

        if($ipCount < FEEDBACK_MAXIMUM_ALLOWED_FROM_ONE_IP)
        {
            $rules = array(
                'userType' => 'required|integer',
                'userName' => 'string',
                'email' => 'required|string',
                'countryCode' => 'string',
                'postCode' => 'integer',
                'adId' => 'integer',
                'restaurantName' => 'string',
                'message' => 'required|string',
            );

            $data = $request->all();

            if($data['userType'] == FEEDBACK_USER_TYPE_RESTAURANT_OWNER)
            {
                $rules['phone'] = 'required|integer';
            }
            else
            {
                $rules['phone'] = 'numeric';
            }

            $validator = Validator::make($request->post(), $rules);

            if($validator->fails())
            {
                throw new Exception(sprintf("FeedbackController.saveFeedback error. %s ", $validator->errors()->first()));
            }

            $adId = NULL;

            if(Auth::id())
            {
                $advertisementId = Advertisement::select('id')->where('author_id', Auth::id())->first();

                if(!empty($advertisementId)){
                    $adId = $advertisementId->id;
                }
            }

            if($request->post('feedbackRestaurantId'))
            {
                $adId = $request->post('feedbackRestaurantId');
            }

            $feedbackModel = new FeedbackModel;
            $feedbackModel->userType = $request->post('userType');
            $feedbackModel->name = $request->post('userName');
            $feedbackModel->email = $request->post('email');
            $feedbackModel->phone = (!empty($request->post('phone')) ? $request->post('phone') : 0);
            $feedbackModel->countryCode = $request->post('countryCode');
            $feedbackModel->restaurantName = (!empty($request->post('restaurantName')) ? $request->post('restaurantName') : null);
            $feedbackModel->restaurantTelephoneNumber = (!empty($request->post('restaurantTelephoneNumber')) ? $request->post('restaurantTelephoneNumber') : null);
            $feedbackModel->postCode = $request->post('postCode');
            $feedbackModel->adId = $adId;
            $feedbackModel->message = $request->post('message');
            $feedbackModel->ip = $clientIp;
            $feedbackModel->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
            $feedbackModel->isRead = FEEDBACK_NOT_READ;
            $feedbackModel->status = FEEDBACK_STATUS_ACTIVE;
            $feedbackModel->feedbackImages = $request->post('feedbackImages') > 0 ? json_encode($request->post('feedbackImages')) : null;
            $feedbackModel->feedbackPdfs = $request->post('feedbackPdfs') > 0 ? json_encode($request->post('feedbackPdfs')) : null;
            $feedbackModel->restaurantOpeningTimings = $request->post('restaurantOpeningTimings') > 0 ? json_encode($request->post('restaurantOpeningTimings')) : null;
            $feedbackModel->save();
        }
        return response()->json(new BaseResponse(true, null, null));
    }

    public function fetchFeedbacks()
    {
        $response = FeedbackModel::select('feedbackId', 'userType', 'name', 'email', 'restaurantName','feedbacks.postCode', 'id', 'author_id','urlTitle', 'address','city', 'serviceDomainName', 'cityUrl', 'phone', 'message','createdOn', 'isRead', 'adId', 'replyContent', 'replyOn', 'replyBy', 'replyInfo', 'replyType', 'restaurantTelephoneNumber', 'feedbackImages', 'feedbackPdfs', 'restaurantOpeningTimings' )
        ->leftJoin('advertisement', 'advertisement.id', '=', 'feedbacks.adId')
        ->where('feedbacks.status', FEEDBACK_STATUS_ACTIVE)
        ->limit(100)->orderBy('feedbackId', 'DESC')
        ->get();

        foreach($response as &$resp)
        {
            if($resp['userType'] == FEEDBACK_USER_TYPE_RESTAURANT_OWNER)
            {
                $resp->feedbackUserType = $this->translatorFactory->translate('Restaurant owner');

                if(empty($resp['city']))
                {
                    $result = PlaceModel::select( 'locality', 'postcode')->where('postcode', $resp['postCode'])->first();

                    if(!empty($result))
                    {
                        $resp->restaurantCompleteAddress = sprintf("%s, %s", $result['locality'], $result['postcode']);
                    }
                }
                else
                {
                    $resp->restaurantCompleteAddress = sprintf("%s, %s, %s", $resp['address'], $resp['city'], $resp['postCode']);
                }

                if(!empty($resp->adId))
                {
                    $resp->restaurantUrl = $this->links->menuLink($resp->adId, $resp->url, $resp->urlTitle, $resp->serviceDomainName, $resp->cityUrl);
                }
            }
            else
            {
                $resp->feedbackUserType = $this->translatorFactory->translate('Customer');
                $resp->restaurantCompleteAddress = '';
            }

            if($resp['name'] != NULL)
            {
                $resp->feedbackUserName = $resp['name'];
            }
            else
            {
                $resp->feedbackUserName = $resp['restaurantName'];
            }

            if(!empty($resp['replyOn']))
            {
                $resp->replyOnDateFormate = $this->datetimeHelpers->getDanishFormattedDateTime($resp->replyOn);

                if($resp['replyType'] = FEEDBACK_REPLY_TYPE_EMAIL)
                {
                    $resp->replyTo = $resp['replyInfo'] ?? $resp['email'];
                }

                if($resp['replyType'] = FEEDBACK_REPLY_TYPE_PHONE_NUMBER)
                {
                    $resp->replyTo = $resp['replyInfo'] ?? $resp['phone'];
                }

                $resp->repliedByName =  $resp->userDetail->name ?? NULL;

                if(isset($resp['userDetail']))
                {
                    unset($resp['userDetail']);
                }
            }

            if(!empty($resp['feedbackImages']))
            {
                $resp->feedbackImages = json_decode($resp['feedbackImages']);
            }

            if(!empty($resp['feedbackPdfs']))
            {
                $resp->feedbackPdfs= json_decode($resp['feedbackPdfs']);
            }

            if(!empty($resp['restaurantOpeningTimings']))
            {
                $resp->restaurantOpeningTimings= json_decode($resp['restaurantOpeningTimings']);
            }

            $resp->formattedDate = $this->datetimeHelpers->getDanishFormattedDateTime($resp->createdOn);
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function delete(int $feedbackId)
    {
        $validatorGet = Validator::make(['feedbackId' => $feedbackId], ['feedbackId' => 'required|integer|min:1']);

        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("FeedbackController delete error. %s ", $validatorGet->errors()->first()));
            return response()->json( $response);
        }

        FeedbackModel::where('feedbackId', $feedbackId)->update(array('status' => DELETED));
        return response()->json(new BaseResponse(true, null, null));
    }

    public function markAsRead(int $feedbackId)
    {
        $validatorGet = Validator::make(['feedbackId' => $feedbackId], ['feedbackId' => 'required|integer|min:1']);

        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("FeedbackController delete error. %s ", $validatorGet->errors()->first()));
            return response()->json( $response);
        }

        FeedbackModel::where('feedbackId', $feedbackId)->update(array('isRead' => FEEDBACK_MARK_AS_READ));
        return response()->json(new BaseResponse(true, null, null));
    }

    public function updateFeedbackReply(Request $request, int $feedbackId)
    {
        $validator = Validator::make($request->all(), [
            'feedbackId' => 'required|integer',
            'replyContent' => 'required|min:3|max:1000',
            'replyType' => 'required|integer',
        ]);

        if ($validator->fails())
        {
            throw new Exception(sprintf("FeedbackController updateFeedbackReply error. %s ", $validator->errors()->first()));
            return response()->json(['stat'=>false, 'errors'=>$validator->errors()]);
        }

        $today = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $userId = Auth::id();

        $feedbackReplyType = $request->input('replyType');
        $feedbackReplyContent = $request->input('replyContent');
        $userInfo = $request->input('userInfo');
        
        $updateFeedbacks = [
            'replyType' => $feedbackReplyType,
            'replyContent' => $feedbackReplyContent,
            'replyOn' => $today,
            'replyBy' => $userId
        ];

        $feedbackData = FeedbackModel::where('feedbackId', $feedbackId)->first();

        if($feedbackData)
        {
            if($feedbackReplyType == FEEDBACK_REPLY_TYPE_EMAIL && $userInfo != $feedbackData['email'])
            {
                $updateFeedbacks['replyInfo'] = $userInfo;
            }

            if($feedbackReplyType == FEEDBACK_REPLY_TYPE_PHONE_NUMBER && $userInfo != $feedbackData['phone'])
            {
                $updateFeedbacks['replyInfo'] = $userInfo;
            }
        }

        FeedbackModel::where('feedbackId', $feedbackId)->update($updateFeedbacks);
      
        if($feedbackReplyType == FEEDBACK_REPLY_TYPE_EMAIL)
        {
            Mail::to($userInfo)->send(new SendFeedbackReplyMail($feedbackReplyContent));
        }
        else
        {
            $this->sms->SendSms(SMS_SENDER, $userInfo, $feedbackReplyContent, ($feedbackData['countryCode'] ?? null));
        }
		
        return response()->json(new BaseResponse(true, null, null));
    }

    public function approveFeedback(int $feedbackId)
    {
        try
        {
            $validator = Validator::make([
                'feedbackId' => $feedbackId
            ], 
            [
                'feedbackId' => 'required|integer'
            ]);      
    
            if($validator->fails())
            {
                throw new Exception(sprintf("Validation failed in FeedbackController@approveFeedback %s ", $validator->errors()->first()));
            }
    
            $feedbackData = FeedbackModel::where('feedbackId', $feedbackId)->first();
            $todayTimeStamp = $this->datetimeHelpers->getCurrentUtcTimeStamp();
    
            if(!empty($feedbackData))
            {
                if($feedbackData['adId'])
                {
                    $advertisement = Advertisement::where('id', $feedbackData['adId'])->first();
    
                    if($advertisement['claimedRestaurant'] == NULL)
                    {
                        $email = $feedbackData['email'];
    
                        if($email)
                        {
                            $user = User::where('email', $email)->first();
                            if($user)
                            {
                                $userId = $user->uid;
    
                                if($user->type == USER_FOODIE)
                                {
                                    User::where('uid', $userId )->update(['type' => USER_NOT_ADMIN, 'modified_on' =>  $todayTimeStamp]);
                                }
                            }
                            else
                            {
                                $userModel = new User;
    
                                $userModel->phone = $feedbackData['restaurantTelephoneNumber'];
                                $userModel->name = $feedbackData['restaurantName'];
                                $userModel->company_name = $feedbackData['restaurantName'];	    
                                $userModel->status = STATUS_ACTIVE;
                                $userModel->type = USER_NOT_ADMIN;
                                $userModel->ip = $this->ipHelpers->clientIpAsLong();
                                $userModel->email = $feedbackData['email'];
                                $userModel->source_type = USER_REGISTRATION_SOURCE_TYPE_FEEDBACK;
                                $userModel->password = md5(mt_rand());
                                $userModel->nick_name = $feedbackData['restaurantName'];
                                $userModel->auto_login_hash = md5(mt_rand());
                                $userModel->created_on = $todayTimeStamp;
                                $userModel->save();
    
                                $userId = $userModel->uid;
                            }
                            Advertisement::where('id', $feedbackData['adId'])->update(['author_id' => $userId, 'claimedRestaurant' => 1, 'claimedOn' => $todayTimeStamp]);

                            $feedbackMailContent = "Vi har accepteret dine ændringer for ". $feedbackData['restaurantName']. " og de er nu synlige på";
                
                            Mail::to($feedbackData['email'])->send(new SendFeedbackReplyMail($feedbackMailContent));
                        }
                    }
                }
                $updateFeedbacks = [
                    'approvedBy' => Auth::id(),  
                    'approvedOn' => $todayTimeStamp,
                    'isRead' => FEEDBACK_MARK_AS_READ,
                ];
    
                FeedbackModel::where('feedbackId', $feedbackId)->update($updateFeedbacks);
            }
        } 
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in FeedbackController@approveFeedback Message is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }  
        return response()->json(new BaseResponse(true, null,null));
    }
}

?>
