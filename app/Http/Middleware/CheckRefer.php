<?php

namespace Zhiyi\Plus\Http\Middleware;

use Closure;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CheckRefer
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
        $referer = $request->header('Referer') ;
        $referer = $referer?parse_url($referer)['host']:'';
        $url = $request->url();
        if(!$referer || (strpos($url,$referer) === false) ){
            throw new AccessDeniedHttpException('非法请求');
        }

        return $next($request);
    }
}
