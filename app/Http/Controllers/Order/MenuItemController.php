<?php

namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Helpers\IPHelpers;
use App\Models\Order\MenuItem;
use App\Models\Order\MenuItemCategory;
use App\Models\Order\MenuItemOption;
use App\Models\Order\MenuItemSize;
use App\Models\Order\MenuItemTag;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use App\Models\Cache\AdvertisementCache;
use App\Shared\EatCommon\Amazon\AmazonS3;
use Validator;
use Auth;

class MenuItemController extends Controller
{
    private $datetimeHelpers;
    private $ipHelpers;
    private $amazonS3;

    function __construct(DatetimeHelper $datetimeHelpers, IPHelpers $ipHelpers, AmazonS3 $amazonS3) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->ipHelpers = $ipHelpers;
        $this->amazonS3 = $amazonS3;
    }

    public function getByRestaurantId($restaurantId){
        $whereArgument = [['restaurantId', $restaurantId], ['status', STATUS_ACTIVE]];
        $resultFromDb =  MenuItem::with([
            'categories.category',
            'categories.categoryRestaurant'
        ])->where($whereArgument)->get()->toArray();

        if (count($resultFromDb) > 1)
        {
            usort($resultFromDb, function($a, $b){

                if (!empty($a['categories'][0]['category_restaurant']) && !is_null($a['categories'][0]['category_restaurant']['position']))
                {
                    $currentPositionA = $a['categories'][0]['category_restaurant']['position'];
                }
                else
                {
                    $currentPositionA = $a['categories'][0]['category']['position'];
                }
    
                if (!empty($b['categories'][0]['category_restaurant']) && !is_null($b['categories'][0]['category_restaurant']['position']))
                {
                    $currentPositionB = $b['categories'][0]['category_restaurant']['position'];
                }
                else
                {
                    $currentPositionB = $b['categories'][0]['category']['position'];
                }

                return $currentPositionA < $currentPositionB ? -1 : 1;
            });
        }

        $result = array();
        $isCategoryPushed = [];
        foreach ($resultFromDb as $row)
        {
            if(!empty($row['categories']) && count($row['categories']) > 0)
            {
                $categoryName = $row['categories'][0]['category']['categoryName'];
                $categoryId = $row['categories'][0]['category']['categoryId'];

                $categoryData = [
                    'categoryId' => $categoryId,
                ];

                $menuItem = array();
                $menuItem["menuItemId"] = $row['menuItemId'];
                $menuItem["menuItemName"] = $row['menuItemName'];
                $menuItem["description"] = $row['description'];
                $menuItem["price"] = $row['price'];
                $menuItem["categoryName"] = $categoryName;
                $menuItem["editLink"] = sprintf('/add_menu_card?ad-id=%s&m-id=%s', $row['restaurantId'], $row['menuItemId']) ;
                $menuItem["copyLink"] = sprintf('/add_menu_card?ad-id=%s&s-id=%s', $row['restaurantId'], $row['menuItemId']) ;
                $menuItem["imageWebPath"] = null;
                $menuItem["position"] = $row['categories'][0]['position'];

                if(!empty($row['images']))
                {
                    $imagesObject = json_decode($row['images'], true);
                    if(!empty($imagesObject))
                    {
                        foreach ($imagesObject as $imageObject)
                        {
                            if(!empty($imageObject['imageName']))
                            {
                                $imageWebPath = $this->amazonS3::GetWebPath(env('AMAZON_IMAGES_BUCKET'), $imageObject['imageFolder'], $imageObject['imageName']);
                                $menuItem["imageWebPath"] = $imageWebPath;
                                break;
                            }
                        }
                    }
                }

                
                $isNewCategory = false;

                if (empty($result))
                {
                    $isNewCategory = true;
                }
                else if (!in_array($categoryId, $isCategoryPushed))
                {
                    $isNewCategory = true;
                }
                else if (!empty($result))
                {
                    foreach($result as &$categoryRow)
                    {
                        if (!empty($categoryRow) && $categoryRow['categoryId'] == $categoryId)
                        {
                            array_push($categoryRow['menuItems'], $menuItem);
                            break;
                        }
                    }
                }
                
                if ($isNewCategory)
                {
                    $categoryData['menuItems'][] = $menuItem;
                    
                    $result[] = $categoryData;
                    $isCategoryPushed[] = $categoryId;
                }
            }
        }

        foreach($result as &$categories)
        {
            if (!empty($categories['menuItems']))
            {
                usort($categories['menuItems'], function($a, $b){

                    if (!is_null($a['position']))
                    {
                        return $a['position'] < $b['position'] ? -1 : 1;
                    }
                    else
                    {
                        return $a['menuItemId'] < $b['menuItemId'] ? -1 : 1;
                    }

                });
            }
        }

        return response()->json(new BaseResponse(true, null, $result));
    }


    public function getByMenuItemId($menuItemId){
        $whereArgument = [['menuItemId', $menuItemId], ['status', STATUS_ACTIVE]];
        $result = MenuItem::with('categories.category')->with(['options.option' => function($query) {
            $query->where('isDeleted', 0);
        }])->with('tags.tag')->with('sizes.size')->where($whereArgument)->first();

        if(!empty($result) && !empty($result->images))
        {
            if(!empty($result->options))
            {
                foreach($result->options as $optionKey => $options)
                {
                    if(empty($options->option))
                    {
                        unset($result->options[$optionKey]);
                    }
                }
            }

            if($result->images != "null")
            {
                $imagesObject = json_decode($result->images);

                foreach ($imagesObject as $imageObject)
                {
                    $imageWebPath = $this->amazonS3::GetWebPath(env('AMAZON_IMAGES_BUCKET'), $imageObject->imageFolder, $imageObject->imageName);
                    $imageObject->imageWebPath = $imageWebPath;
                }
                $result->images = json_encode($imagesObject);
            }

        }
        return response()->json(new BaseResponse(true, null, $result));
    }

    public function add(Request $request)
    {

        $rules = array(
            'menuItemName' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|integer',
            'images' => 'json',
            'restaurantId' => 'required|integer|min:1',
            'categoryId' => 'required|integer|min:1',
            'tags' => 'json',
            'options' => 'json',
            'sizes' => 'json'
        );

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("MenuItemController add error. %s ", $validator->errors()->first()));
        }

        $menuItemName = $request->post('menuItemName');
        $description = $request->post('description');
        $price = $request->post('price');
        $images = $request->post('images');
        $restaurantId = $request->post('restaurantId');
        $categoryId = $request->post('categoryId');
        $tags = $request->post('tags');
        $options = $request->post('options');
        $sizes = $request->post('sizes');

        $menuItemCategoriesInMultidimension = MenuItem::with(['categories' => function($query) use($categoryId) {
            $query->where('categoryId', $categoryId)->max('position');
        }])->where('restaurantId', $restaurantId)->get()->pluck('categories')->toArray();

        $menuItemCategoryPosition = null;

        if (!empty($menuItemCategoriesInMultidimension))
        {
            foreach ($menuItemCategoriesInMultidimension as $menuItemCategories)
            {
                if (!empty($menuItemCategories))
                {
                    foreach ($menuItemCategories as $menuItemCategory)
                    {
                        if (!is_null($menuItemCategory['position']) && $menuItemCategory['position'] > intval($menuItemCategoryPosition))
                        {
                            $menuItemCategoryPosition = $menuItemCategory['position'];
                        }
                    }
                }
            }

            if (!is_null($menuItemCategoryPosition))
            {
                $menuItemCategoryPosition += 1;
            }
        }

        $menuItem = new MenuItem();
        $menuItem->menuItemName = $menuItemName;
        $menuItem->description = $description;
        $menuItem->price = $price;
        $menuItem->restaurantId = $restaurantId;
        $menuItem->images = $images;

        $menuItem->userId = Auth::id();
        $menuItem->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $menuItem->status = STATUS_ACTIVE;
        $menuItem->ip = $this->ipHelpers->clientIpAsLong();
        $menuItem->save();

        $menuItemCategory = new MenuItemCategory();
        $menuItemCategory->menuItemId = $menuItem->menuItemId;
        $menuItemCategory->categoryId = $categoryId;
        $menuItemCategory->userId = Auth::id();
        $menuItemCategory->position = $menuItemCategoryPosition;
        $menuItemCategory->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
        $menuItemCategory->ip = $this->ipHelpers->clientIpAsLong();
        $menuItemCategory->save();

        $counter = 0;

        if(!empty($options))
        {
            $optionsObject = json_decode($options);
            foreach ($optionsObject as $optionObject)
            {
                $menuItemOption = new MenuItemOption();
                $menuItemOption->menuItemId = $menuItem->menuItemId;
                $menuItemOption->optionId = $optionObject->optionId;
                $menuItemOption->isMultipleChoice = empty($optionObject->isMultipleChoice) ? 0 : $optionObject->isMultipleChoice;
                $menuItemOption->maxItemsSelectable =empty($optionObject->maxItemsSelectable) ? null : $optionObject->maxItemsSelectable;
                $menuItemOption->isRequired = empty($optionObject->isRequired) ? 0 : $optionObject->isRequired;
                $menuItemOption->addPriceToMenuItem = empty($optionObject->addPriceToMenuItem) ? 0 : $optionObject->addPriceToMenuItem;
                $menuItemOption->optionPosition = ++$counter;

                $menuItemOption->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $menuItemOption->userId = Auth::id();
                $menuItemOption->ip = $this->ipHelpers->clientIpAsLong();
                $menuItemOption->save();
            }
        }


        if(!empty($tags))
        {
            $tagsObject = json_decode($tags);
            foreach ($tagsObject as $tagId)
            {
                $menuItemTag = new MenuItemTag();
                $menuItemTag->menuItemId = $menuItem->menuItemId;
                $menuItemTag->tagId = $tagId;

                $menuItemTag->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $menuItemTag->userId = Auth::id();
                $menuItemTag->ip = $this->ipHelpers->clientIpAsLong();
                $menuItemTag->save();
            }
        }
        if(!empty($sizes))
        {
            $sizesObject = json_decode($sizes);
            foreach ($sizesObject as $sizeObject)
            {
                $menuItemSize = new MenuItemSize();
                $menuItemSize->menuItemId = $menuItem->menuItemId;
                $menuItemSize->sizeId = $sizeObject->sizeId;
                $menuItemSize->price = $sizeObject->price;
                $menuItemSize->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $menuItemSize->userId = Auth::id();
                $menuItemSize->ip = $this->ipHelpers->clientIpAsLong();
                $menuItemSize->save();
            }
        }

        AdvertisementCache::where('restaurantId', $restaurantId)->update(['restaurantMenuItems' => null]);
        

        return response()->json(new BaseResponse(true, null, $menuItem));
    }
    public function update(Request $request, int $menuItemId)
    {

        $validatorGet = Validator::make(['menuItemId' => $menuItemId], ['menuItemId' => 'required|integer|min:1']);
        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("MenuItemController add error. %s ", $validatorGet->errors()->first()));
        }

        $rules = array(
            'menuItemName' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|integer',
            'images' => 'json',
            'restaurantId' => 'required|integer|min:1',
            'categoryId' => 'required|integer|min:1',
            'tags' => 'json',
            'options' => 'json',
            'sizes' => 'json'
        );

        $validator = Validator::make($request->post(), $rules);
        if ($validator->fails())
        {
            throw new Exception(sprintf("MenuItemController add error. %s ", $validator->errors()->first()));
        }

        $menuItemName = $request->post('menuItemName');
        $description = $request->post('description');
        $price = $request->post('price');
        $images = $request->post('images');
        $restaurantId = $request->post('restaurantId');
        $categoryId = $request->post('categoryId');
        $tags = $request->post('tags');
        $options = $request->post('options');
        $sizes = $request->post('sizes');

        MenuItem::where('menuItemId', $menuItemId)->update(
            array('menuItemName' => $menuItemName, 'description' => $description, 'price' => $price,
                'restaurantId' => $restaurantId, 'images' => $images));

        MenuItemCategory::where('menuItemId', $menuItemId)->update(
            array('categoryId' => $categoryId));

        MenuItemOption::where('menuItemId', $menuItemId)->delete();
        $counter = 0;
        if(!empty($options))
        {
            $optionsObject = json_decode($options);
            foreach ($optionsObject as $optionObject)
            {
                $menuItemOption = new MenuItemOption();
                $menuItemOption->menuItemId = $menuItemId;
                $menuItemOption->optionId = $optionObject->optionId;
                $menuItemOption->isMultipleChoice = empty($optionObject->isMultipleChoice) ? 0 : $optionObject->isMultipleChoice;
                $menuItemOption->maxItemsSelectable =empty($optionObject->maxItemsSelectable) ? null : $optionObject->maxItemsSelectable;
                $menuItemOption->isRequired = empty($optionObject->isRequired) ? 0 : $optionObject->isRequired;
                $menuItemOption->addPriceToMenuItem = empty($optionObject->addPriceToMenuItem) ? 0 : $optionObject->addPriceToMenuItem;
                $menuItemOption->oneItemMultipleSelectionAllowed = empty($optionObject->oneItemMultipleSelection) ? 0 : $optionObject->oneItemMultipleSelection;
                $menuItemOption->maxQuantityAllowedOfOneOptionItem = empty($optionObject->maxQuantityAllowedOfOneOptionItem) ? null : $optionObject->maxQuantityAllowedOfOneOptionItem;
                $menuItemOption->optionPosition = ++$counter;
                $menuItemOption->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $menuItemOption->userId = Auth::id();
                $menuItemOption->ip = $this->ipHelpers->clientIpAsLong();
                $menuItemOption->save();
            }
        }

        MenuItemTag::where('menuItemId', $menuItemId)->delete();

        if(!empty($tags))
        {
            $tagsObject = json_decode($tags);
            foreach ($tagsObject as $tagId)
            {
                $menuItemTag = new MenuItemTag();
                $menuItemTag->menuItemId = $menuItemId;
                $menuItemTag->tagId = $tagId;

                $menuItemTag->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $menuItemTag->userId = Auth::id();
                $menuItemTag->ip = $this->ipHelpers->clientIpAsLong();
                $menuItemTag->save();
            }
        }

        MenuItemSize::where('menuItemId', $menuItemId)->delete();

        if(!empty($sizes))
        {
            $sizesObject = json_decode($sizes);
            foreach ($sizesObject as $sizeObject)
            {
                $menuItemSize = new MenuItemSize();
                $menuItemSize->menuItemId = $menuItemId;
                $menuItemSize->sizeId = $sizeObject->sizeId;
                $menuItemSize->price = $sizeObject->price;
                $menuItemSize->createdOn = $this->datetimeHelpers->getCurrentUtcTimeStamp();
                $menuItemSize->userId = Auth::id();
                $menuItemSize->ip = $this->ipHelpers->clientIpAsLong();
                $menuItemSize->save();
            }
        }

        AdvertisementCache::where('restaurantId', $restaurantId)->update(['restaurantMenuItems' => null]);

        return response()->json(new BaseResponse(true, null, true));
    }

    public function delete(int $menuItemId)
    {
        $validatorGet = Validator::make(['menuItemId' => $menuItemId], ['menuItemId' => 'required|integer|min:1']);

        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("MenuItemController delete error. %s ", $validatorGet->errors()->first()));
        }

        MenuItem::where('menuItemId', $menuItemId)->update(array('status' => STATUS_DELETED));

        return response()->json(new BaseResponse(true, null, null));
    }

    public function updateMenuItemPositions(int $categoryId, int $restaurantId, Request $request)
    {
        $rules = [
            'restaurantId' => 'required|integer|min:1',
            'categoryId' => 'required|integer|min:1',
            'sort' => 'required',
            'sort.*.position' => 'required|integer|min:1',
            'sort.*.menuItemId' => 'required|integer|min:1',
        ];

        $requestData = $request->all();
        $requestData['restaurantId'] = $restaurantId;
        $requestData['categoryId'] = $categoryId;

        $validator = Validator::make($requestData, $rules);

        if ($validator->fails())
        {
            throw new Exception(sprintf("MenuItemController updateMenuItemPositions error. %s ", $validator->errors()->first()));
        }
        $postData = $request->post();
        
        $menuItemCategoriesInMultidimension = MenuItem::with(['categories' => function($query) use($categoryId) {
            $query->where('categoryId', $categoryId);
        }])->where('restaurantId', $restaurantId)->get()->pluck('categories');

        if (!empty($menuItemCategoriesInMultidimension))
        {
            foreach ($menuItemCategoriesInMultidimension as $menuItemCategories)
            {
                if (!empty($menuItemCategories))
                {
                    foreach($menuItemCategories as $menuItemCategory)
                    {
                        foreach($postData['sort'] as $index => $row)
                        {
                            if ($row['menuItemId'] == $menuItemCategory['menuItemId'] && $row['position'] == $menuItemCategory['position'])
                            {
                                unset($postData['sort'][$index]);
                                break;
                            }
                        }
                    }
                }
            }

            if (!empty($postData['sort']))
            {
                foreach($postData['sort'] as $data)
                {
                    $condition = [
                        ['categoryId', $categoryId],
                        ['menuItemId', $data['menuItemId']]
                    ];

                    MenuItemCategory::where($condition)->update(['position' => $data['position']]);
                }
            }
        }

        AdvertisementCache::where('restaurantId', $restaurantId)->update(['restaurantMenuItems' => null]);

        return response()->json(new BaseResponse(true, null, null));
    }

    public function getMenuItemNameByOptionId(int $optionId)
    {
        $validatorGet = Validator::make(['optionId' => $optionId], ['optionId' => 'required|integer|min:1']);

        if ($validatorGet->fails())
        {
            throw new Exception(sprintf("MenuItemController getMenuItemNameByOptionId error. %s ", $validatorGet->errors()->first()));
        }

        $condition = [
            ['optionId', $optionId]
        ];

        $subQuery = MenuItemOption::select('menuItemId')->where($condition)->get()->toArray();        
        $response = MenuItem::select('menuItemName')->whereIn('menuItemId', $subQuery)->get()->toArray();
        
        return response()->json(new BaseResponse(true, null, $response));
    }
}
