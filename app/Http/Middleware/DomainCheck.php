<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Support\Facades\Log;

class DomainCheck
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
        // Pre-Middleware Action

        try
        {
            $allowedHosts = explode(',', env('ALLOWED_DOMAINS'));
    
            if (!empty($allowedHosts))
            {
                $requestHost = parse_url($request->headers->get('origin'), PHP_URL_HOST);

                if (!in_array($requestHost, $allowedHosts, false))
                {
                    $requestInfo = [
                        'host' => $requestHost,
                        'ip' => $request->getClientIp(),
                        'url' => $request->getRequestUri(),
                        'agent' => $request->header('User-Agent'),
                    ];

                    Log::channel('badRequest')->critical("Requested domain is not valid. Request info ", $requestInfo);
                }
            }
        }
        catch (Exception $e)
        {
            Log::channel('badRequest')->critical(sprintf("Domain check error: %s", $e->getMessage()));
        }

        $response = $next($request);

        // Post-Middleware Action

        return $response;
    }
}
