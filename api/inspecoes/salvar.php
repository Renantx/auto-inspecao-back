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
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'Método não permitido', [], [], 405);
}

// ================================
// BODY (JSON)
// ================================
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);

if (!is_array($data)) {
    respond(false, 'Dados inválidos', [], ['JSON inválido'], 422);
}

// ================================
// VALIDAÇÃO DOS DADOS
// ================================
$farmaciaNome = trim((string)($data['farmaciaNome'] ?? ''));
$farmaciaCNPJ = isset($data['farmaciaCNPJ']) ? trim((string)$data['farmaciaCNPJ']) : null;
$responsavelTecnico = isset($data['responsavelTecnico']) ? trim((string)$data['responsavelTecnico']) : null;
$crf = isset($data['crf']) ? trim((string)$data['crf']) : null;
// Trata data de inspeção (pode vir como ISO string ou Y-m-d)
$dataInspecaoRaw = isset($data['dataInspecao']) ? trim((string)$data['dataInspecao']) : '';
$dataInspecao = date('Y-m-d');

if ($dataInspecaoRaw !== '') {
    // Tenta parsear como ISO string primeiro
    $timestamp = strtotime($dataInspecaoRaw);
    if ($timestamp !== false) {
        $dataInspecao = date('Y-m-d', $timestamp);
    } else {
        // Tenta como Y-m-d
        $dataObj = DateTime::createFromFormat('Y-m-d', $dataInspecaoRaw);
        if ($dataObj && $dataObj->format('Y-m-d') === $dataInspecaoRaw) {
            $dataInspecao = $dataInspecaoRaw;
        }
    }
}

$assinaturaDataUrl = isset($data['assinaturaDataUrl']) ? trim((string)$data['assinaturaDataUrl']) : null;
$dataAssinatura = isset($data['dataAssinatura']) ? trim((string)$data['dataAssinatura']) : null;
$questions = $data['questions'] ?? [];

$errors = [];

if ($farmaciaNome === '') {
    $errors[] = 'Nome da farmácia é obrigatório';
}

if (empty($questions) || !is_array($questions)) {
    $errors[] = 'É necessário ter pelo menos uma questão respondida';
}

if (!empty($errors)) {
    respond(false, 'Dados inválidos', [], $errors, 422);
}

// ================================
// CALCULAR PONTUAÇÃO
// ================================
$totalScore = 0;
$maxScore = 0;
$fatalErrorsCount = 0;
$categoryScores = [];

foreach ($questions as $question) {
    if (!isset($question['id']) || !isset($question['weight'])) {
        continue;
    }

    $questionId = (string)$question['id'];
    $category = (string)($question['category'] ?? '');
    $weight = (int)$question['weight'];
    $isFatal = (bool)($question['isFatal'] ?? false);
    $answer = $question['answer'] ?? null;
    $questionType = (string)($question['type'] ?? '');
    
    $questionMaxScore = $weight * 10;
    $maxScore += $questionMaxScore;
    
    $questionScore = 0;
    
    // Verifica se a questão foi respondida
    if ($answer !== null && $answer !== '' && $answer !== false) {
        // Para questões booleanas (SIM/NÃO, R, I, N)
        if (in_array($questionType, ['SIM_NAO', 'R', 'I', 'N'], true)) {
            if ($answer === true) {
                $questionScore = $questionMaxScore;
            } elseif ($answer === false && $isFatal) {
                $fatalErrorsCount++;
            }
        } 
        // Para questões informativas (INF)
        elseif ($questionType === 'INF') {
            if (is_string($answer) && trim($answer) !== '') {
                $questionScore = $questionMaxScore;
            }
        }
    }
    
    $totalScore += $questionScore;
    
    // Acumula pontuação por categoria
    if (!isset($categoryScores[$category])) {
        $categoryScores[$category] = ['score' => 0, 'maxScore' => 0];
    }
    $categoryScores[$category]['score'] += $questionScore;
    $categoryScores[$category]['maxScore'] += $questionMaxScore;
}

$percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;

// Determina o conceito
$grade = 'INSUFICIENTE';
if ($fatalErrorsCount > 0) {
    $grade = 'REPROVADO';
} elseif ($percentage >= 90) {
    $grade = 'EXCELENTE';
} elseif ($percentage >= 75) {
    $grade = 'BOM';
} elseif ($percentage >= 60) {
    $grade = 'REGULAR';
}

// Determina status
$status = 'RASCUNHO';
if ($assinaturaDataUrl && $dataAssinatura) {
    $status = 'ASSINADO';
}

