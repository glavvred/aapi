<?php

namespace App\Http\Middleware;

use App\Planet;
use Closure;
use App\User;

class AstralityScopeMiddleware
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

        $user = User::where('id', $request->auth->id)->first();
        $user->scope = '1231231231';
        //$user = Planet::where('owner_id', $request->auth->id)->get();

        return $next($request);
    }
}