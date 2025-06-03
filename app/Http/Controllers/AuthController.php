<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

    public function register(RegisterRequest $request){
        try {
            $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'dni' => $data['dni'],
            'phone' => $data['phone'] ?? null,
        ]);

        if (!$user) {
            return ApiResponseClass::errorResponse('Error al crear el usuario');
        }
        $token = JWTAuth::claims([
            'token_version' => $user->token_version,
        ])->fromUser($user);

        return ApiResponseClass::sendResponse([
            'user' => new UserResource($user->load('role')),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60
        ], 'Usuario creado exitosamente', 201);
    } catch (\Exception $e) {
        return ApiResponseClass::errorResponse('Error en el proceso de creación del usuario', 500, [$e->getMessage()]);
    }
    }
    public function login(LoginRequest $request)
    {
        try {
        $credentials = $request->validated();

            // Validar si el usuario esta activo y no esta eliminado
            $user = User::where('email', $credentials['email'])->first();
            if (!$user || !$user->is_active || $user->deleted_at) {
                return ApiResponseClass::errorResponse('Credenciales inválidas', 401);
            }


            // Verificar credenciales JWT
            if (!JWTAuth::attempt($credentials)) {
                return ApiResponseClass::errorResponse('Credenciales inválidas', 401);
            }


            $token = JWTAuth::claims(['token_version' => $user->token_version])->fromUser($user);

            return ApiResponseClass::sendResponse([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => new UserResource($user),
            ], 'Usuario autenticado exitosamente', 200);
        } catch (\Exception $e) {

            return ApiResponseClass::errorResponse('Error al iniciar sesión', 500, [$e->getMessage()]);
        }
    }

    public function me()
    {
        $user = Auth::user();
        return $user
            ? ApiResponseClass::sendResponse(['user' => new UserResource($user->load('role'))], 'Usuario autenticado', 200)
            : ApiResponseClass::errorResponse('No autenticado', 401);
    }

    public function refresh()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
            $data = [
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ];
            return ApiResponseClass::sendResponse($data, 'Token renovado exitosamente', 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return ApiResponseClass::errorResponse('Token inválido.', 401);
        }
    }

    public function logout()
    {
        try {
            $user = Auth::user();
            $user->increment('token_version');
            $data = ['message' => 'Sesión cerrada exitosamente.'];
            return ApiResponseClass::sendResponse($data, 'Sesión cerrada exitosamente.', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al cerrar sesión', 500, [$e->getMessage()]);
        }
    }

}
