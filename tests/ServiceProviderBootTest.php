<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Providers\ServiceProvider;
use Alesima\LaravelAzureServiceBus\Tests\Support\FakeConfig;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\QueueManager;
use PHPUnit\Framework\TestCase;

class ServiceProviderBootTest extends TestCase
{
    public function testBootRegistersConnectorAndMergesConfig(): void
    {
        $fakeConfig = new FakeConfig([
            // App-level override must survive the merge with package defaults.
            'azureservicebus' => ['queue' => 'from-app'],
        ]);

        $manager = $this->createMock(QueueManager::class);
        $manager->expects($this->once())
            ->method('addConnector')
            ->with('azureservicebus', $this->isInstanceOf(Closure::class));

        $app = $this->createMock(Application::class);
        $app->method('make')->willReturnMap([
            ['config', [], $fakeConfig],
            ['queue', [], $manager],
        ]);
        $app->method('configPath')->willReturn('/tmp/azureservicebus.php');

        $provider = new ServiceProvider($app);
        $provider->register();
        $provider->boot();

        $merged = $fakeConfig->get('azureservicebus');
        $this->assertIsArray($merged);
        $this->assertSame('from-app', $merged['queue']);
        $this->assertSame('https://example.servicebus.windows.net', $merged['endpoint']);
        $this->assertSame(20, $merged['timeout']);
    }
}
