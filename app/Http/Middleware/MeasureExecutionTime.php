<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Log\Benchmark;
use App\Shared\EatCommon\Helpers\MathHelper;

class MeasureExecutionTime
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Get the response
        $response = $next($request);

        if (BENCHMARK_ENABLE && defined('LUMEN_START'))
        {
            $mathHelper = new MathHelper();
    
            $endTime =  $mathHelper->getMicrotime();
    
            $benchmark = new Benchmark;
            
            $benchmark->url = $request->fullUrl();
            $benchmark->startTime = LUMEN_START;
            $benchmark->endTime = $endTime;
            $benchmark->source = BENCHMARK_SOURCE_LARAVEL_API;
            $benchmark->createdOn = time();
            $benchmark->verb = $request->method();
            $benchmark->payload = json_encode($request->all());
            $benchmark->save();
        }

        return $response;
    }
}