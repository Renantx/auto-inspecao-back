<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../_core/cors.php';
require_once __DIR__ . '/../_core/auth.php';
require __DIR__ . '/../../config/db.php';

// ================================
// CONFIG
// ================================
$JWT_SECRET = 'troque-por-um-segredo-muito-forte-aqui';

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
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ================================
// AUTENTICAÇÃO
// ================================
$claims = require_auth($JWT_SECRET);
$usuarioId = (int)($claims['sub'] ?? 0);

if ($usuarioId === 0) {
    respond(false, 'Usuário não autenticado', [], [], 401);
}

// ================================
// MÉTODO HTTP
// ================================
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(false, 'Método não permitido', [], [], 405);
}

// ================================
// PARÂMETROS DE BUSCA
// ================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;
$farmaciaCNPJ = isset($_GET['farmacia_cnpj']) ? trim((string)$_GET['farmacia_cnpj']) : null;

$limit = max(1, min(100, $limit)); // Entre 1 e 100
$offset = max(0, $offset);

try {
    // ================================
    // BUSCA INSPEÇÕES
    // ================================
    $where = ['i.usuario_id = :usuario_id'];
    $params = [':usuario_id' => $usuarioId];

    if ($status !== null && in_array($status, ['RASCUNHO', 'ASSINADO', 'FINALIZADO'], true)) {
        $where[] = 'i.status = :status';
        $params[':status'] = $status;
    }

    if ($farmaciaCNPJ !== null && $farmaciaCNPJ !== '') {
        $where[] = 'i.farmacia_cnpj = :farmacia_cnpj';
        $params[':farmacia_cnpj'] = $farmaciaCNPJ;
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.farmacia_nome,
            i.farmacia_cnpj,
            i.responsavel_tecnico,
            i.crf,
            i.data_inspecao,
            i.total_score,
            i.max_score,
            i.percentage,
            i.grade,
            i.fatal_errors_count,
            i.status,
            i.criado_em,
            i.atualizado_em
        FROM inspecoes i
        WHERE {$whereClause}
        ORDER BY i.data_inspecao DESC, i.criado_em DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $inspecoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conta total de registros
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM inspecoes i
        WHERE {$whereClause}
    ");
    foreach ($params as $key => $value) {
        $stmtCount->bindValue($key, $value);
    }
    $stmtCount->execute();
    $total = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Formata datas
    foreach ($inspecoes as &$inspecao) {
        $inspecao['id'] = (int)$inspecao['id'];
        $inspecao['total_score'] = (float)$inspecao['total_score'];
        $inspecao['max_score'] = (float)$inspecao['max_score'];
        $inspecao['percentage'] = (float)$inspecao['percentage'];
        $inspecao['fatal_errors_count'] = (int)$inspecao['fatal_errors_count'];
    }

    respond(
        true,
        'Inspeções listadas com sucesso',
        [
            'inspecoes' => $inspecoes,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ],
        [],
        200
    );

} catch (Throwable $e) {
    error_log('Erro ao listar inspeções: ' . $e->getMessage());
    respond(
        false,
        'Erro interno ao listar inspeções',
        [],
        [],
        500
    );
}
