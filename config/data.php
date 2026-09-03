<?php
// Las colecciones se cargan exclusivamente desde MySQL en config/bootstrap.php.
$groups = [];
$expenses = [];
$balances = [];
$activity = [];

function money(float $amount): string
{
    return number_format(abs($amount), 2, ',', '.') . ' €';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
