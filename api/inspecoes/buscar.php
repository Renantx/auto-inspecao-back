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
// PARÂMETROS
// ================================
$inspecaoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($inspecaoId === 0) {
    respond(false, 'ID da inspeção é obrigatório', [], ['id não informado'], 422);
}

try {
    // ================================
    // BUSCA A INSPEÇÃO
    // ================================
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            u.nome as inspetor_nome,
            u.cpf as inspetor_cpf
        FROM inspecoes i
        INNER JOIN usuarios u ON i.usuario_id = u.id
        WHERE i.id = :id AND i.usuario_id = :usuario_id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $inspecaoId,
        ':usuario_id' => $usuarioId
    ]);

    $inspecao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inspecao) {
        respond(false, 'Inspeção não encontrada', [], [], 404);
    }

    // ================================
    // BUSCA AS RESPOSTAS
    // ================================
    $stmtRespostas = $pdo->prepare("
        SELECT 
            question_id,
            category,
            question_type,
            question_text,
            weight,
            is_fatal,
            answer,
            answer_boolean,
            suggestion,
            score,
            max_score
        FROM inspecao_respostas
        WHERE inspecao_id = :inspecao_id
        ORDER BY question_id
    ");

    $stmtRespostas->execute([':inspecao_id' => $inspecaoId]);
    $respostas = $stmtRespostas->fetchAll(PDO::FETCH_ASSOC);

    // ================================
    // BUSCA PONTUAÇÕES POR CATEGORIA
    // ================================
    $stmtCategorias = $pdo->prepare("
        SELECT 
            category,
            category_name,
            score,
            max_score,
            percentage
        FROM inspecao_categoria_scores
        WHERE inspecao_id = :inspecao_id
        ORDER BY category
    ");

    $stmtCategorias->execute([':inspecao_id' => $inspecaoId]);
    $categoriaScores = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

    // Formata os dados
    $inspecao['id'] = (int)$inspecao['id'];
    $inspecao['usuario_id'] = (int)$inspecao['usuario_id'];
    $inspecao['total_score'] = (float)$inspecao['total_score'];
    $inspecao['max_score'] = (float)$inspecao['max_score'];
    $inspecao['percentage'] = (float)$inspecao['percentage'];
    $inspecao['fatal_errors_count'] = (int)$inspecao['fatal_errors_count'];

    // Formata respostas
    foreach ($respostas as &$resposta) {
        $resposta['weight'] = (int)$resposta['weight'];
        $resposta['is_fatal'] = (bool)$resposta['is_fatal'];
        $resposta['score'] = (float)$resposta['score'];
        $resposta['max_score'] = (float)$resposta['max_score'];
        
        // Converte answer_boolean de volta para boolean ou usa answer
        if ($resposta['answer_boolean'] !== null) {
            $resposta['answer'] = (bool)$resposta['answer_boolean'];
        }
        unset($resposta['answer_boolean']);
    }

    // Formata categoria scores
    foreach ($categoriaScores as &$cat) {
        $cat['score'] = (float)$cat['score'];
        $cat['max_score'] = (float)$cat['max_score'];
        $cat['percentage'] = (float)$cat['percentage'];
    }

    respond(
        true,
        'Inspeção encontrada',
        [
            'inspecao' => $inspecao,
            'questions' => $respostas,
            'categoryScores' => $categoriaScores
        ],
        [],
        200
    );

} catch (Throwable $e) {
    error_log('Erro ao buscar inspeção: ' . $e->getMessage());
    respond(
        false,
        'Erro interno ao buscar inspeção',
        [],
        [],
        500
    );
}
