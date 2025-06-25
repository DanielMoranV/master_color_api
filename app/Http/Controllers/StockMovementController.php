<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockMovement;
use App\Http\Resources\StockMovementResource;
use App\Classes\ApiResponseClass;
use App\Http\Requests\StockMovementStoreRequest;
use App\Http\Requests\StockMovementUpdateRequest;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\Log;

class StockMovementController extends Controller
{
    protected $stockMovementService;

    public function __construct(StockMovementService $stockMovementService)
    {
        $this->stockMovementService = $stockMovementService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $stockMovements = StockMovement::with(['user', 'details.stock.product'])
                ->orderBy('created_at', 'desc')
                ->get();
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
            Log::info('Creating stock movement: ' . json_encode($request->validated()));
            $movement = $this->stockMovementService->createMovement($request->validated());
            return ApiResponseClass::sendResponse(
                new StockMovementResource($movement->load(['user', 'details.stock.product'])),
                'Movimiento de stock creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            Log::error('Error creating stock movement: ' . $e->getMessage());
            return ApiResponseClass::errorResponse('Error interno del servidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(StockMovement $stockMovement)
    {
        try {
            return ApiResponseClass::sendResponse(
                new StockMovementResource($stockMovement->load(['user', 'details.stock.product'])),
                'Detalles del movimiento de stock',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error fetching stock movement: ' . $e->getMessage());
            return ApiResponseClass::errorResponse('Error interno del servidor', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StockMovementUpdateRequest $request, StockMovement $stockMovement)
    {
        try {
            $updatedMovement = $this->stockMovementService->updateMovement($stockMovement, $request->validated());
            return ApiResponseClass::sendResponse(
                new StockMovementResource($updatedMovement->load(['user', 'details.stock.product'])),
                'Movimiento de stock actualizado exitosamente',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error updating stock movement: ' . $e->getMessage());
            return ApiResponseClass::errorResponse('Error interno del servidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StockMovement $stockMovement)
    {
        try {
            $this->stockMovementService->deleteMovement($stockMovement);
            return ApiResponseClass::sendResponse(
                null,
                'Movimiento de stock eliminado exitosamente',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error deleting stock movement: ' . $e->getMessage());
            return ApiResponseClass::errorResponse('Error interno del servidor: ' . $e->getMessage(), 500);
        }
    }
}
