<?php

declare(strict_types=1);

namespace Drupal\vedismm\Service;

use RuntimeException;

final class DrupalTransport
{
    public function __construct(
        private readonly object $httpClient,
        private readonly string $baseUrl = 'https://vedismm.ru/api/v1',
    ) {
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function __invoke(array $request): array
    {
        $baseUrl = getenv('VEDISMM_API_BASE_URL') ?: $this->baseUrl;
        $path = '/' . ltrim((string) ($request['path'] ?? ''), '/');
        $response = $this->httpClient->request(
            (string) ($request['method'] ?? 'POST'),
            rtrim($baseUrl, '/') . $path,
            [
                'headers' => is_array($request['headers'] ?? null) ? $request['headers'] : [],
                'json' => is_array($request['body'] ?? null) ? $request['body'] : [],
                'http_errors' => false,
                'timeout' => 15,
            ],
        );
        $status = (int) $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('vedismm_http_error_' . $status);
        }
        $decoded = json_decode((string) $response->getBody(), true);

        return [
            'headers' => self::headers($response->getHeaders()),
            'body' => is_array($decoded) ? $decoded : [],
        ];
    }

    /** @return array<string,string> */
    private static function headers(mixed $headers): array
    {
        if (!is_array($headers)) {
            return [];
        }
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[(string) $name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $normalized;
    }
}
