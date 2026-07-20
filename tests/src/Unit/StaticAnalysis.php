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
echo 'Static analysis checked ' . count($files) . " PHP files.\n";
