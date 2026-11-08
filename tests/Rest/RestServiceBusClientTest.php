<?php

namespace Alesima\LaravelAzureServiceBus\Tests\Rest;

use Alesima\LaravelAzureServiceBus\ServiceBus\Models\IncomingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\OutgoingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\ReceiveMode;
use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\HttpResponse;
use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\RestServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Rest\ServiceBusException;
use Alesima\LaravelAzureServiceBus\Tests\Support\FakeTransport;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RestServiceBusClientTest extends TestCase
{
    private const ENDPOINT = 'https://myns.servicebus.windows.net';

    private FakeTransport $transport;

    private RestServiceBusClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new FakeTransport(new HttpResponse(201, [], ''));
        $this->client = new RestServiceBusClient(
            self::ENDPOINT,
            'keyName',
            'keyValue',
            $this->transport
        );
    }

    public function testSendQueueMessagePostsToMessagesPath(): void
    {
        $this->client->sendQueueMessage('orders', new OutgoingMessage('hello'));

        $request = $this->transport->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertSame(self::ENDPOINT . '/orders/messages', $request['url']);
        $this->assertSame('hello', $request['body']);
        $this->assertStringStartsWith('SharedAccessSignature ', $request['headers']['Authorization']);
        $this->assertArrayNotHasKey('BrokerProperties', $request['headers']);
    }

    public function testSendWithDelaySetsBrokerPropertiesHeader(): void
    {
        $at = new DateTime('+1 hour', new DateTimeZone('America/Sao_Paulo'));

        $this->client->sendQueueMessage('orders', new OutgoingMessage('hello', $at));

        $request = $this->transport->lastRequest();
        $this->assertArrayHasKey('BrokerProperties', $request['headers']);

        $decoded = json_decode($request['headers']['BrokerProperties'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ScheduledEnqueueTimeUtc', $decoded);
        $this->assertIsString($decoded['ScheduledEnqueueTimeUtc']);
        $this->assertSame(
            $at->getTimestamp(),
            (new DateTime($decoded['ScheduledEnqueueTimeUtc']))->getTimestamp()
        );
    }

    public function testSendThrowsOnUnexpectedStatus(): void
    {
        $transport = new FakeTransport(new HttpResponse(500, [], 'boom'));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        $this->expectException(ServiceBusException::class);

        try {
            $client->sendQueueMessage('orders', new OutgoingMessage('hello'));
        } catch (ServiceBusException $e) {
            $this->assertSame(500, $e->getStatusCode());
            throw $e;
        }
    }

    public function testReceiveReturnsNullOnNoContent(): void
    {
        $transport = new FakeTransport(new HttpResponse(204, [], ''));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        $this->assertNull($client->receiveQueueMessage('orders'));
        $this->assertSame('POST', $transport->lastRequest()['method']);
        $this->assertSame(self::ENDPOINT . '/orders/messages/head?timeout=20', $transport->lastRequest()['url']);
    }

    public function testReceiveAndDeleteUsesDeleteMethod(): void
    {
        $transport = new FakeTransport(new HttpResponse(204, [], ''));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        $client->receiveQueueMessage('orders', ReceiveMode::ReceiveAndDelete);

        $this->assertSame('DELETE', $transport->lastRequest()['method']);
    }

    public function testReceiveParsesBrokerPropertiesAndLocation(): void
    {
        $transport = new FakeTransport(new HttpResponse(201, [
            'brokerproperties' => '{"MessageId":"id-9","DeliveryCount":4}',
            'location' => self::ENDPOINT . '/orders/messages/id-9/lock-1',
        ], '{"job":"x"}'));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        $message = $client->receiveQueueMessage('orders');

        $this->assertInstanceOf(IncomingMessage::class, $message);
        $this->assertSame('{"job":"x"}', $message->getBody());
        $this->assertSame('id-9', $message->getMessageId());
        $this->assertSame(4, $message->getDeliveryCount());
        $this->assertSame(self::ENDPOINT . '/orders/messages/id-9/lock-1', $message->getLockLocation());
    }

    public function testDeleteCallsLockLocation(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], ''));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        $client->deleteMessage(new IncomingMessage(
            'body',
            'id-1',
            1,
            self::ENDPOINT . '/orders/messages/id-1/lock-abc'
        ));

        $request = $transport->lastRequest();
        $this->assertSame('DELETE', $request['method']);
        $this->assertSame(self::ENDPOINT . '/orders/messages/id-1/lock-abc', $request['url']);
    }

    public function testDeleteThrowsOnUnexpectedStatus(): void
    {
        $transport = new FakeTransport(new HttpResponse(404, [], 'gone'));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        try {
            $client->deleteMessage(new IncomingMessage('b', 'id-1', 1, self::ENDPOINT . '/q/messages/id-1/lock'));
            $this->fail('Expected ServiceBusException.');
        } catch (ServiceBusException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function testUnlockThrowsOnUnexpectedStatus(): void
    {
        $transport = new FakeTransport(new HttpResponse(500, [], 'boom'));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        $this->expectException(ServiceBusException::class);

        $client->unlockMessage(new IncomingMessage('b', 'id-1', 1, self::ENDPOINT . '/q/messages/id-1/lock'));
    }

    public function testSubscriptionExistsThrowsOnServerError(): void
    {
        $transport = new FakeTransport(new HttpResponse(500, [], 'boom'));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        $this->expectException(ServiceBusException::class);

        $client->subscriptionExists('topic', 'sub');
    }

    public function testCreateSubscriptionThrowsOnServerError(): void
    {
        $transport = new FakeTransport(new HttpResponse(500, [], 'boom'));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        try {
            $client->createSubscription('topic', 'sub');
            $this->fail('Expected ServiceBusException.');
        } catch (ServiceBusException $e) {
            $this->assertSame(500, $e->getStatusCode());
        }
    }

    public function testReceiveAccepts200AndThrowsOnServerError(): void
    {
        $okTransport = new FakeTransport(new HttpResponse(200, ['brokerproperties' => '{"MessageId":"m"}'], 'hi'));
        $okClient = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $okTransport);

        $message = $okClient->receiveQueueMessage('orders');
        $this->assertInstanceOf(IncomingMessage::class, $message);
        $this->assertSame('m', $message->getMessageId());
        $this->assertSame(0, $message->getDeliveryCount());

        $badTransport = new FakeTransport(new HttpResponse(500, [], 'boom'));
        $badClient = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $badTransport);

        $this->expectException(ServiceBusException::class);
        $badClient->receiveQueueMessage('orders');
    }

    public function testSendIncludesContentTypeWhenSet(): void
    {
        $this->client->sendQueueMessage('orders', new OutgoingMessage('hello', null, 'text/plain'));

        $request = $this->transport->lastRequest();
        $this->assertSame('text/plain', $request['headers']['Content-Type']);
    }

    public function testDeleteWithoutLockLocationThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client->deleteMessage(new IncomingMessage('body', null, 0, null));
    }

    public function testUnlockUsesPutOnLockLocation(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], ''));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        $client->unlockMessage(new IncomingMessage(
            'body',
            'id-1',
            1,
            self::ENDPOINT . '/orders/messages/id-1/lock-abc'
        ));

        $request = $transport->lastRequest();
        $this->assertSame('PUT', $request['method']);
        $this->assertSame(self::ENDPOINT . '/orders/messages/id-1/lock-abc', $request['url']);
    }

    public function testSubscriptionExistsMapsStatuses(): void
    {
        $transport = new FakeTransport();
        $transport->queue(new HttpResponse(200, [], ''), new HttpResponse(404, [], ''));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        $this->assertTrue($client->subscriptionExists('topic', 'sub'));
        $this->assertFalse($client->subscriptionExists('topic', 'sub'));
        $this->assertSame('GET', $transport->requests[0]['method']);
        $this->assertSame(
            self::ENDPOINT . '/topic/subscriptions/sub',
            $transport->requests[0]['url']
        );
    }

    public function testCreateSubscriptionSendsAtomEntry(): void
    {
        $this->client->createSubscription('topic', 'sub');

        $request = $this->transport->lastRequest();
        $this->assertSame('PUT', $request['method']);
        $this->assertSame(self::ENDPOINT . '/topic/subscriptions/sub', $request['url']);
        $this->assertSame(
            'application/atom+xml;type=entry;charset=utf-8',
            $request['headers']['Content-Type']
        );
        $this->assertStringContainsString('SubscriptionDescription', $request['body']);
    }

    public function testCreateSubscriptionConflictSurfaces409(): void
    {
        $transport = new FakeTransport(new HttpResponse(409, [], 'conflict'));
        $client = new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $transport);

        try {
            $client->createSubscription('topic', 'sub');
            $this->fail('Expected ServiceBusException.');
        } catch (ServiceBusException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function testConstructorRejectsEmptyEndpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RestServiceBusClient('', 'k', 'v', $this->transport);
    }

    public function testConstructorRejectsBadTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RestServiceBusClient(self::ENDPOINT, 'k', 'v', $this->transport, 61);
    }

    public function testEndpointTrailingSlashIsTrimmed(): void
    {
        $client = new RestServiceBusClient(self::ENDPOINT . '/', 'k', 'v', $this->transport);
        $client->sendQueueMessage('q', new OutgoingMessage('b'));

        $this->assertSame(self::ENDPOINT . '/q/messages', $this->transport->lastRequest()['url']);
    }
}
