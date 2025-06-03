<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use Illuminate\Http\Request;
use App\Models\Address;
use App\Http\Resources\AddressResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ClientAddressController extends Controller
{
    /**
     * Display a listing of the client's addresses.
     */
    public function index()
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $addresses = $client->addresses()->paginate(10);

            return ApiResponseClass::sendResponse(
                AddressResource::collection($addresses),
                'Direcciones del cliente',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al obtener direcciones', 500, [$e->getMessage()]);
        }
    }

    /**
     * Store a newly created address.
     */
    public function store(Request $request)
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $validator = Validator::make($request->all(), [
                'address_full' => 'required|string|max:255',
                'district' => 'required|string|max:100',
                'province' => 'required|string|max:100',
                'department' => 'required|string|max:100',
                'postal_code' => 'required|string|max:20',
                'reference' => 'nullable|string|max:255',
                'is_main' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponseClass::errorResponse('Error de validación', 422, $validator->errors());
            }

            // If this is the main address, unset any existing main address
            if ($request->is_main) {
                $client->addresses()->update(['is_main' => false]);
            }

            // If this is the first address, make it the main one
            $isFirstAddress = $client->addresses()->count() === 0;
            $is_main = $request->has('is_main') ? $request->is_main : $isFirstAddress;

            $address = $client->addresses()->create([
                'address_full' => $request->address_full,
                'district' => $request->district,
                'province' => $request->province,
                'department' => $request->department,
                'postal_code' => $request->postal_code,
                'reference' => $request->reference,
                'is_main' => $is_main,
            ]);

            return ApiResponseClass::sendResponse(
                new AddressResource($address),
                'Dirección creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al crear dirección', 500, [$e->getMessage()]);
        }
    }

    /**
     * Display the specified address.
     */
    public function show(Request $request, $id)
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $address = $client->addresses()->find($id);

            if (!$address) {
                return ApiResponseClass::errorResponse('Dirección no encontrada', 404);
            }

            return ApiResponseClass::sendResponse(
                new AddressResource($address),
                'Detalle de dirección',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al obtener dirección', 500, [$e->getMessage()]);
        }
    }

    /**
     * Update the specified address.
     */
    public function update(Request $request, $id)
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $address = $client->addresses()->find($id);

            if (!$address) {
                return ApiResponseClass::errorResponse('Dirección no encontrada', 404);
            }

            $validator = Validator::make($request->all(), [
                'address_full' => 'sometimes|string|max:255',
                'district' => 'sometimes|string|max:100',
                'province' => 'sometimes|string|max:100',
                'department' => 'sometimes|string|max:100',
                'postal_code' => 'sometimes|string|max:20',
                'reference' => 'nullable|string|max:255',
                'is_main' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponseClass::errorResponse('Error de validación', 422, $validator->errors());
            }

            // If this is being set as the main address, unset any existing main address
            if ($request->has('is_main') && $request->is_main) {
                $client->addresses()->where('id', '!=', $id)->update(['is_main' => false]);
            }

            $address->update($request->all());

            return ApiResponseClass::sendResponse(
                new AddressResource($address),
                'Dirección actualizada exitosamente',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al actualizar dirección', 500, [$e->getMessage()]);
        }
    }

    /**
     * Remove the specified address.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $address = $client->addresses()->find($id);

            if (!$address) {
                return ApiResponseClass::errorResponse('Dirección no encontrada', 404);
            }

            // Check if this is the main address
            $isMain = $address->is_main;

            $address->delete();

            // If we deleted the main address, set another one as main if available
            if ($isMain) {
                $newMainAddress = $client->addresses()->first();
                if ($newMainAddress) {
                    $newMainAddress->update(['is_main' => true]);
                }
            }

            return ApiResponseClass::sendResponse(
                [],
                'Dirección eliminada exitosamente',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al eliminar dirección', 500, [$e->getMessage()]);
        }
    }

    /**
     * Set an address as the main address.
     */
    public function setAsMain(Request $request, $id)
    {
        try {
            $client = Auth::guard('client')->user();

            if (!$client) {
                return ApiResponseClass::errorResponse('No autenticado', 401);
            }

            $address = $client->addresses()->find($id);

            if (!$address) {
                return ApiResponseClass::errorResponse('Dirección no encontrada', 404);
            }

            // Unset any existing main address
            $client->addresses()->where('id', '!=', $id)->update(['is_main' => false]);

            // Set this address as main
            $address->update(['is_main' => true]);

            return ApiResponseClass::sendResponse(
                new AddressResource($address),
                'Dirección establecida como principal',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse('Error al establecer dirección principal', 500, [$e->getMessage()]);
        }
    }


}
