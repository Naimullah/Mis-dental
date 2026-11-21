<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockCountryMutationsAndExports
{
    public function handle(Request $request, Closure $next): never
    {
        // Remove this block only after explicit platform permissions are wired.
        abort(403);
    }
}
