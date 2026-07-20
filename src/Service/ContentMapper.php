<?php

declare(strict_types=1);

namespace Drupal\vedismm\Service;

final class ContentMapper
{
    /** @param array<string,mixed> $entity @param array<string,array<int,mixed>> $targets @return array<string,mixed> */
    public static function fromEntity(array $entity, array $targets): array
    {
        return [
            'title' => self::title((string) ($entity['title'] ?? '')),
            'content' => self::text((string) ($entity['body'] ?? '')),
            'link' => self::url(is_string($entity['url'] ?? null) ? $entity['url'] : null),
            'account_ids' => self::ids($targets['account_ids'] ?? []),
            'group_ids' => self::ids($targets['group_ids'] ?? []),
            'media_ids' => self::ids($targets['media_ids'] ?? []),
        ];
    }

    private static function title(string $value): string
    {
        return mb_substr(self::text($value), 0, 190);
    }

    private static function text(string $value): string
    {
        $withBreaks = preg_replace('/<\s*\/?(?:p|br|div|h[1-6]|li|ul|ol|blockquote)[^>]*>/i', ' ', $value);
        $decoded = html_entity_decode(strip_tags((string) $withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xc2\xa0", ' ', $decoded);
        return trim((string) preg_replace('/\s+/u', ' ', $decoded));
    }

    private static function url(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        if ($trimmed === '' || !is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }
        $canonical = strtolower($scheme) . substr($trimmed, strlen($scheme));
        return filter_var($canonical, FILTER_VALIDATE_URL) === false ? null : $canonical;
    }

    /** @param array<int,mixed> $values @return array<int,int> */
    private static function ids(array $values): array
    {
        $seen = [];
        $ids = [];
        foreach ($values as $value) {
            if (is_string($value) && ctype_digit($value)) {
                $value = (int) $value;
            }
            if (!is_int($value) || $value <= 0 || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $ids[] = $value;
        }
        return $ids;
    }
}
