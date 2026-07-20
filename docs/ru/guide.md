# Руководство VediSMM для Drupal

VediSMM для Drupal добавляет защищённый permission workflow для отправки материалов Drupal 11 в VediSMM.

Модуль хранит токен VediSMM в State API, а не в экспортируемой конфигурации. Действие по умолчанию создаёт черновик VediSMM; публикация запускается только явным действием и защищена permission и CSRF.
