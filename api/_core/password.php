<?php
declare(strict_types=1);

/**
 * Funções auxiliares para manipulação de senhas com bcrypt
 */

/**
 * Gera um hash bcrypt da senha
 * 
 * @param string $senha Senha em texto puro
 * @return string Hash bcrypt da senha
 */
function password_hash_bcrypt(string $senha): string {
    return password_hash($senha, PASSWORD_BCRYPT);
}

/**
 * Verifica se uma senha corresponde ao hash bcrypt
 * Também suporta senhas antigas em texto puro (para migração gradual)
 * 
 * @param string $senha Senha em texto puro a ser verificada
 * @param string $hash Hash armazenado no banco de dados
 * @return bool True se a senha corresponder, false caso contrário
 */
function password_verify_safe(string $senha, string $hash): bool {
    // Verifica se é um hash bcrypt (começa com $2y$, $2a$ ou $2b$)
    $isBcryptHash = (strpos($hash, '$2y$') === 0) 
                 || (strpos($hash, '$2a$') === 0) 
                 || (strpos($hash, '$2b$') === 0);
    
    if ($isBcryptHash) {
        // Usa password_verify() para bcrypt
        return password_verify($senha, $hash);
    }
    
    // Compatibilidade com senhas antigas em texto puro
    // Remove após migrar todas as senhas para bcrypt
    return hash_equals($hash, $senha);
}

/**
 * Verifica se um hash é bcrypt
 * 
 * @param string $hash Hash a ser verificado
 * @return bool True se for bcrypt, false caso contrário
 */
function is_bcrypt_hash(string $hash): bool {
    return (strpos($hash, '$2y$') === 0) 
        || (strpos($hash, '$2a$') === 0) 
        || (strpos($hash, '$2b$') === 0);
}
