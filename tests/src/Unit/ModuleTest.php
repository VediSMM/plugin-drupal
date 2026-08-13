<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Drupal\\vedismm\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../../../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$passed = 0;
$failed = 0;

function drupal_check(string $name, bool $condition, mixed $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ✓ {$name}\n";
        return;
    }
    $failed++;
    echo "  ✗ FAIL: {$name}\n";
    if ($detail !== null) {
        echo '    - ' . json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

function drupal_finish(): never
{
    global $passed, $failed;
    echo "\n----------------------------------------\n";
    echo "Passed: {$passed}, Failed: {$failed}\n";
    exit($failed > 0 ? 1 : 0);
}

$root = dirname(__DIR__, 3);
foreach (['vedismm.info.yml', 'vedismm.permissions.yml', 'vedismm.routing.yml', 'vedismm.services.yml', 'config/schema/vedismm.schema.yml'] as $file) {
    drupal_check("{$file} exists", is_file($root . '/' . $file));
}

$classes = [
    'Drupal\\vedismm\\Service\\ContentMapper',
    'Drupal\\vedismm\\Service\\VediSMMGateway',
    'Drupal\\vedismm\\Service\\SubmissionService',
    'Drupal\\vedismm\\Service\\Idempotency',
    'Drupal\\vedismm\\Form\\SettingsForm',
    'Drupal\\vedismm\\Form\\SubmissionForm',
];
foreach ($classes as $class) {
    drupal_check("{$class} exists", class_exists($class));
}
if (array_filter($classes, static fn (string $class): bool => !class_exists($class)) !== []) {
    drupal_finish();
}

$permissionYaml = (string) file_get_contents($root . '/vedismm.permissions.yml');
drupal_check('module declares send content permission',
    str_contains($permissionYaml, 'send content to vedismm'));

$mapper = 'Drupal\\vedismm\\Service\\ContentMapper';
$idempotency = 'Drupal\\vedismm\\Service\\Idempotency';
$settings = 'Drupal\\vedismm\\Form\\SettingsForm';
$gateway = 'Drupal\\vedismm\\Service\\VediSMMGateway';
$serviceClass = 'Drupal\\vedismm\\Service\\SubmissionService';
$submissionForm = 'Drupal\\vedismm\\Form\\SubmissionForm';

$mapped = $mapper::fromEntity([
    'id' => 42,
    'bundle' => 'article',
    'title' => '  Drupal &amp; VediSMM  ',
    'body' => '<p>Hello&nbsp;<strong>Drupal</strong></p>',
    'url' => 'https://example.test/node/42',
], ['account_ids' => ['101', '101'], 'group_ids' => ['201'], 'media_ids' => []]);
drupal_check('content entity mapper normalizes DraftInput',
    $mapped['title'] === 'Drupal & VediSMM'
    && $mapped['content'] === 'Hello Drupal'
    && $mapped['link'] === 'https://example.test/node/42'
    && $mapped['account_ids'] === [101]
    && $mapped['group_ids'] === [201]);
drupal_check('legacy Drupal mapping defaults tracking off',
    ($mapped['options']['tracking'] ?? null) === [
        'shorten_links' => false,
        'add_source' => false,
    ],
    $mapped);

$tracked = $mapper::fromEntity([], [], [
    'shorten_links' => '1',
    'add_source' => 1,
]);
drupal_check('Drupal mapping emits explicit booleans only under options.tracking',
    ($tracked['options'] ?? null) === [
        'tracking' => [
            'shorten_links' => true,
            'add_source' => true,
        ],
    ]
    && !array_key_exists('shorten_links', $tracked)
    && !array_key_exists('add_source', $tracked),
    $tracked);

$truthy = $mapper::fromEntity([], [], [
    'shorten_links' => 'true',
    'add_source' => 'on',
]);
drupal_check('Drupal mapping rejects truthy strings',
    ($truthy['options']['tracking'] ?? null) === [
        'shorten_links' => false,
        'add_source' => false,
    ],
    $truthy);

$dependent = $mapper::fromEntity([], [], [
    'shorten_links' => false,
    'add_source' => true,
]);
drupal_check('Drupal mapping forces source off without shortening',
    ($dependent['options']['tracking']['add_source'] ?? null) === false);
drupal_check('idempotency key uses content entity format',
    $idempotency::forAction('install-abc', 'node', 42, 7, 'draft') === 'cms:install-abc:node:42:7:draft');
drupal_check('token stays in State API and is absent from config export',
    $settings::saveToken('', 'saved-token') === 'saved-token'
    && $settings::saveToken('', 'saved-token', true) === null
    && !str_contains(json_encode($settings::exportConfig(['token' => 'secret-token']), JSON_THROW_ON_ERROR), 'secret-token'));

$calls = [];
$transport = static function (array $request) use (&$calls): array {
    $calls[] = $request;
    if ($request['path'] === '/posts') {
        return ['headers' => ['Request-Id' => 'fixture-draft', 'ETag' => '"v1"'], 'body' => ['data' => ['id' => 301, 'status' => 'draft', 'version' => 1]]];
    }
    if ($request['path'] === '/posts/301/publish') {
        return ['headers' => ['Request-Id' => 'fixture-publish'], 'body' => ['data' => ['id' => 401, 'status' => 'queued']]];
    }
    throw new RuntimeException('unexpected path');
};
$service = new $serviceClass(new $gateway('secret-token', $transport), 'install-abc');
$result = $service->submit(['id' => 42, 'type' => 'node', 'revision' => 7, 'title' => 'Title', 'body' => '<p>Body</p>', 'url' => 'https://example.test/node/42'], ['account_ids' => ['101'], 'group_ids' => [], 'media_ids' => []], [
    'has_permission' => true,
    'csrf_valid' => true,
    'action' => 'publish',
    'tracking' => ['shorten_links' => '1', 'add_source' => '1'],
]);
drupal_check('submission checks permission, CSRF, versioned publish and audit',
    $result['job_id'] === 401
    && $calls[0]['headers']['Idempotency-Key'] === 'cms:install-abc:node:42:7:draft'
    && $calls[1]['headers']['Idempotency-Key'] === 'cms:install-abc:node:42:7:publish'
    && $calls[1]['body'] === ['version' => 1]
    && $result['audit']['request_id'] === 'fixture-publish');
drupal_check('Drupal gateway body contains only nested tracking and unchanged URLs',
    ($calls[0]['body']['options']['tracking'] ?? null) === [
        'shorten_links' => true,
        'add_source' => true,
    ]
    && !array_key_exists('shorten_links', $calls[0]['body'])
    && !array_key_exists('add_source', $calls[0]['body'])
    && $calls[0]['body']['link'] === 'https://example.test/node/42'
    && $calls[0]['body']['content'] === 'Body',
    $calls[0]['body'] ?? null);

$trackingElements = method_exists($submissionForm, 'trackingElements')
    ? $submissionForm::trackingElements()
    : [];
drupal_check('Drupal Form API exposes accessible dependent tracking checkboxes',
    ($trackingElements['#type'] ?? null) === 'fieldset'
    && ($trackingElements['#tree'] ?? null) === true
    && ($trackingElements['shorten_links']['#type'] ?? null) === 'checkbox'
    && ($trackingElements['shorten_links']['#default_value'] ?? null) === false
    && ($trackingElements['add_source']['#type'] ?? null) === 'checkbox'
    && ($trackingElements['add_source']['#default_value'] ?? null) === false
    && ($trackingElements['add_source']['#states']['disabled'][':input[name="tracking[shorten_links]"]']['checked'] ?? null) === false
    && str_contains((string) ($trackingElements['add_source']['#description'] ?? ''), 'utm_source')
    && str_contains((string) ($trackingElements['add_source']['#description'] ?? ''), 'utm_term'),
    $trackingElements);

foreach ([['has_permission' => false, 'csrf_valid' => true, 'code' => 'vedismm_permission_denied'], ['has_permission' => true, 'csrf_valid' => false, 'code' => 'vedismm_invalid_csrf']] as $case) {
    try {
        $service->submit(['id' => 42, 'type' => 'node', 'revision' => 7], [], ['action' => 'draft'] + $case);
        drupal_check($case['code'], false);
    } catch (RuntimeException $exception) {
        drupal_check($case['code'], $exception->getMessage() === $case['code']);
    }
}

drupal_finish();