try {
    $pdo->beginTransaction();

    // ================================
    // INSERE A INSPEÇÃO
    // ================================
    $stmtInspecao = $pdo->prepare("
        INSERT INTO inspecoes (
            usuario_id, farmacia_nome, farmacia_cnpj, responsavel_tecnico, crf,
            data_inspecao, assinatura_data_url, data_assinatura,
            total_score, max_score, percentage, grade, fatal_errors_count, status
        ) VALUES (
            :usuario_id, :farmacia_nome, :farmacia_cnpj, :responsavel_tecnico, :crf,
            :data_inspecao, :assinatura_data_url, :data_assinatura,
            :total_score, :max_score, :percentage, :grade, :fatal_errors_count, :status
        )
    ");

    $stmtInspecao->execute([
        ':usuario_id' => $usuarioId,
        ':farmacia_nome' => $farmaciaNome,
        ':farmacia_cnpj' => $farmaciaCNPJ,
        ':responsavel_tecnico' => $responsavelTecnico,
        ':crf' => $crf,
        ':data_inspecao' => $dataInspecao,
        ':assinatura_data_url' => $assinaturaDataUrl,
        ':data_assinatura' => $dataAssinatura ? date('Y-m-d H:i:s', strtotime($dataAssinatura)) : null,
        ':total_score' => $totalScore,
        ':max_score' => $maxScore,
        ':percentage' => round($percentage, 2),
        ':grade' => $grade,
        ':fatal_errors_count' => $fatalErrorsCount,
        ':status' => $status
    ]);

    $inspecaoId = (int)$pdo->lastInsertId();

    // ================================
    // INSERE AS RESPOSTAS
    // ================================
    $stmtResposta = $pdo->prepare("
        INSERT INTO inspecao_respostas (
            inspecao_id, question_id, category, question_type, question_text,
            weight, is_fatal, answer, answer_boolean, suggestion, score, max_score
        ) VALUES (
            :inspecao_id, :question_id, :category, :question_type, :question_text,
            :weight, :is_fatal, :answer, :answer_boolean, :suggestion, :score, :max_score
        )
    ");

    foreach ($questions as $question) {
        if (!isset($question['id'])) {
            continue;
        }

        $answer = $question['answer'] ?? null;
        $answerText = null;
        $answerBoolean = null;

        if ($answer !== null) {
            if (is_bool($answer)) {
                $answerBoolean = $answer ? 1 : 0;
                $answerText = $answer ? 'true' : 'false';
            } else {
                $answerText = (string)$answer;
            }
        }

        $questionMaxScore = ((int)($question['weight'] ?? 1)) * 10;
        $questionScore = 0;

        // Calcula pontuação da questão
        if ($answer !== null && $answer !== '' && $answer !== false) {
            $questionType = (string)($question['type'] ?? '');
            if (in_array($questionType, ['SIM_NAO', 'R', 'I', 'N'], true)) {
                if ($answer === true) {
                    $questionScore = $questionMaxScore;
                }
            } elseif ($questionType === 'INF') {
                if (is_string($answer) && trim($answer) !== '') {
                    $questionScore = $questionMaxScore;
                }
            }
        }

        $stmtResposta->execute([
            ':inspecao_id' => $inspecaoId,
            ':question_id' => (string)$question['id'],
            ':category' => (string)($question['category'] ?? ''),
            ':question_type' => (string)($question['type'] ?? ''),
            ':question_text' => (string)($question['text'] ?? ''),
            ':weight' => (int)($question['weight'] ?? 1),
            ':is_fatal' => (bool)($question['isFatal'] ?? false) ? 1 : 0,
            ':answer' => $answerText,
            ':answer_boolean' => $answerBoolean,
            ':suggestion' => isset($question['suggestion']) ? (string)$question['suggestion'] : null,
            ':score' => $questionScore,
            ':max_score' => $questionMaxScore
        ]);
    }

    // ================================
    // INSERE PONTUAÇÕES POR CATEGORIA
    // ================================
    $stmtCategoria = $pdo->prepare("
        INSERT INTO inspecao_categoria_scores (
            inspecao_id, category, category_name, score, max_score, percentage
        ) VALUES (
            :inspecao_id, :category, :category_name, :score, :max_score, :percentage
        )
    ");

    $categoryNames = [
        'IDENTIFICACAO' => 'Identificação',
        'CONDICOES_GERAIS' => 'Condições Gerais',
        'RECURSOS_HUMANOS' => 'Recursos Humanos',
        'INFRAESTRUTURA' => 'Infraestrutura Física',
        'MATERIAIS_EQUIPAMENTOS' => 'Materiais e Equipamentos',
        'LIMPEZA' => 'Limpeza e Sanitização',
        'MATERIAS_PRIMAS' => 'Matérias-Primas',
        'AGUA' => 'Água',
        'MANIPULACAO' => 'Manipulação',
        'CONTROLES' => 'Controles',
        'ESTOQUE_MINIMO' => 'Estoque Mínimo',
        'ROTULAGEM_EMBALAGEM' => 'Rotulagem e Embalagem',
        'CONSERVACAO_TRANSPORTE' => 'Conservação e Transporte',
        'DISPENSACAO' => 'Dispensação',
        'GARANTIA_QUALIDADE' => 'Garantia de Qualidade',
        'BAIXO_INDICE_TERAPEUTICO' => 'Baixo Índice Terapêutico',
        'HORMONIOS_ANTIBIOTICOS' => 'Hormônios, Antibióticos e Citostáticos',
        'PRODUTOS_ESTEREIS' => 'Produtos Estéreis',
        'HOMEOPATICAS' => 'Preparações Homeopáticas',
        'DOSE_UNITARIA' => 'Dose Unitária e Unitarização'
    ];

    foreach ($categoryScores as $category => $scores) {
        $categoryPercentage = $scores['maxScore'] > 0 
            ? ($scores['score'] / $scores['maxScore']) * 100 
            : 0;

        $stmtCategoria->execute([
            ':inspecao_id' => $inspecaoId,
            ':category' => $category,
            ':category_name' => $categoryNames[$category] ?? $category,
            ':score' => $scores['score'],
            ':max_score' => $scores['maxScore'],
            ':percentage' => round($categoryPercentage, 2)
        ]);
    }

    $pdo->commit();

    respond(
        true,
        'Inspeção salva com sucesso',
        [
            'inspecao_id' => $inspecaoId,
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => round($percentage, 2),
            'grade' => $grade,
            'fatal_errors_count' => $fatalErrorsCount
        ],
        [],
        201
    );

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Erro ao salvar inspeção: ' . $e->getMessage());
    respond(
        false,
        'Erro interno ao salvar inspeção',
        [],
        [],
        500
    );
}
