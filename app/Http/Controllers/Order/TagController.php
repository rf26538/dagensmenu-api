<?php

namespace App\Http\Controllers\Order;
use App\Http\Controllers\BaseResponse;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\Tag;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Mockery\CountValidator\Exception;
use Validator;
use Auth;

class TagController extends Controller
{
    const MODEL = "App\Models\Order\Tag";

    private $datetimeHelpers;
    private $ipHelpers;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
    }

    public function get()
    {
        $response = Tag::where('isDeleted', 0)->get();
        return response()->json(new BaseResponse(true, null, $response));
    }
    public function getById(int $tagId)
    {
        $response = Tag::where([['tagId', $tagId], ['isDeleted', 0]])->first();
        return response()->json(new BaseResponse(true, null, $response));
    }
    public function add(Request $request)
    {
        $rules = array(
            'tagName' => 'required|string' // max 50000kb
        );

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("TagController add error. %s ", $validator->errors()->first()));
        }

        $tagName = $request->post('tagName');

        $tag = new Tag();
        $tag->tagName = $tagName;
        $tag->userId = Auth::id();
        $tag->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $tag->isDeleted = 0;
        $tag->ip = $this->ipHelpers->clientIpAsLong();
        $result = $tag->save();

        return response()->json(new BaseResponse(true, null, $tag));

    }
    public function delete(int $tagId)
    {
        $validatorGet = Validator::make(['tagId' => $tagId], ['tagId' => 'required|integer|min:1']);

        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("TagController delete error. %s ", $validatorGet->errors()->first()));
        }

        Tag::where('tagId', $tagId)->update(array('isDeleted' => 1));
        return response()->json(new BaseResponse(true, null, null));
    }
    public function update(Request $request, int $tagId)
    {
        $rules = array(
            'tagName' => 'required|string',
        );

        $validatorPost = Validator::make($request->all(), $rules);
        $validatorGet = Validator::make(['tagId' => $tagId], ['tagId' => 'required|integer|min:1']);

        if ($validatorPost->fails())
        {
            throw new Exception(sprintf("TagController add error. %s ", $validatorPost->errors()->first()));
        }
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("TagController add error. %s ", $validatorGet->errors()->first()));
        }
        $tagName = $request->post('tagName');

        Tag::where('tagId', $tagId)->update(array('tagName' => $tagName));
        return response()->json(new BaseResponse(true, null, null));
    }
}