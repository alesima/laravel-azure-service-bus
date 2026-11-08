<?php

namespace Alesima\LaravelAzureServiceBus\Connectors;

use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use Illuminate\Container\Container;
use Illuminate\Queue\Connectors\ConnectorInterface;
use InvalidArgumentException;
use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\ServiceBus\Internal\IServiceBus;

class AzureConnector implements ConnectorInterface
{
    /** @var callable|null */
    private $serviceBusFactory;

    public function __construct(?callable $serviceBusFactory = null)
    {
        $this->serviceBusFactory = $serviceBusFactory;
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

        $connectionString = self::buildConnectionString($endpoint, $keyName, $key);

        $serviceBus = $this->createServiceBus($connectionString);
        $azureQueue = new AzureQueue($serviceBus, $queueName);
        $azureQueue->setContainer(Container::getInstance());

        return $azureQueue;
    }

    public static function buildConnectionString(
        string $endpoint,
        string $keyName,
        string $key
    ): string {
        return sprintf(
            'Endpoint=%s;SharedAccessKeyName=%s;SharedAccessKey=%s',
            rtrim($endpoint, ';'),
            $keyName,
            $key
        );
    }

    private function createServiceBus(string $connectionString): IServiceBus
    {
        if ($this->serviceBusFactory !== null) {
            $factory = $this->serviceBusFactory;

            return $factory($connectionString);
        }

        return ServicesBuilder::getInstance()->createServiceBusService($connectionString);
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
    }
}
