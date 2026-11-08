<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Connectors\AzureConnector;
use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use Alesima\LaravelAzureServiceBus\ServiceBus\Contracts\ServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\RestServiceBusClient;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AzureConnectorTest extends TestCase
{
    public function testConnectBuildsClientFromConfig(): void
    {
        $captured = [];
        $mockClient = $this->createMock(ServiceBusClient::class);

        $connector = new AzureConnector(
            function (
                string $endpoint,
                string $keyName,
                string $key,
                int $timeout
            ) use (
                &$captured,
                $mockClient
            ): ServiceBusClient {
                $captured = [$endpoint, $keyName, $key, $timeout];

                return $mockClient;
            }
        );

        $queue = $connector->connect([
            'endpoint' => 'https://example.servicebus.windows.net',
            'shared_access_key_name' => 'testKeyName',
            'shared_access_key' => 'testKey',
            'queue' => 'testQueue',
        ]);

        $this->assertInstanceOf(AzureQueue::class, $queue);
        $this->assertSame(
            ['https://example.servicebus.windows.net', 'testKeyName', 'testKey', 20],
            $captured
        );
        $this->assertSame('testQueue', $queue->getDefaultQueue());
    }

    public function testConnectWithoutFactoryBuildsRestClient(): void
    {
        $queue = (new AzureConnector())->connect([
            'endpoint' => 'https://example.servicebus.windows.net',
            'shared_access_key_name' => 'testKeyName',
            'shared_access_key' => 'testKey',
            'queue' => 'testQueue',
        ]);

        $this->assertInstanceOf(AzureQueue::class, $queue);
        $this->assertInstanceOf(RestServiceBusClient::class, $queue->getClient());
    }

    /**
     * @param array<string,mixed> $config
     */
    #[DataProvider('invalidConfigProvider')]
    public function testConnectThrowsOnInvalidConfig(array $config): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AzureConnector())->connect($config);
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function invalidConfigProvider(): array
    {
        $valid = [
            'endpoint' => 'https://example.servicebus.windows.net',
            'shared_access_key_name' => 'k',
            'shared_access_key' => 'v',
            'queue' => 'q',
        ];

        $cases = [];
        foreach (array_keys($valid) as $missing) {
            $copy = $valid;
            unset($copy[$missing]);
            $cases['missing ' . $missing] = [$copy];
        }

        $empty = $valid;
        $empty['queue'] = '';
        $cases['empty queue'] = [$empty];

        $badTimeout = $valid;
        $badTimeout['timeout'] = 61;
        $cases['timeout out of range'] = [$badTimeout];

        $stringTimeout = $valid;
        $stringTimeout['timeout'] = '20';
        $cases['non-int timeout'] = [$stringTimeout];

        return $cases;
    }
}
