<?php

declare(strict_types=1);

namespace App\core;

final class Validator
{
    public static function email(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public static function required(string $value): bool
    {
        return trim($value) !== '';
    }

    public static function minLength(string $value, int $size): bool
    {
        return mb_strlen(trim($value)) >= $size;
    }

    public static function maxLength(string $value, int $size): bool
    {
        return mb_strlen(trim($value)) <= $size;
    }

    public static function integer(mixed $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return false;
        }
        $int = (int) $value;
        return $int >= $min && $int <= $max;
    }

    public static function inArray(string $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    public static function slug(string $value): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
    }

    public static function url(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_URL);
    }
}
