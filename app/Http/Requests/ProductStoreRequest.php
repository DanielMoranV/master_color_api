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
            'image_url' => 'required|string|max:255',
            'barcode' => 'required|string|unique:products,barcode|max:255',
            'brand' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'presentation' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'unidad' => 'required|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
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
            'sku.required' => 'El SKU es obligatorio.',
            'image_url.required' => 'La URL de la imagen es obligatoria.',
            'barcode.required' => 'El código de barras es obligatorio.',
            'brand.required' => 'La marca es obligatoria.',
            'description.required' => 'La descripción es obligatoria.',
            'presentation.required' => 'La presentación es obligatoria.',
            'category.required' => 'La categoría es obligatoria.',
            'unidad.required' => 'La unidad es obligatoria.',
            'user_id.required' => 'El usuario es obligatorio.',
        ];
    }
}
