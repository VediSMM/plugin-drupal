<?php

declare(strict_types=1);

namespace Drupal\vedismm\Form;

final class SubmissionForm
{
    public static function permission(): string
    {
        return 'send content to vedismm';
    }
}
