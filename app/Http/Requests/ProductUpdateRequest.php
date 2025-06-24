<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Classes\ApiResponseClass;

class ProductUpdateRequest extends FormRequest
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
                'sku' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max
                'barcode' => 'required|string|max:255',
                'brand' => 'required|string|max:255',
                'description' => 'required|string|max:255',
                'presentation' => 'required|string|max:255',
                'category' => 'required|string|max:255',
                'unidad' => 'required|string|max:255',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        ApiResponseClass::validationError($validator, []);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser un texto válido.',
            'sku.required' => 'El SKU es obligatorio.',
            'sku.string' => 'El SKU debe ser un texto válido.',
            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg o webp.',
            'image.max' => 'La imagen no puede ser mayor a 5MB.',
            'barcode.required' => 'El código de barras es obligatorio.',
            'brand.required' => 'La marca es obligatoria.',
            'brand.string' => 'La marca debe ser un texto válido.',
            'description.required' => 'La descripción es obligatoria.',
            'description.string' => 'La descripción debe ser un texto válido.',
            'presentation.required' => 'La presentación es obligatoria.',
            'presentation.string' => 'La presentación debe ser un texto válido.',
            'category.required' => 'La categoría es obligatoria.',
            'category.string' => 'La categoría debe ser un texto válido.',
            'unidad.required' => 'La unidad es obligatoria.',
            'unidad.string' => 'La unidad debe ser un texto válido.',
        ];
    }
}
