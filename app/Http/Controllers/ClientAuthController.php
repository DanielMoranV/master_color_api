<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Requests\ClientChangePasswordRequest;
use App\Http\Requests\ClientRegisterRequest;
use App\Http\Requests\ClientUpdateProfileRequest;
use App\Http\Requests\LoginClientRequest;
use App\Http\Resources\ClientResource;
use App\Mail\ClientEmailVerification;
use App\Mail\ClientResetPassword;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class ClientAuthController extends Controller
{
    /**
     * Register a new client
     */
    public function register(ClientRegisterRequest $request)
    {
        try {
            $data = $request->validated();

            // Generar token de verificación único
            $verificationToken = Str::random(60);

            $client = Client::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'client_type' => strtolower($data['client_type']),
                'identity_document' => $data['identity_document'],
                'document_type' => strtoupper($data['document_type']),
                'phone' => $data['phone'] ?? null,
                'verification_token' => $verificationToken,
                'email_verified_at' => null, // Explícitamente establecido como null hasta que se verifique
            ]);

            if (!$client) {
                return ApiResponseClass::errorResponse('Error al crear el cliente');
            }

            // Generate JWT token with client guard
            Auth::guard('client')->setUser($client);
            $token = JWTAuth::fromUser($client, ['type' => 'client']);

            // Enviar correo de verificación
            try {
                Mail::to($client->email)->send(new ClientEmailVerification($client));
            } catch (\Exception $mailException) {
                // Log el error pero continuamos con el registro
                \Log::error('Error al enviar correo de verificación: ' . $mailException->getMessage());
            }

            return ApiResponseClass::sendResponse([
                'user' => new ClientResource($client),
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'verification_email_sent' => true,
                'email_verified' => false
            ], 'Cliente registrado exitosamente. Por favor verifica tu correo electrónico.', 201);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error en el registro del cliente', 500, [$e->getMessage()]);
        }
    }

    public function verifyEmail(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $client = Client::where('verification_token', $request->token)->first();

        if (!$client) {
            return ApiResponseClass::errorResponse('Token inválido o ya verificado.', 400);
        }

        // Verificar si el correo ya fue verificado
        if ($client->email_verified_at) {
            return ApiResponseClass::sendResponse([], 'El correo electrónico ya ha sido verificado anteriormente.');
        }

        $client->email_verified_at = now();
        $client->verification_token = null;
        $client->save();

        return ApiResponseClass::sendResponse([
            'email_verified' => true,
            'user' => new ClientResource($client)
        ], 'Correo verificado exitosamente. Ahora puedes iniciar sesión.');
    }

    /**
     * Reenviar correo de verificación
     */
    public function resendVerificationEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $client = Client::where('email', $request->email)->first();

        if (!$client) {
            return ApiResponseClass::errorResponse('No se encontró ningún cliente con este correo electrónico.', 404);
        }

        if ($client->email_verified_at) {
            return ApiResponseClass::errorResponse('El correo electrónico ya ha sido verificado.', 400);
        }

        // Regenerar token de verificación
        $client->verification_token = Str::random(60);
        $client->save();

        try {
            Mail::to($client->email)->send(new ClientEmailVerification($client));

            return ApiResponseClass::sendResponse([], 'Se ha reenviado el correo de verificación.');
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al enviar el correo de verificación.', 500, [$e->getMessage()]);
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

            if (is_null($client->email_verified_at)) {
                return ApiResponseClass::errorResponse('Debes verificar tu correo antes de iniciar sesión.', 403);
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
    /**
     * Solicitar recuperación de contraseña
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::errorResponse('Error de validación', 422, $validator->errors());
        }

        $client = Client::where('email', $request->email)->first();

        if (!$client) {
            // Por seguridad, no revelamos si el email existe o no
            return ApiResponseClass::sendResponse([], 'Se ha enviado un enlace de recuperación si el correo existe en nuestro sistema.');
        }

        // Eliminar tokens anteriores para este email
        DB::table('client_password_resets')
            ->where('email', $request->email)
            ->delete();

        // Crear nuevo token
        $token = Str::random(60);

        // Guardar token en la base de datos
        DB::table('client_password_resets')->insert([
            'email' => $request->email,
            'token' => Hash::make($token), // Almacenamos el hash del token por seguridad
            'created_at' => Carbon::now()
        ]);

        // Enviar correo con el token
        try {
            Mail::to($request->email)->send(new ClientResetPassword($token, $request->email, $client->name));

            return ApiResponseClass::sendResponse([], 'Se ha enviado un enlace de recuperación a tu correo electrónico.');
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al enviar el correo de recuperación.', 500, [$e->getMessage()]);
        }
    }

    /**
     * Validar token y restablecer contraseña
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::errorResponse('Error de validación', 422, $validator->errors());
        }

        // Verificar si existe el token para el email y no ha expirado (1 hora)
        $passwordReset = DB::table('client_password_resets')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset || !Hash::check($request->token, $passwordReset->token)) {
            return ApiResponseClass::errorResponse('Token inválido o expirado.', 400);
        }

        // Verificar si el token ha expirado (1 hora)
        if (Carbon::parse($passwordReset->created_at)->addHour()->isPast()) {
            // Eliminar el token expirado
            DB::table('client_password_resets')
                ->where('email', $request->email)
                ->delete();

            return ApiResponseClass::errorResponse('El token ha expirado. Por favor solicita un nuevo enlace de recuperación.', 400);
        }

        // Actualizar la contraseña del cliente
        $client = Client::where('email', $request->email)->first();

        if (!$client) {
            return ApiResponseClass::errorResponse('No se encontró ningún cliente con este correo electrónico.', 404);
        }

        $client->password = Hash::make($request->password);
        $client->save();

        // Eliminar el token usado
        DB::table('client_password_resets')
            ->where('email', $request->email)
            ->delete();

        return ApiResponseClass::sendResponse([], 'Contraseña actualizada correctamente.');
    }

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
