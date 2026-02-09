<?php
/**
 * Script de teste para verificar se o bcrypt está funcionando corretamente
 * Execute este arquivo para testar a validação de senha
 */

declare(strict_types=1);

require_once __DIR__ . '/../_core/password.php';

// Dados de teste fornecidos pelo usuário
$cpfBanco = '12345678901';
$senhaTextoPuro = '123456';
$hashBanco = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8Rk3Z2XcV7zJrR1yQm2F7cF0xY5J9S';

echo "=== TESTE DE VALIDAÇÃO BCRYPT ===\n\n";

echo "CPF no banco: {$cpfBanco}\n";
echo "Senha em texto puro: {$senhaTextoPuro}\n";
echo "Hash no banco: {$hashBanco}\n";
echo "Tamanho do hash: " . strlen($hashBanco) . " caracteres\n\n";

// Verifica se é bcrypt
echo "É hash bcrypt? " . (is_bcrypt_hash($hashBanco) ? 'SIM' : 'NÃO') . "\n\n";

// Testa a validação
echo "Testando password_verify() nativo do PHP:\n";
$resultadoNativo = password_verify($senhaTextoPuro, $hashBanco);
echo "password_verify('{$senhaTextoPuro}', hash) = " . ($resultadoNativo ? 'TRUE ✅' : 'FALSE ❌') . "\n\n";

echo "Testando password_verify_safe():\n";
$resultadoSafe = password_verify_safe($senhaTextoPuro, $hashBanco);
echo "password_verify_safe('{$senhaTextoPuro}', hash) = " . ($resultadoSafe ? 'TRUE ✅' : 'FALSE ❌') . "\n\n";

// Testa com senha errada
echo "Testando com senha ERRADA (1234567):\n";
$resultadoErrado = password_verify_safe('1234567', $hashBanco);
echo "password_verify_safe('1234567', hash) = " . ($resultadoErrado ? 'TRUE ✅' : 'FALSE ❌ (esperado)') . "\n\n";

// Gera um novo hash para comparação
echo "Gerando novo hash para a mesma senha:\n";
$novoHash = password_hash_bcrypt($senhaTextoPuro);
echo "Novo hash: {$novoHash}\n";
echo "Novo hash é diferente do antigo? " . ($novoHash !== $hashBanco ? 'SIM ✅ (normal em bcrypt)' : 'NÃO') . "\n";
echo "Mas ambos validam a mesma senha? " . (password_verify($senhaTextoPuro, $novoHash) ? 'SIM ✅' : 'NÃO ❌') . "\n\n";

echo "=== FIM DO TESTE ===\n";
