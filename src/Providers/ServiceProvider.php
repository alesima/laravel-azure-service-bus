<?php

namespace Alesima\LaravelAzureServiceBus\Providers;

use Alesima\LaravelAzureServiceBus\Connectors\AzureConnector;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(QueueManager $manager)
    {
        $manager->addConnector('azureservicebus', function () {
            return new AzureConnector();
        });
    }
}
