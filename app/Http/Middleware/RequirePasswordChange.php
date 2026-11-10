<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowedRoutes = [
            'admin.passwords.index',
            'admin.passwords.update',
            'password.change',
            'password.change.update',
            'logout',
        ];

        if (
            in_array($user?->role, ['admin', 'teacher'], true)
            && $user->password_change_required
            && ! in_array($request->route()?->getName(), $allowedRoutes, true)
        ) {
            return match ($user->role) {
                'admin' => redirect()->route('admin.passwords.index'),
                'teacher' => redirect()->route('password.change'),
            };
        }

        return $next($request);
    }
}
