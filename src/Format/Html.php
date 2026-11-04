<?php

declare(strict_types=1);

namespace SearPHP\Format;

final class Html
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url(string $value): string
    {
        if (preg_match('~^https?://~i', $value) !== 1) {
            return '';
        }

        return self::e(str_replace(['<', '>', '"'], ['%3C', '%3E', '%22'], $value));
    }
}
