<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureJob;
use Alesima\LaravelAzureServiceBus\Drivers\AzureQueue;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;

class AzureQueueTest extends TestCase
{
    private AzureQueue $queue;

    /** @var \PHPUnit\Framework\MockObject\MockObject&\WindowsAzure\ServiceBus\Internal\IServiceBus */
    private $mockServiceBus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServiceBus = $this->createMock(IServiceBus::class);
        $this->queue = new AzureQueue($this->mockServiceBus, 'testQueue');
        $this->queue->setContainer(new Container());
    }

    public function testPushRaw(): void
    {
        $this->mockServiceBus->expects($this->once())
            ->method('sendQueueMessage')
            ->with(
                $this->equalTo('testQueue'),
                $this->callback(function (BrokeredMessage $message) {
                    return $message->getBody() === 'testPayload';
                })
            );

        $this->queue->pushRaw('testPayload');
    }

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
        $this->assertSame('{"job":"TestJob"}', $job->getRawBody());
        $this->assertSame('testQueue', $job->getQueue());
    }

    public function testPopReturnsNullWhenNoMessage(): void
    {
        $this->mockServiceBus->expects($this->once())
            ->method('receiveQueueMessage')
            ->willReturn(null);

        $this->assertNull($this->queue->pop());
    }

    public function testLaterWithSeconds(): void
    {
        $this->mockServiceBus->expects($this->once())
            ->method('sendQueueMessage')
            ->with(
                $this->equalTo('testQueue'),
                $this->callback(function (BrokeredMessage $message) {
                    $scheduled = $message->getScheduledEnqueueTimeUtc();

                    return $scheduled instanceof \DateTime && $scheduled > new \DateTime();
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
