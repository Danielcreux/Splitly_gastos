<?php
declare(strict_types=1);

/**
 * Convierte importes escritos con coma o punto decimal.
 * No admite separadores de miles para evitar interpretaciones ambiguas.
 */
function parseLocalizedDecimal(mixed $value): float|false
{
    if (is_int($value) || is_float($value)) {
        $number = (float) $value;
        return is_finite($number) ? $number : false;
    }

    if (!is_string($value)) {
        return false;
    }

    $normalized = trim($value);
    if (!preg_match('/^\d+(?:[.,]\d{1,2})?$/D', $normalized)) {
        return false;
    }

    $number = (float) str_replace(',', '.', $normalized);
    return is_finite($number) ? $number : false;
}
