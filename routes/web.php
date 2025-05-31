<?php

use Illuminate\Support\Facades\Route;

// Documentación Swagger UI
Route::get('/api/documentation', function () {
    return redirect('/swagger-ui.html');
});

Route::get('/', function () {
    return view('welcome');
});
