<?php

namespace Alesima\LaravelAzureServiceBus\Providers;

use Alesima\LaravelAzureServiceBus\Connectors\AzureConnector;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->configPath(),
            'azureservicebus'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            $this->configPath() => $this->app->configPath('azureservicebus.php'),
        ], 'config');

        $this->registerConnector();
    }

    private function registerConnector(): void
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make('queue');
        $manager->addConnector('azureservicebus', function () {
            return new AzureConnector();
        });
    }

    private function configPath(): string
    {
        return __DIR__ . '/../../config/azureservicebus.php';
    }

    /**
     * @return string[]
     */
    public function provides(): array
    {
        return ['azureservicebus'];
    }
}
