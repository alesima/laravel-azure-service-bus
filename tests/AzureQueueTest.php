<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzureQueueTest extends TestCase
{
    /**
     * The AzureQueue instance.
     *
     * @var AzureQueue
     */
    protected $queue;

    /**
     * The mock IServiceBus.
     *
     * @var IServiceBus&MockObject
     */
    protected $mockServiceBus;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for the IServiceBus
        $this->mockServiceBus = $this->createMock(IServiceBus::class);

        // Create the AzureQueue instance with the mock
        $this->queue = new AzureQueue($this->mockServiceBus, 'testQueue');

        // Inject the container
        $container = new Container();
        $this->queue->setContainer($container);
    }

    /**
     * Test pushing raw payload to the queue.
     */
    public function testPushRaw(): void
    {
        $this->mockServiceBus->expects($this->once())
            ->method('sendQueueMessage')
            ->with(
                $this->equalTo('testQueue'),
                $this->callback(function (BrokeredMessage $message) {
                    return $message instanceof BrokeredMessage && $message->getBody() === 'testPayload';
                })
            );

        $this->queue->pushRaw('testPayload');
    }

    /**
     * Test popping a message from the queue.
     */
    public function testPop(): void
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);
        $mockMessage->method('getBody')->willReturn('{"job":"TestJob"}');

        $this->mockServiceBus->expects($this->once())
            ->method('receiveQueueMessage')
            ->with($this->equalTo('testQueue'))
            ->willReturn($mockMessage);

        $job = $this->queue->pop();

        $this->assertInstanceOf(AzureJob::class, $job);
        $this->assertEquals('{"job":"TestJob"}', $job->getRawBody());
    }

    /**
     * Test pushing a job to be executed later.
     */
    public function testLater(): void
    {
        $this->mockServiceBus->expects($this->once())
            ->method('sendQueueMessage')
            ->with(
                $this->equalTo('testQueue'),
                $this->callback(function (BrokeredMessage $message) {
                    $scheduledTime = $message->getScheduledEnqueueTimeUtc();
                    return $scheduledTime instanceof \DateTime &&
                        $scheduledTime > new \DateTime();
                })
            );

        $this->queue->later(60, 'TestJob', ['data' => 'value']);
    }

    /**
     * Test popping when there are no messages in the queue.
     */
    public function testPopReturnsNullWhenNoMessage(): void
    {
        $this->mockServiceBus->expects($this->once())
            ->method('receiveQueueMessage')
            ->with($this->equalTo('testQueue'))
            ->willReturn(null);

        $job = $this->queue->pop();

        $this->assertNull($job);
    }

    /**
     * Test pushing raw payload with a specific queue.
     */
    public function testPushRawWithSpecificQueue(): void
    {
        $this->mockServiceBus->expects($this->once())
            ->method('sendQueueMessage')
            ->with(
                $this->equalTo('specificQueue'),
                $this->callback(function (BrokeredMessage $message) {
                    return $message->getBody() === 'testPayload';
                })
            );

        $this->queue->pushRaw('testPayload', 'specificQueue');
    }
}
