<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSetupCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBootstrapAdmin()) {
            // Allow only setup wizard routes and logout for bootstrap admin
            if (! $request->routeIs('admin.setup*') && ! $request->routeIs('logout')) {
                return redirect()->route('admin.setup')
                    ->with('info', 'Initial administrative setup is required before accessing the operations panel.');
            }
        } elseif ($user && $user->role === 'admin' && ! $user->isBootstrapAdmin()) {
            // Normal / fully configured Admin attempting to access setup is redirected to dashboard
            if ($request->routeIs('admin.setup*')) {
                return redirect()->route('admin.dashboard');
            }
        }

        return $next($request);
    }
}
