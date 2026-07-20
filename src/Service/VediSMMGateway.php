<?php

declare(strict_types=1);

namespace Drupal\vedismm\Service;

use Closure;
use RuntimeException;
use Throwable;

final class VediSMMGateway
{
    private Closure $transport;

    /** @param callable(array<string,mixed>): array<string,mixed> $transport */
    public function __construct(private readonly string $token = '', ?callable $transport = null)
    {
        $this->transport = Closure::fromCallable($transport ?? static fn (array $request): array => ['headers' => [], 'body' => []]);
    }

    /** @param array<string,mixed> $body @return array{headers:array<string,string>,body:array<string,mixed>} */
    public function post(string $path, array $headers, array $body): array
    {
        try {
            $response = ($this->transport)([
                'method' => 'POST',
                'path' => $path,
                'headers' => ['Authorization' => 'Bearer ' . $this->token, 'Content-Type' => 'application/json'] + $headers,
                'body' => $body,
            ]);
        } catch (Throwable $exception) {
            $redacted = (string) preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', str_replace($this->token, '[redacted]', $exception->getMessage()));
            throw new RuntimeException('vedismm_api_error: ' . $redacted, 0, $exception);
        }

        return [
            'headers' => is_array($response['headers'] ?? null) ? $response['headers'] : [],
            'body' => is_array($response['body'] ?? null) ? $response['body'] : [],
        ];
    }
}
