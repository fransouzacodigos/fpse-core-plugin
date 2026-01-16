# 📡 Como Funciona a Integração React + WordPress Plugin

## 🏗️ Arquitetura da Integração

```
┌─────────────────────────────────────────────────────────────────────┐
│                        NAVEGADOR DO USUÁRIO                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │         React Form (Port 5176 ou HTTPS)                     │   │
│  │  ┌─────────────────────────────────────────────────────┐    │   │
│  │  │ Step 1: Dados Pessoais                              │    │   │
│  │  │ Step 2: Endereço (ViaCEP)                           │    │   │
│  │  │ Step 3: Contato                                     │    │   │
│  │  │ Step 4: Perfil & Vínculo                            │    │   │
│  │  │ Step 5: Acessibilidade                              │    │   │
│  │  │ Step 6: Resumo (Submit)                             │    │   │
│  │  └─────────────────────────────────────────────────────┘    │   │
│  │                                                              │   │
│  │  Validação: Zod (client-side)                             │   │
│  │  Masking: useMask hook                                    │   │
│  │  State: react-hook-form                                   │   │
│  └──────────────┬───────────────────────────────────────────────┘   │
│                 │                                                    │
└─────────────────┼────────────────────────────────────────────────────┘
                  │
         ┌────────▼─────────┐
         │  HTTP/HTTPS      │
         │  POST Request    │
         │  JSON Payload    │
         └────────┬─────────┘
                  │
┌─────────────────▼────────────────────────────────────────────────────┐
│                    WORDPRESS SERVER                                  │
├──────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │  FPSE Core Plugin - REST API                                   │ │
│  │  Endpoint: /wp-json/fpse/v1/register                           │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐ │ │
│  │  │ 1. Nonce Verification (CSRF Protection)                │ │ │
│  │  │    ✓ Valida token enviado pelo React                 │ │ │
│  │  │    ✓ Previne ataques cross-site                      │ │ │
│  │  └──────────────────────────────────────────────────────────┘ │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐ │ │
│  │  │ 2. Rate Limiting (IP-based)                            │ │ │
│  │  │    ✓ Max 5 registros por hora                         │ │ │
│  │  │    ✓ Usa transients do WordPress                      │ │ │
│  │  │    ✓ IP do cliente (com proxy support)                │ │ │
│  │  └──────────────────────────────────────────────────────────┘ │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐ │ │
│  │  │ 3. Input Validation & Sanitization                     │ │ │
│  │  │    ✓ Validação de perfil                              │ │ │
│  │  │    ✓ Validação de estado                              │ │ │
│  │  │    ✓ Sanitização de strings                           │ │ │
│  │  │    ✓ Tipos de dados                                   │ │ │
│  │  └──────────────────────────────────────────────────────────┘ │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐ │ │
│  │  │ 4. User Creation/Update                                │ │ │
│  │  │    ✓ Cria conta WordPress (wp_users)                  │ │ │
│  │  │    ✓ Armazena dados em wp_usermeta (snake_case)      │ │ │
│  │  │    ✓ Gera password hash (bcrypt)                      │ │ │
│  │  │    ✓ Atribui roles/capabilities                       │ │ │
│  │  └──────────────────────────────────────────────────────────┘ │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐ │ │
│  │  │ 5. Event Recording (Audit Trail)                       │ │ │
│  │  │    ✓ Registra em wp_fpse_events                        │ │ │
│  │  │    ✓ Evento: "user_registered"                         │ │ │
│  │  │    ✓ Metadados: perfil, estado, timestamp             │ │ │
│  │  └──────────────────────────────────────────────────────────┘ │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐ │ │
│  │  │ 6. Logging (Optional)                                  │ │ │
│  │  │    ✓ Mascaramento de dados sensíveis                   │ │ │
│  │  │    ✓ CPF → ***MASKED***                                │ │ │
│  │  │    ✓ Email → ***MASKED***                              │ │ │
│  │  │    ✓ Arquivo: fpse-core.log                            │ │ │
│  │  └──────────────────────────────────────────────────────────┘ │ │
│  │                                                                │ │
│  │  Response JSON:                                              │ │
│  │  {                                                            │ │
│  │    "success": true,                                          │ │
│  │    "message": "Usuário criado com sucesso",                │ │
│  │    "user_id": 42,                                           │ │
│  │    "perfil": "estudante-ies",                              │ │
│  │    "estado": "CE"                                           │ │
│  │  }                                                            │ │
│  └────────────────┬───────────────────────────────────────────────┘ │
│                   │                                                  │
│  ┌────────────────▼───────────────────────────────────────────────┐ │
│  │  Databases                                                     │ │
│  │  ├─ wp_users (usuário criado)                                │ │
│  │  ├─ wp_usermeta (dados de cadastro)                         │ │
│  │  └─ wp_fpse_events (audit trail)                            │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                       │
└─────────────────┬────────────────────────────────────────────────────┘
                  │
         ┌────────▼─────────┐
         │  HTTP Response   │
         │  JSON Result     │
         │  Status 200/400  │
         └────────┬─────────┘
                  │
┌─────────────────▼────────────────────────────────────────────────────┐
│              BROWSER - React (Recebe Resposta)                       │
├──────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │ Success Case (response.success === true):                      │ │
│  │                                                                │ │
│  │ 1. Mostrar mensagem: "Cadastro realizado com sucesso!"        │ │
│  │ 2. Armazenar user_id no localStorage                          │ │
│  │ 3. Redirecionar para página de sucesso                        │ │
│  │ 4. Trigger callback (opcional)                                │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                       │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │ Error Case (response.success === false):                       │ │
│  │                                                                │ │
│  │ 1. Mostrar mensagem de erro                                   │ │
│  │ 2. Manter dados do formulário                                 │ │
│  │ 3. Permitir que usuário edite/reenvie                         │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                       │
└───────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Fluxo Passo-a-Passo

### Passo 1: React Prepara os Dados
```typescript
// Usuário preenche 6 passos do formulário
const data = {
  nomeCompleto: "João Silva",
  cpf: "123.456.789-00",
  email: "joao@example.com",
  // ... mais 30+ campos
};
```

### Passo 2: React Obtém Nonce (Token de Segurança)
```typescript
const nonce = await registrationService.getNonce();
// GET /wp-json/fpse/v1/nonce
// Retorna: { nonce: "abc123...", nonce_action: "fpse_register_action" }
```

### Passo 3: React Envia POST com Dados + Nonce
```typescript
const response = await axios.post(
  '/wp-json/fpse/v1/register',
  {
    fpse_nonce: nonce,
    nome_completo: "João Silva",
    cpf: "123.456.789-00",
    // ... todos os campos em snake_case
  }
);
```

### Passo 4: WordPress Valida Nonce
```php
// RegistrationController.php
$nonce_middleware = new NonceMiddleware($this->plugin);
if (!$nonce_middleware->verifyNonce($_POST['fpse_nonce'])) {
    return ['success' => false, 'message' => 'Invalid nonce'];
}
```

### Passo 5: WordPress Verifica Rate Limit
```php
// RateLimit.php
$rate_limiter = new RateLimit($this->plugin);
if (!$rate_limiter->checkLimit('register', 5)) { // 5 per hour
    return ['success' => false, 'message' => 'Rate limit exceeded'];
}
```

### Passo 6: WordPress Valida Dados
```php
// ProfileResolver.php & RegistrationDTO.php
$dto = RegistrationDTO::fromArray($_POST);
$resolver->validateProfile($dto->perfil_usuario);
```

### Passo 7: WordPress Cria/Atualiza Usuário
```php
// UserService.php
$user_id = wp_create_user($email_login, $senha_login, $email);
// Armazena todos os dados em wp_usermeta
```

### Passo 8: WordPress Registra Evento
```php
// EventRecorder.php
$recorder->recordRegistration($user_id, $perfil, $estado, $metadata);
// Insere em wp_fpse_events table
```

### Passo 9: WordPress Retorna Sucesso
```json
{
  "success": true,
  "message": "Usuário criado com sucesso",
  "user_id": 42,
  "perfil": "estudante-ies",
  "estado": "CE"
}
```

### Passo 10: React Exibe Sucesso
```typescript
if (response.success) {
  alert("Cadastro realizado!");
  localStorage.setItem("fpse_user_id", "42");
  window.location.href = "/success";
}
```

---

## 📦 Dados que Trafegam

### Request (React → WordPress)

```json
{
  "fpse_nonce": "abc123xyz...",
  "nome_completo": "João da Silva",
  "cpf": "123.456.789-00",
  "email": "joao@example.com",
  "email_login": "joao.silva@example.com",
  "senha_login": "SecurePass123!",
  "telefone": "(85) 98765-4321",
  "data_nascimento": "1990-05-15",
  "genero": "masculino",
  "raca_cor": "branca",
  "perfil_usuario": "estudante-ies",
  "vinculo_institucional": "aluno",
  "estado": "CE",
  "municipio": "Fortaleza",
  "logradouro": "Rua Principal",
  "cep": "60025-100",
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

### Response (WordPress → React)

**Sucesso:**
```json
{
  "success": true,
  "message": "Usuário criado com sucesso",
  "user_id": 42,
  "perfil": "estudante-ies",
  "estado": "CE"
}
```

**Erro:**
```json
{
  "success": false,
  "message": "CPF já cadastrado",
  "errors": ["CPF duplicado no sistema"]
}
```

---

## 🔐 Segurança em Cada Etapa

| Etapa | Proteção | Como Funciona |
|-------|----------|---------------|
| **CSRF** | Nonce Token | React obtém token único e inclui em POST |
| **Rate Limit** | IP-Based | Máximo 5 registros/hora por IP |
| **Validação** | Server-side | WordPress valida TUDO novamente |
| **SQL Injection** | Prepared Statements | Dados sempre parametrizados |
| **Data Exposure** | Field Masking | CPF/Email mascarados em logs |
| **Authentication** | WordPress Roles | Usuário criado com role padrão |

---

## 🌐 CORS Configuration

### Desenvolvimento (Mesmo Servidor, Portas Diferentes)
```
React:      http://localhost:5176
WordPress:  http://localhost/
```

**Configuração necessária** (adicionar em functions.php ou plugin):
```php
add_filter('rest_pre_serve_request', function() {
    header('Access-Control-Allow-Origin: http://localhost:5176');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    return true;
}, 15);
```

### Produção (Domínios Diferentes)
```
React:      https://example.com
WordPress:  https://api.example.com
```

**Configuração necessária:**
```php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'https://example.com') {
    header("Access-Control-Allow-Origin: $origin");
}
```

---

## 📡 Exemplo Real: Fluxo Completo

### 1. Usuário Preenche Formulário
```
Nome: João Silva
CPF: 123.456.789-00
Email: joao@example.com
...
[Clica em "Finalizar Cadastro"]
```

### 2. React Valida (Client-side)
```typescript
// Zod validation no cliente
const schema = z.object({
  nomeCompleto: z.string().min(3),
  cpf: z.string().regex(/^\d{3}\.\d{3}\.\d{3}-\d{2}$/),
  email: z.string().email(),
  // ...
});
const validation = schema.parse(data); // ✓ OK
```

### 3. React Obtém Nonce
```bash
GET /wp-json/fpse/v1/nonce
→ { "nonce": "abc123xyz", "nonce_action": "fpse_register_action" }
```

### 4. React Envia POST
```bash
POST /wp-json/fpse/v1/register
Body: {
  fpse_nonce: "abc123xyz",
  nome_completo: "João Silva",
  cpf: "12345678900",  // sem máscaras
  email: "joao@example.com",
  // ... todos os campos
}
```

### 5. WordPress Processa
```php
// Valida nonce
// Valida rate limit
// Valida dados
// Cria usuário no wp_users
// Armazena dados em wp_usermeta
// Registra evento em wp_fpse_events
// Retorna sucesso
```

### 6. WordPress Responde
```json
{
  "success": true,
  "message": "Usuário criado com sucesso",
  "user_id": 42,
  "perfil": "estudante-ies",
  "estado": "CE"
}
```

### 7. React Exibe Sucesso
```
Modal de sucesso:
"Cadastro realizado com sucesso!"
"ID: 42"
[Botão: Ir para Dashboard]
```

### 8. Dados Salvos em Banco
```
wp_users:
  ID: 42
  user_login: "joao.silva@example.com"
  user_email: "joao@example.com"

wp_usermeta:
  nome_completo: "João Silva"
  cpf: "12345678900"
  perfil_usuario: "estudante-ies"
  estado: "CE"
  ...30+ mais campos

wp_fpse_events:
  user_id: 42
  event: "user_registered"
  perfil: "estudante-ies"
  estado: "CE"
  created_at: 2026-01-15 19:05:00
```

---

## 🛠️ Código Necessário no React

### Arquivo: `src/services/registrationService.ts`
```typescript
import axios from 'axios';

const API_URL = process.env.REACT_APP_API_URL || 'http://localhost/wp-json';

export const registrationService = {
  async getNonce() {
    const res = await axios.get(`${API_URL}/fpse/v1/nonce`);
    return res.data.nonce;
  },

  async submitRegistration(data) {
    const nonce = await this.getNonce();
    const response = await axios.post(`${API_URL}/fpse/v1/register`, {
      fpse_nonce: nonce,
      ...data
    });
    return response.data;
  }
};
```

### Arquivo: `src/components/ResumoStep.tsx`
```typescript
import { registrationService } from '../services/registrationService';

export function ResumoStep({ data, onPrevious, onSuccess }) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async () => {
    setLoading(true);
    try {
      const response = await registrationService.submitRegistration(data);
      if (response.success) {
        onSuccess?.(response);
      } else {
        setError(response.message);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      {error && <div className="error">{error}</div>}
      <button onClick={handleSubmit} disabled={loading}>
        {loading ? 'Enviando...' : 'Finalizar'}
      </button>
    </div>
  );
}
```

### Arquivo: `.env`
```
REACT_APP_API_URL=http://localhost/wp-json
```

---

## ✅ Checklist de Integração

- [ ] Arquivo `registrationService.ts` criado
- [ ] Variável de ambiente `REACT_APP_API_URL` definida
- [ ] Componente `ResumoStep` atualizado com submit
- [ ] CORS configurado no WordPress
- [ ] Plugin FPSE Core ativado
- [ ] Testou endpoint `/wp-json/fpse/v1/nonce`
- [ ] Testou POST em `/wp-json/fpse/v1/register`
- [ ] Cadastro criado no banco de dados
- [ ] Evento registrado em wp_fpse_events
- [ ] Sucesso exibido no React

**Status**: ✅ Integração Completa!
