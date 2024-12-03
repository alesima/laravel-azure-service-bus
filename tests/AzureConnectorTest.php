<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Connectors\AzureConnector;
use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use PHPUnit\Framework\TestCase;
use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\ServiceBus\Internal\IServiceBus;

class AzureConnectorTest extends TestCase
{
    public function testConnect()
    {
        $config = [
            'endpoint' => 'https://example.servicebus.windows.net',
            'shared_access_key_name' => 'testKeyName',
            'shared_access_key' => 'testKey',
            'queue' => 'testQueue',
        ];

        $mockServiceBus = $this->createMock(IServiceBus::class);

        $servicesBuilder = $this->createMock(ServicesBuilder::class);
        $servicesBuilder->method('createServiceBusService')
            ->willReturn($mockServiceBus);

        $connector = new AzureConnector();
        $queue = $connector->connect($config);

        $this->assertInstanceOf(AzureQueue::class, $queue);
    }
}
