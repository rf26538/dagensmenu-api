<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log\DuplicateAdvertisement;
use Illuminate\Http\Request;
use App\Models\Restaurant\Advertisement;
use Illuminate\Support\Facades\DB;
use App\Shared\EatCommon\Link\Links;
use App\Models\Log\SystemStat;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Shared\EatCommon\Helpers\IPHelpers;

class AdvertisementDuplicateController extends Controller
{
	private $request;
	private $links;
	private $ipHelper;
	private $datetimeHelper;

	public function __construct(Request $request, Links $links, IPHelpers $ipHelper, DatetimeHelper $datetimeHelper)
	{
		$this->request = $request;
		$this->links = $links;
		$this->ipHelper = $ipHelper;
		$this->datetimeHelper = $datetimeHelper;
	}

	public function saveDuplicateRestaurants()
	{
		$requestData = $this->request->all();

		$validator = Validator::make($requestData, [
			'restaurantIds' => 'required|string',
		]);

		if ($validator->fails())
		{
            throw new Exception(sprintf("AdvertisementDuplicateController saveDuplicateRestaurants error. %s ", $validator->errors()->first()));
		}

		$duplicateAdvertisement = new DuplicateAdvertisement();
		$duplicateAdvertisement->advertisementIds = $requestData['restaurantIds'];
		$duplicateAdvertisement->userId = Auth::id();
		$duplicateAdvertisement->ip = $this->ipHelper->clientIpAsLong();
		$duplicateAdvertisement->createdOn = $this->datetimeHelper->getCurrentUtcTimeStamp();
		$duplicateAdvertisement->save();

		return response()->json(new BaseResponse(true, null, []));
	}

