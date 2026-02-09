# Banco de Dados - Sistema de Auto-Inspeção

## 📋 Instalação

1. **Crie o banco de dados:**
   ```sql
   CREATE DATABASE auto_inspecao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE auto_inspecao;
   ```

2. **Execute o script de criação das tabelas:**
   ```bash
   mysql -u seu_usuario -p auto_inspecao < schema.sql
   ```
   
   Ou copie e cole o conteúdo de `schema.sql` no seu cliente MySQL.

## 📊 Estrutura das Tabelas

### `usuarios`
Armazena os usuários do sistema (inspetores).

### `inspecoes`
Armazena os dados principais de cada inspeção realizada.

**Campos principais:**
- `farmacia_nome`: Nome da farmácia inspecionada
- `farmacia_cnpj`: CNPJ da farmácia
- `responsavel_tecnico`: Nome do responsável técnico
- `data_inspecao`: Data em que a inspeção foi realizada
- `total_score`, `max_score`, `percentage`: Pontuação calculada
- `grade`: Conceito (EXCELENTE, BOM, REGULAR, INSUFICIENTE, REPROVADO)
- `status`: Status da inspeção (RASCUNHO, ASSINADO, FINALIZADO)

### `inspecao_respostas`
Armazena as respostas de cada questão da inspeção.

**Campos principais:**
- `question_id`: ID da questão (ex: "1.1", "2.3")
- `answer`: Resposta em texto (para questões INF) ou "true"/"false" (para booleanas)
- `answer_boolean`: Resposta como boolean (para facilitar consultas)
- `score`, `max_score`: Pontuação da questão específica

### `inspecao_categoria_scores`
Armazena a pontuação por categoria de cada inspeção.

## 🔍 Consultas Úteis

### Listar todas as inspeções de um usuário
```sql
SELECT i.*, u.nome as inspetor_nome
FROM inspecoes i
INNER JOIN usuarios u ON i.usuario_id = u.id
WHERE i.usuario_id = 1
ORDER BY i.data_inspecao DESC;
```

### Buscar inspeção com todas as respostas
```sql
SELECT 
    i.*,
    ir.question_id,
    ir.question_text,
    ir.answer,
    ir.score,
    ir.max_score
FROM inspecoes i
LEFT JOIN inspecao_respostas ir ON i.id = ir.inspecao_id
WHERE i.id = 1
ORDER BY ir.question_id;
```

### Estatísticas de inspeções
```sql
SELECT 
    COUNT(*) as total_inspecoes,
    AVG(percentage) as media_percentual,
    COUNT(CASE WHEN grade = 'REPROVADO' THEN 1 END) as reprovadas,
    COUNT(CASE WHEN grade = 'EXCELENTE' THEN 1 END) as excelentes
FROM inspecoes
WHERE status = 'FINALIZADO';
```

## 🔐 Segurança

- Todas as senhas devem ser armazenadas com hash bcrypt
- Use prepared statements em todas as consultas
- Valide todos os dados de entrada
- Use transações para operações que envolvem múltiplas tabelas
