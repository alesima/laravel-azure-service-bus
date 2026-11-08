<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureTopic;
use Alesima\LaravelAzureServiceBus\ServiceBus\Contracts\ServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\IncomingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\OutgoingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\ReceiveMode;
use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\ServiceBusException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AzureTopicTest extends TestCase
{
    private AzureTopic $topic;

    /** @var \PHPUnit\Framework\MockObject\MockObject&ServiceBusClient */
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = $this->createMock(ServiceBusClient::class);
        $this->topic = new AzureTopic($this->mockClient, 'defaultQueue');
    }

    public function testPublishArrayPayloadAsJson(): void
    {
        $payload = ['data' => 'test'];

        $this->mockClient->expects($this->once())
            ->method('sendTopicMessage')
            ->with(
                $this->equalTo('testTopic'),
                $this->callback(function (OutgoingMessage $message) use ($payload): bool {
                    return $message->getBody() === json_encode($payload);
                })
            );

        $this->topic->publish('testTopic', $payload);
    }

    public function testPublishRawStringMessage(): void
    {
        $payload = 'Simple string message';

        $this->mockClient->expects($this->once())
            ->method('sendTopicMessage')
            ->with(
                $this->equalTo('testTopic'),
                $this->callback(function (OutgoingMessage $message) use ($payload): bool {
                    return $message->getBody() === $payload;
                })
            );

        $this->topic->publish('testTopic', $payload);
    }

    public function testPublishRejectsNonArrayNonStringPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->topic->publish('testTopic', 123);
    }

    public function testPublishRejectsEmptyTopic(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->topic->publish('', 'body');
    }

    public function testSubscribeRetrievesMessageWhenSubscriptionExists(): void
    {
        $message = new IncomingMessage('{"event":"user.created"}', 'id-1', 1, 'lock');

        $this->mockClient->method('subscriptionExists')->willReturn(true);
        $this->mockClient->expects($this->never())->method('createSubscription');
        $this->mockClient->expects($this->once())
            ->method('receiveSubscriptionMessage')
            ->with('testTopic', 'testSubscription', ReceiveMode::PeekLock)
            ->willReturn($message);

        $result = $this->topic->subscribe('testTopic', 'testSubscription');

        $this->assertSame($message, $result);
    }

    public function testSubscribeCreatesMissingSubscription(): void
    {
        $message = new IncomingMessage('{"event":"user.created"}', 'id-1', 1, 'lock');

        $this->mockClient->method('subscriptionExists')->willReturn(false);
        $this->mockClient->expects($this->once())
            ->method('createSubscription')
            ->with('testTopic', 'testSubscription');
        $this->mockClient->method('receiveSubscriptionMessage')->willReturn($message);

        $this->assertSame($message, $this->topic->subscribe('testTopic', 'testSubscription'));
    }

    public function testSubscribeReturnsNullWhenNoMessage(): void
    {
        $this->mockClient->method('subscriptionExists')->willReturn(true);
        $this->mockClient->method('receiveSubscriptionMessage')->willReturn(null);

        $this->assertNull($this->topic->subscribe('testTopic', 'testSubscription'));
    }

    public function testSubscribeSwallowsAlreadyExistsConflict(): void
    {
        $message = new IncomingMessage('x', 'id-1', 1, 'lock');

        $this->mockClient->method('subscriptionExists')->willReturn(false);
        $this->mockClient->method('createSubscription')
            ->willThrowException(new ServiceBusException('conflict', 409));
        $this->mockClient->method('receiveSubscriptionMessage')->willReturn($message);

        $this->assertSame($message, $this->topic->subscribe('testTopic', 'testSubscription'));
    }

    public function testSubscribeRethrowsRealErrors(): void
    {
        $this->mockClient->method('subscriptionExists')->willReturn(false);
        $this->mockClient->method('createSubscription')
            ->willThrowException(new ServiceBusException('boom', 500));

        $this->expectException(ServiceBusException::class);

        $this->topic->subscribe('testTopic', 'testSubscription');
    }

    public function testSubscribeRejectsEmptyNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->topic->subscribe('', 'sub');
    }
}
