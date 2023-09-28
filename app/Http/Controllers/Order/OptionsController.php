<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\Option;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Validator;
use Auth;

class OptionsController extends Controller {

    const MODEL = "App\Models\Order\Option";

    private $datetimeHelpers;
    private $ipHelpers;


    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
    }
    //use RESTActions;
    public function getByRestaurantId($restaurantId, $includeItems = null)
    {
        $whereArgument = [['restaurantId', $restaurantId], ['isDeleted', 0]];
        if($includeItems == 1){
            $response = Option::with('Items')->with('items.sizes')->where($whereArgument)->get();
        }else{
            $response = Option::where($whereArgument)->get();
        }
        return response()->json(new BaseResponse(true, null, $response));
    }

    public function getById($optionId, $includeItems = null)
    {
        $response = null;
        if ($includeItems == 1){
            $response = Option::with('Items')->where([['optionId', $optionId], ['isDeleted', 0]])->first();
        }else{
            $response = Option::where([['optionId', $optionId], ['isDeleted', 0]])->first();
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function add(Request $request)
    {
        $rules = array(
            'optionName' => 'required|string', // max 50000kb
            'restaurantId' => 'required|int'
        );

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("OptionController add error. %s ", $validator->errors()->first()));
        }

        $optionName = $request->post('optionName');
        $restaurantId = $request->post('restaurantId');
        $subOptionType = $request->post('suboptionType');

        $result = Option::where([['optionName', $optionName],['restaurantId', $restaurantId], ['isDeleted', 0]])->first();

        if($result != null){
            throw new Exception(sprintf("OptionController add error. %s ", 'option already exists'));
        }

        $option = new Option();
        $option->optionName = $optionName;
        $option->suboptionType = SUBOPTION_TYPE_OPTION;
        $option->userId = Auth::id();
        $option->restaurantId = $restaurantId;
        $option->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $option->isDeleted = 0;
        $option->ip = $this->ipHelpers->clientIpAsLong();
        $result = $option->save();

        return response()->json(new BaseResponse(true, null, $option));
    }

    public function delete(int $optionId)
    {
        $validatorGet = Validator::make(['optionId' => $optionId], ['optionId' => 'required|integer|min:1']);

        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("OptionController delete error. %s ", $validatorGet->errors()->first()));
            return response()->json( $response);
        }

        Option::where('optionId', $optionId)->update(array('isDeleted' => 1, 'lastModifiedOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp()));
        return response()->json(new BaseResponse(true, null, null));
    }

    public function update(Request $request, int $optionId)
    {
        $rules = array(
            'optionName' => 'required|string'
        );
        $validatorPost = Validator::make($request->all(), $rules);
        $validatorGet = Validator::make(['optionId' => $optionId], ['optionId' => 'required|integer|min:1']);

        if ($validatorPost->fails())
        {
            throw new Exception(sprintf("OptionController add error. %s ", $validatorPost->errors()->first()));
        }
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("OptionController add error. %s ", $validatorGet->errors()->first()));
        }
        $optionName = $request->post('optionName');

        Option::where('optionId', $optionId)->update(array('optionName' => $optionName));
        return response()->json(new BaseResponse(true, null, null));
    }
}
