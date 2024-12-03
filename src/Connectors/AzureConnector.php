<?php

namespace Alesima\LaravelAzureServiceBus\Connectors;

use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use WindowsAzure\Common\ServicesBuilder;

class AzureConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param array $config
     * @return AzureQueue
     */
    public function connect(array $config)
    {
        $connectionString = sprintf(
            "Endpoint=%s;SharedAccessKeyName=%s;SharedAccessKey=%s",
            $config['endpoint'],
            $config['shared_access_key_name'],
            $config['shared_access_key']
        );

        $serviceBus = ServicesBuilder::getInstance()->createServiceBusService($connectionString);

        return new AzureQueue($serviceBus, $config['queue']);
    }
}
