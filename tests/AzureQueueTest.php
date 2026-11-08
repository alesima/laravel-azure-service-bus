<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use Alesima\LaravelAzureServiceBus\ServiceBus\Contracts\ServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\IncomingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\OutgoingMessage;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class AzureQueueTest extends TestCase
{
    private AzureQueue $queue;

    /** @var \PHPUnit\Framework\MockObject\MockObject&ServiceBusClient */
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = $this->createMock(ServiceBusClient::class);
        $this->queue = new AzureQueue($this->mockClient, 'testQueue');
        $this->queue->setContainer(new Container());
    }

    public function testPushRaw(): void
    {
        $this->mockClient->expects($this->once())
            ->method('sendQueueMessage')
            ->with(
                $this->equalTo('testQueue'),
                $this->callback(function (OutgoingMessage $message): bool {
                    return $message->getBody() === 'testPayload'
                        && $message->getScheduledEnqueueTimeUtc() === null;
                })
            );

        $this->queue->pushRaw('testPayload');
    }

    public function testPushRawWithSpecificQueue(): void
    {
        $this->mockClient->expects($this->once())
            ->method('sendQueueMessage')
            ->with(
                $this->equalTo('specificQueue'),
                $this->callback(
                    function (OutgoingMessage $message): bool {
                        return $message->getBody() === 'testPayload';
                    }
                )
            );

        $this->queue->pushRaw('testPayload', 'specificQueue');
    }

    public function testPop(): void
    {
        $this->mockClient->expects($this->once())
            ->method('receiveQueueMessage')
            ->with($this->equalTo('testQueue'))
            ->willReturn(new IncomingMessage('{"job":"TestJob"}', 'id-1', 1, 'lock'));

        $job = $this->queue->pop();

        $this->assertInstanceOf(AzureJob::class, $job);
        $this->assertSame('{"job":"TestJob"}', $job->getRawBody());
        $this->assertSame('testQueue', $job->getQueue());
    }

    public function testPopReturnsNullWhenNoMessage(): void
    {
        $this->mockClient->expects($this->once())
            ->method('receiveQueueMessage')
            ->willReturn(null);

        $this->assertNull($this->queue->pop());
    }

    public function testLater(): void
    {
        $before = time();

        $this->mockClient->expects($this->once())
            ->method('sendQueueMessage')
            ->with(
                $this->equalTo('testQueue'),
                $this->callback(function (OutgoingMessage $message) use ($before): bool {
                    $scheduled = $message->getScheduledEnqueueTimeUtc();

                    return $scheduled !== null
                        && $scheduled->getTimestamp() >= $before + 59
                        && $scheduled->getTimestamp() <= $before + 61;
                })
            );

        $this->queue->later(60, 'TestJob', ['data' => 'value']);
    }

    public function testSizeAlwaysZero(): void
    {
        $this->assertSame(0, $this->queue->size());
        $this->assertSame(0, $this->queue->size('other'));
    }

    public function testGetDefaultQueue(): void
    {
        $this->assertSame('testQueue', $this->queue->getDefaultQueue());
    }
}
