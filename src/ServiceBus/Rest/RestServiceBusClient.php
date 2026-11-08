<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus\Rest;

use Alesima\LaravelAzureServiceBus\ServiceBus\Contracts\ServiceBusClient;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\IncomingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\Models\OutgoingMessage;
use Alesima\LaravelAzureServiceBus\ServiceBus\ReceiveMode;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Service Bus REST API client (SAS authentication).
 *
 * Endpoints mirror the retired microsoft/windowsazure SDK
 * (ServiceBusRestProxy): {endpoint}/{queue}/messages[/head],
 * {endpoint}/{topic}/subscriptions/{subscription}[/messages/head].
 */
final class RestServiceBusClient implements ServiceBusClient
{
    private const DATE_FORMAT = 'D, d M Y H:i:s T';
    private const ATOM_CONTENT_TYPE = 'application/atom+xml;type=entry;charset=utf-8';
    private const SUBSCRIPTION_XML_NAMESPACE = 'http://schemas.microsoft.com/netservices/2010/10/servicebus/connect';

    private string $endpoint;

    private SasTokenProvider $tokens;

    private HttpTransport $transport;

    private int $timeoutSeconds;

    public function __construct(
        string $endpoint,
        string $keyName,
        string $key,
        ?HttpTransport $transport = null,
        int $timeoutSeconds = 20,
        int $tokenTtlSeconds = 3600
    ) {
        $endpoint = rtrim(trim($endpoint), '/');

        if ($endpoint === '' || $keyName === '' || $key === '') {
            throw new InvalidArgumentException('Endpoint, key name and key must not be empty.');
        }

        if ($timeoutSeconds < 0 || $timeoutSeconds > 60) {
            throw new InvalidArgumentException('Timeout must be between 0 and 60 seconds.');
        }

        $this->endpoint = $endpoint;
        $this->tokens = new SasTokenProvider($keyName, $key, $tokenTtlSeconds);
        $this->transport = $transport ?? new CurlHttpTransport();
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function sendQueueMessage(string $queue, OutgoingMessage $message): void
    {
        $this->send($this->path($queue) . '/messages', $message);
    }

    public function receiveQueueMessage(
        string $queue,
        ReceiveMode $mode = ReceiveMode::PeekLock
    ): ?IncomingMessage {
        return $this->receive($this->path($queue) . '/messages/head', $mode);
    }

    public function sendTopicMessage(string $topic, OutgoingMessage $message): void
    {
        $this->send($this->path($topic) . '/messages', $message);
    }

    public function receiveSubscriptionMessage(
        string $topic,
        string $subscription,
        ReceiveMode $mode = ReceiveMode::PeekLock
    ): ?IncomingMessage {
        $url = $this->path($topic) . '/subscriptions/' . $this->segment($subscription) . '/messages/head';

        return $this->receive($url, $mode);
    }

    public function deleteMessage(IncomingMessage $message): void
    {
        $response = $this->request('DELETE', $this->lockPath($message));

        if ($response->statusCode !== 200) {
            throw new ServiceBusException(
                sprintf('Delete message failed with HTTP %d.', $response->statusCode),
                $response->statusCode
            );
        }
    }

    public function unlockMessage(IncomingMessage $message): void
    {
        $response = $this->request('PUT', $this->lockPath($message));

        if ($response->statusCode !== 200) {
            throw new ServiceBusException(
                sprintf('Unlock message failed with HTTP %d.', $response->statusCode),
                $response->statusCode
            );
        }
    }

    public function subscriptionExists(string $topic, string $subscription): bool
    {
        $response = $this->request('GET', $this->subscriptionPath($topic, $subscription));

        if ($response->statusCode === 200) {
            return true;
        }

        if ($response->statusCode === 404) {
            return false;
        }

        throw new ServiceBusException(
            sprintf('Get subscription failed with HTTP %d.', $response->statusCode),
            $response->statusCode
        );
    }

    public function createSubscription(string $topic, string $subscription): void
    {
        $url = $this->subscriptionPath($topic, $subscription);
        $response = $this->transport->request('PUT', $url, [
            'Authorization' => $this->tokens->createForUrl($url),
            'Content-Type' => self::ATOM_CONTENT_TYPE,
        ], $this->subscriptionEntryXml());

        if ($response->statusCode !== 201) {
            throw new ServiceBusException(
                sprintf('Create subscription failed with HTTP %d.', $response->statusCode),
                $response->statusCode
            );
        }
    }

    private function send(string $url, OutgoingMessage $message): void
    {
        $headers = ['Authorization' => $this->tokens->createForUrl($url)];

        if ($message->getContentType() !== null) {
            $headers['Content-Type'] = $message->getContentType();
        }

        if ($message->getScheduledEnqueueTimeUtc() !== null) {
            $brokerProperties = json_encode([
                'ScheduledEnqueueTimeUtc' => $this->formatDate($message->getScheduledEnqueueTimeUtc()),
            ]);

            if ($brokerProperties === false) {
                throw new ServiceBusException('Failed to encode BrokerProperties header.', 0);
            }

            $headers['BrokerProperties'] = $brokerProperties;
        }

        $response = $this->transport->request('POST', $url, $headers, $message->getBody());

        if ($response->statusCode !== 201) {
            throw new ServiceBusException(
                sprintf('Send message failed with HTTP %d.', $response->statusCode),
                $response->statusCode
            );
        }
    }

    private function receive(string $url, ReceiveMode $mode): ?IncomingMessage
    {
        $url .= '?timeout=' . $this->timeoutSeconds;
        $method = $mode === ReceiveMode::ReceiveAndDelete ? 'DELETE' : 'POST';
        $response = $this->request($method, $url);

        if ($response->statusCode === 204) {
            return null;
        }

        if ($response->statusCode !== 200 && $response->statusCode !== 201) {
            throw new ServiceBusException(
                sprintf('Receive message failed with HTTP %d.', $response->statusCode),
                $response->statusCode
            );
        }

        $properties = $this->parseBrokerProperties($response->header('BrokerProperties'));

        return new IncomingMessage(
            $response->body,
            $properties['MessageId'],
            $properties['DeliveryCount'],
            $response->header('Location')
        );
    }

    /**
     * @return array{MessageId: ?string, DeliveryCount: int}
     */
    private function parseBrokerProperties(?string $header): array
    {
        $result = ['MessageId' => null, 'DeliveryCount' => 0];

        if ($header === null || $header === '') {
            return $result;
        }

        $decoded = json_decode($header, true);

        if (! is_array($decoded)) {
            return $result;
        }

        if (isset($decoded['MessageId']) && is_string($decoded['MessageId'])) {
            $result['MessageId'] = $decoded['MessageId'];
        }

        if (isset($decoded['DeliveryCount']) && is_numeric($decoded['DeliveryCount'])) {
            $result['DeliveryCount'] = (int) $decoded['DeliveryCount'];
        }

        return $result;
    }

    private function request(string $method, string $url): HttpResponse
    {
        return $this->transport->request($method, $url, [
            'Authorization' => $this->tokens->createForUrl($url),
        ]);
    }

    private function lockPath(IncomingMessage $message): string
    {
        $location = $message->getLockLocation();

        if ($location === null || $location === '') {
            throw new InvalidArgumentException('The lock location of the message is missing.');
        }

        $parts = parse_url($location);

        if (! is_array($parts) || ! isset($parts['path'])) {
            throw new InvalidArgumentException('The lock location of the message is invalid.');
        }

        $path = ltrim($parts['path'], '/');

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        return $this->endpoint . '/' . $path;
    }

    private function subscriptionPath(string $topic, string $subscription): string
    {
        return $this->path($topic) . '/subscriptions/' . $this->segment($subscription);
    }

    private function path(string $name): string
    {
        return $this->endpoint . '/' . $this->segment($name);
    }

    private function segment(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Queue, topic and subscription names must not be empty.');
        }

        return $value;
    }

    private function formatDate(\DateTimeInterface $date): string
    {
        $utc = new DateTime('now', new DateTimeZone('UTC'));
        $utc->setTimestamp($date->getTimestamp());

        return $utc->format(self::DATE_FORMAT);
    }

    private function subscriptionEntryXml(): string
    {
        return '<entry xmlns="http://www.w3.org/2005/Atom">'
            . '<content type="application/xml">'
            . '<SubscriptionDescription xmlns="' . self::SUBSCRIPTION_XML_NAMESPACE . '" />'
            . '</content>'
            . '</entry>';
    }
}
