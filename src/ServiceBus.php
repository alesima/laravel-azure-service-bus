<?php

namespace Alesima\LaravelAzureServiceBus;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use DateInterval;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use InvalidArgumentException;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;
use WindowsAzure\ServiceBus\Models\ReceiveMessageOptions;

/**
 * Laravel queue implementation for Azure Service Bus (queues).
 */
class ServiceBus extends Queue implements QueueContract
{
    protected IServiceBus $azure;

    protected string $defaultQueue;

    protected ReceiveMessageOptions $receiveOptions;

    public function __construct(IServiceBus $azure, string $defaultQueue)
    {
        $this->azure = $azure;
        $this->defaultQueue = $defaultQueue;
        $this->initializeReceiveOptions();
    }

    protected function initializeReceiveOptions(): void
    {
        $this->receiveOptions = new ReceiveMessageOptions();
        $this->receiveOptions->setPeekLock();
    }

    public function getAzure(): IServiceBus
    {
        return $this->azure;
    }

    public function getReceiveOptions(): ReceiveMessageOptions
    {
        return $this->receiveOptions;
    }

    public function getDefaultQueue(): string
    {
        return $this->defaultQueue;
    }

    /**
     * Azure Service Bus has no cheap count API on this SDK path.
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
        $message = new BrokeredMessage($payload);
        $this->sendMessageToQueue($queueName, $message);

        return null;
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        $scheduledTime = $this->resolveDelayToDateTime($delay);
        $queueName = $this->getQueueName($queue);
        $payload = $this->createPayload($job, $queueName, $data);

        $message = new BrokeredMessage($payload);
        $message->setScheduledEnqueueTimeUtc($scheduledTime);

        $this->sendMessageToQueue($queueName, $message);

        return null;
    }

    public function pop($queue = null)
    {
        $queueName = $this->getQueueName($queue);
        $message = $this->receiveMessageFromQueue($queueName);

        if ($message === null) {
            return null;
        }

        return new AzureJob(
            $this->container,
            $this->azure,
            $message,
            $queueName,
            (string) $message->getBody()
        );
    }

    protected function sendMessageToQueue(string $queue, BrokeredMessage $message): void
    {
        $this->azure->sendQueueMessage($queue, $message);
    }

    protected function receiveMessageFromQueue(string $queue): ?BrokeredMessage
    {
        return $this->azure->receiveQueueMessage($queue, $this->receiveOptions);
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
