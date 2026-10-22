<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowedRoutes = [
            'admin.passwords.index',
            'admin.passwords.update',
            'logout',
        ];

        if (
            $user?->role === 'admin'
            && $user->password_change_required
            && ! in_array($request->route()?->getName(), $allowedRoutes, true)
        ) {
            return redirect()->route('admin.passwords.index');
        }

        return $next($request);
    }
}
