<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use App\Classes\ApiResponseClass;
use Illuminate\Contracts\Validation\Validator;
class ProductStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
                'name' => 'required|string|max:255',
                'sku' => 'required|string|unique:products,sku|max:255',
                'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max
                'barcode' => 'required|string|unique:products,barcode|max:255',
                'brand' => 'required|string|max:255',
                'description' => 'required|string|max:255',
                'presentation' => 'required|string|max:255',
                'category' => 'required|string|max:255',
                'unidad' => 'required|string|max:255',
                // Stock fields
                'quantity' => 'nullable|integer|min:0',
                'min_stock' => 'nullable|integer|min:0',
                'max_stock' => 'nullable|integer|min:0',
                'purchase_price' => 'required|numeric|min:0|max:99999999.99',
                'sale_price' => 'required|numeric|min:0|max:99999999.99',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        ApiResponseClass::validationError($validator, []);
    }

    public function messages(): array
    {
        return [
            // Mensajes para required
            'name.required' => 'El nombre es obligatorio.',
            'sku.required' => 'El SKU es obligatorio.',
            'image.required' => 'La imagen es obligatoria.',
            'barcode.required' => 'El código de barras es obligatorio.',
            'brand.required' => 'La marca es obligatoria.',
            'description.required' => 'La descripción es obligatoria.',
            'presentation.required' => 'La presentación es obligatoria.',
            'category.required' => 'La categoría es obligatoria.',
            'unidad.required' => 'La unidad es obligatoria.',

            // Mensajes para unique
            'sku.unique' => 'Este SKU ya existe en el sistema.',
            'barcode.unique' => 'Este código de barras ya existe en el sistema.',

            // Mensajes para max
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'sku.max' => 'El SKU no puede tener más de 255 caracteres.',
            'barcode.max' => 'El código de barras no puede tener más de 255 caracteres.',
            'brand.max' => 'La marca no puede tener más de 255 caracteres.',
            'description.max' => 'La descripción no puede tener más de 255 caracteres.',
            'presentation.max' => 'La presentación no puede tener más de 255 caracteres.',
            'category.max' => 'La categoría no puede tener más de 255 caracteres.',
            'unidad.max' => 'La unidad no puede tener más de 255 caracteres.',

            // Mensajes para string
            'name.string' => 'El nombre debe ser un texto válido.',
            'sku.string' => 'El SKU debe ser un texto válido.',
            'barcode.string' => 'El código de barras debe ser un texto válido.',
            'brand.string' => 'La marca debe ser un texto válido.',
            'description.string' => 'La descripción debe ser un texto válido.',
            'presentation.string' => 'La presentación debe ser un texto válido.',
            'category.string' => 'La categoría debe ser un texto válido.',
            'unidad.string' => 'La unidad debe ser un texto válido.',

            // Mensajes para image
            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg o webp.',
            'image.max' => 'La imagen no puede ser mayor a 5MB.',

            // Mensajes para stock fields
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad no puede ser negativa.',
            'min_stock.integer' => 'El stock mínimo debe ser un número entero.',
            'min_stock.min' => 'El stock mínimo no puede ser negativo.',
            'max_stock.integer' => 'El stock máximo debe ser un número entero.',
            'max_stock.min' => 'El stock máximo no puede ser negativo.',
            'purchase_price.required' => 'El precio de compra es obligatorio.',
            'purchase_price.numeric' => 'El precio de compra debe ser un número válido.',
            'purchase_price.min' => 'El precio de compra no puede ser negativo.',
            'purchase_price.max' => 'El precio de compra no puede exceder 99999999.99.',
            'sale_price.required' => 'El precio de venta es obligatorio.',
            'sale_price.numeric' => 'El precio de venta debe ser un número válido.',
            'sale_price.min' => 'El precio de venta no puede ser negativo.',
            'sale_price.max' => 'El precio de venta no puede exceder 99999999.99.',
        ];
    }
}
