<?php

namespace App\Http\Controllers\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Mockery\CountValidator\Exception;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use App\Models\Order\CategoryRestaurant;
use App\Models\Order\Category;

class CategoryRestaurantController extends Controller 
{
    
    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers)
    {   
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;   
    }

    public function updateOrCreate(Request $request) 
    {

        $rules = array(
            'categoryId' => 'required|integer|min:1',
            'categoryDescription' => 'required|string',
            'restaurantId' => 'required|integer|min:1'
        );

        if ($request->post('categoryRestaurantId') > 0) {
            $rules['categoryDescription'] = 'string';
        }

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("CategoryRestaurantController updateOrCreate error. %s ", $validator->errors()->first()));
        }

        $categoryId = $request->post('categoryId');
        $categoryDescription = trim($request->post('categoryDescription'));
        $restaurantId = $request->post('restaurantId');

        $categoryRestaurant = CategoryRestaurant::updateOrCreate(
            [
                'categoryId' => $categoryId,
                'restaurantId' => $restaurantId
            ],
            [
                'categoryId' => $categoryId,
                'categoryDescription' => $categoryDescription,
                'restaurantId' => $restaurantId,
                'userId' => Auth::id(),
                'createdOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                'ip' => $this->ipHelpers->clientIpAsLong(),
            ]
        );

        return response()->json(new BaseResponse(true, null, $categoryRestaurant));
    }

    public function get(int $restaurantId) 
    {
        $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|integer|min:1']);
        if ($validator->fails())
        {
            throw new Exception(sprintf("CategoryRestaurantController get error. %s ", $validator->errors()->first()));
        }

        $results = Category::with(['categoriesRestaurants' => function($query) use($restaurantId) {
            $query->where('restaurantId', $restaurantId);
        }])->with(['menuItemsCategories.menuItem' => function($query) use($restaurantId) {
            $query->where([['restaurantId', $restaurantId], ['status', STATUS_ACTIVE]]);
        }])->orderByRaw('categoryName')->get()->toArray();

        $response = [];

        if (!empty($results)) 
        {
            foreach ($results as $result) 
            {
                $hasMenuItem = false;
                $categoryRestaurantId = $categoryDescription = null;
                $categoriesRestaurant = $result['categories_restaurants'];
                $menuItemsCategories = $result['menu_items_categories'];
                $position = $result['position'];

                if (!empty($categoriesRestaurant)) 
                {
                    $categoryRestaurantId = $categoriesRestaurant[0]['categoryRestaurantId'];
                    $categoryDescription = $categoriesRestaurant[0]['categoryDescription'];
                    $position = $categoriesRestaurant[0]['position'];      
                }
   
                $hasMenuItem = false;
                if (!empty($menuItemsCategories)) 
                {
                    foreach($menuItemsCategories as $menuItemCategory)
                    {
                        if (!empty($menuItemCategory['menu_item']))
                        {
                            $hasMenuItem = true;
                            break;
                        }
                    }
                }

                if ($hasMenuItem)
                {
                    $data['position'] = $position;
                    $data['categoryId'] = $result['categoryId'];
                    $data['categoryName'] = $result['categoryName'];
                    $data['categoryRestaurantId'] = $categoryRestaurantId;
                    $data['categoryDescription'] = $categoryDescription;
    
                    $response[] = $data;
                }
            }

            if (!empty($response) && !is_null($response[0]['position']))
            {
                usort($response, function($a, $b){
                    return $a['position'] - $b['position'];
                });
            }
        }

        return response()->json(new BaseResponse(true, null, $response));
    }


    public function updateCategoryPositions(int $restaurantId, Request $request)
    {
        $rules = [
            'restaurantId' => 'required|integer|min:1',
            'sort.*.position' => 'required|integer',
            'sort.*.categoryId' => 'required|integer|min:1',
        ];

        $requestData = $request->all();
        $requestData['restaurantId'] = $restaurantId;

        $validator = Validator::make($requestData, $rules);

        if ($validator->fails())
        {
            throw new Exception(sprintf("CategoryRestaurantController updateCategoryPositions error. %s ", $validator->errors()->first()));
        }

        $postData = $request->post();
        $categories = CategoryRestaurant::where('restaurantId', $restaurantId)->get()->toArray();

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
                $categoryId = $data['categoryId'];

                CategoryRestaurant::updateOrCreate(
                    [
                        'categoryId' => $categoryId,
                        'restaurantId' => $restaurantId
                    ],
                    [
                        'categoryId' => $categoryId,
                        'restaurantId' => $restaurantId,
                        'userId' => Auth::id(),
                        'createdOn' => $this->datetimeHelpers->getCurrentUtcTimeStamp(),
                        'ip' => $this->ipHelpers->clientIpAsLong(),
                        'position' => $data['position']
                    ]
                );
            }
        }

        return response()->json(new BaseResponse(true, null, null));
    }
}

?>