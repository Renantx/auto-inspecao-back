# API de Inspeções - Documentação

## 📋 Endpoints Disponíveis

### 1. Salvar Inspeção
**POST** `/api/inspecoes/salvar.php`

Salva uma nova inspeção no banco de dados.

**Autenticação:** Bearer Token (JWT)

**Body (JSON):**
```json
{
  "farmaciaNome": "Farmácia Exemplo LTDA",
  "farmaciaCNPJ": "12.345.678/0001-90",
  "responsavelTecnico": "João Silva",
  "crf": "12345/SP",
  "dataInspecao": "2026-02-09",
  "assinaturaDataUrl": "data:image/png;base64,...",
  "dataAssinatura": "2026-02-09T14:30:00Z",
  "questions": [
    {
      "id": "1.1",
      "category": "IDENTIFICACAO",
      "type": "INF",
      "text": "Razão Social",
      "weight": 1,
      "isFatal": false,
      "answer": "Farmácia Exemplo LTDA"
    },
    {
      "id": "1.8",
      "category": "IDENTIFICACAO",
      "type": "I",
      "text": "Nome do Responsável Técnico presente?",
      "weight": 5,
      "isFatal": true,
      "answer": true
    }
  ]
}
```

**Resposta de Sucesso (201):**
```json
{
  "success": true,
  "message": "Inspeção salva com sucesso",
  "data": {
    "inspecao_id": 1,
    "total_score": 150,
    "max_score": 200,
    "percentage": 75.00,
    "grade": "BOM",
    "fatal_errors_count": 0
  }
}
```

### 2. Listar Inspeções
**GET** `/api/inspecoes/listar.php`

Lista as inspeções do usuário autenticado.

**Autenticação:** Bearer Token (JWT)

**Query Parameters:**
- `limit` (opcional): Número máximo de resultados (padrão: 50, máximo: 100)
- `offset` (opcional): Número de registros para pular (padrão: 0)
- `status` (opcional): Filtrar por status (RASCUNHO, ASSINADO, FINALIZADO)
- `farmacia_cnpj` (opcional): Filtrar por CNPJ da farmácia

**Exemplo:**
```
GET /api/inspecoes/listar.php?limit=10&offset=0&status=ASSINADO
```

**Resposta (200):**
```json
{
  "success": true,
  "message": "Inspeções listadas com sucesso",
  "data": {
    "inspecoes": [
      {
        "id": 1,
        "farmacia_nome": "Farmácia Exemplo LTDA",
        "farmacia_cnpj": "12.345.678/0001-90",
        "data_inspecao": "2026-02-09",
        "total_score": 150,
        "max_score": 200,
        "percentage": 75.00,
        "grade": "BOM",
        "fatal_errors_count": 0,
        "status": "ASSINADO"
      }
    ],
    "total": 1,
    "limit": 10,
    "offset": 0
  }
}
```

### 3. Buscar Inspeção Específica
**GET** `/api/inspecoes/buscar.php?id=1`

Busca uma inspeção específica com todas as respostas.

**Autenticação:** Bearer Token (JWT)

**Query Parameters:**
- `id` (obrigatório): ID da inspeção

**Resposta (200):**
```json
{
  "success": true,
  "message": "Inspeção encontrada",
  "data": {
    "inspecao": {
      "id": 1,
      "farmacia_nome": "Farmácia Exemplo LTDA",
      "farmacia_cnpj": "12.345.678/0001-90",
      "data_inspecao": "2026-02-09",
      "total_score": 150,
      "max_score": 200,
      "percentage": 75.00,
      "grade": "BOM",
      "fatal_errors_count": 0,
      "status": "ASSINADO"
    },
    "questions": [
      {
        "question_id": "1.1",
        "category": "IDENTIFICACAO",
        "question_type": "INF",
        "question_text": "Razão Social",
        "weight": 1,
        "is_fatal": false,
        "answer": "Farmácia Exemplo LTDA",
        "score": 10,
        "max_score": 10
      }
    ],
    "categoryScores": [
      {
        "category": "IDENTIFICACAO",
        "category_name": "Identificação",
        "score": 50,
        "max_score": 60,
        "percentage": 83.33
      }
    ]
  }
}
```

## 🔐 Autenticação

Todos os endpoints requerem autenticação via Bearer Token no header:

```
Authorization: Bearer <seu_token_jwt>
```

## 📊 Cálculo de Pontuação

- Cada questão tem um `weight` (peso) de 1 a 5
- Pontuação máxima da questão = `weight * 10`
- Questões respondidas corretamente ganham a pontuação máxima
- Questões não respondidas ou respondidas incorretamente ganham 0 pontos
- Se houver erro fatal (`isFatal: true` e resposta negativa), a inspeção recebe conceito "REPROVADO"

## 🎯 Conceitos (Grade)

- **EXCELENTE**: ≥ 90% e sem erros fatais
- **BOM**: ≥ 75% e sem erros fatais
- **REGULAR**: ≥ 60% e sem erros fatais
- **INSUFICIENTE**: < 60% e sem erros fatais
- **REPROVADO**: Qualquer percentual com erros fatais

## 📝 Status da Inspeção

- **RASCUNHO**: Inspeção iniciada mas não assinada
- **ASSINADO**: Inspeção assinada pelo responsável técnico
- **FINALIZADO**: Inspeção finalizada e arquivada

## ⚠️ Tratamento de Erros

**Erro de Validação (422):**
```json
{
  "success": false,
  "message": "Dados inválidos",
  "errors": ["Nome da farmácia é obrigatório"]
}
```

**Erro de Autenticação (401):**
```json
{
  "success": false,
  "message": "Usuário não autenticado"
}
```

**Erro Interno (500):**
```json
{
  "success": false,
  "message": "Erro interno ao salvar inspeção"
}
```
