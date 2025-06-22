<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockMovement;
use App\Http\Resources\StockMovementResource;
use App\Classes\ApiResponseClass;
use App\Http\Requests\StockMovementStoreRequest;
use Illuminate\Support\Facades\Log;

class StockMovementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $stockMovements = StockMovement::all();
            return ApiResponseClass::sendResponse(
                StockMovementResource::collection($stockMovements),
                'Lista de movimientos de stock',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error fetching stock movements: ' . $e->getMessage());
            return ApiResponseClass::errorResponse('Error interno del servidor', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StockMovementStoreRequest $request)
    {
        try {
            $stockMovement = StockMovement::create($request->validated());
            return ApiResponseClass::sendResponse(new StockMovementResource($stockMovement), 'Movimiento de stock creado exitosamente', 201);
        } catch (\Exception $e) {
            Log::error('Error creating stock movement: ' . $e->getMessage());
            return ApiResponseClass::errorResponse('Error interno del servidor', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
