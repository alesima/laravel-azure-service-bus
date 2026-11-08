# Changelog

## 2.0.0

Breaking: dropped the abandoned `microsoft/windowsazure` SDK; the package
now ships its own Service Bus REST client (`src/ServiceBus/Rest/`).

- New `ServiceBusClient` contract + `RestServiceBusClient` (SAS auth,
  send/receive/delete/unlock, subscriptions) over `ext-curl`.
- New value objects: `IncomingMessage`, `OutgoingMessage`, `ReceiveMode` enum.
- `HttpTransport` is injectable — the whole suite runs offline (`FakeTransport`).
- `AzureConnector` builds the REST client (config: `endpoint`, key name/key,
  `queue`, `timeout` 0-60); injectable factory for tests.
- Covered by `RestServiceBusClientTest` + `SasTokenProviderTest`
  (paths, headers, status mapping, SAS algorithm).
- PHP `^8.1`, Laravel `^10|^11`, PHPUnit `^10|^11`.

Migration from 1.x: `AzureJob::getAzure()` → `getClient()`,
`AzureJob::getAzureJob()` → `getMessage()`, `AzureTopic::subscribe()`
now returns `IncomingMessage` (same `getBody()` accessor).

## 1.1.0

- Fix `ServiceBus::$receiveOptions` visibility (`private` → `protected`).
- Fix `push/pushRaw/later` signatures to match `Illuminate\Contracts\Queue\Queue`.
- Fix `AzureTopic::ensureSubscriptionExists()` 409 Conflict on every second `subscribe()`.
- Fix `AzureJob::getJobId()/attempts()` null handling, `delete()` order.
- Failed-job cleanup goes through `fail()` → `delete()` (same as `SqsJob`).
- Add `config/azureservicebus.php`, `ServiceProvider::register()` + publishes.
- Add `AzureConnector` config validation + testable factory.
- Modernize: PHP `^8.1`, Laravel `^10|^11`, PHPUnit `^10|^11`, CI matrix, PHPStan level 8.
- Tests: `ServiceBusDelayTest` + `ServiceProviderTest`, 409 handling, invalid configs.
