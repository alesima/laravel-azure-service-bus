# Laravel Azure Service Bus Queue Driver

[![CI](https://github.com/alesima/laravel-azure-service-bus/actions/workflows/ci.yml/badge.svg)](https://github.com/alesima/laravel-azure-service-bus/actions)
[![Coverage](https://coveralls.io/repos/github/alesima/laravel-azure-service-bus/badge.svg?branch=master)](https://coveralls.io/github/alesima/laravel-azure-service-bus?branch=master)
[![Packagist](https://img.shields.io/packagist/v/alesima/laravel-azure-service-bus.svg)](https://packagist.org/packages/alesima/laravel-azure-service-bus)
[![PHP](https://img.shields.io/packagist/php-v/alesima/laravel-azure-service-bus.svg)](https://packagist.org/packages/alesima/laravel-azure-service-bus)
[![License](https://img.shields.io/packagist/l/alesima/laravel-azure-service-bus.svg)](LICENSE)

Integrate **Azure Service Bus** as a Laravel queue driver, with support for queues and topics/subscriptions (Pub/Sub). Ships its own lightweight **Service Bus REST client** (SAS auth) — no abandoned SDK required.

## Requirements

- PHP `^8.1` with `ext-curl`, `ext-json`
- Laravel `10.*` / `11.*`

## Installation

```bash
composer require alesima/laravel-azure-service-bus
```

Publish the config:

```bash
php artisan vendor:publish --provider="Alesima\LaravelAzureServiceBus\Providers\ServiceProvider" --tag=config
```

## Configuration

`config/azureservicebus.php`:

```php
return [
    'endpoint' => env('SERVICE_BUS_ENDPOINT', 'https://<your-namespace>.servicebus.windows.net'),
    'shared_access_key_name' => env('SERVICE_BUS_SHARED_ACCESS_KEY_NAME'),
    'shared_access_key' => env('SERVICE_BUS_SHARED_ACCESS_KEY'),
    'queue' => env('SERVICE_BUS_QUEUE', 'default'),
    'timeout' => (int) env('SERVICE_BUS_TIMEOUT', 20), // receive long-poll, 0-60s
];
```

`config/queue.php`:

```php
'connections' => [
    'azureservicebus' => [
        'driver' => 'azureservicebus',
        'endpoint' => config('azureservicebus.endpoint'),
        'shared_access_key_name' => config('azureservicebus.shared_access_key_name'),
        'shared_access_key' => config('azureservicebus.shared_access_key'),
        'queue' => config('azureservicebus.queue', 'default'),
        'timeout' => config('azureservicebus.timeout', 20),
    ],
],
```

Set `QUEUE_CONNECTION=azureservicebus` or dispatch explicitly.

## Usage

### Queues

```php
dispatch(new MyJob($data));
dispatch((new MyJob($data))->delay(60));
dispatch((new MyJob($data))->delay(new DateInterval('PT10M')));
dispatch((new MyJob($data))->delay(new DateTime('+1 hour')));
```

`size()` always returns `0` — Service Bus exposes no cheap count API. Don't use it for autoscaling.

`release($delay)` unlocks the message; PeekLock unlock does **not** honor a server-side delay. For a true delay, delete + re-push with `later()`.

### Topics / Subscriptions (Pub/Sub)

```php
use Alesima\LaravelAzureServiceBus\Drivers\AzureTopic;

/** @var AzureTopic $topics */
$topics = app('queue')->connection('azureservicebus');

// Publish (array is JSON-encoded, string is sent raw)
$topics->publish('orders', ['event' => 'order.created', 'id' => 123]);

// Subscribe (creates the subscription if missing; 409 races are ignored)
$message = $topics->subscribe('orders', 'billing');
if ($message !== null) {
    $body = $message->getBody();
}
```

## How it works

`src/ServiceBus/Rest/RestServiceBusClient.php` talks to the
Service Bus REST API directly:

- SAS tokens generated per request (same algorithm as the retired SDK).
- `POST …/{queue}/messages` to send (`BrokerProperties` header carries `ScheduledEnqueueTimeUtc`).
- `POST …/{queue}/messages/head?timeout=N` to receive with PeekLock (`Location` header = lock URI).
- `DELETE` / `PUT` on the lock URI to settle.
- `PUT …/{topic}/subscriptions/{sub}` (ATOM entry) to create subscriptions.

The HTTP layer (`HttpTransport`) is injectable, so tests run fully offline — see `tests/Support/FakeTransport.php`.

## Lumen

```php
$app->register(Alesima\LaravelAzureServiceBus\Providers\ServiceProvider::class);
```

## Testing

```bash
composer test
composer analyse
composer lint
```

## Releasing

No local commands needed. Go to the **Actions** tab → **Release** →
**Run workflow**, enter the version (`X.Y.Z`, e.g. `2.1.0`). The workflow
tags `master`, opens a GitHub Release, and the Packagist webhook publishes
the version automatically.

## License

MIT.
