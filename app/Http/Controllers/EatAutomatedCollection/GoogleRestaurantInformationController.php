<?php

namespace App\Http\Controllers\EatAutomatedCollection;
use App\Shared\EatCommon\Link\Links;
use App\Models\EatAutomatedCollection\GoogleRestaurantInformation;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Mockery\CountValidator\Exception;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleRestaurantInformationController extends Controller
{
    private $datetimeHelper;
    private $links;

    public function __construct(DatetimeHelper $datetimeHelper, Links $links)
    {
        $this->datetimeHelper = $datetimeHelper;
        $this->links = $links;
    }
    
    public function get(Request $request)
    {
        $response = false;

        try
        {            
            $results = GoogleRestaurantInformation::select(['googleRestaurantId','restaurantName', 'restaurantAddress', 'restaurantType', 
            'navnelBnr', 'restaurantCreationError','googleImageError', 'dagensMenuId', 'restaurantPhoneNumber', 'commentBy', 'comment', 'commentOn'
            ])->whereNotNull('restaurantCreationError')->whereNull('restaurantCreationIgnoredOn')->whereNull('dagensMenuId');

            
            if($request->post('postCode'))
            {
                $results->where('restaurantAddress', 'LIKE', "%{$request->post('postCode')}%");
            }

            $totalNumberOfFailedRestaurant = count($results->get());

            $response = [
                'countOfGoogleRestaurantAdvertisementNotCreated' => $totalNumberOfFailedRestaurant,
                'results' => $results->limit(200)->orderBy('lastModifiedOn', 'DESC')->get()
            ];

            if(!empty($response['results']))
            {
                $pattern = "/[0-9]{4}/i";
                foreach($response['results'] as &$result)
                {
                    preg_match($pattern, $result['restaurantAddress'], $matchedValue);
                    $result['postCode'] = $matchedValue[0] ?? "";
                    $result['commentedOn'] = !empty($result['commentOn']) ? $this->datetimeHelper->getDanishFormattedDate($result['commentOn']) : null;
                    $result['commentedBy'] = $result->userDetail->name ?? null;
                    $result['createRestaurantUrl'] = $this->links->createUrl(PAGE_RESTAURANT_CREATE_EDIT_PROFILE, array(QUERY_SMILEYUNIQUEID => $result['navnelBnr']));
                    
                    if(isset($result['userDetail']))
                    {
                        unset($result['userDetail']);
                    }

                } 
            }

        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in GoogleRestaurantInformationController@get error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function updateGoogleRestarantInformation(int $googleRestaurantId, Request $request)
    {
        $response = null;
        try
        {
            $validator = Validator::make(['googleRestaurantId' => $googleRestaurantId], ['googleRestaurantId' => 'required|integer|min:1']);
            
            if ($validator->fails())
            {
                throw new Exception(sprintf("GoogleRestaurantInformationController@updateGoogleRestarantInformation error. %s ", $validator->errors()->first()));
            }
    
            $requestAll = $request->post();
    
            $data = ['restaurantCreationIgnoredOn' => $this->datetimeHelper->getCurrentUtcTimeStamp(), 'restaurantCreationIgnoredBy' => Auth::id()];
    
            if(isset($requestAll['comment']) && $requestAll['comment'])
            {
                $data = ['comment' => $requestAll['comment'], 'commentBy' => Auth::id(), 'commentOn' => $this->datetimeHelper->getCurrentUtcTimeStamp()];
                $response = ['commentedBy' => Auth::user()->name, 'commentedOn' => $this->datetimeHelper->getDanishFormattedDate($this->datetimeHelper->getCurrentUtcTimeStamp())];
            }
    
            GoogleRestaurantInformation::where('googleRestaurantId', $googleRestaurantId)->update($data);
        }
        catch(Exception $e)
        {
            dd("here");
            Log::critical(sprintf("Error found in GoogleRestaurantInformationController@updateGoogleRestarantInformation error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    
}