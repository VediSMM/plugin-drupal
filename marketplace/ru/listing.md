# VediSMM для Drupal

Отправляйте материалы Drupal в VediSMM как черновики или явные задания на публикацию.

Модуль требует аккаунт VediSMM и выполняет серверные запросы к `https://vedismm.ru/api/v1`. API-токен хранится в Drupal State API, не попадает в configuration export, действия защищены permission `send content to vedismm`, CSRF-проверкой и стабильными idempotency keys.
