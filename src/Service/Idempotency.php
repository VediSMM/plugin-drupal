<?php

declare(strict_types=1);

namespace Drupal\vedismm\Service;

final class Idempotency
{
    public static function forAction(string $installationId, string $entityType, int $entityId, int $revision, string $action): string
    {
        return sprintf('cms:%s:%s:%d:%d:%s', self::segment($installationId), self::segment($entityType), $entityId, $revision, self::segment($action));
    }

    private static function segment(string $value): string
    {
        $segment = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($value)), '-');
        return $segment === '' ? 'unknown' : $segment;
    }
}
