<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Client;
use Symfony\Component\HttpFoundation\Response;

class ClientAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = str_replace('Bearer ', '', $request->header('Authorization'));
            
            if (!$token) {
                return ApiResponseClass::errorResponse('Token no proporcionado', 401);
            }

            $decoded = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
            
            if (!isset($decoded->type) || $decoded->type !== 'client') {
                return ApiResponseClass::errorResponse('Token inválido o no es un cliente', 401);
            }

            $client = Client::find($decoded->sub);
            
            if (!$client) {
                return ApiResponseClass::errorResponse('Cliente no encontrado', 401);
            }

            // Add client to request
            $request->client = $client;

            return $next($request);
        } catch (\Firebase\JWT\ExpiredException $e) {
            return ApiResponseClass::errorResponse('Token expirado', 401);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Token inválido', 401);
        }
    }
}
