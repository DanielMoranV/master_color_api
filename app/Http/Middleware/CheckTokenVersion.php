<?php

namespace App\Http\Middleware;

use App\Classes\ApiResponseClass;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckTokenVersion
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        try {
            // Asegura que el usuario esté autenticado
            if (!$user = Auth::user()) {
                return ApiResponseClass::errorResponse('Usuario no autenticado.', 401);
            }

            // Obtiene el token actual y el valor de 'token_version'
            $tokenPayload = JWTAuth::parseToken()->getPayload();
            $tokenVersion = $tokenPayload->get('token_version');

            // Compara con el valor actual en la base de datos
            if ($user->token_version !== $tokenVersion) {
                return ApiResponseClass::errorResponse('Token inválido. Por favor, inicie sesión nuevamente.', 401);
            }

            return $next($request);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error validando token.', 401);
        }
    }
}
