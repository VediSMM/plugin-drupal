<?php

declare(strict_types=1);

if (!interface_exists('Symfony\\Component\\DependencyInjection\\ContainerInterface')) {
    eval(<<<'PHP'
namespace Symfony\Component\DependencyInjection;
interface ContainerInterface
{
    public function get(string $id): mixed;
}
PHP);
}

if (!interface_exists('Drupal\\Core\\Form\\FormStateInterface')) {
    eval(<<<'PHP'
namespace Drupal\Core\Form;
interface FormStateInterface
{
    public function getValue(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): static;
    public function get(string $key): mixed;
}
PHP);
}

if (!interface_exists('Drupal\\Core\\Form\\FormInterface')) {
    eval(<<<'PHP'
namespace Drupal\Core\Form;
interface FormInterface
{
    public function getFormId();
    public function buildForm(array $form, FormStateInterface $form_state);
    public function validateForm(array &$form, FormStateInterface $form_state);
    public function submitForm(array &$form, FormStateInterface $form_state);
}
PHP);
}

if (!class_exists('Drupal\\Core\\Form\\FormBase')) {
    eval(<<<'PHP'
namespace Drupal\Core\Form;
use Symfony\Component\DependencyInjection\ContainerInterface;
abstract class FormBase implements FormInterface
{
    public static function create(ContainerInterface $container) { return new static(); }
    public function validateForm(array &$form, FormStateInterface $form_state) {}
    protected function t($string, array $args = [], array $options = []) { return $string; }
    protected function messenger() { return $GLOBALS['drupal_test_messenger']; }
}
PHP);
}

if (!interface_exists('Drupal\\Core\\Entity\\EntityTypeManagerInterface')) {
    eval(<<<'PHP'
namespace Drupal\Core\Entity;
interface EntityTypeManagerInterface
{
    public function getStorage($entity_type_id);
}
PHP);
}

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

$formState = new class implements \Drupal\Core\Form\FormStateInterface {
    /** @var array<string,mixed> */
    public array $values = [];
    /** @var array<string,mixed> */
    public array $storage = [];

    public function getValue(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): static
    {
        $this->storage[$key] = $value;
        return $this;
    }

    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }
};
$entity = new class {
    public function id(): int { return 42; }
    public function getRevisionId(): int { return 7; }
    public function bundle(): string { return 'article'; }
    public function label(): string { return 'Routed Drupal article'; }
    public function hasField(string $name): bool { return $name === 'body'; }
    public function get(string $name): object
    {
        return new class {
            /** @return array<int,array{value:string}> */
            public function getValue(): array { return [['value' => '<p>Routed body</p>']]; }
        };
    }
    public function toUrl(string $rel, array $options): object
    {
        return new class {
            public function toString(): string { return 'https://example.test/node/42'; }
        };
    }
};
$entityTypeManager = new class($entity) implements \Drupal\Core\Entity\EntityTypeManagerInterface {
    public function __construct(private readonly object $entity) {}
    public function getStorage($entity_type_id): object
    {
        $entity = $this->entity;
        return new class($entity) {
            public function __construct(private readonly object $entity) {}
            public function load(mixed $id): ?object { return (string) $id === '42' ? $this->entity : null; }
        };
    }
};
$container = new class($service, $entityTypeManager) implements \Symfony\Component\DependencyInjection\ContainerInterface {
    public function __construct(private readonly object $service, private readonly object $entityTypeManager) {}
    public function get(string $id): mixed
    {
        return match ($id) {
            'vedismm.submission' => $this->service,
            'entity_type.manager' => $this->entityTypeManager,
            default => throw new RuntimeException("Unknown test service: {$id}"),
        };
    }
};
$GLOBALS['drupal_test_messenger'] = new class {
    /** @var array<int,string> */
    public array $statuses = [];
    public function addStatus(mixed $message): void { $this->statuses[] = (string) $message; }
};

drupal_check('submission route class is a native Form API form',
    is_subclass_of($submissionForm, \Drupal\Core\Form\FormBase::class)
    && is_subclass_of($submissionForm, \Drupal\Core\Form\FormInterface::class));
$routedForm = method_exists($submissionForm, 'create') ? $submissionForm::create($container) : null;
drupal_check('Drupal container can instantiate the routed submission form',
    $routedForm instanceof \Drupal\Core\Form\FormInterface);
$builtForm = $routedForm instanceof \Drupal\Core\Form\FormInterface
    ? $routedForm->buildForm([], $formState, 'node', '42')
    : [];
drupal_check('routed form builds tracking controls and a native submit action',
    ($builtForm['tracking']['#type'] ?? null) === 'fieldset'
    && ($builtForm['actions']['submit']['#type'] ?? null) === 'submit'
    && $formState->get('vedismm_entity_type') === 'node'
    && $formState->get('vedismm_entity_id') === '42',
    $builtForm);

if ($routedForm instanceof \Drupal\Core\Form\FormInterface) {
    $formState->values['tracking'] = ['shorten_links' => '1', 'add_source' => '1'];
    $submissionArray = [];
    $routedForm->submitForm($submissionArray, $formState);
}
$routedRequest = $calls[2] ?? [];
drupal_check('native form submission loads the routed entity and reaches the gateway',
    ($routedRequest['path'] ?? null) === '/posts'
    && ($routedRequest['body']['title'] ?? null) === 'Routed Drupal article'
    && ($routedRequest['body']['content'] ?? null) === 'Routed body'
    && ($routedRequest['body']['link'] ?? null) === 'https://example.test/node/42'
    && ($routedRequest['body']['options']['tracking'] ?? null) === [
        'shorten_links' => true,
        'add_source' => true,
    ]
    && !array_key_exists('shorten_links', $routedRequest['body'] ?? [])
    && !array_key_exists('add_source', $routedRequest['body'] ?? []),
    $routedRequest);
drupal_check('native Form API submission reports success without a custom CSRF field',
    ($formState->get('vedismm_result')['post_id'] ?? null) === 301
    && $GLOBALS['drupal_test_messenger']->statuses === ['Content sent to VediSMM.']
    && !array_key_exists('token', $builtForm)
    && !array_key_exists('csrf_token', $builtForm));

foreach ([['has_permission' => false, 'csrf_valid' => true, 'code' => 'vedismm_permission_denied'], ['has_permission' => true, 'csrf_valid' => false, 'code' => 'vedismm_invalid_csrf']] as $case) {
    try {
        $service->submit(['id' => 42, 'type' => 'node', 'revision' => 7], [], ['action' => 'draft'] + $case);
        drupal_check($case['code'], false);
    } catch (RuntimeException $exception) {
        drupal_check($case['code'], $exception->getMessage() === $case['code']);
    }
}

drupal_finish();
