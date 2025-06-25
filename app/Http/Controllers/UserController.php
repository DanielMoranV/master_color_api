<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Classes\ApiResponseClass;
use App\Http\Request;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}
    public function index()
    {
        try {
            $users = $this->userService->getAllUsers(15);
            return ApiResponseClass::sendResponse(
                UserResource::collection($users),
                'Lista de usuarios',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return ApiResponseClass::errorResponse('Error interno del servidor', 500);
        }
    }

    public function store(UserStoreRequest $request)
    {
        try {
            $user = $this->userService->createUser($request);
            
            return ApiResponseClass::sendResponse(
                new UserResource($user),
                'Usuario creado exitosamente',
                201
            );

        } catch (ValidationException $e) {
            return ApiResponseClass::errorResponse(
                'Error de validación: ' . $e->getMessage(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            return ApiResponseClass::errorResponse(
                'Error interno del servidor',
                500
            );
        }
    }

    public function show(string $id)
    {
        try {
            $user = $this->userService->getUserById((int) $id);
            
            if (!$user) {
                return ApiResponseClass::errorResponse('Usuario no encontrado', 404);
            }

            return ApiResponseClass::sendResponse(
                new UserResource($user),
                'Detalle de usuario',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage(), ['user_id' => $id]);
            return ApiResponseClass::errorResponse('Error interno del servidor', 500);
        }
    }

    public function update(UserUpdateRequest $request, string $id)
    {
        try {
            $user = $this->userService->updateUser($request, (int) $id);
            
            if (!$user) {
                return ApiResponseClass::errorResponse('Usuario no encontrado', 404);
            }

            return ApiResponseClass::sendResponse(
                new UserResource($user),
                'Usuario actualizado correctamente',
                200
            );

        } catch (ValidationException $e) {
            return ApiResponseClass::errorResponse(
                'Error de validación: ' . $e->getMessage(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            return ApiResponseClass::errorResponse(
                'Error interno del servidor',
                500
            );
        }
    }

    public function destroy(string $id)
    {
        try {
            $deleted = $this->userService->deleteUser((int) $id);
            
            if (!$deleted) {
                return ApiResponseClass::errorResponse('Usuario no encontrado', 404);
            }

            return ApiResponseClass::sendResponse(
                null,
                'Usuario eliminado correctamente',
                200
            );

        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return ApiResponseClass::errorResponse(
                'Error interno del servidor',
                500
            );
        }
    }
}
