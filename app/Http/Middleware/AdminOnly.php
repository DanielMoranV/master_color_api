<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user || ($user->role && $user->role->name !== 'Admin')) {
            return ApiResponseClass::errorResponse('Acceso solo permitido para administradores', 403);
        }
        return $next($request);
    }
}
