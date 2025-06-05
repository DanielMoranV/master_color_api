<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Requests\ClientChangePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Client;
use App\Http\Resources\ClientResource;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\ClientRegisterRequest;
use App\Http\Requests\ClientUpdateProfileRequest;
use App\Http\Requests\LoginClientRequest;

class ClientAuthController extends Controller
{
    /**
     * Register a new client
     */
    public function register(ClientRegisterRequest $request)
    {
        try {
            $data = $request->validated();

            $client = Client::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'client_type' => strtolower($data['client_type']),
                'identity_document' => $data['identity_document'],
                'document_type' => strtoupper($data['document_type']),
                'phone' => $data['phone'] ?? null,
            ]);

            if (!$client) {
                return ApiResponseClass::errorResponse('Error al crear el cliente');
            }

            // Generate JWT token with client guard
            Auth::guard('client')->setUser($client);
            $token = JWTAuth::fromUser($client, ['type' => 'client']);

            return ApiResponseClass::sendResponse([
                'user' => new ClientResource($client),
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60
            ], 'Cliente registrado exitosamente', 201);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error en el registro del cliente', 500, [$e->getMessage()]);
        }
    }

    /**
     * Login a client
     */
    public function login(LoginClientRequest $request)
    {
        try {
            $data = $request->validated();

            // Find client by email
            $client = Client::where('email', $data['email'])->first();

            if (!$client) {
                return ApiResponseClass::errorResponse('Credenciales inválidas', 401);
            }

            // Verify password manually since we're using a custom guard
            if (!Hash::check($data['password'], $client->password)) {
                return ApiResponseClass::errorResponse('Credenciales inválidas', 401);
            }

            // Set the client in the auth guard and generate token

            $token = JWTAuth::claims([
                'token_version' => $client->token_version,
            ])->fromUser($client);


            return ApiResponseClass::sendResponse([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => new ClientResource($client),
            ], 'Cliente autenticado exitosamente', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al iniciar sesión', 500, [$e->getMessage()]);
        }
    }

    public function me()
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            return ApiResponseClass::sendResponse(
                ['user' => new ClientResource($client)],
                'Perfil del cliente',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al obtener el perfil', 500, [$e->getMessage()]);
        }
    }

    /**
     * Get authenticated client profile
     */
    public function profile()
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            return ApiResponseClass::sendResponse(
                ['client' => new ClientResource($client)],
                'Perfil del cliente',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al obtener el perfil', 500, [$e->getMessage()]);
        }
    }

    /**
     * Update client profile
     */
    public function updateProfile(ClientUpdateProfileRequest $request)
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $data = $request->validated();

            $data = array_filter($data, function ($value) {
                return !is_null($value);
            });

            $client->update($data);

            return ApiResponseClass::sendResponse(
                ['client' => new ClientResource($client)],
                'Perfil actualizado exitosamente',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al actualizar el perfil', 500, [$e->getMessage()]);
        }
    }

    /**
     * Change client password
     */
    public function changePassword(ClientChangePasswordRequest $request)
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $data = $request->validated();

            // Check current password
            if (!Hash::check($data['current_password'], $client->password)) {
                return ApiResponseClass::errorResponse('La contraseña actual es incorrecta', 422);
            }

            $client->password = Hash::make($data['password']);
            $client->save();

            return ApiResponseClass::sendResponse(
                [],
                'Contraseña actualizada exitosamente',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al cambiar la contraseña', 500, [$e->getMessage()]);
        }
    }

    /**
     * Refresh the token
     */
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

    /**
     * Logout client
     */
    public function logout()
    {
        try {
            $client = Auth::guard('client')->user();
            $client->increment('token_version');
            $client->save();
            $data = ['message' => 'Sesión cerrada exitosamente.'];
            return ApiResponseClass::sendResponse($data, 'Sesión cerrada exitosamente.', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al cerrar sesión', 500, [$e->getMessage()]);
        }
    }
}
