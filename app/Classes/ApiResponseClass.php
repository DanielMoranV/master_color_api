<?php

namespace App\Classes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApiResponseClass
{
    public static function rollback($e, $message = 'Error en el proceso de inserción de datos')
    {
        DB::rollBack();
        self::throw($e, $message);
    }

    public static function throw($e, $message = 'Falló el proceso', $code = 500)
    {
        // Registra el tipo de excepción, mensaje y trazas más detalladas
        Log::error('Exception caught', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Devuelve un mensaje más descriptivo si la aplicación está en modo de depuración
        $errorDetails = config('app.debug') ? [
            'exception' => get_class($e),
            'error_message' => $e->getMessage(),
            // 'trace' => $e->getTrace(),
        ] : [];

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'details' => $errorDetails, // Añadimos detalles si es modo debug
        ], $code));
    }

    public static function sendResponse($result, $message = '', $code = 200)
    {
        // Estandariza la respuesta de éxito
        return response()->json([
            'success' => true,
            'message' => $message,
            'status' => $code,
            'data' => $result,
            'errors' => null
        ], $code);
    }

    public static function errorResponse($message, $code = 500, $errors = null)
    {
        // Estandariza la respuesta de error
        return response()->json([
            'success' => false,
            'message' => $message,
            'status' => $code,
            'data' => [],
            'errors' => $errors
        ], $code);
    }

    public static function validationError($validator, $data)
    {
        // Información adicional de validación fallida
        $errors = $validator->errors();
        Log::warning('Validation errors occurred', ['errors' => $errors, 'data' => $data]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $errors,
            'data' => null,
        ], 422));
    }
}