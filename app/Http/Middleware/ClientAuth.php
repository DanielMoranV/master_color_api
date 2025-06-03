<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (!$token = JWTAuth::getToken()) {
                return ApiResponseClass::errorResponse('Token no proporcionado', 401);
            }

            $payload = JWTAuth::getPayload($token)->toArray();
            
            // Check if the token is for a client
            if (!isset($payload['type']) || $payload['type'] !== 'client') {
                return ApiResponseClass::errorResponse('Token inválido o no es un cliente', 401);
            }

            // Authenticate the client
            if (!Auth::guard('client')->check()) {
                return ApiResponseClass::errorResponse('Cliente no autenticado', 401);
            }

            return $next($request);
        } catch (TokenExpiredException $e) {
            return ApiResponseClass::errorResponse('Token expirado', 401);
        } catch (TokenInvalidException $e) {
            return ApiResponseClass::errorResponse('Token inválido', 401);
        } catch (JWTException $e) {
            return ApiResponseClass::errorResponse('Token no proporcionado', 401);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error de autenticación', 500, [$e->getMessage()]);
        }
    }
}
