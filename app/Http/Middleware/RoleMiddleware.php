<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage in route: ->middleware('role:Admin') or ->middleware('role:Admin,Vendedor')
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $roles  (comma separated)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $roles)
    {
        $user = Auth::user();
        $rolesArray = array_map('trim', explode(',', $roles));
        if (!$user || !$user->role || !in_array($user->role->name, $rolesArray)) {
            return ApiResponseClass::errorResponse('Acceso solo permitido para los roles: ' . implode(', ', $rolesArray), 403);
        }
        return $next($request);
    }
}
