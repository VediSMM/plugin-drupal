<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
header('Content-Type: application/json');
header('Request-Id: fixture-drupal-smoke');
if ($path === '/api/v1/posts') {
    http_response_code(201);
    header('ETag: "v1"');
    echo json_encode(['data' => ['id' => 301, 'status' => 'draft', 'version' => 1]], JSON_THROW_ON_ERROR);
    return;
}
http_response_code(401);
echo json_encode(['code' => 'unauthorized'], JSON_THROW_ON_ERROR);
