<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\Category;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Validator;
use Auth;

class CategoryController extends Controller
{
    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
    }

    public function get(){
        $response = Category::where('status', STATUS_ACTIVE)->orderByRaw(
            'CASE WHEN position IS NOT NULL THEN position END ASC',
            'CASE WHEN position IS NULL THEN categoryName END DESC'
        )->get();

        return response()->json(new BaseResponse(true, null, $response));
    }


    public function getById($id){
        $response = Category::where([['categoryId', $id],['status', STATUS_ACTIVE]])->first();
        return response()->json(new BaseResponse(true, null, $response));
    }

    public function add(Request $request)
    {
        $rules = array(
            'categoryName' => 'required|string' // max 50000kb
        );

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("OptionController add error. %s ", $validator->errors()->first()));
        }

        $categoryName = $request->post('categoryName');

        $result = Category::where([['categoryName', $categoryName], ['status', STATUS_ACTIVE]])->first();

        if($result != null){
            throw new Exception(sprintf("CategoryController add error. %s ", 'category already exists'));
        }

        $lastPositionOfCategory = Category::select('position')->orderBy('position', 'desc')->first();
        $position = null;
        if (!empty($lastPositionOfCategory) && !is_null($lastPositionOfCategory->position))
        {
            $position = $lastPositionOfCategory->position + 1;
        }

        $model = new Category();
        $model->categoryName = $categoryName;
        $model->userId = Auth::id();
        $model->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $model->status = STATUS_ACTIVE;
        $model->position = $position;
        $model->ip = $this->ipHelpers->clientIpAsLong();
        $result = $model->save();

        return response()->json(new BaseResponse(true, null, $model));
    }

    public function update(Request $request, int $categoryId)
    {
        $rules = array(
            'categoryName' => 'required|string'
        );
        $validatorPost = Validator::make($request->all(), $rules);
        $validatorGet = Validator::make(['categoryId' => $categoryId], ['categoryId' => 'required|integer|min:1']);

        if ($validatorPost->fails())
        {
            throw new Exception(sprintf("CategoryController add error. %s ", $validatorPost->errors()->first()));
        }
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("CategoryController add error. %s ", $validatorGet->errors()->first()));
        }
        $categoryName = $request->post('categoryName');

        Category::where('categoryId', $categoryId)->update(array('categoryName' => $categoryName));
        return response()->json(new BaseResponse(true, null, null));
    }

    public function updateCategoryDescription(Request $request, int $categoryId)
    {
        $rules = array(
            'categoryDescription' => 'required|string',
            'categoryId' => 'required|integer|min:1'
        );

        $requestData = $request->all();
        $requestData['categoryId'] = $categoryId;

        if (isset($requestData['isUpdate']) && $requestData['isUpdate'])
        {
            $rules['categoryDescription'] = 'string';
        }

        $validator = Validator::make($requestData, $rules);

        if ($validator->fails())
        {
            throw new Exception(sprintf("CategoryController updateCategoryDescription error. %s ", $validator->errors()->first()));
        }

        $categoryDescription = $request->post('categoryDescription');

        Category::where('categoryId', $categoryId)->update(array('categoryDescription' => $categoryDescription));
        return response()->json(new BaseResponse(true, null, null));
    }

    public function delete(int $categoryId)
    {
        $validatorGet = Validator::make(['categoryId' => $categoryId], ['categoryId' => 'required|integer|min:1']);

        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("CategoryController delete error. %s ", $validatorGet->errors()->first()));
            return response()->json( $response);
        }

        Category::where('categoryId', $categoryId)->update(array('status' => STATUS_DELETED));
        return response()->json(new BaseResponse(true, null, null));
    }

    public function updateCategoryPositions(Request $request)
    {
        $rules = [
            'sort.*.position' => 'required|integer',
            'sort.*.categoryId' => 'required|integer|min:1',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails())
        {
            throw new Exception(sprintf("CategoryController updateCategoryPositions error. %s ", $validator->errors()->first()));
        }

        $postData = $request->post();
        $categories = Category::where('status', STATUS_ACTIVE)->get()->toArray();

        foreach ($categories as $category)
        {
            foreach($postData['sort'] as $index => &$row)
            {
                if ($row['categoryId'] == $category['categoryId'] && $row['position'] == $category['position'])
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
                Category::where('categoryId', $data['categoryId'])->update(['position' => $data['position']]);
            }
        }

        return response()->json(new BaseResponse(true, null, null));
    }
}