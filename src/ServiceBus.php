<?php

namespace Alesima\LaravelAzureServiceBus;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Alesima\LaravelAzureServiceBus\ServiceBus\Contracts\ServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\OutgoingMessage;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use DateInterval;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use InvalidArgumentException;

/**
 * Laravel queue implementation for Azure Service Bus (queues).
 */
class ServiceBus extends Queue implements QueueContract
{
    protected ServiceBusClient $client;

    protected string $defaultQueue;

    public function __construct(ServiceBusClient $client, string $defaultQueue)
    {
        $this->client = $client;
        $this->defaultQueue = $defaultQueue;
    }

    public function getClient(): ServiceBusClient
    {
        return $this->client;
    }

    public function getDefaultQueue(): string
    {
        return $this->defaultQueue;
    }

    /**
     * Azure Service Bus has no cheap count API on this driver.
     * Returns 0 to satisfy the contract without an extra HTTP call.
     */
    public function size($queue = null): int
    {
        return 0;
    }

    public function push($job, $data = '', $queue = null)
    {
        $queueName = $this->getQueueName($queue);
        $payload = $this->createPayload($job, $queueName, $data);
        $this->pushRaw($payload, $queueName);

        return null;
    }

    /**
     * @param array<string,mixed> $options
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $queueName = $this->getQueueName($queue);
        $this->client->sendQueueMessage($queueName, new OutgoingMessage((string) $payload));

        return null;
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        $scheduledTime = $this->resolveDelayToDateTime($delay);
        $queueName = $this->getQueueName($queue);
        $payload = $this->createPayload($job, $queueName, $data);

        $this->client->sendQueueMessage(
            $queueName,
            new OutgoingMessage((string) $payload, $scheduledTime)
        );

        return null;
    }

    public function pop($queue = null)
    {
        $queueName = $this->getQueueName($queue);
        $message = $this->client->receiveQueueMessage($queueName);

        if ($message === null) {
            return null;
        }

        return new AzureJob(
            $this->container,
            $this->client,
            $message,
            $queueName,
            $message->getBody()
        );
    }

    /**
     * @param DateTimeInterface|DateInterval|int $delay
     */
    protected function resolveDelayToDateTime($delay): DateTime
    {
        if ($delay instanceof DateTimeInterface) {
            $dateTime = new DateTime('now', new DateTimeZone('UTC'));
            $dateTime->setTimestamp($delay->getTimestamp());

            return $dateTime;
        }

        if ($delay instanceof DateInterval) {
            $dateTime = new DateTime('now', new DateTimeZone('UTC'));
            $dateTime->add($delay);

            return $dateTime;
        }

        if (is_int($delay)) {
            if ($delay < 0) {
                throw new InvalidArgumentException('Delay in seconds must be >= 0.');
            }

            $dateTime = new DateTime('now', new DateTimeZone('UTC'));
            $dateTime->add(new DateInterval("PT{$delay}S"));

            return $dateTime;
        }

        throw new InvalidArgumentException(
            'Invalid delay type. Must be DateTimeInterface, DateInterval, or int (seconds).'
        );
    }

    protected function getQueueName(?string $queue): string
    {
        if (! empty($queue)) {
            return $queue;
        }

        return $this->defaultQueue;
    }
}
