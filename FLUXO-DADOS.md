# 🔄 Fluxo de Dados: React → WordPress

## Visão Geral do Fluxo de Integração

```
┌────────────────────────────────────────────────────────────────────────┐
│                           NAVEGADOR                                    │
├────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│                     React Form (http://localhost:5176)                │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  FormRegistro.tsx                                               │   │
│  │  ├─ Step 1: Dados Pessoais    [nome, cpf, data nascimento]    │   │
│  │  ├─ Step 2: Endereço          [rua, cep, município]            │   │
│  │  ├─ Step 3: Contato           [email, telefone]                │   │
│  │  ├─ Step 4: Perfil & Vínculo  [perfil, vinculoInstitucional]  │   │
│  │  ├─ Step 5: Acessibilidade    [acessibilidade]                 │   │
│  │  └─ Step 6: Resumo (SUBMIT)   [ResumoStep.tsx]                │   │
│  └──────────────────────────────┬──────────────────────────────────┘   │
│                                  │                                      │
│                                  │ handleSubmit()                       │
│                                  ▼                                      │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  registrationService.submitRegistration(data)                   │   │
│  │                                                                  │   │
│  │  1. Converte camelCase → snake_case                            │   │
│  │  2. Obtém nonce: getNonce() ────┐                             │   │
│  │  3. Prepara payload com nonce    │                             │   │
│  │  4. axios.post() → API ──────────┘                             │   │
│  │  5. Retorna response              ▼                             │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└────────────────────┬────────────────────────────────────────────────────┘
                     │
         ┌───────────▼─────────────┐
         │  HTTP REQUEST           │
         │  ┌─────────────────────┐│
         │  │ GET /wp-json/...    ││
         │  │ /fpse/v1/nonce      ││
         │  │ (ou)                ││
         │  │ POST /wp-json/...   ││
         │  │ /fpse/v1/register   ││
         │  │ Content-Type: JSON  ││
         │  └─────────────────────┘│
         └───────────┬─────────────┘
                     │
┌────────────────────▼──────────────────────────────────────────────────┐
│                        WORDPRESS SERVER                              │
├───────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ENDPOINT 1: GET /wp-json/fpse/v1/nonce                              │
│  ┌───────────────────────────────────────────────────────────────┐   │
│  │ RegistrationController::handleGetNonce()                      │   │
│  │                                                                │   │
│  │ 1. Cria token com NonceMiddleware::generateNonce()           │   │
│  │ 2. Retorna JSON:                                             │   │
│  │    {                                                         │   │
│  │      success: true,                                         │   │
│  │      nonce: "abc123xyz...",                                │   │
│  │      nonce_name: "fpse_nonce",                             │   │
│  │      nonce_action: "fpse_register_action"                  │   │
│  │    }                                                         │   │
│  └───────────────────────────────────────────────────────────────┘   │
│                                                                        │
│  ───────────────────────────────────────────────────────────────────  │
│                                                                        │
│  ENDPOINT 2: POST /wp-json/fpse/v1/register                          │
│  ┌───────────────────────────────────────────────────────────────┐   │
│  │ RegistrationController::handleRegister()                      │   │
│  │                                                                │   │
│  │ 📋 REQUEST BODY:                                             │   │
│  │ {                                                            │   │
│  │   "fpse_nonce": "abc123xyz...",  ← Do Step 1                │   │
│  │   "nome_completo": "João Silva",                            │   │
│  │   "cpf": "12345678900",  ← sem máscaras                     │   │
│  │   "email": "joao@example.com",                              │   │
│  │   "email_login": "joao.silva@example.com",                  │   │
│  │   "senha_login": "SecurePass123!",                          │   │
│  │   "telefone": "85987654321",  ← sem máscaras               │   │
│  │   "data_nascimento": "1990-05-15",                          │   │
│  │   "genero": "masculino",                                    │   │
│  │   "raca_cor": "branca",                                     │   │
│  │   "perfil_usuario": "estudante-ies",                        │   │
│  │   "vinculo_institucional": "aluno",                         │   │
│  │   "estado": "CE",                                           │   │
│  │   "municipio": "Fortaleza",                                 │   │
│  │   "logradouro": "Rua Principal",                            │   │
│  │   "cep": "60025100",  ← sem máscaras                        │   │
│  │   "numero": "123",                                          │   │
│  │   "bairro": "Centro",                                       │   │
│  │   "complemento": "Apto 101",                                │   │
│  │   "acessibilidade": false,                                  │   │
│  │   "descricao_acessibilidade": null,                         │   │
│  │   "instituicao_nome": "UECE",                               │   │
│  │   "curso_nome": "Pedagogia",                                │   │
│  │   "matricula": "2024001234"                                 │   │
│  │ }                                                            │   │
│  │                                                                │   │
│  │ ✅ STEP 1: Verify Nonce (CSRF Protection)                   │   │
│  │    └─ NonceMiddleware::verifyNonce()                        │   │
│  │       ├─ Valida token (não expirado)                       │   │
│  │       ├─ Valida ação (fpse_register_action)                │   │
│  │       └─ Se falhar: Return 403 Forbidden                   │   │
│  │                                                                │   │
│  │ ✅ STEP 2: Check Rate Limit (IP-based)                      │   │
│  │    └─ RateLimit::checkLimit('register', 5)                 │   │
│  │       ├─ Obtém IP do cliente (com proxy support)          │   │
│  │       ├─ Verifica transient: "fpse_register_<IP>"         │   │
│  │       ├─ Se < 5 registros/hora: OK                        │   │
│  │       └─ Se >= 5: Return 429 Too Many Requests            │   │
│  │                                                                │   │
│  │ ✅ STEP 3: Parse & Validate Input                           │   │
│  │    └─ RegistrationDTO::fromArray($_POST)                   │   │
│  │       ├─ snake_case → camelCase conversion                 │   │
│  │       ├─ Type coercion (string, int, bool, date)          │   │
│  │       ├─ Remove empty/null values                          │   │
│  │       └─ Store in typed object                            │   │
│  │                                                                │   │
│  │ ✅ STEP 4: Validate Profile                                 │   │
│  │    └─ ProfileResolver::validateProfile($perfil)            │   │
│  │       ├─ Verifica se perfil existe em config              │   │
│  │       ├─ Obtém campos obrigatórios do perfil              │   │
│  │       └─ Valida que todos os campos obrigatórios existem  │   │
│  │                                                                │   │
│  │ ✅ STEP 5: Validate State                                   │   │
│  │    └─ Plugin::getConfig('states')                          │   │
│  │       ├─ Verifica se UF existe (AC, AL, AP, ... TO)      │   │
│  │       └─ Se não existir: Return 400 Bad Request           │   │
│  │                                                                │   │
│  │ ✅ STEP 6: Create/Update User                               │   │
│  │    └─ UserService::createOrUpdate($dto)                    │   │
│  │       ├─ Checks for duplicates (by email)                 │   │
│  │       ├─ Calls wp_create_user() or wp_update_user()       │   │
│  │       ├─ wp_set_user_capabilities($user_id, ['role'])     │   │
│  │       └─ Returns $user_id (or error)                      │   │
│  │                                                                │   │
│  │ ✅ STEP 7: Store User Meta                                  │   │
│  │    └─ UserService::storeUserMeta($user_id, $dto)          │   │
│  │       ├─ For each field in $dto:                          │   │
│  │       │  └─ update_user_meta($user_id, $field, $value)   │   │
│  │       └─ All fields stored in snake_case:                 │   │
│  │          wp_usermeta.meta_key = "nome_completo"           │   │
│  │          wp_usermeta.meta_value = "João Silva"            │   │
│  │                                                                │   │
│  │ ✅ STEP 8: Record Events (Audit Trail)                      │   │
│  │    └─ EventRecorder::recordRegistration()                  │   │
│  │       ├─ recordEvent($user_id, "user_registered", ...)    │   │
│  │       └─ Inserts into wp_fpse_events:                      │   │
│  │          user_id: 42                                       │   │
│  │          event: "user_registered"                         │   │
│  │          perfil: "estudante-ies"                          │   │
│  │          estado: "CE"                                      │   │
│  │          metadata: { ... }                                │   │
│  │          created_at: 2026-01-15 19:05:00                  │   │
│  │                                                                │   │
│  │ ✅ STEP 9: Optional Logging                                 │   │
│  │    └─ Logger::info("User registered", [...])              │   │
│  │       ├─ Write to fpse-core.log                           │   │
│  │       ├─ Mask sensitive fields:                           │   │
│  │       │  cpf: "***MASKED***"                              │   │
│  │       │  email: "***MASKED***"                            │   │
│  │       │  telefone: "***MASKED***"                         │   │
│  │       └─ Timestamp and level (INFO)                       │   │
│  │                                                                │   │
│  │ 📊 RESPONSE:                                                 │   │
│  │ {                                                            │   │
│  │   "success": true,                                         │   │
│  │   "message": "Usuário criado com sucesso",                │   │
│  │   "user_id": 42,                                          │   │
│  │   "perfil": "estudante-ies",                              │   │
│  │   "estado": "CE"                                          │   │
│  │ }                                                            │   │
│  │                                                                │   │
│  │ HTTP Status: 201 Created                                    │   │
│  └───────────────────────────────────────────────────────────────┘   │
│                                                                        │
│  📦 DATABASE UPDATES:                                                 │
│  ┌───────────────────────────────────────────────────────────────┐   │
│  │  TABLE: wp_users                                              │   │
│  │  ┌──────────────────────────────────────────────────────────┐ │   │
│  │  │ ID  │ user_login               │ user_email             │ │   │
│  │  ├─────┼──────────────────────────┼────────────────────────┤ │   │
│  │  │ 42  │ joao.silva@example.com   │ joao@example.com       │ │   │
│  │  └──────────────────────────────────────────────────────────┘ │   │
│  │                                                                │   │
│  │  TABLE: wp_usermeta (para user_id=42)                         │   │
│  │  ┌──────────────────────────────────────────────────────────┐ │   │
│  │  │ meta_key              │ meta_value                        │ │   │
│  │  ├──────────────────────┼─────────────────────────────────┤ │   │
│  │  │ nome_completo        │ João Silva                      │ │   │
│  │  │ cpf                  │ 12345678900                    │ │   │
│  │  │ email                │ joao@example.com              │ │   │
│  │  │ email_login          │ joao.silva@example.com        │ │   │
│  │  │ telefone             │ 85987654321                   │ │   │
│  │  │ data_nascimento      │ 1990-05-15                    │ │   │
│  │  │ genero               │ masculino                     │ │   │
│  │  │ ... (30+ mais) ...   │ ...                           │ │   │
│  │  └──────────────────────────────────────────────────────────┘ │   │
│  │                                                                │   │
│  │  TABLE: wp_fpse_events                                        │   │
│  │  ┌──────────────────────────────────────────────────────────┐ │   │
│  │  │ user_id │ event            │ perfil           │ created_at │ │   │
│  │  ├─────────┼──────────────────┼──────────────────┼────────────┤ │   │
│  │  │ 42      │ user_registered  │ estudante-ies    │ 2026-01-15 │ │   │
│  │  │         │                  │                  │ 19:05:00   │ │   │
│  │  └──────────────────────────────────────────────────────────┘ │   │
│  └───────────────────────────────────────────────────────────────┘   │
│                                                                        │
└────────────────────┬───────────────────────────────────────────────────┘
                     │
         ┌───────────▼─────────────┐
         │  HTTP RESPONSE          │
         │  ┌─────────────────────┐│
         │  │ 201 Created         ││
         │  │ Content-Type: JSON  ││
         │  │ {                   ││
         │  │   success: true,    ││
         │  │   message: "...",   ││
         │  │   user_id: 42       ││
         │  │ }                   ││
         │  └─────────────────────┘│
         └───────────┬─────────────┘
                     │
┌────────────────────▼──────────────────────────────────────────────────┐
│                     REACT (Recebe Resposta)                           │
├───────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ResumoStep.tsx::handleSubmit()                                       │
│  └─ Processa response                                                 │
│                                                                        │
│     if (response.success) {                                           │
│       // ✅ Sucesso                                                   │
│       setSubmitted(true)                                             │
│       localStorage.setItem('fpse_user_id', '42')                     │
│       localStorage.setItem('fpse_user_perfil', 'estudante-ies')     │
│       localStorage.setItem('fpse_user_estado', 'CE')                │
│       onSuccess?.call(response)                                      │
│       setTimeout(() => {                                             │
│         window.location.href = '/sucesso'  // Redireciona           │
│       }, 2000)                                                        │
│     } else {                                                          │
│       // ❌ Erro                                                      │
│       setError(response.message)                                     │
│       onError?.call(response.message)                                │
│       // Mantém dados do form para reenvio                          │
│     }                                                                 │
│                                                                        │
│  🎉 SUCESSO: Mostra componente de sucesso                            │
│     ┌──────────────────────────────────────┐                        │
│     │ ✅ Cadastro Realizado com Sucesso!  │                        │
│     │                                      │                        │
│     │ Seu cadastro foi enviado para        │                        │
│     │ análise. Você receberá um email      │                        │
│     │ de confirmação em breve.             │                        │
│     │                                      │                        │
│     │ ID do Cadastro: 42                   │                        │
│     │                                      │                        │
│     │ [Ir para Dashboard] [OK]             │                        │
│     └──────────────────────────────────────┘                        │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 📊 Mapeamento de Dados

### O que entra no formulário React (camelCase):

```javascript
{
  nomeCompleto: "João Silva",
  cpf: "123.456.789-00",  // com máscaras
  email: "joao@example.com",
  emailLogin: "joao.silva@example.com",
  senhaLogin: "SecurePass123!",
  telefone: "(85) 98765-4321",  // com máscaras
  dataNascimento: "1990-05-15",
  genero: "masculino",
  racaCor: "branca",
  perfilUsuario: "estudante-ies",
  vinculoInstitucional: "aluno",
  estado: "CE",
  municipio: "Fortaleza",
  logradouro: "Rua Principal",
  cep: "60.025-100",  // com máscaras
  numero: "123",
  bairro: "Centro",
  complemento: "Apto 101",
  acessibilidade: false,
  descricaoAcessibilidade: null,
  instituicaoNome: "UECE",
  cursoNome: "Pedagogia",
  matricula: "2024001234"
}
```

### O que é enviado ao WordPress (snake_case, sem máscaras):

```json
{
  "fpse_nonce": "abc123xyz...",
  "nome_completo": "João Silva",
  "cpf": "12345678900",  // sem máscaras
  "email": "joao@example.com",
  "email_login": "joao.silva@example.com",
  "senha_login": "SecurePass123!",
  "telefone": "85987654321",  // sem máscaras
  "data_nascimento": "1990-05-15",
  "genero": "masculino",
  "raca_cor": "branca",
  "perfil_usuario": "estudante-ies",
  "vinculo_institucional": "aluno",
  "estado": "CE",
  "municipio": "Fortaleza",
  "logradouro": "Rua Principal",
  "cep": "60025100",  // sem máscaras
  "numero": "123",
  "bairro": "Centro",
  "complemento": "Apto 101",
  "acessibilidade": false,
  "descricao_acessibilidade": null,
  "instituicao_nome": "UECE",
  "curso_nome": "Pedagogia",
  "matricula": "2024001234"
}
```

### O que é armazenado no WordPress (wp_usermeta):

```sql
-- Todos em snake_case, sem máscaras:
meta_key: "nome_completo"       → "João Silva"
meta_key: "cpf"                 → "12345678900"
meta_key: "email"               → "joao@example.com"
meta_key: "email_login"         → "joao.silva@example.com"
meta_key: "telefone"            → "85987654321"
meta_key: "data_nascimento"     → "1990-05-15"
meta_key: "genero"              → "masculino"
meta_key: "raca_cor"            → "branca"
meta_key: "perfil_usuario"      → "estudante-ies"
meta_key: "vinculo_institucional" → "aluno"
meta_key: "estado"              → "CE"
meta_key: "municipio"           → "Fortaleza"
meta_key: "logradouro"          → "Rua Principal"
meta_key: "cep"                 → "60025100"
meta_key: "numero"              → "123"
meta_key: "bairro"              → "Centro"
meta_key: "complemento"         → "Apto 101"
meta_key: "acessibilidade"      → "false"
meta_key: "descricao_acessibilidade" → NULL
meta_key: "instituicao_nome"    → "UECE"
meta_key: "curso_nome"          → "Pedagogia"
meta_key: "matricula"           → "2024001234"
```

---

## 🔄 Transformações de Dados

```
REACT (camelCase com máscaras)
           │
           ▼
    convertToSnakeCase()
           │
           ▼
JAVASCRIPT OBJECT (snake_case com máscaras)
           │
           ▼
    axios.post() → JSON string
           │
           ▼
WORDPRESS (recebe JSON)
           │
           ▼
    $_POST (PHP array)
           │
           ▼
    RegistrationDTO::fromArray()
           │
           ▼
TYPE-SAFE DTO OBJECT (validação)
           │
           ▼
    UserService::createOrUpdate()
           │
           ▼
    wp_create_user() + update_user_meta()
           │
           ▼
DATABASE (wp_users + wp_usermeta)
```

---

## ✅ Checklist de Fluxo

- [ ] React obtém nonce
- [ ] React envia POST com dados
- [ ] WordPress valida nonce
- [ ] WordPress verifica rate limit
- [ ] WordPress cria usuário
- [ ] WordPress armazena meta
- [ ] WordPress registra evento
- [ ] WordPress retorna sucesso
- [ ] React exibe sucesso
- [ ] Dados aparecem no banco

**Resultado**: ✅ Fluxo Completo Funcionando!
