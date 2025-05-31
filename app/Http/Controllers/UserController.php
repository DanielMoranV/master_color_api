<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Classes\ApiResponseClass;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('role')->paginate(15);
        return ApiResponseClass::sendResponse(UserResource::collection($users), 'Lista de usuarios', 200);
    }

    public function store(UserStoreRequest $request)
    {
        $data = $request->validated();
        $user = User::create($data);
        $user->load('role');
        return ApiResponseClass::sendResponse(new UserResource($user), 'Usuario creado exitosamente', 201);
    }

    public function show($id)
    {
        $user = User::with('role')->find($id);
        if (!$user) {
            return ApiResponseClass::errorResponse('Usuario no encontrado', 404);
        }
        return ApiResponseClass::sendResponse(new UserResource($user), 'Detalle de usuario', 200);
    }

    public function update(UserUpdateRequest $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return ApiResponseClass::errorResponse('Usuario no encontrado', 404);
        }
        $user->update($request->validated());
        $user->load('role');
        return ApiResponseClass::sendResponse(new UserResource($user), 'Usuario actualizado correctamente', 200);
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return ApiResponseClass::errorResponse('Usuario no encontrado', 404);
        }
        $user->delete();
        return ApiResponseClass::sendResponse([], 'Usuario eliminado correctamente', 200);
    }
}
