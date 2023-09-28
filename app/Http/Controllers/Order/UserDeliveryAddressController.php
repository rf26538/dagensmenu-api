<?php

namespace App\Http\Controllers\Order;
use App\Http\Controllers\BaseResponse;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Libs\Helpers\Authentication;
use App\Models\Order\UserDeliveryAddress;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Location\PlaceModel;
use App\Models\User;
use Mockery\CountValidator\Exception;
use Validator;
use Auth;
use Log;

class UserDeliveryAddressController extends Controller
{
    const MODEL = "App\Models\Order\UserDeliveryAddress";

    private $datetimeHelpers;
    private $ipHelpers;
    private $authentication;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, Authentication $authentication) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->authentication = $authentication;
    }

    public function get(Request $request)
    {
        $orderType = $request->get('orderType');
        $userId = Auth::id();

        if($orderType == ORDER_ONLINE_TYPE_TAKE_AWAY)
        {
            $data['fullName'] = Auth::user()->name;
            $data['telephone'] = Auth::user()->phone;
            $data['countryCode'] = Auth::user()->countryCode;
        }
        else
        {
            $response = UserDeliveryAddress::where([['userId', $userId], ['isDeleted', 0]])->get()->toArray();

            if(!empty($response))
            {
                $allPostcodes = [];
                foreach($response as $row)
                {
                    $allPostcodes[$row['postcode']] = $row['postcode'];
                }

                $postcodePlaceNames = [];

                if (!empty($allPostcodes))
                {
                    $postcodePlaceNames = PlaceModel::whereIn('postcode', $allPostcodes)->get()->keyBy('postcode')->toArray();
                }

                foreach($response as &$resp)
                {
                    $resp['fullName'] = $resp['name'];
                    $resp['telephone'] = $resp['phoneNumber'];
                    $resp['locality'] = $postcodePlaceNames[$resp['postcode']]['locality'] ?? '';
                    unset($resp['name']);
                    unset($resp['phoneNumber']);
                }

                $data['deliveryAddressDetails'] = $response;
            }
            else
            {
                $user['fullName'] = Auth::user()->name;
                $user['telephone'] = Auth::user()->phone;
                $user['countryCode'] = Auth::user()->countryCode;
                $data['user'] = $user;
            }
        }

        return response()->json(new BaseResponse(true, null, $data));
    }
    public function add(Request $request)
    {
        $rules = array(
            'addressLine1' => 'required|string',
            'postcode' => 'required|int',
            'city' => 'required|string',
            'telephone' => 'required|string',
            'fullName' => 'required|string',
            'addressType' => 'required|int',
            'countryCode' => 'required|string'
        );

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("UserDeliveryAddressController add error. %s ", $validator->errors()->first()));
        }

        $addressLine1 = trim($request->post('addressLine1'));
        $addressLine2 = trim($request->post('addressLine2'));
        $addressLine3 = trim($request->post('addressLine3'));
        $postcode = trim($request->post('postcode'));
        $city = trim($request->post('city'));
        $telephone = trim($request->post('telephone'));
        $fullName = trim($request->post('fullName'));
        $addressType = trim($request->post('addressType'));
        $addressName = trim($request->post('addressName'));
        $countryCode = trim($request->post('countryCode'));
        $isPrimaryAddress = null;

        $userTelephoneNumber = User::where('uid', Auth::id())->first();

        if(is_null($userTelephoneNumber['phone']) || empty($userTelephoneNumber['phone']))
        {
            Auth::user()->phone = $telephone;
            Auth::user()->save();
        }

        $hasDeliveryAddress = UserDeliveryAddress::where([['userId', Auth::id()], ['isDeleted', 0]])->get()->toArray();

        if(empty($hasDeliveryAddress))
        {
            $isPrimaryAddress = 1;
        }

        $data = [
            'addressLine1' => $addressLine1,
            'addressLine2' => $addressLine2,
            'addressLine3' => $addressLine3,
            'postcode' => $postcode,
            'city' => $city,
            'addressType' => $addressType,
            'addressName' => $addressName,
            'isPrimaryAddress' => $isPrimaryAddress,
            'userId' =>  Auth::id(),
            'createdOn' =>  $this->datetimeHelpers->getCurrentUtcTimeStamp(),
            'isDeleted' =>  0,
            'ip' =>  $this->ipHelpers->clientIpAsLong(),
            'name' => $fullName,
            'countryCode' => $countryCode,
            'phoneNumber' => $telephone
        ];

        $userDeliveryAddressId = UserDeliveryAddress::insertGetId($data);
        $locationName = PlaceModel::where('postcode', $data['postcode'])->first();

        $response['deliveryAddressId'] = $userDeliveryAddressId;
        $response['locality'] = $locationName['locality'];

        return response()->json(new BaseResponse(true, null, $response));

    }

    public function addTelephoneNumber(Request $request)
    {
        $rules = array(
            'telephone' => 'required|string',
            'countryCode' => 'required|string',
            'fullName' => 'required|string'
        );

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("UserDeliveryAddressController addTelephoneNumber error. %s ", $validator->errors()->first()));
        }

        $countryCode = trim($request->post('countryCode'));
        $telephone = trim($request->post('telephone'));
        $fullName = trim($request->post('fullName'));

        if ($fullName)
        {
            Auth::user()->name = $fullName;
        }

        Auth::user()->phone = $telephone;
        Auth::user()->countryCode = $countryCode;
        Auth::user()->save();
        return response()->json(new BaseResponse(true, null, null));
    }

    public function delete(int $deliveryAddressId)
    {
        $validatorGet = Validator::make(['deliveryAddressId' => $deliveryAddressId], ['deliveryAddressId' => 'required|integer|min:1']);

        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("UserDeliveryAddressController delete error. %s ", $validatorGet->errors()->first()));
        }

        $userDeliveryAddress = UserDeliveryAddress::where(['deliveryAddressId' => $deliveryAddressId, 'isDeleted' => 0])->first();

        if($userDeliveryAddress)
        {
            if($this->userDeliveryAddressBelongsToUser($userDeliveryAddress->userId, $deliveryAddressId, 'delete'))
            {
                $userDeliveryAddress->isDeleted = 1;
                $userDeliveryAddress->save();
            }
        }
        else
        {
            throw new Exception(sprintf("UserDeliveryAddressController address %s not found", $deliveryAddressId));
        }
        return response()->json(new BaseResponse(true, null, null));
    }

    public function update(Request $request, int $deliveryAddressId)
    {

        // update authentication and authorization
        $rules = array(
            'addressLine1' => 'required|string',
            'postcode' => 'required|int',
            'city' => 'required|string',
            'telephone' => 'required|string',
            'fullName' => 'required|string',
            'countryCode' => 'required|string',
            'addressType' => 'required|int'
        );

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("UserDeliveryAddress add error. %s ", $validator->errors()->first()));
        }

        $realUpdate = $request->post('realUpdate') ? true : false;

        $data = [
            'addressLine1' => $request->post('addressLine1'),
            'addressLine2' => $request->post('addressLine2'),
            'addressLine3' => $request->post('addressLine3'),
            'postcode' => $request->post('postcode'),
            'city' => $request->post('city'),
            'addressType' => trim($request->post('addressType')),
            'addressName' => trim($request->post('addressName')),
            'isPrimaryAddress' => ($request->post('isPrimaryAddress')) ? 1 : null,
            'userId' =>  Auth::id(),
            'createdOn' =>  $this->datetimeHelpers->getCurrentUtcTimeStamp(),
            'isDeleted' =>  0,
            'ip' =>  $this->ipHelpers->clientIpAsLong(),
            'name' => $request->post('fullName'),
            'phoneNumber' => $request->post('telephone'),
            'countryCode' => $request->post('countryCode'),
        ];

        $validatorPost = Validator::make($request->all(), $rules);
        $validatorGet = Validator::make(['deliveryAddressId' => $deliveryAddressId], ['deliveryAddressId' => 'required|integer|min:1']);

        if ($validatorPost->fails())
        {
            throw new Exception(sprintf("UserDeliveryAddressController add error. %s ", $validatorPost->errors()->first()));
        }
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("UserDeliveryAddressController add error. %s ", $validatorGet->errors()->first()));
        }

        $userDeliveryAddress = UserDeliveryAddress::where(['deliveryAddressId' => $deliveryAddressId, 'isDeleted' => 0])->first();

        $response = [];

        if($userDeliveryAddress)
        {
            if($this->userDeliveryAddressBelongsToUser($userDeliveryAddress->userId, $deliveryAddressId, 'update'))
            {
                if($realUpdate)
                {
                    UserDeliveryAddress::where('deliveryAddressId', $deliveryAddressId)->update(['isDeleted' => 1]);

                    $userDeliveryAddressId = UserDeliveryAddress::insertGetId($data);
                    $locationName = PlaceModel::where('postcode', $data['postcode'])->first();

                    $response['deliveryAddressId'] = $userDeliveryAddressId;
                    $response['locality'] = $locationName['locality'];

                }

            }
        }
        else
        {
            throw new Exception(sprintf("UserDeliveryAddressController address %s not found", $deliveryAddressId));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function updateOrderPhoneNumber(int $addressId, int $phoneNumber, string $countryCode)
    {
        try
        {
            $validator = Validator::make([
                'addressId' => $addressId,
                'phoneNumber' => $phoneNumber,
                'countryCode' => $countryCode
            ], [
                'addressId' => 'required|int',
                'phoneNumber' => 'required|int',
                'countryCode' => 'required|string'
            ]);

            if ($validator->fails())
            {
                throw new Exception($validator->errors()->first());
            }

            UserDeliveryAddress::where('deliveryAddressId', $addressId)->update(['phoneNumber' => $phoneNumber, 'countryCode' => $countryCode]);
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in UserDeliveryAddressController@updateOrderPhoneNumber error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, true));
    }

    private function userDeliveryAddressBelongsToUser(int $userIdFromDb, int $deliveryAddressId, string $action)
    {
        if($this->authentication->doesEntityBelongsToUser($userIdFromDb))
        {
            return true;
        }
        else
        {
            throw new Exception(sprintf("UserDeliveryAddressController unauthenticated request to %s address %s by user %s", $action, $deliveryAddressId,  Auth::id()));
        }

    }
}
