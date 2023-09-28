<?php

namespace App\Http\Controllers\FindSmiley;
use App\Models\FindSmiley\FindSmileyModel;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Http\Controllers\BaseResponse;
use Illuminate\Support\Facades\Log;
use App\Shared\EatCommon\Link\Links;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Restaurant\Advertisement;

class FindSmileyController extends Controller
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
            $condition = [['restaurantExistsInGoogle', 0], ['googleFetchIgnoredOn', null]];

            if($request->post('postCode'))
            {
                $condition = [['restaurantExistsInGoogle', 0], ['googleFetchIgnoredOn', null], ['postNr', $request->post('postCode')]];
            }

            $results = FindSmileyModel::select(['navnelBnr', 'navn1', 'adresse1', 'postNr', 'city','googleFailureMessage', 'googleFetchRetryCount',
                'googleFailureReason', 'googleDataFetchedOn', 'comment', 'commentBy', 'commentOn'
            ])->where($condition)->orderBy('googleDataFetchedOn', 'DESC');

            $totalNumberOfFindSmiley = count($results->get());

            $data = [
                'totalNumberOfFindSmiley' => $totalNumberOfFindSmiley,
                'results' => $results->limit(200)->orderBy('googleDataFetchedOn', 'DESC')->get()
            ];
 
            if(!empty($data))
            {
                foreach($data['results'] as &$result)
                {
                    if(strpos($result['googleFailureMessage'], 'Stack'))
                    {
                        $stackPosition = strpos($result['googleFailureMessage'], 'Stack');
                        $textWithoutStack = substr($result['googleFailureMessage'], 0, $stackPosition);
                        $result['googleFailureMessage'] = str_replace("Error in GoogleRestaurantInformation.getRestaurantInformation", "", $textWithoutStack);
                    }
                    else if(strpos($result['googleFailureMessage'], 'file_get_contents'))
                    {
                        $result['googleFailureMessage'] = "File content not found";
                    }

                    $restauranntName = sprintf('%s %s Denmark', $result['navn1'], $result['adresse1']);
                    $result['lastRetryOn'] = $this->datetimeHelper->getDanishFormattedDate($result['googleDataFetchedOn']);
                    $result['commentedOn'] = !empty($result['commentOn']) ? $this->datetimeHelper->getDanishFormattedDate($result['commentOn']) : null;
                    $result['commentedBy'] = $result->userDetail->name ?? null;
                    $result['googleUrl'] = sprintf('%s%s', GOOGLE_RESTAURANT_SEARCH_URL, urlencode($restauranntName)); 
                    $result['fetchedHtmlUrl'] = $this->links->createUrl(PAGE_SHOW_FETCHED_HTML, array(QUERY_SMILEY_RESTAURANT_ID => $result['navnelBnr']));
                    $result['createRestaurantUrl'] = $this->links->createUrl(PAGE_RESTAURANT_CREATE_EDIT_PROFILE, array(QUERY_SMILEYUNIQUEID => $result['navnelBnr']));
                    
                    if(isset($result['userDetail']))
                    {
                        unset($result['userDetail']);
                    }
                } 

                $response = $data;
            }
        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in FindSmileyController@get error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function updateGoogleFetchInformation(int $navnelBnr, Request $request)
    {
        $validator = Validator::make(['navnelBnr' => $navnelBnr], ['navnelBnr' => 'required|integer|min:1']);
        
        if ($validator->fails())
        {
            throw new Exception(sprintf("FindSmileyController update error. %s ", $validator->errors()->first()));
        }

        $requestAll = $request->post();
        $response = null;

        $data = ['googleFetchIgnoredOn' => $this->datetimeHelper->getCurrentUtcTimeStamp(), 'googleFetchIgnoredBy' => Auth::id()];

        if(isset($requestAll['retry']) && $requestAll['retry'])
        {
            $data = ['googleDataFetchedOn' => null, 'restaurantExistsInGoogle' => null];
        }

        if(isset($requestAll['comment']) && $requestAll['comment'])
        {
            $data = ['comment' => $requestAll['comment'], 'commentBy' => Auth::id(), 'commentOn' => $this->datetimeHelper->getCurrentUtcTimeStamp()];
            $response = ['commentedBy' => Auth::user()->name, 'commentedOn' => $this->datetimeHelper->getDanishFormattedDate($this->datetimeHelper->getCurrentUtcTimeStamp())];
        }

        FindSmileyModel::where('navnelBnr', $navnelBnr)->update($data);

        return response()->json(new BaseResponse(true, null, $response));
    }

    public function fetchWrongSmileyRestaurants()
    {
        $results = '';
        $res = FindSmileyModel::select(['navnelBnr'])
        ->where([['isWrongSmiley', '!=', NULL],['isWrongSmileyFindAndUpdated', NULL], ['isWrongSmileyIgnored', NULL]])
        ->get();
        if(!empty($res))
        {
            $findSmileyIds = $res->pluck('navnelBnr')->toArray();
            $results = Advertisement::select(['id','title','city','postcode','smileyUniqueId'])->where('status',1)->whereIn('smileyUniqueId', $findSmileyIds)->get();
                
            if(!empty($results))
            {
                foreach($results as $result)
                {
                    $result['navn1'] = $result['title'];
                    $result['city'] = $result['city'];
                    $result['postNr'] = $result['postcode'];
                    $result['dagensmenuId'] = $result['id'];
                    $result['navnelBnr'] = $result['smileyUniqueId'];
                    $result['menuLink'] = sprintf("%s/t?%s=%s", SITE_BASE_URL, QUERY_ADIDWITHDASH, $result['id']);
                    $result['editLink'] = $this->links->createUrl(PAGE_RESTAURANT_CREATE_EDIT_PROFILE, array(QUERY_ADIDWITHDASH => $result['id']));
                    $result['smileySearchUrl'] = sprintf('%s%s', GOOGLE_RESTAURANT_FIND_WRONG_SMILEY, $result['title']);
                }
            }
        }
            
        return response()->json(new BaseResponse(true, null, $results));
        
    }

    public function updateWrongSmileyFoundAndMarkAsIgnored(int $navnelBnr)
    {

        $validator = Validator::make(['navnelBnr' => $navnelBnr], ['navnelBnr' => 'required|int|min:1']);

        if($validator->fails())
        {
            throw new Exception(sprintf("FindSmileyController updateWrongSmileyFoundAndMarkAsIgnored error. %s ", $validator->errors()->first()));
        }

        FindSmileyModel::where('navnelBnr', $navnelBnr)->update([
            'isWrongSmileyIgnored' => WRONG_SMILEY_FOUND_AND_MARKED_AS_IGNORED,
            'ignoredBy' => Auth::id(),
            'ignoredOn' => $this->datetimeHelper->getCurrentUtcTimeStamp()        
        ]); 



        return response()->json(new BaseResponse(true, NULL, NULL));
    }
}