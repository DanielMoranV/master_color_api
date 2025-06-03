<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Classes\ApiResponseClass;
use Illuminate\Contracts\Validation\Validator;

class ClientUpdateProfileRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string',
            'email' => 'sometimes|string|email|max:255|unique:clients,email',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        ApiResponseClass::validationError($validator, []);
    }

    public function messages()
    {
        return [
            'name.string' => 'El campo nombre debe ser una cadena de texto.',
            'name.max' => 'El campo nombre debe tener un máximo de 255 caracteres.',
            'phone.string' => 'El campo teléfono debe ser una cadena de texto.',
            'email.string' => 'El campo correo electrónico debe ser una cadena de texto.',
            'email.email' => 'El campo correo electrónico debe ser un correo electrónico válido.',
            'email.max' => 'El campo correo electrónico debe tener un máximo de 255 caracteres.',
            'email.unique' => 'El correo electrónico ya está en uso.',
        ];
    }
}
