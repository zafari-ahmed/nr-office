<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    /** Pages of a module switched off in Settings › Modules are not found. */
    public function handle(Request $request, Closure $next): Response
    {
        $module = Modules::forRoute($request->route()?->getName());
        abort_if($module && ! Modules::enabled($module), 404);

        return $next($request);
    }
}
