# VediSMM for Drupal

Send Drupal content entities to VediSMM as drafts or explicit publish jobs.

The module requires a VediSMM account and sends server-side requests to `https://vedismm.ru/api/v1`. It keeps the API token in Drupal State API, excludes it from configuration export, checks the `send content to vedismm` permission, validates CSRF, and uses stable idempotency keys.
