<?php

declare(strict_types=1);

namespace Drupal\vedismm\Service;

use RuntimeException;

final class SubmissionService
{
    public function __construct(private readonly VediSMMGateway $gateway, private readonly string $installationId = 'drupal')
    {
    }

    /** @param array<string,mixed> $entity @param array<string,array<int,mixed>> $targets @param array<string,mixed> $context @return array<string,mixed> */
    public function submit(array $entity, array $targets, array $context): array
    {
        if (($context['has_permission'] ?? false) !== true) {
            throw new RuntimeException('vedismm_permission_denied');
        }
        if (($context['csrf_valid'] ?? false) !== true) {
            throw new RuntimeException('vedismm_invalid_csrf');
        }

        $entityType = (string) ($entity['type'] ?? 'node');
        $entityId = (int) ($entity['id'] ?? 0);
        $revision = (int) ($entity['revision'] ?? 1);
        $action = (string) ($context['action'] ?? 'draft');
        $draftKey = Idempotency::forAction($this->installationId, $entityType, $entityId, $revision, 'draft');
        $draft = $this->gateway->post('/posts', ['Idempotency-Key' => $draftKey], ContentMapper::fromEntity($entity, $targets));
        $draftData = is_array($draft['body']['data'] ?? null) ? $draft['body']['data'] : [];
        $postId = (int) ($draftData['id'] ?? 0);
        $version = (int) ($draftData['version'] ?? 1);

        if ($action === 'publish') {
            $publishKey = Idempotency::forAction($this->installationId, $entityType, $entityId, $revision, 'publish');
            $response = $this->gateway->post("/posts/{$postId}/publish", ['Idempotency-Key' => $publishKey], ['version' => $version]);
            $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
            return $this->result($entityType, $entityId, $revision, $action, $response, $postId, (int) ($data['id'] ?? 0));
        }

        return $this->result($entityType, $entityId, $revision, 'draft', $draft, $postId, null);
    }

    /** @param array{headers:array<string,string>,body:array<string,mixed>} $response @return array<string,mixed> */
    private function result(string $entityType, int $entityId, int $revision, string $action, array $response, int $postId, ?int $jobId): array
    {
        return [
            'post_id' => $postId,
            'job_id' => $jobId,
            'audit' => [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'revision' => $revision,
                'action' => $action,
                'request_id' => $response['headers']['Request-Id'] ?? null,
            ],
        ];
    }
}
