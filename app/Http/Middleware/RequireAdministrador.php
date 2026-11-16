<?php

namespace App\Http\Middleware;

use App\Support\Caja\Access;
use Closure;
use Illuminate\Http\Request;

class RequireAdministrador
{
    public function handle(Request $request, Closure $next): mixed
    {
        Access::requireAdmin($request);

        return $next($request);
    }
}
