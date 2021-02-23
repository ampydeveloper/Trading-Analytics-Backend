<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\Cors as Middleware;

class Cors extends Middleware {

    public function handle($request, Closure $next) {
        return $next($request)
                        ->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }

}
