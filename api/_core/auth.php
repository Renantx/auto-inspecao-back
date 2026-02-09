<?php
declare(strict_types=1);

require_once __DIR__ . '/jwt.php';

function get_bearer_token(): ?string {
  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
  if (!$auth) return null;

  if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
    return $m[1];
  }
  return null;
}

function require_auth(string $secret): array {
  $token = get_bearer_token();
  if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Token ausente']);
    exit;
  }

  try {
    return jwt_verify($token, $secret);
  } catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido ou expirado']);
    exit;
  }
}
