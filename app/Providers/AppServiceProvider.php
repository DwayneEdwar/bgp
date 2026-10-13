<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\NetworkEvent;
use App\Models\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Router::created(static function (Router $router): void {
            self::recordRouterActivity($router, 'created', 'Router registered.');
        });

        Router::updated(static function (Router $router): void {
            $changed = array_intersect($router->getChanges(), ['name', 'host', 'port', 'username', 'connection_type', 'status']);

            if ($changed === []) {
                return;
            }

            self::recordRouterActivity($router, 'updated', 'Router details updated.');
        });

        Router::deleted(static function (Router $router): void {
            self::recordRouterActivity($router, 'deleted', 'Router removed.');
        });
    }

    private static function recordRouterActivity(Router $router, string $action, string $description): void
    {
        try {
            $actorId = Auth::id();
            $createdAt = now();

            ActivityLog::query()->create([
                'actor_id' => $actorId,
                'action' => "router.{$action}",
                'module' => 'routers',
                'target_type' => Router::class,
                'target_id' => $router->getKey(),
                'description' => $description,
                'ip_address' => request()->ip(),
                'created_at' => $createdAt,
            ]);

            NetworkEvent::query()->create([
                'router_id' => $action === 'deleted' ? null : $router->getKey(),
                'actor_id' => $actorId,
                'type' => "router_{$action}",
                'module' => 'routers',
                'target_type' => Router::class,
                'target_id' => $router->getKey(),
                'description' => $description,
                'metadata' => ['router_name' => $router->name],
                'created_at' => $createdAt,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Router activity event could not be recorded.', [
                'router_id' => $router->getKey(),
                'exception_class' => $exception::class,
            ]);
        }
    }
}
