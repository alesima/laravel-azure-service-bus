<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Connectors\AzureConnector;
use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;

class AzureConnectorTest extends TestCase
{
    public function testBuildConnectionString(): void
    {
        $result = AzureConnector::buildConnectionString(
            'https://example.servicebus.windows.net',
            'testKeyName',
            'testKey'
        );

        $this->assertSame(
            'Endpoint=https://example.servicebus.windows.net;SharedAccessKeyName=testKeyName;SharedAccessKey=testKey',
            $result
        );
    }

    public function testConnectReturnsAzureQueue(): void
    {
        $captured = null;
        $mockServiceBus = $this->createMock(IServiceBus::class);

        $connector = new AzureConnector(function (string $connectionString) use (&$captured, $mockServiceBus) {
            $captured = $connectionString;

            return $mockServiceBus;
        });

        $queue = $connector->connect([
            'endpoint' => 'https://example.servicebus.windows.net',
            'shared_access_key_name' => 'testKeyName',
            'shared_access_key' => 'testKey',
            'queue' => 'testQueue',
        ]);

        $this->assertInstanceOf(AzureQueue::class, $queue);
        $this->assertSame(
            'Endpoint=https://example.servicebus.windows.net;SharedAccessKeyName=testKeyName;SharedAccessKey=testKey',
            $captured
        );
        $this->assertSame('testQueue', $queue->getDefaultQueue());
    }

    /**
     * @param array<string,string> $config
     * @dataProvider missingConfigProvider
     */
    public function testConnectThrowsOnInvalidConfig(array $config): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AzureConnector())->connect($config);
    }

    /**
     * @return array<string,array<int,array<string,string>>>
     */
    public static function missingConfigProvider(): array
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

        return $cases;
    }
}
