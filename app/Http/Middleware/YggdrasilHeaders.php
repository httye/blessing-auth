<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class YggdrasilHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // authlib-injector 通过该响应头自动发现 API 根地址
        $response->headers->set('X-Authlib-Injector-API-Location', url('api/yggdrasil'));

        return $response;
    }
}
