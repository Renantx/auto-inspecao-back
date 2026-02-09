<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

function env(string $key): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        throw new RuntimeException("Variável {$key} não definida");
    }
    return $value;
}

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST'),
        env('DB_PORT'),
        env('DB_NAME')
    );

    $pdo = new PDO(
        $dsn,
        env('DB_USER'),
        env('DB_PASS'),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

} catch (Throwable $e) {
    error_log('DB ERROR: ' . $e->getMessage());
    http_response_code(500);
    die('Erro interno.');
}
