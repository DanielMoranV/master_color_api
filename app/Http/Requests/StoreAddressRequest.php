<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Classes\ApiResponseClass;
use Illuminate\Contracts\Validation\Validator;

class StoreAddressRequest extends FormRequest
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
            'address_full' => 'required|string|max:255',
            'district' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'reference' => 'nullable|string|max:255',
            'is_main' => 'boolean',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        ApiResponseClass::validationError($validator, []);
    }

    public function messages(): array
    {
        return [
            'address_full.required' => 'La dirección completa es obligatoria.',
            'address_full.string' => 'La dirección completa debe ser un texto válido.',
            'address_full.max' => 'La dirección completa no puede tener más de 255 caracteres.',
            
            'district.required' => 'El distrito es obligatorio.',
            'district.string' => 'El distrito debe ser un texto válido.',
            'district.max' => 'El distrito no puede tener más de 100 caracteres.',
            
            'province.required' => 'La provincia es obligatoria.',
            'province.string' => 'La provincia debe ser un texto válido.',
            'province.max' => 'La provincia no puede tener más de 100 caracteres.',
            
            'department.required' => 'El departamento es obligatorio.',
            'department.string' => 'El departamento debe ser un texto válido.',
            'department.max' => 'El departamento no puede tener más de 100 caracteres.',
            
            'postal_code.required' => 'El código postal es obligatorio.',
            'postal_code.string' => 'El código postal debe ser un texto válido.',
            'postal_code.max' => 'El código postal no puede tener más de 20 caracteres.',
            
            'reference.string' => 'La referencia debe ser un texto válido.',
            'reference.max' => 'La referencia no puede tener más de 255 caracteres.',
            
            'is_main.boolean' => 'El campo dirección principal debe ser verdadero o falso.',
        ];
    }
}