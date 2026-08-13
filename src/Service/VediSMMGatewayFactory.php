<?php

declare(strict_types=1);

namespace Drupal\vedismm\Service;

final class VediSMMGatewayFactory
{
    public static function create(object $state, DrupalTransport $transport): VediSMMGateway
    {
        $token = method_exists($state, 'get')
            ? (string) $state->get('vedismm.api_token', '')
            : '';

        return new VediSMMGateway($token, $transport);
    }
}
