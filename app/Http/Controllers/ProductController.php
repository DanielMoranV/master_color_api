<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;
use App\Http\Resources\ProductResource;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Para APIs REST, este método no es necesario
        // Pero si lo necesitas, puedes retornar metadatos para el formulario
        return ApiResponseClass::sendResponse(
            ['message' => 'Endpoint para obtener datos del formulario de creación'],
            'Formulario de creación',
            200
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            // Validar datos
            $validated = $request->validated();

            // Manejar la imagen si existe
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $this->handleImageUpload($request->file('image'));
            }

            // Preparar datos para crear el producto
            $productData = array_merge($validated, [
                'image_url' => $imagePath,
                'user_id' => Auth::id(),
            ]);

            // Remover el campo image del array ya que no va a la BD
            unset($productData['image']);

            // Crear el producto
            $product = Product::create($productData);

            DB::commit();

            return ApiResponseClass::sendResponse(
                new ProductResource($product),
                'Producto creado exitosamente',
                201
            );

        } catch (ValidationException $e) {
            DB::rollBack();
            return ApiResponseClass::errorResponse(
                'Error de validación: ' . $e->getMessage(),
                422
            );
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return ApiResponseClass::errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            DB::rollBack();

            // Log del error para debugging
            Log::error('Error creating product: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->except(['image']),
                'trace' => $e->getTraceAsString()
            ]);

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
            $product = Product::find($id);
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
            DB::beginTransaction();
            // Buscar el producto
            $product = Product::find($id);
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }

            // // Verificar que el usuario tenga permisos para actualizar este producto
            // if ($product->user_id !== Auth::id()) {
            //     return ApiResponseClass::errorResponse(
            //         'No tienes permisos para actualizar este producto',
            //         403
            //     );
            // }

            // Validar datos
            $validated = $request->validated();

            // Manejar la imagen si se está actualizando
            if ($request->hasFile('image')) {
                // Eliminar imagen anterior si existe
                $this->deleteOldImage($product->image_url);

                // Subir nueva imagen
                $validated['image_url'] = $this->handleImageUpload($request->file('image'));
            }

            // Remover el campo image del array ya que no va a la BD
            unset($validated['image']);

            // Actualizar el producto
            $product->update($validated);

            DB::commit();

            // Recargar el producto con los datos actualizados
            $product->refresh();

            return ApiResponseClass::sendResponse(
                new ProductResource($product),
                'Producto actualizado correctamente',
                200
            );

        } catch (ValidationException $e) {
            DB::rollBack();
            return ApiResponseClass::errorResponse(
                'Error de validación: ' . $e->getMessage(),
                422
            );
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return ApiResponseClass::errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            DB::rollBack();

            // Log del error para debugging
            Log::error('Error updating product: ' . $e->getMessage(), [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'request_data' => $request->except(['image']),
                'trace' => $e->getTraceAsString()
            ]);

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
            DB::beginTransaction();

            $product = Product::find($id);
            if (!$product) {
                return ApiResponseClass::errorResponse('Producto no encontrado', 404);
            }

            // // Verificar permisos
            // if ($product->user_id !== Auth::id()) {
            //     return ApiResponseClass::errorResponse(
            //         'No tienes permisos para eliminar este producto',
            //         403
            //     );
            // }

            // Eliminar imagen si existe
            $this->deleteOldImage($product->image_url);

            // Eliminar producto
            $product->delete();

            DB::commit();

            return ApiResponseClass::sendResponse(
                null,
                'Producto eliminado correctamente',
                200
            );

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting product: ' . $e->getMessage(), [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponseClass::errorResponse(
                'Error interno del servidor',
                500
            );
        }
    }

    /**
     * Maneja la subida de imagen del producto
     */
    private function handleImageUpload(UploadedFile $image): string
    {
        // Validar que sea una imagen válida
        $allowedMimes = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = $image->getClientOriginalExtension();

        if (!in_array(strtolower($extension), $allowedMimes)) {
            throw new \InvalidArgumentException('Tipo de archivo no permitido. Solo se permiten: jpg, jpeg, png, webp');
        }

        // Validar tamaño (máximo 5MB)
        if ($image->getSize() > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('El archivo es demasiado grande. Máximo 5MB permitidos');
        }

        // Generar nombre único
        $fileName = uniqid('product_') . '_' . time() . '.' . $extension;

        // Guardar en storage/app/public/products
        $path = $image->storeAs('products', $fileName, 'public');

        // Retornar solo la ruta relativa
        return $path;
    }

    /**
     * Eliminar imagen anterior del storage
     */
    private function deleteOldImage(?string $imageUrl): void
    {
        if (!$imageUrl) {
            return;
        }

        // Si es una URL completa, extraer la ruta
        if (str_starts_with($imageUrl, 'http')) {
            $parsedUrl = parse_url($imageUrl);
            if (isset($parsedUrl['path'])) {
                $path = str_replace('/storage/', '', $parsedUrl['path']);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            return;
        }

        // Si es una ruta relativa, eliminar directamente
        if (Storage::disk('public')->exists($imageUrl)) {
            Storage::disk('public')->delete($imageUrl);
        }
    }
}
