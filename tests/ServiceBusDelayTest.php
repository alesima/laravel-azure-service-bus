<?php

namespace Alesima\LaravelAzureServiceBus\Tests;

use Alesima\LaravelAzureServiceBus\ServiceBus;
use Alesima\LaravelAzureServiceBus\ServiceBus\Contracts\ServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\OutgoingMessage;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ServiceBusDelayTest extends TestCase
{
    private ServiceBus $bus;

    /** @var \PHPUnit\Framework\MockObject\MockObject&ServiceBusClient */
    private $mockClient;

    /** @var \ArrayObject<string, mixed> */
    private \ArrayObject $sent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = $this->createMock(ServiceBusClient::class);
        $this->sent = new \ArrayObject();

        $this->mockClient->method('sendQueueMessage')
            ->willReturnCallback(function (string $queue, OutgoingMessage $message): void {
                $this->sent['message'] = $message;
            });
        $this->mockClient->method('receiveQueueMessage')->willReturn(null);

        $this->bus = new ServiceBus($this->mockClient, 'default');
        $this->bus->setContainer(new Container());
    }

    private function lastMessage(): OutgoingMessage
    {
        $message = $this->sent['message'] ?? null;
        $this->assertInstanceOf(OutgoingMessage::class, $message);

        return $message;
    }

    public function testLaterWithDateTime(): void
    {
        $at = new DateTime('+1 hour', new DateTimeZone('America/Sao_Paulo'));

        $this->bus->later($at, 'TestJob');

        $scheduled = $this->lastMessage()->getScheduledEnqueueTimeUtc();
        $this->assertNotNull($scheduled);
        $this->assertSame($at->getTimestamp(), $scheduled->getTimestamp());
        $this->assertSame('UTC', $scheduled->getTimezone()->getName());
    }

    public function testLaterWithDateTimeImmutable(): void
    {
        $at = new DateTimeImmutable('+30 minutes');

        $this->bus->later($at, 'TestJob');

        $scheduled = $this->lastMessage()->getScheduledEnqueueTimeUtc();
        $this->assertNotNull($scheduled);
        $this->assertSame($at->getTimestamp(), $scheduled->getTimestamp());
    }

    public function testLaterWithDateInterval(): void
    {
        $before = time();
        $this->bus->later(new DateInterval('PT10M'), 'TestJob');

        $scheduled = $this->lastMessage()->getScheduledEnqueueTimeUtc();
        $this->assertNotNull($scheduled);
        $delta = $scheduled->getTimestamp() - $before;
        $this->assertGreaterThanOrEqual(595, $delta);
        $this->assertLessThanOrEqual(605, $delta);
    }

    public function testLaterRejectsNegativeInt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->bus->later(-5, 'TestJob');
    }

    public function testLaterRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->bus->later('tomorrow', 'TestJob'); // @phpstan-ignore-line
    }

    public function testPushUsesDefaultQueueWhenEmpty(): void
    {
        $queues = [];
        $client = $this->createMock(ServiceBusClient::class);
        $client->method('sendQueueMessage')
            ->willReturnCallback(function (string $queue) use (&$queues): void {
                $queues[] = $queue;
            });

        $bus = new ServiceBus($client, 'fallback');
        $bus->setContainer(new Container());
        $bus->pushRaw('payload', null);
        $bus->pushRaw('payload', '');

        $this->assertSame(['fallback', 'fallback'], $queues);
    }
}
