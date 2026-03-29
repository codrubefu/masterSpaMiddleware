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
            $context = [
                'saved_at' => now()->toDateTimeString(),
                'model' => $model::class,
                'table' => $model->getTable(),
                'primary_key' => $model->getKey(),
                'was_recently_created' => $model->wasRecentlyCreated,
                'changes' => $model->getChanges(),
                'controller' => $controllerAction ?? 'N/A (non-http context)',
            ];

            Log::info('Model saved event fired', $context);

            try {
                Log::channel('model_saves')->info('Model saved', $context);
            } catch (\Throwable $exception) {
                Log::error('Failed to write model save log to model_saves channel', [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'model' => $model::class,
                    'table' => $model->getTable(),
                    'primary_key' => $model->getKey(),
                ]);
            }
        });
    }
}
