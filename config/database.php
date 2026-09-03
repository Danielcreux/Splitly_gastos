<?php
declare(strict_types=1);

/**
 * Configuración PDO.
 * Las variables de entorno tienen prioridad; los valores por defecto son
 * los habituales de Laragon en local. Nunca publiques una contraseña aquí.
 */
$app = require __DIR__ . '/app.php';
$environmentValues = [
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT'),
    'name' => getenv('DB_NAME'),
    'user' => getenv('DB_USER'),
    'pass' => getenv('DB_PASS'),
];

if ($app['production']) {
    foreach (['host', 'name', 'user', 'pass'] as $required) {
        if ($environmentValues[$required] === false || $environmentValues[$required] === '') {
            throw new RuntimeException('Falta configuración obligatoria de base de datos.');
        }
    }
    if (strtolower((string) $environmentValues['user']) === 'root') {
        throw new RuntimeException('La aplicación no debe conectarse como root en producción.');
    }
}

return [
    'host' => $environmentValues['host'] ?: '127.0.0.1',
    'port' => $environmentValues['port'] ?: '3306',
    'name' => $environmentValues['name'] ?: 'splitly',
    'user' => $environmentValues['user'] ?: 'root',
    'pass' => $environmentValues['pass'] !== false ? $environmentValues['pass'] : '',
    'charset' => 'utf8mb4',
];
