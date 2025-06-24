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
        private FileUploadService $fileUploadService
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

            $productData = array_merge($validated, [
                'image_url' => $imagePath,
                'user_id' => Auth::id(),
            ]);

            unset($productData['image']);

            $product = Product::create($productData);

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

            unset($validated['image']);

            $product->update($validated);
            
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