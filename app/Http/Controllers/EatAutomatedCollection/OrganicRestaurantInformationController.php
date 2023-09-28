<?php

namespace App\Http\Controllers\EatAutomatedCollection;
use App\Models\EatAutomatedCollection\OrganicRestaurantInformation;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Link\Links;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class OrganicRestaurantInformationController extends Controller
{
    
    private $datetimeHelper;
    private $links;

    public function __construct(DatetimeHelper $datetimeHelper, Links $links)
    {
        $this->datetimeHelper = $datetimeHelper;
        $this->links = $links;
    }

    public function get()
    {
        $response = false;

        try
        {         
            $results = OrganicRestaurantInformation::select(['organicRestaurantInformationId', 'restaurantName', 'streetAddress', 'postcodeAndDistrict', 'postcode', 'city', 'restaurantLink', 'restaurantCreationIgnoredOn', 'restaurantCreationIgnoredBy', 'restaurantCreationIgnoredReason'])->whereNull('dagensmenuId')->where('typeOfRestaurant', ORGANIC_RESTAURANT_TYPE);

            $totalOrganicResturantWeDontHave = count($results->get());
            
            $response = [
                'countOfGoogleRestaurantAdvertisementNotCreated' => $totalOrganicResturantWeDontHave,
                'results' => $results->orderBy('lastUpdatedOn', 'DESC')->get()
            ];

            if(!empty($response))
            {
                foreach($response['results'] as &$result)
                {
                    $restaurantName = sprintf('%s, %s', $result['restaurantName'], $result['streetAddress']);
                    $result['ignoredOn'] = !empty($result['restaurantCreationIgnoredOn']) ? $this->datetimeHelper->getDanishFormattedDate($result['restaurantCreationIgnoredOn']) : null;
                    $result['ignoredBy'] = $result->userDetail->name ?? null;
                    $result['googleUrl'] = sprintf('%s%s', GOOGLE_RESTAURANT_SEARCH_URL, urlencode($restaurantName)); 
                    $result['createRestaurantUrl'] = $this->links->createUrl(PAGE_RESTAURANT_CREATE_EDIT_PROFILE, array());
                    
                    if(isset($result['userDetail']))
                    {
                        unset($result['userDetail']);
                    }
                } 

            }

        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in OrganicRestaurantInformationController@get error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

public function update(int $organicRestaurantInformationId, Request $request)
    {
        $response = null;
        try
        {        
            $validator = Validator::make(['organicRestaurantInformationId' => $organicRestaurantInformationId], ['organicRestaurantInformationId' => 'required|integer|min:1']);
        
            if ($validator->fails())
            {
                throw new Exception(sprintf("OrganicRestaurantInformationController update error. %s ", $validator->errors()->first()));
            }

            $requestAll = $request->post();
            
            if(isset($requestAll['ignoreReason']) && $requestAll['ignoreReason'])
            {
                $data = [
                    'restaurantCreationIgnoredOn' => $this->datetimeHelper->getCurrentUtcTimeStamp(), 
                    'restaurantCreationIgnoredBy' => Auth::id(),
                    'restaurantCreationIgnoredReason' => $requestAll['ignoreReason']
                ];

                $response = ['ignoredBy' => Auth::user()->name, 'ignoredOn' => $this->datetimeHelper->getDanishFormattedDate($this->datetimeHelper->getCurrentUtcTimeStamp())];
            }

            if(isset($requestAll['dagensmenuId']) && $requestAll['dagensmenuId'])
            {
                $data = ['dagensmenuId' => $requestAll['dagensmenuId'], 
                'lastUpdatedOn' => $this->datetimeHelper->getCurrentUtcTimeStamp(),
                'dagensmenuIdUpdatedBy' => Auth::id()
                ];
            }
            
            OrganicRestaurantInformation::where('organicRestaurantInformationId', $organicRestaurantInformationId)->update($data);
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in OrganicRestaurantInformationController@get error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

 

    
}