<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$result = [
  'step' => [],
  'php_version' => PHP_VERSION,
];

try {
  $result['step'][] = '1: cors.php';
  require_once __DIR__ . '/../_core/cors.php';

  $result['step'][] = '2: jwt.php';
  require_once __DIR__ . '/../_core/jwt.php';

  $result['step'][] = '3: db.php';
  require_once __DIR__ . '/../../config/db.php';

  $result['step'][] = '4: pdo check';
  $result['pdo_ok'] = isset($pdo) && ($pdo instanceof PDO);

  $result['step'][] = '5: test query';
  $stmt = $pdo->query('SELECT 1 as ok');
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $result['db_query'] = $row;

  // testa se a função jwt_sign existe
  $result['jwt_sign_exists'] = function_exists('jwt_sign');

  echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
    'steps' => $result['step'],
    'php_version' => PHP_VERSION
  ], JSON_UNESCAPED_UNICODE);
}
