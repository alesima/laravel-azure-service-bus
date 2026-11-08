# Changelog

## 1.1.0

- Fix `ServiceBus::$receiveOptions` visibility (`private` → `protected`).
- Fix `push/pushRaw/later` signatures to match `Illuminate\Contracts\Queue\Queue`.
- Fix `AzureTopic::ensureSubscriptionExists()` 409 Conflict on every second `subscribe()`.
- Fix `AzureJob::getJobId()/attempts()` null handling, `delete()` order.
  Failed-job cleanup intentionally goes through `fail()` → `delete()`
  (same as `SqsJob`); no `failed()` override, whose parent signature
  differs between illuminate 8 and 10/11.
- Add `config/azureservicebus.php`, `ServiceProvider::register()` + publishes.
- Add `AzureConnector::buildConnectionString()` + config validation + injectable factory.
- Modernize: PHP `^8.1`, Laravel `10|11`, PHPUnit `10|11`, CI matrix, PHPStan level 8.
- Tests: fix false-positive connector test, add `ServiceBusDelayTest` + `ServiceProviderTest`, cover 409 handling.
- Docs: rewrite README with correct namespaces (`Alesima\...`, `Drivers\AzureTopic`).
