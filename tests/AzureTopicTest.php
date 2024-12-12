<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzureTopic;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use PHPUnit\Framework\MockObject\MockObject;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;
use WindowsAzure\ServiceBus\Models\ReceiveMessageOptions;

class AzureTopicTest extends TestCase
{
    /**
     * Instance of AzureTopic.
     *
     * @var AzureTopic
     */
    protected $topic;

    /**
     * Mock for IServiceBus.
     *
     * @var IServiceBus&MockObject
     */
    protected $mockServiceBus;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for IServiceBus
        $this->mockServiceBus = $this->createMock(IServiceBus::class);

        // Create the AzureTopic instance with the mock
        $this->topic = new AzureTopic($this->mockServiceBus, 'testSubscription');
    }

    /**
     * Test that a message is published to the correct topic.
     */
    public function testPublishMessage(): void
    {
        $payload = ['data' => 'test'];

        $this->mockServiceBus->expects($this->once())
            ->method('sendTopicMessage')
            ->with(
                $this->equalTo('testTopic'), // Verify the topic name
                $this->callback(function (BrokeredMessage $message) use ($payload) {
                    return $message->getBody() === json_encode($payload);
                }) // Verify the payload content
            );

        $this->topic->publish('testTopic', $payload);
    }

    /**
     * Test subscribing to a topic and retrieving a message.
     */
    public function testSubscribeMessage(): void
    {
        $mockMessage = $this->createMock(BrokeredMessage::class);
        $mockMessage->method('getBody')->willReturn('{"event":"user.created"}');

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
        $this->assertEquals('{"event":"user.created"}', $message->getBody());
    }

    /**
     * Test subscribing when no message is available.
     */
    public function testSubscribeReturnsNullWhenNoMessage(): void
    {
        $this->mockServiceBus->expects($this->once())
            ->method('receiveSubscriptionMessage')
            ->with(
                $this->equalTo('testTopic'),
                $this->equalTo('testSubscription'),
                $this->isInstanceOf(ReceiveMessageOptions::class)
            )
            ->willReturn(null);

        $message = $this->topic->subscribe('testTopic', 'testSubscription');

        $this->assertNull($message);
    }

    /**
     * Test publishing with a raw string payload.
     */
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
}
