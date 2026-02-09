<?php
declare(strict_types=1);

// Libera qualquer origem
header('Access-Control-Allow-Origin: *');

// Libera métodos
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

// Libera headers
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Cache do preflight
header('Access-Control-Max-Age: 86400');

// Sempre JSON
header('Content-Type: application/json; charset=UTF-8');

// Trata preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
