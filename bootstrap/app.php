<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Middleware\CheckTokenVersion;
use Illuminate\Validation\UnauthorizedException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Classes\ApiResponseClass;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.auth' => JwtMiddleware::class,
            'check.token.version' => CheckTokenVersion::class,
            'admin.only' => \App\Http\Middleware\AdminOnly::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
          // Manejo de excepciones de autenticación
          $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponseClass::errorResponse(
                    app()->isProduction() ? 'No autorizado' : $e->getMessage(),
                    401
                );
            }
        });

        // Manejo de excepciones de acceso denegado
        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponseClass::errorResponse(
                    app()->isProduction() ? 'Acceso denegado' : $e->getMessage(),
                    403
                );
            }
        });

        // Manejo de excepciones de no encontrado
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->is('api')) {
                return ApiResponseClass::errorResponse(
                    app()->isProduction() ? 'Recurso no encontrado' : $e->getMessage(),
                    404
                );
            }
        });
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->is('api')) {
                return ApiResponseClass::errorResponse(
                    app()->isProduction() ? 'Método no permitido' : $e->getMessage(),
                    405
                );
            }
        });

        // Manejo de excepciones de ruta no encontrada (RouteNotFoundException)
        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->is('api')) {
                return ApiResponseClass::errorResponse('Route not found', 404);
            }
        });

        // Manejo de errores de validación
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $e->errors(),
                    'status' => 422,
                    'data' => null,
                ], 422);
            }
        });

        // Handler catch-all para errores no controlados
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponseClass::errorResponse(
                    app()->isProduction() ? 'Error interno del servidor' : $e->getMessage(),
                    500
                );
            }
        });
    })->create();

