<?php

namespace App\Http\Controllers\Stats;

use App\Http\Controllers\BaseResponse;
use App\Http\Controllers\Controller;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Shared\EatCommon\Link\Links;
use App\Shared\EatCommon\Language\TranslatorFactory;
use App\Models\Restaurant\Advertisement;
use App\Models\Location\PostcodePopulation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class AdvertisementMenuCardStatisticsController extends Controller
{
    private $datetimeHelpers;
    private $translatorFactory;
    private $links;

    function __construct(DatetimeHelper $datetimeHelpers, TranslatorFactory $translatorFactory, Links $links) {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->translatorFactory = $translatorFactory::getTranslator();
        $this->links = $links;
    }

    public function fetchAllMenuCardStats()
    {

        $oneYearOldTimeStamp = strtotime("-1 year", time());
        $condition = [
            ['status', RESTAURANT_STATUS_ACTIVE]
        ];

        $results = Advertisement::select([
            'postcode', 
            DB::raw('MAX(city) as city'),
            DB::raw('count(id) as totalNumberOfRestaurant'),
            DB::raw('SUM(CASE WHEN (menuImages IS NOT NULL AND menuImages !="") OR manualMenuCard IS NOT NULL THEN 1 ELSE 0 END) as restaurantWithMenuCard'),
            DB::raw('SUM(CASE WHEN (menuImages IS NULL OR menuImages ="") AND manualMenuCard IS NULL THEN 1 ELSE 0 END) as restaurantWithoutMenuCard'),
            DB::raw(sprintf('SUM(CASE WHEN menuCardUpdatedOn <= %s AND ((menuImages IS NOT NULL AND menuImages !="") OR manualMenuCard IS NOT NULL) THEN 1 END) as menuCardOlderThanOneYear', $oneYearOldTimeStamp))
            ])->with(['postcodePopulation' => function($query){
                $query->select(['postcode', 'population']);
            }])->where($condition)->groupBy('postcode')->orderBy('menuCardOlderThanOneYear', 'DESC')->get()->toArray();
            
            if(!empty($results))
            {
                foreach($results as &$result)
                {                    
                    if(!empty($result['postcode']))
                    {
                        $result['postcode'] = intval($result['postcode']);
                    }

                    if(!empty($result['city']))
                    {
                        $result['city'] = $result['city'];
                    }

                    if(!empty($result['totalNumberOfRestaurant']))
                    {
                        $result['totalNumberOfRestaurant'] = intval($result['totalNumberOfRestaurant']);
                    }

                    if(!empty($result['restaurantWithMenuCard']))
                    {
                        $result['restaurantWithMenuCard'] = intval($result['restaurantWithMenuCard']);
                    }

                    if(!empty($result['restaurantWithoutMenuCard']))
                    {
                        $result['restaurantWithoutMenuCard'] = intval($result['restaurantWithoutMenuCard']);
                    }

                    if(!empty($result['menuCardOlderThanOneYear']))
                    {
                        $result['menuCardOlderThanOneYear'] = intval($result['menuCardOlderThanOneYear']);
                    }

                    $result['postcodePopulation'] = !empty($result['postcode_population']) ?  intval($result['postcode_population']['population']) : 0;

                    unset($result['postcode_population']);
                    
                }
            }
            
        return response()->json(new BaseResponse(true, null, $results));
    }

    public function fetchMenuCardStatsOnPostCode(Request $request)
    {
        $postCode = $request->postCode;
        $selectedYear = !empty($request->year) ? $request->year : "";
        
        if(!empty($postCode))
        {
            $validator = Validator::make(['postcode' => $postCode], ['postcode' => 'required|int|min:1']);

            if ($validator->fails())
            {
                throw new Exception(sprintf("AdvertisementMenuCardStatisticsController fetchMenuCardStatsOnPostCode error. %s ", $validator->errors()->first()));
            }
        }

        $condition = [
            ['status', RESTAURANT_STATUS_ACTIVE],
            ['postcode', $postCode]
        ];
        
        $results = Advertisement::select([
            'id',
            'url',
            'urlTitle',
            'serviceDomainName',
            'cityUrl',
            'title',
            'menuCardUpdatedOn',
            'menuCardUpdatedBy',
            'extra',
            'menuImages',
            'manualMenuCard'
            ])->with(['advertisementMenuCardUpdatedBy' => function($query){
                $query->select(['uid', 'first_name', 'last_name', 'nick_name']);
            }])->where($condition);

        if(!empty($selectedYear))
        {
            $results->whereRaw("FROM_UNIXTIME(menuCardUpdatedOn, '%Y') <= $selectedYear");
        }  

        $results = $results->orderBy('menuCardUpdatedOn', 'ASC')->get()->toArray();  

        if(!empty($results))
        {
            $extra = [];
            foreach($results as &$result)
            {
                $address = json_decode($result['extra']);
                $result['extra'] = $address->address;
                
                if(!empty($result['advertisement_menu_card_updated_by']))
                {
                    if(!empty($result['advertisement_menu_card_updated_by']['first_name']) && !empty($result['advertisement_menu_card_updated_by']['last_name']))
                    {
                        $result['userName'] = sprintf('%s %s', $result['advertisement_menu_card_updated_by']['first_name'], $result['advertisement_menu_card_updated_by']['last_name']);
                    }
                    else
                    {
                        $result['userName'] = $result['advertisement_menu_card_updated_by']['nick_name'];
                    }
                    unset($result['advertisement_menu_card_updated_by']);
                }
                
                if(!empty($result['menuCardUpdatedOn']))
                {
                    $result['menuCardUpdatedTimeStamp'] = $result['menuCardUpdatedOn'];
                    $result['menuCardUpdatedOn'] = $this->datetimeHelpers->getDanishFormattedDate($result['menuCardUpdatedOn']);
                }

                if(!empty($result['menuImages']))
                {
                    $result['hasMenuCard'] = $this->translatorFactory->translate('Yes');
                }
                else
                {
                    $result['hasMenuCard'] = $this->translatorFactory->translate('No');
                }

                if(!empty($result['menuImages']))
                {
                    $result['menuImages'] = $this->translatorFactory->translate('Yes');
                }
                else
                {
                    $result['menuImages'] = $this->translatorFactory->translate('No');
                }

                if(!empty($result['manualMenuCard']))
                {
                    $result['manualMenuCard'] = $this->translatorFactory->translate('Yes');
                }
                else
                {
                    $result['manualMenuCard'] = $this->translatorFactory->translate('No');
                }

                if(!empty($result['id']))
                {
                    $result['advLink'] = $this->links->menuLink($result['id'], $result['url'], $result['urlTitle'], $result['serviceDomainName'], $result['cityUrl']); 
                    $result['editMenuLink'] = $this->links->createUrl(PAGE_RESTAURANT_CREATE_EDIT_PROFILE, array(QUERY_ADIDWITHDASH => $result['id']));   
                }
            }
        }
        return response()->json(new BaseResponse(true, null, $results));
    }
} 