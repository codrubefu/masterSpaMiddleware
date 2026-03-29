<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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
        Event::listen('eloquent.saved: *', function (string $eventName, array $data): void {
            $model = $data[0] ?? null;

            if (! $model instanceof Model) {
                return;
            }

            $route = request()?->route();
            $controllerAction = $route?->getActionName();

            Log::channel('model_saves')->info('Model saved', [
                'saved_at' => now()->toDateTimeString(),
                'event' => $eventName,
                'model' => $model::class,
                'table' => $model->getTable(),
                'primary_key' => $model->getKey(),
                'controller' => $controllerAction ?? 'N/A (non-http context)',
                'saved_fields' => $model->getAttributes(),
            ]);
        });
    }
}
