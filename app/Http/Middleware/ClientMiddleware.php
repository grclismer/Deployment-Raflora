<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the client portal to client accounts.
 *
 * Admin and Staff accounts have their own workspaces; letting them into the client
 * portal would silently create client records for staff emails and allow admin
 * profile changes that bypass the Admin email-change OTP flow.
 */
class ClientMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->guest(route('login'));
        }

        // users.role defaults to "client" in the database; an unset in-memory role is the default.
        $role = $user->role ?? 'client';

        if ($role !== 'client') {
            if ($request->expectsJson()) {
                abort(403, 'Client portal access is limited to client accounts.');
            }

            return redirect()->to(match ($role) {
                'admin' => route('admin.dashboard'),
                'staff' => route('staff.dashboard'),
                default => route('home'),
            })->with('error', 'The client portal is only available to client accounts.');
        }

        return $next($request);
    }
}
