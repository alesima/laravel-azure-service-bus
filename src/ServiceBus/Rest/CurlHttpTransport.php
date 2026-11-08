<?php

namespace Alesima\LaravelAzureServiceBus\ServiceBus\Rest;

/**
 * cURL-based HTTP transport.
 */
final class CurlHttpTransport implements HttpTransport
{
    /**
     * @param array<string,string> $headers
     */
    public function request(string $method, string $url, array $headers, string $body = ''): HttpResponse
    {
        if ($method === '' || $url === '') {
            throw new ServiceBusException('cURL request method and URL must not be empty.', 0);
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new ServiceBusException('cURL initialization failed for ' . $method . ' ' . $url, 0);
        }

        $responseHeaders = [];

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        // Safety cap only: the server always answers first
        // (long-poll is bounded by the ?timeout= query parameter).
        curl_setopt($handle, CURLOPT_TIMEOUT, 120);
        curl_setopt(
            $handle,
            CURLOPT_HEADERFUNCTION,
            function (\CurlHandle $curl, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);

                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);

                    if ($name !== '') {
                        $responseHeaders[$name] = $value;
                    }
                }

                return $length;
            }
        );

        if ($body !== '') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);

        if ($responseBody === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new ServiceBusException('cURL request failed: ' . $error, 0);
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new HttpResponse($statusCode, $responseHeaders, (string) $responseBody);
    }
}
