<?php
namespace App\Http\Controllers\Order;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use App\Models\Order\Order;
use Mockery\CountValidator\Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Shared\EatCommon\Link\Links;
class OrderReportController extends Controller
{
    private $datetimeHelpers;
    private $links;

    function __construct(DatetimeHelper $datetimeHelpers, Links $links)
    {
        $this->datetimeHelpers = $datetimeHelpers;
        $this->links = $links;
    }

    public function getRestaurantOrderMonthlyReport(Request $request, int $restaurantId){

        $months = !empty($request->months) ? $request->months : 12;
        $validator = Validator::make(['restaurantId' => $restaurantId, 'months' => $months], ['restaurantId' => 'required|int|min:1', 'months' => 'required|int|min:1']);

        if ($validator->fails())
        {
            throw new Exception(sprintf("OrderReportController getRestaurantOrderMonthlyReport error. %s ", $validator->errors()->first()));
        }
    
        $date = date("Y-m-d", strtotime(sprintf('- %s month', $months)));
        $timestamp = strtotime($date);
        $condition = [
            ['restaurantId', $restaurantId], 
            ['createdOn', '>=' ,$timestamp]
        ];

        $results = Order::select([
            DB::raw("COUNT(*) as totalOrder"),
            DB::raw("SUM(totalFoodPrice) as totalOrderPrice"),
            DB::raw(sprintf("SUM(CASE WHEN orderType = %s THEN 1 ELSE 0 END) as deliveryOrders", ORDER_ONLINE_TYPE_DELIVERY)),
            DB::raw(sprintf("SUM(CASE WHEN orderType = %s THEN totalFoodPrice ELSE 0 END) as deliveryOrderPrice", ORDER_ONLINE_TYPE_DELIVERY)),
            DB::raw(sprintf("SUM(CASE WHEN orderType = %s THEN deliveryPrice ELSE 0 END) as deliveryCost", ORDER_ONLINE_TYPE_DELIVERY)),
            DB::raw(sprintf("SUM(CASE WHEN orderType = %s THEN 1 ELSE 0 END) as takeawayOrders", ORDER_ONLINE_TYPE_TAKE_AWAY)),
            DB::raw(sprintf("SUM(CASE WHEN orderType = %s THEN totalFoodPrice ELSE 0 END) as takeawayOrderPrice", ORDER_ONLINE_TYPE_TAKE_AWAY)),
            DB::raw("FROM_UNIXTIME(createdOn, '%Y-%m') as yearMonth")
        ])->where($condition)->whereIn('orderStatus', [ORDER_ONLINE_STATUS_ACCEPTED_BY_RESTAURANT, ORDER_ONLINE_STATUS_FOOD_READY])->whereIn('orderType', [ORDER_ONLINE_TYPE_DELIVERY, ORDER_ONLINE_TYPE_TAKE_AWAY])->groupBy('yearMonth')->orderBy('yearMonth', 'DESC')->get()->toArray();
        
        for($i=0; $i<=$months; $i++)
        {
            $monthYear = date('Y-m', strtotime("-$i month"));
	        $month = intval(date('m', strtotime($monthYear)));
            $year = date('Y', strtotime($monthYear));

            if(!array_key_exists($i, $results) || $results[$i]['yearMonth'] != $monthYear){
                $missingMonthsData[] = ["totalOrder" => 0, "totalOrderPrice" => 0, 'deliveryOrders' => 0, 'deliveryOrderPrice' => 0, 'deliveryCost' => 0, 'takeawayOrders' => 0, 'takeawayOrderPrice' => 0, "yearMonth" => $monthYear];
                array_splice($results, $i, 0, $missingMonthsData);
            }

            $fullMonthName = $this->datetimeHelpers->getMonthNameByMonthNumber($month);
            $danishMonth = $this->datetimeHelpers->getSmallDanishMonthNameForEnglishName($fullMonthName);
            $results[$i]['monthInHumanReadable'] = sprintf('%s %s', $danishMonth, $year);
            $missingMonthsData = NULL; 
        }    

        return response()->json(new BaseResponse(true, null, $results));
    }

    public function getRestaurantOrdersForAdmin(){
        $orders = Order::select('orderId', 'restaurantId','orderType', 'totalFoodPrice', 'deliveryPrice', 'orderStatus', 'orderUniqueId', 'paymentType', 'createdOn', 'orderNumber', 'orderMessage', 'deliveryApproxTimeInSeconds')
        ->with([
            'restaurant:id,title,extra,urlTitle'           
        ])
        ->limit(100)->orderBy('orderId', 'desc')->get()->toArray();  
        
        foreach($orders as &$order)
        {
            $extra = json_decode($order['restaurant']['extra']);

            $order['formattedDate'] = $this->datetimeHelpers->getDanishFormattedDateTime($order['createdOn']);
            $order['restaurantOrderUrl'] =$this->links->createUrl(PAGE_RESTAURANT_ORDERS, array(QUERY_ADIDWITHDASH => $order['restaurantId']));
            $order['telephone'] = $extra->telephone;
            $order['menuCardLink'] = $this->links->menuLink($order['restaurantId'], null, $order['restaurant']['urlTitle'], null, null);
        }
        
        return response()->json(new BaseResponse(true, null, $orders));
    }
}