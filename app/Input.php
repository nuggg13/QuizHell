<?php
declare(strict_types=1);

final class Input
{
    public static function text(mixed $value, int $max, bool $required = true): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            throw new HttpError(422, 'Format teks tidak valid.');
        }
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $max) {
            throw new HttpError(422, 'Isian kosong atau terlalu panjang.');
        }
        return $value;
    }

    public static function integer(mixed $value, int $min = 0, int $max = PHP_INT_MAX, bool $form = false): int
    {
        if ($form && is_string($value) && preg_match('/\A[0-9]+\z/D', $value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new HttpError(422, 'Bilangan tidak valid.');
        }
        return $value;
    }

    public static function token(mixed $value, int $length = 64): string
    {
        if (!is_string($value) || strlen($value) !== $length || !ctype_xdigit($value) || strtolower($value) !== $value) {
            throw new HttpError(422, 'Token tidak valid.');
        }
        return $value;
    }

    public static function password(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 72 || str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8')) {
            throw new HttpError(422, 'Format password tidak valid.');
        }
        return $value;
    }
}
