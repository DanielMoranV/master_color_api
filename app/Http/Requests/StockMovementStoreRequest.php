<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Classes\ApiResponseClass;
use Illuminate\Contracts\Validation\Validator;

class StockMovementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movement_type' => 'required|in:entrada,salida,ajuste,devolucion',
            'reason' => 'required|string|max:500',
            'voucher_number' => 'nullable|string|max:255',
            'stocks' => 'required|array|min:1',
            'stocks.*.stock_id' => 'required|exists:stocks,id',
            'stocks.*.quantity' => 'required|integer|min:1',
            'stocks.*.unit_price' => 'nullable|numeric|min:0',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        ApiResponseClass::validationError($validator, []);
    }

    public function messages(): array
    {
        return [
            'movement_type.required' => 'El tipo de movimiento es obligatorio.',
            'movement_type.in' => 'El tipo de movimiento debe ser: entrada, salida, ajuste o devolución.',
            'reason.required' => 'La razón del movimiento es obligatoria.',
            'reason.string' => 'La razón debe ser un texto válido.',
            'reason.max' => 'La razón no puede tener más de 500 caracteres.',
            'voucher_number.string' => 'El número de comprobante debe ser un texto válido.',
            'voucher_number.max' => 'El número de comprobante no puede tener más de 255 caracteres.',
            'stocks.required' => 'Debe incluir al menos un producto.',
            'stocks.array' => 'Los productos deben ser un arreglo válido.',
            'stocks.min' => 'Debe incluir al menos un producto.',
            'stocks.*.stock_id.required' => 'El ID del stock es obligatorio.',
            'stocks.*.stock_id.exists' => 'El stock seleccionado no existe.',
            'stocks.*.quantity.required' => 'La cantidad es obligatoria.',
            'stocks.*.quantity.integer' => 'La cantidad debe ser un número entero.',
            'stocks.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
            'stocks.*.unit_price.numeric' => 'El precio unitario debe ser un número válido.',
            'stocks.*.unit_price.min' => 'El precio unitario debe ser mayor o igual a 0.',
        ];
    }
}
