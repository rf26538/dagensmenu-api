<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use App\Models\Restaurant\Advertisement;

class CheckRestaurantBelongsToTheUser
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $restaurantId = $this->getRestaurantId($request);

        if ($restaurantId < 1) {
            return response('restaurantId is missing from request', 400);
        }


        if ($this->auth->guard($guard)->user()->type != USER_SUPER_ADMIN && $this->auth->guard($guard)->user()->type != USER_ADMIN) {
            
            $whereCondition =  ['id' => $restaurantId, 'author_id' => $this->auth->guard($guard)->user()->uid];
            $result =  Advertisement::where($whereCondition)->get()->toArray();

            if (empty($result)) {
                return response('Unauthorized.', 401);
            }
        }

        return $next($request);
    }

    private function getRestaurantId(Request $request): int {
        $method = $request->method();
        $restaurantId =  0;

        if ($method === 'GET') {
            $lastUriSegement =  (int)$request->segment(count($request->segments()));

            if ($request->query('restaurantId')) {
                $restaurantId = (int)$request->query('restaurantId');
            } elseif ($lastUriSegement > 0) {
                $restaurantId = $lastUriSegement;
            }
        } elseif ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
            $restaurantId = (int)$request->post('restaurantId');
        }

        return $restaurantId;
    }

}
