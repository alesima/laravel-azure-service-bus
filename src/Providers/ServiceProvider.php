<?php

namespace LaravelAzureServiceBus\Providers;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use LaravelAzureServiceBus\Connectors\AzureConnector;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // You can register additional bindings here if needed.
    }

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->app->resolving('queue', function (QueueManager $queueManager) {
            $queueManager->addConnector('azure', function () {
                return new AzureConnector();
            });
        });
    }
}
