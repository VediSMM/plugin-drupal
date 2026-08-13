<?php

declare(strict_types=1);

namespace Drupal\vedismm\Service;

use RuntimeException;

final class VediSMMGatewayFactory
{
    public static function create(
        object $state,
        object $configOrTransport,
        ?object $httpClient = null,
    ): VediSMMGateway
    {
        $token = method_exists($state, 'get')
            ? trim((string) $state->get('vedismm.api_token', ''))
            : '';
        if ($token === '') {
            throw new RuntimeException('vedismm_token_not_configured');
        }

        if ($configOrTransport instanceof DrupalTransport) {
            return new VediSMMGateway($token, $configOrTransport);
        }
        if ($httpClient === null || !method_exists($configOrTransport, 'get')) {
            throw new RuntimeException('vedismm_transport_not_configured');
        }

        $config = $configOrTransport->get('vedismm.settings');
        $baseUrl = is_object($config) && method_exists($config, 'get')
            ? trim((string) $config->get('api_base_url'))
            : '';
        if ($baseUrl === '') {
            $baseUrl = 'https://vedismm.ru/api/v1';
        }

        return new VediSMMGateway($token, new DrupalTransport($httpClient, rtrim($baseUrl, '/')));
    }
}
