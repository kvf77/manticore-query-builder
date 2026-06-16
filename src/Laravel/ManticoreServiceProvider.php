<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Laravel;

use Illuminate\Support\ServiceProvider;
use Kvf77\Manticore\Drivers\ConnectionInterface;
use Kvf77\Manticore\Drivers\Mysqli\Connection as MysqliConnection;
use Kvf77\Manticore\Drivers\Pdo\Connection as PdoConnection;

class ManticoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/manticore.php', 'manticore');

        $this->app->singleton(ConnectionInterface::class, function ($app): ConnectionInterface {
            $config = $app['config']['manticore'];

            $connection = ($config['driver'] ?? 'pdo') === 'mysqli'
                ? new MysqliConnection()
                : new PdoConnection();

            $params = array_filter([
                'host'   => $config['host'] ?? '127.0.0.1',
                'port'   => $config['port'] ?? 9306,
                'socket' => $config['socket'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');

            $connection->setParams($params);

            return $connection;
        });

        $this->app->singleton(Manticore::class, static fn ($app) => new Manticore($app->make(ConnectionInterface::class)));
        $this->app->alias(Manticore::class, 'manticore');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/manticore.php' => $this->app->configPath('manticore.php'),
            ], 'manticore-config');
        }
    }
}
