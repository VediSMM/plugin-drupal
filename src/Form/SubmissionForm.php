<?php

declare(strict_types=1);

namespace Drupal\vedismm\Form;

final class SubmissionForm
{
    public static function permission(): string
    {
        return 'send content to vedismm';
    }

    /** @return array<string,mixed> */
    public static function trackingElements(): array
    {
        $selector = ':input[name="tracking[shorten_links]"]';

        return [
            '#type' => 'fieldset',
            '#title' => self::translate('Tracking links'),
            '#tree' => true,
            'shorten_links' => [
                '#type' => 'checkbox',
                '#title' => self::translate('Shorten links'),
                '#description' => self::translate('Create a separate short link automatically for each target network.'),
                '#default_value' => false,
                '#return_value' => 1,
            ],
            'add_source' => [
                '#type' => 'checkbox',
                '#title' => self::translate('Add network source'),
                '#description' => self::translate('Available only with shortening. If utm_source is absent, add utm_source=<network> and preserve existing utm_term. If utm_source exists, preserve it and replace or add one utm_term=<network>.'),
                '#default_value' => false,
                '#return_value' => 1,
                '#states' => [
                    'disabled' => [
                        $selector => ['checked' => false],
                    ],
                ],
            ],
        ];
    }

    private static function translate(string $source): mixed
    {
        $class = '\\Drupal\\Core\\StringTranslation\\TranslatableMarkup';

        return class_exists($class) ? new $class($source) : $source;
    }
}
