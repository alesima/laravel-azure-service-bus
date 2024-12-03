<?php

return [
    'endpoint' => env('AZURE_SERVICE_BUS_ENDPOINT', 'https://your-namespace.servicebus.windows.net'),
    'shared_access_key_name' => env('AZURE_SERVICE_BUS_KEY_NAME', 'defaultKeyName'),
    'shared_access_key' => env('AZURE_SERVICE_BUS_KEY', 'defaultKey'),
    'queue' => env('AZURE_SERVICE_BUS_QUEUE', 'defaultQueue'),
    'topics' => explode(',', env('AZURE_SERVICE_BUS_TOPICS', '')),
];
