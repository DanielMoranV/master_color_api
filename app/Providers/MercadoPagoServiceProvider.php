<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MercadoPagoWrapper;

class MercadoPagoServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(MercadoPagoWrapper::class, function ($app) {
            return new MercadoPagoWrapper();
        });
    }

    public function boot()
    {
        //
    }
}