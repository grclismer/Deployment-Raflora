<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !in_array(auth()->user()->role, ['admin', 'staff'], true)) {
            abort(403, 'Unauthorized. Staff access required.');
        }

        if (auth()->user()->isBootstrapAdmin()) {
            return redirect()->route('admin.setup')
                ->with('info', 'Initial administrative setup is required before accessing operations.');
        }

        return $next($request);
    }
}
