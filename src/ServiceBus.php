<?php

namespace Alesima\LaravelAzureServiceBus;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use DateTime;
use DateTimeInterface;
use DateInterval;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;
use WindowsAzure\ServiceBus\Models\ReceiveMessageOptions;

/**
 * Class ServiceBus
 *
 * A Laravel queue implementation for Azure Service Bus.
 */
class ServiceBus extends Queue implements QueueContract
{
    /**
     * Azure Service Bus instance.
     *
     * @var IServiceBus
     */
    protected $azure;

    /**
     * Default queue name.
     *
     * @var string
     */
    protected $defaultQueue;

    /**
     * Options for receiving messages (PeekAndLock).
     *
     * @var ReceiveMessageOptions
     */
    protected $receiveOptions;

    /**
     * ServiceBus constructor.
     *
     * @param IServiceBus $azure Azure Service Bus instance.
     * @param string $defaultQueue Default queue name.
     */
    public function __construct(IServiceBus $azure, string $defaultQueue)
    {
        $this->azure = $azure;
        $this->defaultQueue = $defaultQueue;
        $this->initializeReceiveOptions();
    }

    /**
     * Initialize receive options with PeekAndLock.
     *
     * @return void
     */
    private function initializeReceiveOptions(): void
    {
        $this->receiveOptions = new ReceiveMessageOptions();
        $this->receiveOptions->setPeekLock();
    }

    /**
     * Get the size of the queue.
     *
     * @param string|null $queue Queue name (optional).
     * @return int Always returns 0 (not supported by Azure Service Bus).
     */
    public function size($queue = null): int
    {
        return 0; // Azure Service Bus doesn't support retrieving queue size
    }

    /**
     * Push a new job onto the queue.
     *
     * @param mixed $job The job instance or class name.
     * @param mixed $data The payload for the job.
     * @param string|null $queue Queue name (optional).
     * @return void
     * @throws \Exception If sending the message fails.
     */
    public function push($job, $data = '', $queue = null): void
    {
        $payload = $this->createPayload($job, $data);
        $this->pushRaw($payload, $queue);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload Raw job payload.
     * @param string|null $queue Queue name (optional).
     * @param array $options Additional options (ignored).
     * @return void
     * @throws \Exception If sending the message fails.
     */
    public function pushRaw($payload, $queue = null, array $options = []): void
    {
        $queue = $this->getQueueName($queue);
        $message = new BrokeredMessage($payload);
        $this->sendMessageToQueue($queue, $message);
    }

    /**
     * Push a job onto the queue to be processed later.
     *
     * @param DateTimeInterface|DateInterval|int $delay Delay before processing.
     * @param mixed $job The job instance or class name.
     * @param mixed $data The payload for the job.
     * @param string|null $queue Queue name (optional).
     * @return void
     * @throws \Exception If sending the delayed message fails.
     */
    public function later($delay, $job, $data = '', $queue = null): void
    {
        $scheduledTime = $this->resolveDelayToDateTime($delay);
        $payload = $this->createPayload($job, $data);

        $message = new BrokeredMessage($payload);
        $message->setScheduledEnqueueTimeUtc($scheduledTime);

        $queue = $this->getQueueName($queue);
        $this->sendMessageToQueue($queue, $message);
    }

    /**
     * Pop the next job off the queue.
     *
     * @param string|null $queue Queue name (optional).
     * @return AzureJob|null A queued job instance or null if none available.
     * @throws \Exception If receiving the message fails.
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueueName($queue);
        $message = $this->receiveMessageFromQueue($queue);

        if (!$message) {
            return null;
        }

        return new AzureJob($this->container, $this->azure, $message, $queue, $message->getBody());
    }

    /**
     * Send a message to a queue.
     *
     * @param string $queue Queue name.
     * @param BrokeredMessage $message The message to send.
     * @return void
     * @throws \Exception If sending the message fails.
     */
    protected function sendMessageToQueue(string $queue, BrokeredMessage $message): void
    {
        $this->azure->sendQueueMessage($queue, $message);
    }

    /**
     * Receive a message from a queue.
     *
     * @param string $queue Queue name.
     * @return BrokeredMessage|null The received message or null if none.
     * @throws \Exception If receiving the message fails.
     */
    protected function receiveMessageFromQueue(string $queue): ?BrokeredMessage
    {
        return $this->azure->receiveQueueMessage($queue, $this->receiveOptions);
    }

    /**
     * Resolve a delay to a DateTime instance.
     *
     * @param DateTimeInterface|DateInterval|int $delay The delay.
     * @return DateTime A DateTime in UTC.
     * @throws \InvalidArgumentException If the delay type is invalid.
     */
    protected function resolveDelayToDateTime($delay): DateTime
    {
        if ($delay instanceof DateTimeInterface) {
            $dateTime = new DateTime();
            $dateTime->setTimestamp($delay->getTimestamp());
            $dateTime->setTimezone(new \DateTimeZone('UTC'));
            return $dateTime;
        }

        if ($delay instanceof DateInterval) {
            $dateTime = new DateTime('now', new \DateTimeZone('UTC'));
            return $dateTime->add($delay);
        }

        if (is_int($delay)) {
            $dateTime = new DateTime('now', new \DateTimeZone('UTC'));
            return $dateTime->add(new DateInterval("PT{$delay}S"));
        }

        throw new \InvalidArgumentException('Invalid delay type. Must be DateTimeInterface, DateInterval, or int.');
    }

    /**
     * Get the resolved queue name.
     *
     * @param string|null $queue Queue name (optional).
     * @return string Resolved queue name.
     */
    protected function getQueueName(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }
}
