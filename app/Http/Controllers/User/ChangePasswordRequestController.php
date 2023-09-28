<?php

namespace App\Http\Controllers\User;
use App\Models\User\ChangePasswordRequestModel;
use App\Models\User;
use App\Libs\Helpers\Authentication;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Shared\EatCommon\Helpers\StringHelper;
use App\Http\Controllers\BaseResponse;
use Exception;
use Auth;

class ChangePasswordRequestController extends Controller {
    private $ipHelpers;
    private $datetimeHelpers;
    private $translatorFactory;
    private $stringHelper;

    function __construct(TranslatorFactory $translatorFactory, DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, StringHelper $stringHelper) 
    {   
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->stringHelper = $stringHelper;
    }

    public function saveDetailsForChangePassword(Request $request)
    {
        $rules = array( 
            'email' => 'required|string',
        );

        $validator = Validator::make($request->post(), $rules);
            
        if($validator->fails())
        {
            throw new Exception(sprintf("ChangePasswordRequestController.saveDetailsForChangePassword error. %s ", $validator->errors()->first()));
        }
        
        $clientIp = $this->ipHelpers->clientIpAsLong();
        $lastHour = $this->datetimeHelpers->getCurrentUtcTimeStamp() - (24*60*60);
        $ipCondition = [
            ['ip', $clientIp],
            ['createdOn', '>', $lastHour]
        ];
        
        $ipCount = ChangePasswordRequestModel::where($ipCondition)->get()->count();
        
        if($ipCount < CHANGE_PASSWORD_MAXIMUM_ALLOWED_FROM_ONE_IP)
        {   
            
            $randomCharactersForToken = $this->stringHelper->generateRandomCharacters(8);
            $token = md5($randomCharactersForToken); 

            $email = $request->post('email');
            $condition = [
                ['email', $email]
            ]; 
            $result = User::select('uid','name', 'email')->where($condition)->first();
                
            if(!empty($result))
            {
                $uid = $result->uid;
                $name = $result->name;
                $email = $result->email;

                $ChangePasswordRequestModel = new ChangePasswordRequestModel;
                $ChangePasswordRequestModel->userId = $uid;
                $ChangePasswordRequestModel->name = $name;
                $ChangePasswordRequestModel->email = $email;
                $ChangePasswordRequestModel->token = $token;
                $ChangePasswordRequestModel->ip = $clientIp;
                $ChangePasswordRequestModel->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $ChangePasswordRequestModel->status = 0;
                $ChangePasswordRequestModel->isEmailSent = 0;
                $ChangePasswordRequestModel->save();
            }
        }
        return response()->json(new BaseResponse(true, null, null)); 
    }

    public function changePassword(Request $request)
    {
        $rules = array( 
            'password' => 'required|string'
        );
        
        $validator = Validator::make($request->post(), $rules);
        
        if($validator->fails())
        {
            throw new Exception(sprintf("ChangePasswordRequestController.changePassword error. %s ", $validator->errors()->first()));
        }

        $userId = Auth::id();

        if(!$userId)
        {
            $rules = array( 
                'token' => 'required|string'
            );
            $validator = Validator::make($request->post(), $rules);
        
            if($validator->fails())
            {
                throw new Exception(sprintf("ChangePasswordRequestController.changePassword error. %s ", $validator->errors()->first()));
            }

            $token = $request->post('token');

            $condition = [
                ['token', $token], 
                ['status', 0]
            ]; 
            $result = ChangePasswordRequestModel::select('userId')->where($condition)->first();
            $userId = $result->userId;
            ChangePasswordRequestModel::where('userId', $userId)->update(array('status' => 1));
        }
        
        $password = $request->post('password');
        $password = md5($password); 

        if(empty($userId))
        {
            return response()->json(new BaseResponse(false, null, null));
        }
        
        $result = User::where('uid', $userId)->update(array('password' => $password));

        return response()->json(new BaseResponse(true, null, null));
        
    }

    public function getUserDetails(Request $request)
    {
        $rules = array( 
            'token' => 'required|string',
        );

        $validator = Validator::make($request->post(), $rules);
            
        if($validator->fails())
        {
            throw new Exception(sprintf("ChangePasswordRequestController.getUserDetails error. %s ", $validator->errors()->first()));
        }

        $token = $request->post('token');
        $condition = [
            ['token', $token]
        ]; 
        $result = ChangePasswordRequestModel::select('name')->where($condition)->first();
        return response()->json(new BaseResponse(true, null, $result));
    }
}
?>