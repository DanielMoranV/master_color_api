<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Classes\ApiResponseClass;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::paginate(15);
        return ApiResponseClass::sendResponse(RoleResource::collection($roles), 'Lista de roles', 200);
    }

    public function store(RoleStoreRequest $request)
    {
        $data = $request->validated();
        $role = Role::create($data);
        return ApiResponseClass::sendResponse(RoleResource::make($role), 'Rol creado exitosamente', 201);
    }

    public function show($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return ApiResponseClass::errorResponse('Rol no encontrado', 404);
        }
        return ApiResponseClass::sendResponse(RoleResource::make($role), 'Detalle de rol', 200);
    }

    public function update(RoleUpdateRequest $request, $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return ApiResponseClass::errorResponse('Rol no encontrado', 404);
        }
        $role->update($request->validated());
        return ApiResponseClass::sendResponse(RoleResource::make($role), 'Rol actualizado correctamente', 200);
    }

    public function destroy($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return ApiResponseClass::errorResponse('Rol no encontrado', 404);
        }
        $role->delete();
        return ApiResponseClass::sendResponse([], 'Rol eliminado correctamente', 200);
    }
}
