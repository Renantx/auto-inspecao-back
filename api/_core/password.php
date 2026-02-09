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
    // Remove espaços extras do hash (caso tenha sido armazenado com espaços)
    $hash = trim($hash);
    
    // Verifica se é um hash bcrypt (começa com $2y$, $2a$ ou $2b$)
    $isBcryptHash = (strpos($hash, '$2y$') === 0) 
                 || (strpos($hash, '$2a$') === 0) 
                 || (strpos($hash, '$2b$') === 0);
    
    error_log("PASSWORD_VERIFY_SAFE - Senha recebida: '" . $senha . "' (length: " . strlen($senha) . ")");
    error_log("PASSWORD_VERIFY_SAFE - Hash recebido: '" . $hash . "' (length: " . strlen($hash) . ")");
    error_log("PASSWORD_VERIFY_SAFE - Hash (primeiros 30 chars): " . substr($hash, 0, 30) . '...');
    error_log("PASSWORD_VERIFY_SAFE - É bcrypt: " . ($isBcryptHash ? 'SIM' : 'NÃO'));
    
    // Valida tamanho do hash bcrypt (deve ter 60 caracteres)
    if ($isBcryptHash && strlen($hash) !== 60) {
        error_log("PASSWORD_VERIFY_SAFE - AVISO: Hash bcrypt deve ter 60 caracteres, mas tem " . strlen($hash));
    }
    
    if ($isBcryptHash) {
        // Usa password_verify() para bcrypt
        $resultado = password_verify($senha, $hash);
        error_log("PASSWORD_VERIFY_SAFE - password_verify() retornou: " . ($resultado ? 'TRUE ✅' : 'FALSE ❌'));
        
        // Se falhou, tenta verificar se há problema com encoding ou espaços
        if (!$resultado) {
            error_log("PASSWORD_VERIFY_SAFE - Tentando verificar com senha sem trim...");
            $resultadoSemTrim = password_verify(trim($senha), trim($hash));
            error_log("PASSWORD_VERIFY_SAFE - password_verify() sem trim retornou: " . ($resultadoSemTrim ? 'TRUE ✅' : 'FALSE ❌'));
        }
        
        return $resultado;
    }
    
    // Compatibilidade com senhas antigas em texto puro
    // Remove após migrar todas as senhas para bcrypt
    $resultado = hash_equals($hash, $senha);
    error_log("PASSWORD_VERIFY_SAFE - hash_equals() retornou: " . ($resultado ? 'TRUE ✅' : 'FALSE ❌'));
    return $resultado;
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
