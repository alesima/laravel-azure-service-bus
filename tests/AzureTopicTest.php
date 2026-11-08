<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureTopic;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;
use WindowsAzure\ServiceBus\Models\ReceiveMessageOptions;
use WindowsAzure\ServiceBus\Models\SubscriptionInfo;

class AzureTopicTest extends TestCase
{
    private AzureTopic $topic;

    /** @var \PHPUnit\Framework\MockObject\MockObject&\WindowsAzure\ServiceBus\Internal\IServiceBus */
    private $mockServiceBus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServiceBus = $this->createMock(IServiceBus::class);
        $this->topic = new AzureTopic($this->mockServiceBus, 'defaultQueue');
    }

    public function testPublishArrayPayloadAsJson(): void
    {
        $payload = ['data' => 'test'];

        $this->mockServiceBus->expects($this->once())
            ->method('sendTopicMessage')
            ->with(
                $this->equalTo('testTopic'),
                $this->callback(function (BrokeredMessage $message) use ($payload) {
                    return $message->getBody() === json_encode($payload);
                })
            );

        $this->topic->publish('testTopic', $payload);
    }

    public function testPublishRawStringMessage(): void
    {
        $payload = 'Simple string message';

        $this->mockServiceBus->expects($this->once())
            ->method('sendTopicMessage')
            ->with(
                $this->equalTo('testTopic'),
                $this->callback(function (BrokeredMessage $message) use ($payload) {
                    return $message->getBody() === $payload;
                })
            );

        $this->topic->publish('testTopic', $payload);
    }

    public function testPublishRejectsNonSerializablePayload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Resource / recursive payloads are not valid here; int forces the type guard.
        $this->topic->publish('testTopic', 123); // @phpstan-ignore-line
    }

    public function testSubscribeRetrievesMessage(): void
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);
        $mockMessage->method('getBody')->willReturn('{"event":"user.created"}');

        $this->mockServiceBus->method('getSubscription')->willReturn(new SubscriptionInfo('testSubscription'));

        $this->mockServiceBus->expects($this->once())
            ->method('receiveSubscriptionMessage')
            ->with(
                $this->equalTo('testTopic'),
                $this->equalTo('testSubscription'),
                $this->isInstanceOf(ReceiveMessageOptions::class)
            )
            ->willReturn($mockMessage);

        $message = $this->topic->subscribe('testTopic', 'testSubscription');

        $this->assertInstanceOf(BrokeredMessage::class, $message);
        $this->assertSame('{"event":"user.created"}', $message->getBody());
    }

    public function testSubscribeReturnsNullWhenNoMessage(): void
    {
        $this->mockServiceBus->method('getSubscription')->willReturn(new SubscriptionInfo('testSubscription'));
        $this->mockServiceBus->method('receiveSubscriptionMessage')->willReturn(null);

        $this->assertNull($this->topic->subscribe('testTopic', 'testSubscription'));
    }

    public function testSubscribeSwallowsAlreadyExistsConflict(): void
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);

        // Simulate "not found" on get, then 409 on create (race between workers).
        $this->mockServiceBus->method('getSubscription')
            ->willThrowException(new \RuntimeException('404 Not Found'));
        $this->mockServiceBus->method('createSubscription')
            ->willThrowException(new \RuntimeException('409 Conflict: subscription already exists'));
        $this->mockServiceBus->method('receiveSubscriptionMessage')->willReturn($mockMessage);

        $result = $this->topic->subscribe('testTopic', 'testSubscription');

        $this->assertSame($mockMessage, $result);
    }

    public function testSubscribeRethrowsRealErrors(): void
    {
        $this->mockServiceBus->method('getSubscription')
            ->willThrowException(new \RuntimeException('404 Not Found'));
        $this->mockServiceBus->method('createSubscription')
            ->willThrowException(new \RuntimeException('500 Internal Server Error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('500');

        $this->topic->subscribe('testTopic', 'testSubscription');
    }

    public function testSubscribeRejectsEmptyNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->topic->subscribe('', 'sub');
    }
}
