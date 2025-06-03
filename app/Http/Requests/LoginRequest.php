<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Classes\ApiResponseClass;
use Illuminate\Contracts\Validation\Validator;

class LoginRequest extends FormRequest
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
            'email' => 'required|email',
            'password' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'email.required' => 'El campo email es obligatorio.',
            'email.email' => 'El campo email debe ser un correo electrónico válido.',
            'password.required' => 'El campo contraseña es obligatorio.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        ApiResponseClass::validationError($validator, []);
    }
}
