<?php

namespace App\Http\Controllers\Order;
use Illuminate\Http\Request;
use Mockery\CountValidator\Exception;
use App\Http\Controllers\Controller;
use App\Models\Order\OrderPerRestaurantStatsModel;
use App\Http\Controllers\BaseResponse;
use Illuminate\Support\Facades\Log;
use App\Shared\EatCommon\Helpers\DatetimeHelper;
use Illuminate\Support\Facades\DB;
use Validator;
use DateTime;

class OrderPerRestaurantStatsController extends Controller
{

    private $datetimeHelpers;

    public function __construct(DatetimeHelper $datetimeHelpers)
    {
        $this->datetimeHelpers = $datetimeHelpers;
    }

    public function getRestaurantOrderStatistics(int $restaurantId, Request $request)
    {
        $orders = [];

        try
        {
            $validator = Validator::make(['restaurantId' => $restaurantId], ['restaurantId' => 'required|int|min:1']);

            if ($validator->fails())
            {
                throw new Exception(sprintf("OrderPerRestaurantStatsController.getRestaurantOrderStatistics error. %s ", $validator->errors()->first()));
            }

            $orderDurationType = trim($request->get('type'));

            if($orderDurationType == ORDER_ONLINE_ORDERS_STATISTICS_DAILY)
            {
                $orderDuration = strtotime("-14 days");

                $orders = OrderPerRestaurantStatsModel::select(DB::raw('DATE_FORMAT(DATE(FROM_UNIXTIME(timestamp)), "%D %M %D") as orderDuration , numberOfOrders, totalOrderPrice'))->where([['restaurantId', $restaurantId], ['timestamp', '>=', $orderDuration]])->orderBy('timestamp')->get()->toArray();

                foreach($orders as &$order)
                {
                    $explodeOrder = explode(" ", $order['orderDuration']);

                    $order['orderDuration'] = sprintf("%s %s", $explodeOrder[0], $this->datetimeHelpers->getSmallDanishMonthNameForEnglishName($explodeOrder[1]));
                    $order['totalOrderPrice'] = $order['totalOrderPrice'] / CURRENCY_MULTIPLIER;
                }

            }
            else if($orderDurationType == ORDER_ONLINE_ORDERS_STATISTICS_MONTHLY)
            {
                $orderDuration = strtotime("-1 year");

                $orders = OrderPerRestaurantStatsModel::select(DB::raw('CONCAT(MONTHNAME(DATE(FROM_UNIXTIME(timestamp))),"-", YEAR(FROM_UNIXTIME(timestamp))) as orderDuration, COUNT(*) as numberOfOrders, SUM(totalOrderPrice) as totalOrderPrice'))->where([['restaurantId', $restaurantId], ['timestamp', '>=', $orderDuration]])->groupBy('orderDuration')->orderByRaw('YEAR(FROM_UNIXTIME(timestamp)) , MONTH(FROM_UNIXTIME(timestamp))')->get()->toArray();

                foreach($orders as &$order)
                {
                    $explodeOrder = explode("-", $order['orderDuration']);
                    $order['orderDuration'] = sprintf("%s %s", $this->datetimeHelpers->getSmallDanishMonthNameForEnglishName($explodeOrder[0]), $explodeOrder[1]);
                    $order['totalOrderPrice'] = $order['totalOrderPrice'] / CURRENCY_MULTIPLIER;
                }
            }
            else
            {
                $orderDuration = strtotime("-8 week");
                $orders = OrderPerRestaurantStatsModel::select(DB::raw('WEEK(from_unixtime(timestamp))  as orderDuration, COUNT(*) as numberOfOrders, SUM(totalOrderPrice) as totalOrderPrice'))->where([['restaurantId', $restaurantId], ['timestamp', '>=', $orderDuration]])->groupBy('orderDuration')->get()->toArray();

                foreach($orders as &$order)
                {
                    $startAndEndWeek = $this->getStartAndEndDate($order['orderDuration'], "2021");

                    $explodeStartWeek = explode(" ", $startAndEndWeek['startDate']);
                    $explodeEndWeek = explode(" ", $startAndEndWeek['endDate']);

                    $order['orderDuration'] = sprintf("%s %s %s - %s %s %s",
                                                        $explodeStartWeek[0],
                                                        $this->datetimeHelpers->getSmallDanishMonthNameForEnglishName($explodeStartWeek[1]),
                                                        $explodeStartWeek[2],
                                                        $explodeEndWeek[0],
                                                        $this->datetimeHelpers->getSmallDanishMonthNameForEnglishName($explodeEndWeek[1]),
                                                        $explodeEndWeek[2]
                                                    );
                    $order['totalOrderPrice'] = $order['totalOrderPrice'] / CURRENCY_MULTIPLIER;
                }
            }

        }
        catch(Exception $e)
        {
            Log::critical(sprintf("Error found in OrderPerRestaurantStatsController@getRestaurantOrderStatistics error is %s, Stack trace is %s", $e->getMessage(), $e->getTraceAsString()));
        }

        return response()->json(new BaseResponse(true, null, $orders));
    }

    private function getStartAndEndDate($week, $year)
    {
        $dateTime = new DateTime();
        $dateTime->setISODate($year, $week);
        $result['startDate'] = $dateTime->format('d F Y');
        $dateTime->modify('+6 days');
        $result['endDate'] = $dateTime->format('d F Y');
        return $result;
      }
}
