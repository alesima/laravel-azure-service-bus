<?php

namespace Alesima\LaravelAzureServiceBus\Drivers;

use DateInterval;
use DateTime;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;
use WindowsAzure\ServiceBus\Models\ReceiveMessageOptions;

class AzureQueue extends Queue implements QueueContract
{
    /**
     * The Azure IServiceBus instance.
     *
     * @var IServiceBus
     */
    protected $azure;

    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default;

    /**
     * The options to set PeekAndLock.
     *
     * @var ReceiveMessageOptions
     */
    protected $receiveOptions;

    /**
     * Create a new AzureQueue instance.
     *
     * @param IServiceBus $azure
     * @param string $default
     */
    public function __construct(IServiceBus $azure, string $default)
    {
        $this->azure = $azure;
        $this->default = $default;
        $this->receiveOptions = new ReceiveMessageOptions();
        $this->receiveOptions->setPeekLock();
    }

    /**
     * Send a message to the queue.
     *
     * @param string $queue
     * @param BrokeredMessage $message
     * @throws \Exception
     */
    protected function sendInternal(string $queue, BrokeredMessage $message): void
    {
        $this->azure->sendQueueMessage($queue, $message);
    }

    /**
     * Receive a message from the queue.
     *
     * @param string $queue
     * @param ReceiveMessageOptions $receiveOptions
     * @return BrokeredMessage|null
     * @throws \Exception
     */
    protected function receiveInternal(string $queue, ReceiveMessageOptions $receiveOptions): ?BrokeredMessage
    {
        return $this->azure->receiveQueueMessage($queue, $receiveOptions);
    }

    /**
     * Get the size of the queue.
     *
     * @param string|null $queue
     * @return int
     */
    public function size($queue = null): int
    {
        return 0; // Azure Service Bus doesn't provide queue size directly
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @throws \Exception
     */
    public function push($job, $data = '', $queue = null): void
    {
        $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string|null $queue
     * @param array $options
     * @throws \Exception
     */
    public function pushRaw($payload, $queue = null, array $options = []): void
    {
        $queue = $this->getQueue($queue);
        $message = new BrokeredMessage($payload);
        $this->sendInternal($queue, $message);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     * @throws \Exception
     */
    public function later($delay, $job, $data = '', $queue = null): void
    {
        $releaseTime = $this->resolveDelayToDateTime($delay);
        $payload = $this->createPayload($job, $data);

        if ($releaseTime instanceof \DateTimeImmutable) {
            $releaseTime = \DateTime::createFromImmutable($releaseTime);
        }

        $message = new BrokeredMessage($payload);
        $message->setScheduledEnqueueTimeUtc($releaseTime);

        $this->azure->sendQueueMessage($this->getQueue($queue), $message);
    }

    /**
     * Resolve the delay to a \DateTime object.
     *
     * @param mixed $delay
     * @return \DateTimeInterface
     * @throws \Exception
     */
    protected function resolveDelayToDateTime($delay): \DateTimeInterface
    {
        if ($delay instanceof \DateTimeInterface) {
            return (new \DateTimeImmutable())
                ->setTimestamp($delay->getTimestamp())
                ->setTimezone(new \DateTimeZone('UTC'));
        }

        if ($delay instanceof \DateInterval) {
            return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->add($delay);
        }

        if (is_int($delay)) {
            return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->add(new \DateInterval('PT' . $delay . 'S'));
        }

        throw new \InvalidArgumentException('Delay must be an int, DateTimeInterface, or DateInterval.');
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     * @return \Illuminate\Queue\Jobs\Job|null
     * @throws \Exception
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);
        $message = $this->receiveInternal($queue, $this->receiveOptions);

        if (!$message) {
            return null;
        }

        return new AzureJob($this->container, $this->azure, $message, $queue, $message->getBody());
    }

    /**
     * Get the queue name or return the default.
     *
     * @param string|null $queue
     * @return string
     */
    public function getQueue($queue): string
    {
        return $queue ?: $this->default;
    }
}
