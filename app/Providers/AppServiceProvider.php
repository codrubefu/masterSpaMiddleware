<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::saved(function (Model $model): void {
            $route = request()?->route();
            $controllerAction = $route?->getActionName();

            Log::channel('model_saves')->info('Model saved', [
                'saved_at' => now()->toDateTimeString(),
                'model' => $model::class,
                'table' => $model->getTable(),
                'primary_key' => $model->getKey(),
                'controller' => $controllerAction ?? 'N/A (non-http context)',
            ]);
        });
    }
}
