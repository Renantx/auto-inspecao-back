# Implementação de Bcrypt no Backend

## 📋 Resumo

O backend foi atualizado para usar **bcrypt** para criptografia de senhas, que é o padrão recomendado de segurança.

## ✅ O que foi implementado

1. **Arquivo helper** (`api/_core/password.php`):
   - `password_hash_bcrypt()` - Gera hash bcrypt de senhas
   - `password_verify_safe()` - Verifica senhas (suporta bcrypt e texto puro para migração)
   - `is_bcrypt_hash()` - Verifica se um hash é bcrypt

2. **Login atualizado** (`api/auth/login.php`):
   - Agora valida senhas usando bcrypt
   - Mantém compatibilidade com senhas antigas em texto puro (para migração gradual)

## 🔐 Como funciona

### Validação de Senha no Login

O sistema agora:
1. Verifica se a senha no banco é um hash bcrypt (começa com `$2y$`, `$2a$` ou `$2b$`)
2. Se for bcrypt, usa `password_verify()` para validar
3. Se não for bcrypt, compara como texto puro (compatibilidade com dados antigos)

### Criar Nova Senha com Bcrypt

```php
require_once __DIR__ . '/../_core/password.php';

$senha = 'minhaSenha123';
$hash = password_hash_bcrypt($senha);
// $hash será algo como: $2y$10$abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRST
```

### Verificar Senha

```php
require_once __DIR__ . '/../_core/password.php';

$senhaDigitada = 'minhaSenha123';
$hashDoBanco = '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRST';

if (password_verify_safe($senhaDigitada, $hashDoBanco)) {
    echo "Senha correta!";
} else {
    echo "Senha incorreta!";
}
```

## 🔄 Migração de Senhas Antigas

### Opção 1: Migração Automática no Login

Descomente as linhas 99-105 no arquivo `api/auth/login.php`:

```php
if (!is_bcrypt_hash($senhaHash)) {
    $newHash = password_hash_bcrypt($senha);
    $updateStmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
    $updateStmt->execute([':senha' => $newHash, ':id' => $user['id']]);
}
```

Isso migrará automaticamente as senhas quando o usuário fizer login.

### Opção 2: Script de Migração em Lote

```php
require_once __DIR__ . '/../_core/password.php';
require __DIR__ . '/../../config/db.php';

$stmt = $pdo->query("SELECT id, senha FROM usuarios");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($usuarios as $usuario) {
    // Pula se já for bcrypt
    if (is_bcrypt_hash($usuario['senha'])) {
        continue;
    }
    
    // ATENÇÃO: Você precisa ter a senha em texto puro para migrar
    // Se não tiver, o usuário precisará redefinir a senha
    // $senhaTextoPuro = 'senhaConhecida'; // ⚠️ Use apenas se souber a senha
    
    // $newHash = password_hash_bcrypt($senhaTextoPuro);
    // $updateStmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
    // $updateStmt->execute([':senha' => $newHash, ':id' => $usuario['id']]);
}
```

## 📝 Exemplos de Uso

Veja o arquivo `api/auth/exemplo-cadastro-usuario.php` para exemplos completos de:
- Cadastrar novo usuário com senha bcrypt
- Atualizar senha de usuário existente
- Migrar senha antiga para bcrypt

## 🔒 Segurança

- **Bcrypt** é um algoritmo de hash seguro e lento por design
- Cada hash é único (mesmo para a mesma senha)
- O custo padrão é 10 (pode ser ajustado se necessário)
- Senhas antigas em texto puro continuam funcionando durante a migração

## ⚠️ Importante

1. **Nunca** armazene senhas em texto puro no banco de dados
2. **Sempre** use `password_hash_bcrypt()` ao criar/atualizar senhas
3. **Sempre** use `password_verify_safe()` ao validar senhas
4. Remova a compatibilidade com texto puro após migrar todas as senhas

## 🧪 Testando

Para testar se está funcionando:

1. Crie um usuário com senha em bcrypt usando `password_hash_bcrypt()`
2. Tente fazer login com a senha original
3. Deve funcionar corretamente

```php
// Teste rápido
$senha = 'teste123';
$hash = password_hash_bcrypt($senha);
var_dump(password_verify_safe($senha, $hash)); // Deve retornar true
```
