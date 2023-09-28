<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\MenuItemOption;
use App\Models\Order\OptionItem;
use App\Models\Order\OptionItemSize;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Validator;
use Auth;

class OptionItemController extends Controller
{
    const MODEL = "App\Models\Order\OptionItem";

    private $datetimeHelpers;
    private $ipHelpers;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
    }

    public function getByOptionId($optionId) {
        $whereArgument = [['optionId', $optionId], ['isDeleted', 0]];

        $response = OptionItem::where($whereArgument)->orderby('optionItemPosition', 'asc')->get();
        return response()->json(new BaseResponse(true, null, $response));
    }

    public function getById($optionItemId){
        $whereArgument = [['optionItemId', $optionItemId], ['isDeleted', 0]];

        $response = OptionItem::where($whereArgument)->orderby('optionItemPosition', 'asc')->get();
        return response()->json(new BaseResponse(true, null, $response));
    }

    public function add(Request $request)
    {
        $rules = array(
            'optionItemName' => 'required|string', // max 50000kb
            'isDefault' => 'required|integer|min:0',
            'optionId' => 'required|integer|min:1',
            'sizes' => 'json'
        );

        $validator = Validator::make($request->post(), $rules);

        if ($validator->fails())
        {
            throw new Exception(sprintf("OptionItemController add error. %s ", $validator->errors()->first()));
        }

        $optionItemName = $request->post('optionItemName');
        $isDefault = $request->post('isDefault');
        $optionId = $request->post('optionId');
        $price = $request->post('price');
        $sizes = $request->post('sizes');
        if(empty($price))
        {
            $price = null;
        }
        else
        {
            $price = $price;
        }

        $maxPositionObj = OptionItem::select('optionItemPosition')->whereRaw('optionId = ? and optionItemPosition = (select max(optionItemPosition) from option_items where optionId = ? and isdeleted = 0)', [$optionId, $optionId])-> first();
        if($isDefault == 1){
            OptionItem::where([['optionId', $optionId],['isDefault', $isDefault]])
                ->update(array('isDefault' => 0));
        }

        $optionItemMaxPosition = 0;
        if(!empty($maxPositionObj))
        {
            $optionItemMaxPosition = $maxPositionObj->optionItemPosition;
        }

        $model = new OptionItem();
        $model->optionItemName = $optionItemName;
        $model->optionItemPosition = $optionItemMaxPosition + 1;
        $model->isDefault = $isDefault;
        $model->optionId = $optionId;
        $model->price = $price;
        $model->userId = Auth::id();
        $model->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $model->isDeleted = 0;
        $model->ip = $this->ipHelpers->clientIpAsLong();
        $result = $model->save();
        if(!empty($sizes))
        {
            $sizesObject = json_decode($sizes);
            foreach ($sizesObject as $sizeObject)
            {
                $optionItemSize = new OptionItemSize();
                $optionItemSize->optionItemId = $model->optionItemId;
                $optionItemSize->sizeId = $sizeObject->sizeId;
                $optionItemSize->price = $sizeObject->price;
                $optionItemSize->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $optionItemSize->userId = Auth::id();
                $optionItemSize->ip = $this->ipHelpers->clientIpAsLong();
                $optionItemSize->isDeleted = 0;
                $optionItemSize->save();
            }
        }

        $whereArgument = [['optionItemId', $model->optionItemId], ['isDeleted', 0]];

        $response = OptionItem::where($whereArgument)->with('sizes.size')->orderby('optionItemPosition', 'asc')->first();

        return response()->json(new BaseResponse(true, null, $response));
    }
    public function update(Request $request, int $optionItemId)
    {
        $rules = array(
            'optionItemName' => 'required|string', // max 50000kb
            'isDefault' => 'required|integer|min:0',
            'optionId' => 'required|integer|min:1',
            'sizes' => 'json'
        );

        $validatorPost = Validator::make($request->all(), $rules);
        $validatorGet = Validator::make(['optionItemId' => $optionItemId], ['optionItemId' => 'required|integer|min:1']);

        if ($validatorPost->fails())
        {
            throw new Exception(sprintf("OptionItemController add error. %s ", $validatorPost->errors()->first()));
        }
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("OptionItemController add error. %s ", $validatorGet->errors()->first()));
        }

        $optionItemName = $request->post('optionItemName');
        $isDefault = $request->post('isDefault');
        $optionId = $request->post('optionId');
        $price = $request->post('price');
        $sizes = $request->post('sizes');
        if(empty($price))
        {
            $price = null;
        }
        else
        {
            $price = $price;
        }

        if($isDefault == 1){
            OptionItem::where([['optionId', $optionId],['isDefault', $isDefault]])
                ->update(array('isDefault' => 0));
        }

        OptionItem::where('optionItemId', $optionItemId)->update(
            array('optionItemName' => $optionItemName
                , 'isDefault' => $isDefault
                , 'optionId' => $optionId
                , 'price' => $price));

        OptionItemSize::where('optionItemId', $optionItemId)->delete();
        if(!empty($sizes))
        {
            $sizesObject = json_decode($sizes);
            foreach ($sizesObject as $sizeObject)
            {
                $optionItemSize = new OptionItemSize();
                $optionItemSize->optionItemId = $optionItemId;
                $optionItemSize->sizeId = $sizeObject->sizeId;
                $optionItemSize->price = $sizeObject->price;
                $optionItemSize->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $optionItemSize->userId = Auth::id();
                $optionItemSize->ip = $this->ipHelpers->clientIpAsLong();
                $optionItemSize->isDeleted = 0;
                $optionItemSize->save();
            }
        }

        return response()->json(new BaseResponse(true, null, null));
    }

    public function delete(int $optionItemId)
    {
        $validatorGet = Validator::make(['optionItemId' => $optionItemId], ['optionItemId' => 'required|integer|min:1']);
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("OptionItemController delete error. %s ", $validatorGet->errors()->first()));
            return response()->json( $response);
        }
        
        OptionItem::where('optionItemId', $optionItemId)->update(array('isDeleted' => 1, 'lastModifiedOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp()));
        
        return response()->json(new BaseResponse(true, null, true));
    }

    public function updateOptionItemPositions(int $optionId, Request $request)
    {
        $rules = [
            'optionId' => 'required|integer|min:1',
            'sort.*.position' => 'required|integer',
            'sort.*.optionItemId' => 'required|integer|min:1',
        ];

        $requestData = $request->all();
        $requestData['optionId'] = $optionId;

        $validator = Validator::make($requestData, $rules);

        if ($validator->fails())
        {
            throw new Exception(sprintf("OptionItemController updateOptionItemPositions error. %s ", $validator->errors()->first()));
        }

        $postData = $request->post();
        $optionItems = OptionItem::where('optionId', $optionId)->get()->toArray();

        foreach ($optionItems as $optionItem)
        {
            foreach($postData['sort'] as $index => &$row)
            {
                if ($row['optionItemId'] == $optionItem['optionItemId'] && $row['position'] == $optionItem['optionItemPosition'])
                {
                    unset($row[$index]);
                    break;
                }
            }
        }
        
        if (!empty($postData['sort']))
        {
            foreach($postData['sort'] as $data)
            {
                $optionItemId = $data['optionItemId'];

                OptionItem::where('optionItemId', $optionItemId)->update([
                        'userId' => Auth::id(),
                        'lastModifiedOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                        'ip' => $this->ipHelpers->clientIpAsLong(),
                        'optionItemPosition' => $data['position']
                    ]
                );
            }
        }

        return response()->json(new BaseResponse(true, null, null));
    }
}
