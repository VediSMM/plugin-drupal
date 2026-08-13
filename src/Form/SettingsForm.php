<?php

declare(strict_types=1);

namespace Drupal\vedismm\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SettingsForm extends FormBase
{
    private const DEFAULT_API_BASE_URL = 'https://vedismm.ru/api/v1';

    public function __construct(
        private readonly ?StateInterface $state = null,
        private readonly ?ConfigFactoryInterface $settingsConfigFactory = null,
    ) {
    }

    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('state'),
            $container->get('config.factory'),
        );
    }

    public function getFormId(): string
    {
        return 'vedismm_settings';
    }

    /** @param array<string,mixed> $form @return array<string,mixed> */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        $settings = $this->settingsConfigFactory?->get('vedismm.settings');
        $configuredBaseUrl = is_object($settings) && method_exists($settings, 'get')
            ? $settings->get('api_base_url')
            : null;
        $hasToken = trim((string) $this->state?->get('vedismm.api_token', '')) !== '';

        $form['api_base_url'] = [
            '#type' => 'url',
            '#title' => $this->t('VediSMM API base URL'),
            '#default_value' => is_string($configuredBaseUrl) && $configuredBaseUrl !== ''
                ? $configuredBaseUrl
                : self::DEFAULT_API_BASE_URL,
            '#required' => true,
            '#description' => $this->t('Use an absolute HTTP or HTTPS URL. The default is the public VediSMM API.'),
        ];
        $form['api_token'] = [
            '#type' => 'password',
            '#title' => $this->t('VediSMM API token'),
            '#description' => $hasToken
                ? $this->t('A token is saved. Leave this field blank to keep it unchanged.')
                : $this->t('Enter the server-side token used to authenticate VediSMM API requests.'),
            '#attributes' => ['autocomplete' => 'new-password'],
        ];
        $form['clear_api_token'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Remove the saved API token'),
            '#default_value' => false,
            '#return_value' => '1',
            '#description' => $this->t('Requests fail closed until a new token is saved.'),
        ];
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save configuration'),
            '#button_type' => 'primary',
        ];

        return $form;
    }

    /** @param array<string,mixed> $form */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        parent::validateForm($form, $form_state);
        if (self::normalizeBaseUrl((string) $form_state->getValue('api_base_url', '')) === null) {
            $form_state->setErrorByName(
                'api_base_url',
                $this->t('Enter an absolute HTTP or HTTPS API base URL.'),
            );
        }
    }

    /** @param array<string,mixed> $form */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        if ($this->state === null || $this->settingsConfigFactory === null) {
            return;
        }

        $submittedToken = (string) $form_state->getValue('api_token', '');
        $existingToken = $this->state->get('vedismm.api_token');
        $removeToken = self::enabled($form_state->getValue('clear_api_token', false));
        $token = self::saveToken(
            $submittedToken,
            is_string($existingToken) ? $existingToken : null,
            $removeToken,
        );

        if ($token === null) {
            $this->state->delete('vedismm.api_token');
        } elseif (trim($submittedToken) !== '') {
            $this->state->set('vedismm.api_token', $token);
        }

        $baseUrl = self::normalizeBaseUrl((string) $form_state->getValue('api_base_url', ''));
        if ($baseUrl !== null) {
            $this->settingsConfigFactory
                ->getEditable('vedismm.settings')
                ->set('api_base_url', $baseUrl)
                ->save();
        }

        $this->messenger()->addStatus($this->t('The VediSMM configuration has been saved.'));
    }

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
        return ['api_base_url' => self::DEFAULT_API_BASE_URL];
    }

    private static function enabled(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private static function normalizeBaseUrl(string $value): ?string
    {
        $value = trim($value);
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = (string) parse_url($value, PHP_URL_HOST);
        if (
            !in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || parse_url($value, PHP_URL_USER) !== null
            || parse_url($value, PHP_URL_PASS) !== null
            || parse_url($value, PHP_URL_QUERY) !== null
            || parse_url($value, PHP_URL_FRAGMENT) !== null
        ) {
            return null;
        }

        return rtrim($value, '/');
    }
}
