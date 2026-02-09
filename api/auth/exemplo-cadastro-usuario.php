<?php
/**
 * EXEMPLO: Como cadastrar/atualizar usuário com senha em bcrypt
 * 
 * Este é um arquivo de exemplo. Não use em produção sem adaptações.
 * Crie um endpoint adequado seguindo os padrões do projeto.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_core/password.php';
require __DIR__ . '/../../config/db.php';

// ================================
// EXEMPLO 1: Cadastrar novo usuário
// ================================
function cadastrarUsuario(string $cpf, string $nome, string $senha): bool {
    global $pdo;
    
    try {
        // Gera hash bcrypt da senha
        $senhaHash = password_hash_bcrypt($senha);
        
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (cpf, nome, senha) 
            VALUES (:cpf, :nome, :senha)
        ");
        
        return $stmt->execute([
            ':cpf' => $cpf,
            ':nome' => $nome,
            ':senha' => $senhaHash
        ]);
    } catch (Throwable $e) {
        error_log('Erro ao cadastrar usuário: ' . $e->getMessage());
        return false;
    }
}

// ================================
// EXEMPLO 2: Atualizar senha de usuário
// ================================
function atualizarSenha(int $usuarioId, string $novaSenha): bool {
    global $pdo;
    
    try {
        // Gera hash bcrypt da nova senha
        $senhaHash = password_hash_bcrypt($novaSenha);
        
        $stmt = $pdo->prepare("
            UPDATE usuarios 
            SET senha = :senha 
            WHERE id = :id
        ");
        
        return $stmt->execute([
            ':senha' => $senhaHash,
            ':id' => $usuarioId
        ]);
    } catch (Throwable $e) {
        error_log('Erro ao atualizar senha: ' . $e->getMessage());
        return false;
    }
}

// ================================
// EXEMPLO 3: Migrar senha antiga para bcrypt
// ================================
function migrarSenhaParaBcrypt(int $usuarioId, string $senhaAtualTextoPuro): bool {
    global $pdo;
    
    try {
        // Busca usuário
        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $usuarioId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // Verifica se já está em bcrypt
        if (is_bcrypt_hash($user['senha'])) {
            return true; // Já está migrado
        }
        
        // Migra para bcrypt
        $senhaHash = password_hash_bcrypt($senhaAtualTextoPuro);
        
        $updateStmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
        return $updateStmt->execute([
            ':senha' => $senhaHash,
            ':id' => $usuarioId
        ]);
    } catch (Throwable $e) {
        error_log('Erro ao migrar senha: ' . $e->getMessage());
        return false;
    }
}

// ================================
// EXEMPLO DE USO:
// ================================
/*
// Cadastrar novo usuário
cadastrarUsuario('12345678900', 'João Silva', 'senha123');

// Atualizar senha
atualizarSenha(1, 'novaSenha456');

// Migrar senha antiga
migrarSenhaParaBcrypt(1, 'senhaAntiga');
*/
