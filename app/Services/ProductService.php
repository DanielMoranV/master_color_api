<?php

namespace App\Services;

use App\Models\Product;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class ProductService
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private StockService $stockService,
        private StockMovementService $stockMovementService
    ) {}

    public function getAllProducts(int $perPage = 15): LengthAwarePaginator
    {
        return Cache::remember('products_paginated_' . $perPage . '_' . request('page', 1), 300, function () use ($perPage) {
            return Product::with(['user', 'stock'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });
    }

    public function getProductById(int $id): ?Product
    {
        return Cache::remember("product_{$id}", 600, function () use ($id) {
            return Product::with(['user', 'stock'])->find($id);
        });
    }

    public function createProduct(ProductStoreRequest $request): Product
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validated();
            
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $this->fileUploadService->uploadImage(
                    $request->file('image'),
                    'products',
                    'product'
                );
            }

            // Separate product data from stock data
            $stockData = [
                'quantity' => 0, // Start with 0, will be set by stock movement
                'min_stock' => $validated['min_stock'] ?? 0,
                'max_stock' => $validated['max_stock'] ?? 0,
                'purchase_price' => $validated['purchase_price'],
                'sale_price' => $validated['sale_price'],
            ];

            $productData = array_merge($validated, [
                'image_url' => $imagePath,
                'user_id' => Auth::id(),
            ]);

            // Remove stock fields and image from product data
            unset($productData['image'], $productData['quantity'], $productData['min_stock'], 
                  $productData['max_stock'], $productData['purchase_price'], $productData['sale_price']);

            $product = Product::create($productData);

            $stock = $this->stockService->createInitialStock($product, $stockData);

            // Create initial stock movement if quantity > 0
            $initialQuantity = $validated['quantity'] ?? 0;
            if ($initialQuantity > 0) {
                $this->stockMovementService->createMovement([
                    'movement_type' => 'entrada',
                    'reason' => 'Stock inicial del producto',
                    'voucher_number' => 'INIT-' . $product->id,
                    'stocks' => [[
                        'stock_id' => $stock->id,
                        'quantity' => $initialQuantity,
                        'unit_price' => $stockData['purchase_price']
                    ]]
                ]);
            }

            $this->clearProductCache();
            
            DB::commit();
            
            return $product->load(['user', 'stock']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            if (isset($imagePath)) {
                $this->fileUploadService->deleteImage($imagePath);
            }
            
            throw $e;
        }
    }

    public function updateProduct(ProductUpdateRequest $request, int $id): ?Product
    {
        $product = Product::find($id);
        
        if (!$product) {
            return null;
        }

        DB::beginTransaction();
        
        try {
            $validated = $request->validated();
            $oldImagePath = $product->image_url;

            if ($request->hasFile('image')) {
                $this->fileUploadService->deleteImage($oldImagePath);
                
                $validated['image_url'] = $this->fileUploadService->uploadImage(
                    $request->file('image'),
                    'products',
                    'product'
                );
            }

            // Separate stock data from product data
            $stockData = [];
            $stockFields = ['min_stock', 'max_stock', 'purchase_price', 'sale_price'];
            
            foreach ($stockFields as $field) {
                if (isset($validated[$field])) {
                    $stockData[$field] = $validated[$field];
                    unset($validated[$field]);
                }
            }

            unset($validated['image']);

            $product->update($validated);

            // Update stock if stock data provided
            if (!empty($stockData)) {
                $this->stockService->updateStock($product->id, $stockData);
            }
            
            $this->clearProductCache($id);
            
            DB::commit();
            
            return $product->refresh()->load(['user', 'stock']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            if (isset($validated['image_url'])) {
                $this->fileUploadService->deleteImage($validated['image_url']);
            }
            
            throw $e;
        }
    }

    public function deleteProduct(int $id): bool
    {
        $product = Product::find($id);
        
        if (!$product) {
            return false;
        }

        DB::beginTransaction();
        
        try {
            $this->fileUploadService->deleteImage($product->image_url);
            
            $product->delete();
            
            $this->clearProductCache($id);
            
            DB::commit();
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function searchProducts(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return Product::with(['user', 'stock'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('barcode', 'like', "%{$query}%")
                  ->orWhere('brand', 'like', "%{$query}%")
                  ->orWhere('category', 'like', "%{$query}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getProductsByCategory(string $category, int $perPage = 15): LengthAwarePaginator
    {
        return Cache::remember("products_category_{$category}_{$perPage}_" . request('page', 1), 300, function () use ($category, $perPage) {
            return Product::with(['user', 'stock'])
                ->where('category', $category)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });
    }

    private function clearProductCache(?int $productId = null): void
    {
        if ($productId) {
            Cache::forget("product_{$productId}");
        }
        
        Cache::flush();
    }
}