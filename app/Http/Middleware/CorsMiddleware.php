<?php namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $originValue = "*";
        if(env('ENABLE_CORS'))
        {
            $allowedDomains = [
                'http://www.e.com',
                'http://e.com',
                'https://www.dagensmenu.dk',
                'https://api.dagensmenu.dk'
            ];

            $origin = null;

            if (isset($_SERVER['HTTP_REFERER']))
            {
                $origin = $_SERVER['HTTP_REFERER'];
            }
            else if (isset($_SERVER['HTTP_ORIGIN']))
            {
                $origin = $_SERVER['HTTP_ORIGIN'];
            }
            
            $httpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''; 
            $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
            $originValue = null;

            if (!is_null($origin))
            {
                $parseOrigin = parse_url($origin);

                if (isset($parseOrigin['host']) && isset($parseOrigin['scheme']))
                {
                    $origin = sprintf('%s://%s', $parseOrigin['scheme'], $parseOrigin['host']);
                }

                if (in_array($origin, $allowedDomains)) 
                {
                    $originValue = $origin;
                }
            }
            else if (!empty($serverName) || !empty($httpHost))
            {
                // Find out with their host
                foreach($allowedDomains as $domain)
                {
                    $parseDomain = parse_url($domain);

                    if (isset($parseDomain['host']))
                    {
                        if ($serverName == $parseDomain['host'])
                        {
                            $originValue = $domain;
                            break;
                        }

                        if ($httpHost == $parseDomain['host'])
                        {
                            $originValue = $domain;
                            break;
                        }
                    }
                }
            }

            if (!app()->environment('production') && $serverName == 'localhost')
            {
                $originValue = '*';
            }

            if (is_null($originValue))
            {
                $requestHeaders = collect($request->header())->transform(function ($item) {
                    return $item[0];
                })->toArray();
        
        
                if (isset($_SERVER['REMOTE_ADDR']))
                {
                    $requestHeaders['ipAddress'] = $_SERVER['REMOTE_ADDR'];
                }
        
                Log::critical("This request is not allowed: headers ", $requestHeaders);

                $originValue = '*';
            }
        }

        $headers = [
            'Access-Control-Allow-Origin'      => $originValue,
            'Access-Control-Allow-Methods'     => 'POST, GET, OPTIONS, PUT, DELETE',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With'
        ];

        if ($request->isMethod('OPTIONS'))
        {
            return response()->json('{"method":"OPTIONS"}', 200, $headers);
        }

        $requestData = [
            'method' => $request->method(),
            'input' => $request->all(),
            'fullUrl' => $request->fullUrl(),
            'ip' => $request->getClientIp()
        ];

        $response = $next($request);
        foreach($headers as $key => $value)
        {
            if(is_string($response))
            {
                Log::critical(sprintf("CORS middleware: response is string. Value is %s", $response));
                Log::critical("Request data is ", $requestData);   
            }
            else
            {
                $response->header($key, $value);
            }
        }

        return $response;
    }
}