	public function fetchDuplicateRestaurants()
	{

		$duplicateAdvertisements = DuplicateAdvertisement::select('advertisementIds')->get()->toArray();
		$duplicateAdvertisementIds = [];

		if (!empty($duplicateAdvertisements))
		{
			foreach($duplicateAdvertisements as $duplicateAdvertisement)
			{
				$explodeAdvIds = explode('-', $duplicateAdvertisement['advertisementIds']);

				if (!empty($explodeAdvIds))
				{
					array_push($duplicateAdvertisementIds, $explodeAdvIds);
				}
			}
		}

		$allActiveAdvertisements = Advertisement::select([
			"id", "title", "postcode", "address", "url", "urlTitle", "extra", "city", "smileyUrl", "views", "hasMenuCard", "serviceDomainName", "cityUrl", "reviewersCount as restaurantTotalReviews", "smileyUniqueId", "companyCvr", "phoneNumber", DB::raw("FROM_UNIXTIME(creation_date) AS createdOn")
		])->where('status', STATUS_ACTIVE)->whereNotIn('id', $duplicateAdvertisementIds)->get()->toArray();

		$results = [];

		if (!empty($allActiveAdvertisements))
		{
			foreach($allActiveAdvertisements as $advRow)
			{
				$advRow['address'] = str_replace(' ', '', trim($advRow['address']));
				$advRow['companyCvr'] = str_replace(' ', '', trim($advRow['companyCvr']));
				$advRow['phoneNumber'] = str_replace(' ', '', trim($advRow['phoneNumber']));

				$isMatchFound = 0;

				$arrayKey = str_replace(' ', '', sprintf("%s-%s-%s", $advRow['postcode'], $advRow['phoneNumber'], $advRow['companyCvr']));
				
				foreach($allActiveAdvertisements as $rowIndex => $row)
				{
					$conditionMachedCounter = 0;

					if ($row['id'] != $advRow['id'] && $row['postcode'] == $advRow['postcode'])
					{
						$row['address'] = str_replace(' ', '', trim($row['address']));
						$row['companyCvr'] = str_replace(' ', '', trim($row['companyCvr']));
						$row['phoneNumber'] = str_replace(' ', '', trim($row['phoneNumber']));

						if ($row['address'] == $advRow['address'])
						{
							++$conditionMachedCounter;
						}
	
						if (intval($row['phoneNumber']) > 0 && intval($advRow['phoneNumber']) > 0 && ($advRow['phoneNumber'] == $row['phoneNumber']))
						{
							++$conditionMachedCounter;
						}
	
						if (!empty($row['companyCvr']) && !empty($advRow['companyCvr']) && $row['companyCvr'] == $advRow['companyCvr'])
						{
							++$conditionMachedCounter;
						}
	
						if ($row['smileyUniqueId'] > 0 && ($row['smileyUniqueId'] == $advRow['smileyUniqueId']))
						{
							++$conditionMachedCounter;
						}
		
						if ($conditionMachedCounter >= 2)
						{
							$results[$arrayKey][$row['id']] = $row;    
							$isMatchFound = 1;
							
							unset($allActiveAdvertisements[$rowIndex]);
						}
					}
				}

				if ($isMatchFound)
				{
					$results[$arrayKey][$advRow['id']] = $advRow;
				}
			}

			if (!empty($results))
			{
				// Remove duplicate restaurant group by their ids
				$removeDuplicateRestaurantGroupFromArray = [];

				foreach($results as $restaurants)
				{
					$allRestaurants = [];
					$restaurantIds = [];

					foreach($restaurants as $restaurant)
					{
						$allRestaurants[] = $restaurant;
						$restaurantIds[$restaurant['id']] = $restaurant['id'];
					}

					sort($restaurantIds);

					$removeDuplicateRestaurantGroupFromArray[implode('-', $restaurantIds)] = $allRestaurants;
				}

				$results = $removeDuplicateRestaurantGroupFromArray;
			}
		}

		$response = [];
		$restaurantIds = [];

		if (!empty($results))
		{
			usort($results, function($a, $b) { return count($b) - count($a); });

			foreach($results as $restaurants)
			{
				$responseData = [];

				if (!empty($restaurants))
				{
					$totalRestaurantCount = count($restaurants);

					foreach($restaurants as $restaurant)
					{
						$responseData[] = [
							'restaurantId' => $restaurant['id'],
							'restaurantName' => $restaurant['title'],
							'smileyUrl' => $restaurant['smileyUrl'],
							'restaurantAddress' => $restaurant['address'],
							'restaurantMenuCardLink' => $this->links->menuLink($restaurant['id'], $restaurant['url'], $restaurant['urlTitle'], $restaurant['serviceDomainName'], $restaurant['cityUrl']),
							'restaurantPostcode' => $restaurant['postcode'],
							'restaurantCvrNumber' => $restaurant['companyCvr'],
							'restaurantPhoneNumber' => $restaurant['phoneNumber'],
							'restaurantSmileyUniqueId' => $restaurant['smileyUniqueId'],
							'restaurantHasMenuCard' => is_null($restaurant['hasMenuCard']) ? 0 : $restaurant['hasMenuCard'],
							'restaurantTotalReviews' => is_null($restaurant['restaurantTotalReviews']) ? 0 : $restaurant['restaurantTotalReviews'],
							'restaurantTotalViews' => 0,
							'duplicateCounter' => $totalRestaurantCount,
						];

						$restaurantIds[$restaurant['id']] = $restaurant['id'];
					}
				}

				$response[] = $responseData;
			}
		}

		if (!empty($response))
		{
			$systemStats = SystemStat::select([
				'adId',
				DB::raw(sprintf('SUM(CASE WHEN statsType = %s THEN 1 ELSE 0 END) as totalViews', STATS_COMPLETE_AD_SHOWN))
			])->where('adType', AD_FULL)->whereIn('adId', $restaurantIds)->groupBy('adId')->get()->toArray();

			if (!empty($systemStats))
			{
				$systemStats = $this->changeArrayIndexByColumnValue($systemStats, 'adId');

				foreach($response as &$restaurantGroups)
				{
					foreach($restaurantGroups as &$restaurantInfo)
					{
						if (isset($systemStats[$restaurantInfo['restaurantId']]))
						{
							$restaurantInfo['restaurantTotalViews'] = $systemStats[$restaurantInfo['restaurantId']]['totalViews'];
						}
					}
				}
			}			
		}

		return response()->json(new BaseResponse(true, null, $response));
	}

	
	private function changeArrayIndexByColumnValue(array $data, string $columnName): array
	{
		$results = [];
		if (!empty($data) && !empty($columnName) && isset($data[0][$columnName]))
		{
			foreach($data as $row)
			{
				$results[$row[$columnName]] = $row;
			}
		}

		return $results;
	} 

}

?>