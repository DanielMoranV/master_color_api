<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function createInitialStock(Product $product, array $stockData = []): Stock
    {
        DB::beginTransaction();
        
        try {
            $defaultStockData = [
                'product_id' => $product->id,
                'quantity' => 0,
                'min_stock' => 0,
                'max_stock' => 0,
                'purchase_price' => 0.00,
                'sale_price' => 0.00
            ];

            $stockData = array_merge($defaultStockData, $stockData, ['product_id' => $product->id]);

            $stock = Stock::create($stockData);

            DB::commit();
            
            return $stock;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateStock(int $productId, array $stockData): ?Stock
    {
        $stock = Stock::where('product_id', $productId)->first();
        
        if (!$stock) {
            return null;
        }

        DB::beginTransaction();
        
        try {
            // Filter out null values to allow partial updates
            $updateData = array_filter($stockData, function($value) {
                return $value !== null;
            });
            
            if (!empty($updateData)) {
                $stock->update($updateData);
            }
            
            DB::commit();
            
            return $stock->refresh();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getStockByProductId(int $productId): ?Stock
    {
        return Stock::where('product_id', $productId)->first();
    }
}