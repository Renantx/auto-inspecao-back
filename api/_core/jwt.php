<?php
declare(strict_types=1);

function base64url_encode(string $data): string {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
  $remainder = strlen($data) % 4;
  if ($remainder) $data .= str_repeat('=', 4 - $remainder);
  return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

function jwt_sign(array $payload, string $secret, int $ttlSeconds = 3600): string {
  $header = ['typ' => 'JWT', 'alg' => 'HS256'];

  $now = time();
  $payload['iat'] = $payload['iat'] ?? $now;
  $payload['exp'] = $payload['exp'] ?? ($now + $ttlSeconds);

  $h = base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
  $p = base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));

  $sig = hash_hmac('sha256', "{$h}.{$p}", $secret, true);
  $s = base64url_encode($sig);

  return "{$h}.{$p}.{$s}";
}

function jwt_verify(string $token, string $secret): array {
  $parts = explode('.', $token);
  if (count($parts) !== 3) throw new RuntimeException('Token inválido');

  [$h, $p, $s] = $parts;
  $sigCheck = base64url_encode(hash_hmac('sha256', "{$h}.{$p}", $secret, true));
  if (!hash_equals($sigCheck, $s)) throw new RuntimeException('Assinatura inválida');

  $payload = json_decode(base64url_decode($p), true);
  if (!is_array($payload)) throw new RuntimeException('Payload inválido');

  $now = time();
  if (isset($payload['exp']) && $now >= (int)$payload['exp']) {
    throw new RuntimeException('Token expirado');
  }

  return $payload;
}
