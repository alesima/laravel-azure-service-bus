<?php

namespace Alesima\LaravelAzureServiceBus\Connectors;

use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use Alesima\LaravelAzureServiceBus\ServiceBus\Contracts\ServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\RestServiceBusClient;
use Illuminate\Container\Container;
use Illuminate\Queue\Connectors\ConnectorInterface;
use InvalidArgumentException;

class AzureConnector implements ConnectorInterface
{
    /**
     * @var callable(string, string, string, int): ServiceBusClient|null
     */
    private $clientFactory;

    public function __construct(?callable $clientFactory = null)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * @param array<string,mixed> $config
     */
    public function connect(array $config)
    {
        $this->validateConfig($config);

        /** @var string $endpoint */
        $endpoint = $config['endpoint'];
        /** @var string $keyName */
        $keyName = $config['shared_access_key_name'];
        /** @var string $key */
        $key = $config['shared_access_key'];
        /** @var string $queueName */
        $queueName = $config['queue'];
        /** @var int $timeout */
        $timeout = $config['timeout'] ?? 20;

        $client = $this->createClient($endpoint, $keyName, $key, $timeout);
        $queue = new AzureQueue($client, $queueName);
        $queue->setContainer(Container::getInstance());

        return $queue;
    }

    private function createClient(string $endpoint, string $keyName, string $key, int $timeout): ServiceBusClient
    {
        if ($this->clientFactory !== null) {
            $factory = $this->clientFactory;

            return $factory($endpoint, $keyName, $key, $timeout);
        }

        return new RestServiceBusClient($endpoint, $keyName, $key, null, $timeout);
    }

    /**
     * @param array<string,mixed> $config
     */
    private function validateConfig(array $config): void
    {
        foreach (['endpoint', 'shared_access_key_name', 'shared_access_key', 'queue'] as $key) {
            if (empty($config[$key]) || ! is_string($config[$key])) {
                throw new InvalidArgumentException(
                    "Azure Service Bus config missing or invalid: '{$key}' is required."
                );
            }
        }

        if (
            isset($config['timeout'])
            && (! is_int($config['timeout']) || $config['timeout'] < 0 || $config['timeout'] > 60)
        ) {
            throw new InvalidArgumentException(
                'Azure Service Bus config missing or invalid: \'timeout\' must be an int between 0 and 60.'
            );
        }
    }
}
