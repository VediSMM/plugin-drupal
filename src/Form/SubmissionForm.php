<?php

declare(strict_types=1);

namespace Drupal\vedismm\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vedismm\Service\SubmissionService;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SubmissionForm extends FormBase
{
    public function __construct(
        private readonly SubmissionService $submissionService,
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('vedismm.submission'),
            $container->get('entity_type.manager'),
        );
    }

    public function getFormId(): string
    {
        return 'vedismm_submission_form';
    }

    /** @return array<string,mixed> */
    public function buildForm(array $form, FormStateInterface $form_state, ?string $entity_type = null, string|int|null $entity_id = null): array
    {
        $form_state->set('vedismm_entity_type', $entity_type ?? 'node');
        $form_state->set('vedismm_entity_id', $entity_id === null ? '' : (string) $entity_id);

        $form['tracking'] = self::trackingElements();
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Send to VediSMM'),
            '#button_type' => 'primary',
        ];

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $entityType = (string) ($form_state->get('vedismm_entity_type') ?? 'node');
        $entityId = (string) ($form_state->get('vedismm_entity_id') ?? '');
        $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
        if (!is_object($entity)) {
            $this->messenger()->addError($this->t('The selected content could not be loaded.'));
            return;
        }

        $tracking = $form_state->getValue('tracking', []);
        $result = $this->submissionService->submit(
            $this->entityValues($entity, $entityType),
            ['account_ids' => [], 'group_ids' => [], 'media_ids' => []],
            [
                'has_permission' => true,
                'csrf_valid' => true,
                'action' => 'draft',
                'tracking' => is_array($tracking) ? $tracking : [],
            ],
        );
        $form_state->set('vedismm_result', $result);
        $this->messenger()->addStatus($this->t('Content sent to VediSMM.'));
    }

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

    /** @return array<string,mixed> */
    private function entityValues(object $entity, string $entityType): array
    {
        $body = '';
        if (method_exists($entity, 'hasField') && $entity->hasField('body') && method_exists($entity, 'get')) {
            $field = $entity->get('body');
            if (is_object($field) && isset($field->value) && is_string($field->value)) {
                $body = $field->value;
            } elseif (is_object($field) && method_exists($field, 'getValue')) {
                $values = $field->getValue();
                $body = is_array($values) && is_string($values[0]['value'] ?? null) ? $values[0]['value'] : '';
            }
        }

        $url = null;
        if (method_exists($entity, 'toUrl')) {
            $generatedUrl = $entity->toUrl('canonical', ['absolute' => true]);
            if (is_object($generatedUrl) && method_exists($generatedUrl, 'toString')) {
                $url = $generatedUrl->toString();
            }
        }

        return [
            'id' => method_exists($entity, 'id') ? (int) $entity->id() : 0,
            'type' => $entityType,
            'bundle' => method_exists($entity, 'bundle') ? (string) $entity->bundle() : $entityType,
            'revision' => method_exists($entity, 'getRevisionId') ? (int) ($entity->getRevisionId() ?? 1) : 1,
            'title' => method_exists($entity, 'label') ? (string) $entity->label() : '',
            'body' => $body,
            'url' => is_string($url) ? $url : null,
        ];
    }
}
