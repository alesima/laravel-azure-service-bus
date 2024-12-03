<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\Drivers\AzurePubSub;
use PHPUnit\Framework\TestCase;
use WindowsAzure\ServiceBus\Internal\IServiceBus;

class AzurePubSubTest extends TestCase
{
    protected $pubsub;

    protected function setUp(): void
    {
        $mockServiceBus = $this->createMock(IServiceBus::class);
        $this->pubsub = new AzurePubSub($mockServiceBus);
    }

    public function testPublishMessage()
    {
        $mockServiceBus = $this->createMock(\WindowsAzure\ServiceBus\Internal\IServiceBus::class);
        $mockServiceBus->expects($this->once())
            ->method('sendTopicMessage');

        $pubSub = new AzurePubSub($mockServiceBus);
        $pubSub->publish('testTopic', ['data' => 'test']);
    }

    public function testSubscribeMessage()
    {
        $mockMessage = $this->createMock(\WindowsAzure\ServiceBus\Models\BrokeredMessage::class);
        $mockMessage->method('getBody')->willReturn('{"event":"user.created"}');

        $mockServiceBus = $this->createMock(\WindowsAzure\ServiceBus\Internal\IServiceBus::class);
        $mockServiceBus->expects($this->once())
            ->method('receiveSubscriptionMessage')
            ->willReturn($mockMessage);

        $pubSub = new AzurePubSub($mockServiceBus);
        $message = $pubSub->subscribe('testTopic', 'testSubscription');

        $this->assertEquals('{"event":"user.created"}', $message->getBody());
    }
}
