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
// DEBUG (remover em produção)
// ================================
error_log("LOGIN DEBUG - Usuario recebido: " . $usuario);
error_log("LOGIN DEBUG - Senha recebida (length): " . strlen($senha));
error_log("LOGIN DEBUG - Data recebida: " . json_encode($data));

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
    // Remove formatação do CPF para busca (garante que funciona mesmo se tiver pontos/traços)
    $cpfLimpo = preg_replace('/\D/', '', $usuario);
    
    // Tenta buscar primeiro com o CPF exatamente como foi enviado, depois com CPF limpo
    $stmt = $pdo->prepare("
        SELECT id, cpf, senha, nome
        FROM usuarios
        WHERE cpf = :cpf
        LIMIT 1
    ");
    $stmt->execute([':cpf' => $cpfLimpo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não encontrou, tenta com o CPF original (caso tenha formatação no banco)
    if (!$user && $cpfLimpo !== $usuario) {
        $stmt->execute([':cpf' => $usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ================================
    // DEBUG (remover em produção)
    // ================================
    error_log("LOGIN DEBUG - Usuario buscado no banco: " . ($user ? $user['cpf'] : 'NÃO ENCONTRADO'));
    if ($user) {
        error_log("LOGIN DEBUG - Hash no banco (primeiros 20 chars): " . substr($user['senha'], 0, 20) . '...');
        error_log("LOGIN DEBUG - Hash é bcrypt: " . (is_bcrypt_hash($user['senha']) ? 'SIM' : 'NÃO'));
    }

    if (!$user) {
        error_log("LOGIN DEBUG - Usuário não encontrado no banco");
        respond(false, 'Credenciais inválidas', [], [], 401);
    }

    // ================================
    // VALIDA SENHA (bcrypt com compatibilidade)
    // ================================
    $senhaHash = (string)$user['senha'];
    
    // DEBUG
    error_log("LOGIN DEBUG - Verificando senha...");
    error_log("LOGIN DEBUG - Senha digitada: " . $senha);
    error_log("LOGIN DEBUG - Hash do banco: " . $senhaHash);
    
    // Usa função helper que suporta bcrypt e senhas antigas em texto puro
    $senhaValida = password_verify_safe($senha, $senhaHash);
    error_log("LOGIN DEBUG - Senha válida: " . ($senhaValida ? 'SIM' : 'NÃO'));
    
    if (!$senhaValida) {
        error_log("LOGIN DEBUG - Senha inválida!");
        respond(false, 'Credenciais inválidas', [], [], 401);
    }
    
    error_log("LOGIN DEBUG - Senha válida! Gerando token...");
    
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
