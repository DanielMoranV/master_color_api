<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\DetailMovement;
use App\Models\Stock;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockMovementService
{
    public function createMovement(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $movement = StockMovement::create([
                'movement_type' => $data['movement_type'],
                'reason' => $data['reason'],
                'voucher_number' => $data['voucher_number'] ?? null,
                'user_id' => Auth::id()
            ]);

            foreach ($data['stocks'] as $stockData) {
                $this->processStockMovement($movement, $stockData);
            }

            return $movement;
        });
    }

    public function updateMovement(StockMovement $movement, array $data): StockMovement
    {
        return DB::transaction(function () use ($movement, $data) {
            $this->revertStockChanges($movement);

            $movement->update([
                'movement_type' => $data['movement_type'] ?? $movement->movement_type,
                'reason' => $data['reason'] ?? $movement->reason,
                'voucher_number' => $data['voucher_number'] ?? $movement->voucher_number
            ]);

            if (isset($data['stocks'])) {
                $movement->details()->delete();
                
                foreach ($data['stocks'] as $stockData) {
                    $this->processStockMovement($movement, $stockData);
                }
            }

            return $movement;
        });
    }

    public function deleteMovement(StockMovement $movement): void
    {
        DB::transaction(function () use ($movement) {
            $this->revertStockChanges($movement);
            $movement->details()->delete();
            $movement->delete();
        });
    }

    private function processStockMovement(StockMovement $movement, array $stockData): void
    {
        $stock = Stock::findOrFail($stockData['stock_id']);
        $quantity = $stockData['quantity'];
        $unitPrice = $stockData['unit_price'] ?? 0;
        $previousStock = $stock->quantity;

        $newStock = $this->calculateNewStock($stock, $movement->movement_type, $quantity);

        DetailMovement::create([
            'stock_movement_id' => $movement->id,
            'stock_id' => $stock->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock
        ]);

        $this->updateStockQuantity($stock, $movement->movement_type, $quantity);
    }

    private function updateStockQuantity(Stock $stock, string $movementType, int $quantity): void
    {
        switch ($movementType) {
            case 'entrada':
                $stock->increment('quantity', $quantity);
                break;
            case 'salida':
                $this->validateSufficientStock($stock, $quantity);
                $stock->decrement('quantity', $quantity);
                break;
            case 'ajuste':
                $stock->update(['quantity' => $quantity]);
                break;
            case 'devolucion':
                $stock->increment('quantity', $quantity);
                break;
        }
    }

    private function revertStockChanges(StockMovement $movement): void
    {
        foreach ($movement->details as $detail) {
            $stock = $detail->stock;
            $stock->update(['quantity' => $detail->previous_stock]);
        }
    }

    private function calculateNewStock(Stock $stock, string $movementType, int $quantity): int
    {
        switch ($movementType) {
            case 'entrada':
                return $stock->quantity + $quantity;
            case 'salida':
                $this->validateSufficientStock($stock, $quantity);
                return $stock->quantity - $quantity;
            case 'ajuste':
                return $quantity;
            case 'devolucion':
                return $stock->quantity + $quantity;
            default:
                return $stock->quantity;
        }
    }

    private function validateSufficientStock(Stock $stock, int $quantity): void
    {
        if ($stock->quantity < $quantity) {
            throw new \Exception("Stock insuficiente para el producto {$stock->product->name}. Stock actual: {$stock->quantity}, cantidad solicitada: {$quantity}");
        }
    }

    public function cancelMovement(StockMovement $movement): StockMovement
    {
        if ($movement->canceled_at) {
            throw new \Exception("Este movimiento ya ha sido cancelado");
        }

        $reverseType = $this->getReverseMovementType($movement->movement_type);
        
        if (!$reverseType) {
            throw new \Exception("No se puede cancelar un movimiento de tipo '{$movement->movement_type}'");
        }

        return DB::transaction(function () use ($movement, $reverseType) {
            // Marcar movimiento original como cancelado
            $movement->update([
                'canceled_at' => now()
            ]);

            // Crear movimiento de cancelación
            $cancelationMovement = StockMovement::create([
                'movement_type' => $reverseType,
                'reason' => "ANULACIÓN del movimiento #{$movement->id} - {$movement->reason}",
                'voucher_number' => "ANUL-{$movement->id}-" . now()->format('YmdHis'),
                'user_id' => Auth::id()
            ]);

            // Crear detalles del movimiento de cancelación (mismos productos, mismas cantidades)
            foreach ($movement->details as $detail) {
                $this->processStockMovement($cancelationMovement, [
                    'stock_id' => $detail->stock_id,
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price
                ]);
            }

            return $cancelationMovement;
        });
    }

    private function getReverseMovementType(string $movementType): ?string
    {
        $reverseTypes = [
            'entrada' => 'salida',
            'salida' => 'entrada',
            'devolucion' => 'salida',
            'ajuste' => null // No permitido
        ];

        return $reverseTypes[$movementType] ?? null;
    }

    /**
     * Process stock reduction for a paid order
     */
    public function processOrderStockReduction(Order $order): StockMovement
    {
        return DB::transaction(function () use ($order) {
            // Crear movimiento de stock por venta
            $movement = StockMovement::create([
                'movement_type' => 'salida',
                'reason' => "VENTA - Orden #{$order->id} - Cliente: {$order->client->name}",
                'voucher_number' => "VENTA-{$order->id}-" . now()->format('YmdHis'),
                'user_id' => $order->user_id ?? 1 // Usuario del sistema si no hay usuario asignado
            ]);

            // Procesar cada producto de la orden
            foreach ($order->orderDetails as $detail) {
                $stock = $detail->product->stock;
                
                if (!$stock) {
                    throw new \Exception("Producto {$detail->product->name} no tiene stock configurado");
                }

                // Validar stock suficiente
                if ($stock->quantity < $detail->quantity) {
                    throw new \Exception("Stock insuficiente para {$detail->product->name}. Disponible: {$stock->quantity}, Requerido: {$detail->quantity}");
                }

                // Crear detalle del movimiento
                $previousStock = $stock->quantity;
                $newStock = $previousStock - $detail->quantity;

                DetailMovement::create([
                    'stock_movement_id' => $movement->id,
                    'stock_id' => $stock->id,
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock
                ]);

                // Actualizar stock
                $stock->update(['quantity' => $newStock]);
            }

            return $movement;
        });
    }

}