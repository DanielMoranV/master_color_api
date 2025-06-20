<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;
use App\Http\Resources\ProductResource;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use Illuminate\Support\Facades\Auth;
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $products = Product::all();
            return ApiResponseClass::sendResponse(ProductResource::collection($products), 'Lista de productos', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $products = Product::all();
            return ApiResponseClass::sendResponse(ProductResource::collection($products), 'Lista de productos', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductStoreRequest $request)
    {
        try {
            // Validar datos
            $validated = $request->validated();

            // Crear directorio si no existe
            $storagePath = storage_path('app/public/products');
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Guardar imagen
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = uniqid('product_') . '.' . $image->getClientOriginalExtension();
                $image->storeAs('public/products', $imageName);
                $imageUrl = asset('storage/products/' . $imageName);
            } else {
                $imageUrl = null;
            }

            $validated['image_url'] = $imageUrl;
            $validated['user_id'] = Auth::user()->id;
            unset($validated['image']);

            $product = Product::create($validated);
            return ApiResponseClass::sendResponse(
                new ProductResource($product),
                'Producto creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }
            return ApiResponseClass::sendResponse(new ProductResource($product), 'Detalle de producto', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }
            return ApiResponseClass::sendResponse(new ProductResource($product), 'Detalle de producto', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductUpdateRequest $request, string $id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }
            $validated = $request->validated();
            $validated['user_id'] = Auth::user()->id;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = uniqid('product_') . '.' . $image->getClientOriginalExtension();
                $image->storeAs('public/products', $imageName);
                $imageUrl = asset('storage/products/' . $imageName);
            } else {
                $imageUrl = null;
            }
            $validated['image_url'] = $imageUrl;
            unset($validated['image']);
            $product->update($validated);
            return ApiResponseClass::sendResponse(new ProductResource($product), 'Producto actualizado correctamente', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }
            $product->delete();
            return ApiResponseClass::sendResponse([], 'Producto eliminado correctamente', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::errorResponse($e->getMessage(), 500);
        }
    }
}
