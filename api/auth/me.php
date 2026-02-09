<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/auth.php';
require __DIR__ . '/../../config/db.php';

// ⚠️ Mesmo segredo do login.php
$JWT_SECRET = 'troque-por-um-segredo-muito-forte-aqui';

$claims = require_auth($JWT_SECRET);

echo json_encode([
  'user' => [
    'id'   => (int)($claims['sub'] ?? 0),
    'cpf'  => (string)($claims['cpf'] ?? ''),
    'nome' => (string)($claims['nome'] ?? ''),
  ]
]);
