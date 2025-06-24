<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;
use App\Http\Resources\ProductResource;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Services\ProductService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $products = Product::all();
            return ApiResponseClass::sendResponse(
                ProductResource::collection($products),
                'Lista de productos',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage());
            return ApiResponseClass::errorResponse('Error interno del servidor', 500);
        }
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductStoreRequest $request)
    {
        try {
            $product = $this->productService->createProduct($request);
            
            return ApiResponseClass::sendResponse(
                new ProductResource($product),
                'Producto creado exitosamente',
                201
            );

        } catch (ValidationException $e) {
            return ApiResponseClass::errorResponse(
                'Error de validación: ' . $e->getMessage(),
                422
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponseClass::errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Error creating product: ' . $e->getMessage());
            return ApiResponseClass::errorResponse(
                'Error interno del servidor',
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $product = $this->productService->getProductById((int) $id);
            
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }

            return ApiResponseClass::sendResponse(
                new ProductResource($product),
                'Detalle de producto',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error fetching product: ' . $e->getMessage(), ['product_id' => $id]);
            return ApiResponseClass::errorResponse('Error interno del servidor', 500);
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

            // // Verificar permisos
            // if ($product->user_id !== Auth::id()) {
            //     return ApiResponseClass::errorResponse(
            //         'No tienes permisos para editar este producto',
            //         403
            //     );
            // }

            return ApiResponseClass::sendResponse(
                new ProductResource($product),
                'Datos para edición de producto',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error fetching product for edit: ' . $e->getMessage(), ['product_id' => $id]);
            return ApiResponseClass::errorResponse('Error interno del servidor', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateProduct(ProductUpdateRequest $request, string $id)
    {
        try {
            $product = $this->productService->updateProduct($request, (int) $id);
            
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }

            return ApiResponseClass::sendResponse(
                new ProductResource($product),
                'Producto actualizado correctamente',
                200
            );

        } catch (ValidationException $e) {
            return ApiResponseClass::errorResponse(
                'Error de validación: ' . $e->getMessage(),
                422
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponseClass::errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Error updating product: ' . $e->getMessage());
            return ApiResponseClass::errorResponse(
                'Error interno del servidor',
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $deleted = $this->productService->deleteProduct((int) $id);
            
            if (!$deleted) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }

            return ApiResponseClass::sendResponse(
                null,
                'Producto eliminado correctamente',
                200
            );

        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());
            return ApiResponseClass::errorResponse(
                'Error interno del servidor',
                500
            );
        }
    }

}
