<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Azure Service Bus endpoint
    |--------------------------------------------------------------------------
    | Full Service Bus namespace endpoint, e.g.
    | https://<your-namespace>.servicebus.windows.net
    */
    'endpoint' => env('SERVICE_BUS_ENDPOINT', env('SERVICE_BUS_NAMESPACE'
        ? sprintf('https://%s.servicebus.windows.net', env('SERVICE_BUS_NAMESPACE'))
        : 'https://example.servicebus.windows.net')),

    'shared_access_key_name' => env('SERVICE_BUS_SHARED_ACCESS_KEY_NAME'),
    'shared_access_key' => env('SERVICE_BUS_SHARED_ACCESS_KEY'),

    'queue' => env('SERVICE_BUS_QUEUE', 'default'),
];
