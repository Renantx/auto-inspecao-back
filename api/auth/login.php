<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/jwt.php';
require_once __DIR__ . '/../_core/password.php';
require __DIR__ . '/../../config/db.php';

// ================================
// CONFIG
// ================================
$JWT_SECRET = 'troque-por-um-segredo-muito-forte-aqui';
$JWT_TTL    = 60 * 60; // 1 hora

// ================================
// FUNÇÃO PADRÃO DE RESPOSTA
// ================================
function respond(
    bool $success,
    string $message,
    array $data = [],
    array $errors = [],
    int $statusCode = 200
): void {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
        'errors'  => $errors
    ]);
    exit;
}

// ================================
// MÉTODO HTTP
// ================================
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'Método não permitido', [], [], 405);
}

// ================================
// BODY (JSON ou FORM)
// ================================
$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);

if (!is_array($data)) {
    $data = $_POST ?? [];
}

$usuario = trim((string)($data['usuario'] ?? '')); // CPF
$senha   = trim((string)($data['senha'] ?? ''));

// ================================
// VALIDAÇÃO
// ================================
if ($usuario === '' || $senha === '') {
    respond(
        false,
        'Informe usuário e senha',
        [],
        ['usuario ou senha não informados'],
        422
    );
}

try {
    // ================================
    // BUSCA USUÁRIO
    // ================================
    $stmt = $pdo->prepare("
        SELECT id, cpf, senha, nome
        FROM usuarios
        WHERE cpf = :cpf
        LIMIT 1
    ");
    $stmt->execute([':cpf' => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        respond(false, 'Credenciais inválidas', [], [], 401);
    }

    // ================================
    // VALIDA SENHA (bcrypt com compatibilidade)
    // ================================
    $senhaHash = (string)$user['senha'];
    
    // Usa função helper que suporta bcrypt e senhas antigas em texto puro
    if (!password_verify_safe($senha, $senhaHash)) {
        respond(false, 'Credenciais inválidas', [], [], 401);
    }
    
    // Opcional: Migração automática de senhas antigas para bcrypt
    // Descomente as linhas abaixo para migrar automaticamente quando o usuário fizer login:
    /*
    if (!is_bcrypt_hash($senhaHash)) {
        $newHash = password_hash_bcrypt($senha);
        $updateStmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
        $updateStmt->execute([':senha' => $newHash, ':id' => $user['id']]);
    }
    */

    // ================================
    // GERA TOKEN
    // ================================
    $payload = [
        'sub'  => (int)$user['id'],
        'cpf'  => (string)$user['cpf'],
        'nome' => (string)($user['nome'] ?? 'Usuário'),
    ];

    $token = jwt_sign($payload, $JWT_SECRET, $JWT_TTL);

    // ================================
    // SUCESSO
    // ================================
    respond(
        true,
        'Login realizado com sucesso',
        [
            'token' => $token,
            'user'  => [
                'id'   => (int)$user['id'],
                'cpf'  => (string)$user['cpf'],
                'nome' => (string)($user['nome'] ?? 'Usuário'),
            ]
        ],
        [],
        200
    );

} catch (Throwable $e) {
    // ⚠️ Em produção, não exponha $e->getMessage()
    respond(
        false,
        'Erro interno ao autenticar usuário',
        [],
        [],
        500
    );
}
