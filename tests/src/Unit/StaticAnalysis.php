<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$files = [];
foreach (['src', 'tests'] as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
sort($files, SORT_STRING);
foreach ($files as $file) {
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file), $output, $exit);
    if ($exit !== 0) {
        echo "Lint failed: {$file}\n";
        exit(1);
    }
}

$form = (string) file_get_contents($root . '/src/Form/SubmissionForm.php');
$mapper = (string) file_get_contents($root . '/src/Service/ContentMapper.php');
$translation = (string) file_get_contents($root . '/translations/vedismm.ru.po');
$englishGuide = (string) file_get_contents($root . '/docs/en/guide.md');
$russianGuide = (string) file_get_contents($root . '/docs/ru/guide.md');

$contractChecks = [
    'native Drupal checkbox controls' => substr_count($form, "'#type' => 'checkbox'") >= 2,
    'native Drupal dependency states' => str_contains($form, "'#states'") && str_contains($form, "tracking[shorten_links]"),
    'native Form API CSRF convention' => !str_contains($form, "'#token'") && !str_contains($form, 'csrf_token'),
    'nested tracking request shape' => str_contains($mapper, "'tracking'"),
    'strict Drupal checkbox normalization' => str_contains($mapper, "=== '1'"),
    'Russian tracking translation' => str_contains($translation, 'Сокращать ссылки') && str_contains($translation, 'Добавлять источник площадки'),
    'English exact UTM docs' => str_contains($englishGuide, 'utm_source') && str_contains($englishGuide, 'utm_term'),
    'Russian exact UTM docs' => str_contains($russianGuide, 'utm_source') && str_contains($russianGuide, 'utm_term'),
    'no plugin URL rewriting' => !str_contains($mapper, 'go.vedismm.ru'),
    'no generated-link state' => !str_contains($form . $mapper, 'generated_link'),
];

foreach ($contractChecks as $name => $passed) {
    if (!$passed) {
        echo "Static contract check failed: {$name}\n";
        exit(1);
    }
}
echo 'Static analysis checked ' . count($files) . " PHP files.\n";
