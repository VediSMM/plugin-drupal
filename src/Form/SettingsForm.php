<?php

declare(strict_types=1);

namespace Drupal\vedismm\Form;

final class SettingsForm
{
    public static function saveToken(string $submitted, ?string $existing, bool $remove = false): ?string
    {
        if ($remove) {
            return null;
        }

        $trimmed = trim($submitted);
        return $trimmed === '' ? $existing : $trimmed;
    }

    /** @param array<string,mixed> $stateValues @return array<string,string> */
    public static function exportConfig(array $stateValues): array
    {
        return ['api_base_url' => 'https://vedismm.ru/api/v1'];
    }
}
