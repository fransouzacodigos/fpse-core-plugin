# Diagnóstico: Por Que os Dados Não Estão Sendo Salvos

## Data: 16 de Janeiro de 2026

---

## 🔍 Análise do Fluxo de Dados

### Fluxo Completo

1. **Frontend - React Form**
   ```
   RegistrationForm.tsx
   → Coleta dados de todas as etapas
   → handleSubmit() chama registrationService.submitRegistration()
   ```

2. **Frontend - Service Layer**
   ```
   registrationService.ts
   → mapFormData() converte camelCase para snake_case
   → Remove campos vazios (exceto senha_login)
   → Envia para /wp-json/fpse/v1/register
   ```

3. **Backend - REST Controller**
   ```
   RegistrationController.php
   → Recebe JSON via $request->get_body()
   → RegistrationDTO::fromArray() cria DTO
   → UserService::createOrUpdate() processa
   ```

4. **Backend - User Service**
   ```
   UserService.php
   → createUser() cria usuário WordPress
   → storeUserMeta() deveria salvar todos os campos
   ```

---

## 🐛 Problemas Identificados

### Problema #1: Campos Podem Estar Sendo Removidos no Frontend

**Localização:** `src/services/registrationService.ts` linha 147-154

**Código Atual:**
```typescript
// Remover campos vazios (exceto senha_login que é obrigatória)
Object.keys(mapped).forEach((key) => {
  if (key === 'senha_login') {
    return;
  }
  if (mapped[key] === '' || mapped[key] === null || mapped[key] === undefined) {
    delete mapped[key];
  }
});
```

**Problema:**
- Strings vazias (`''`) são removidas
- `null` e `undefined` são removidos
- Isso é correto, MAS precisamos verificar se campos válidos estão chegando aqui

**Ação Recomendada:**
Adicionar log antes e depois da remoção:

```typescript
console.log('FPSE: Campos ANTES de remover vazios:', Object.keys(mapped).length);
Object.keys(mapped).forEach((key) => {
  // ... código existente ...
});
console.log('FPSE: Campos DEPOIS de remover vazios:', Object.keys(mapped).length);
console.log('FPSE: Campos que serão enviados:', Object.keys(mapped));
```

---

### Problema #2: RegistrationDTO::fromArray() Pode Não Estar Incluindo Campos Específicos

**Localização:** `fpse-core/src/Domain/RegistrationDTO.php` linha 53-96

**Análise:**

O método `fromArray()` tem um `$mapping` que só inclui campos padrão:

```php
$mapping = [
    'nome_completo' => 'nomeCompleto',
    'cpf' => 'cpf',
    // ... outros campos padrão ...
];
```

Campos que NÃO estão no `$mapping` são armazenados em `profileSpecificFields`:

```php
// Store any remaining fields as profile-specific
foreach ($data as $key => $value) {
    if (!isset($mapping[$key]) && $key !== 'fpse_nonce') {
        $dto->profileSpecificFields[$key] = $value;
    }
}
```

**Problema Potencial:**
- Campos específicos do perfil (como `instituicao_nome`, `setor_gti`, etc.) vão para `profileSpecificFields`
- Isso está CORRETO
- Mas precisamos verificar se `toArray()` está incluindo esses campos

**Verificação Necessária:**
```php
// Em RegistrationDTO::toArray(), adicionar:
error_log('FPSE DTO: profileSpecificFields = ' . wp_json_encode($this->profileSpecificFields));
error_log('FPSE DTO: Total de campos no array final = ' . count($result));
```

---

### Problema #3: storeUserMeta() Pode Ter Problema na Conversão

**Localização:** `fpse-core/src/Services/UserService.php` linha 228

**Método `camelToSnakeCase()`:**
```php
private function camelToSnakeCase($str) {
    $str = preg_replace('/[A-Z]/', '_$0', $str);
    return strtolower(trim($str, '_'));
}
```

**Teste:**
- `nomeCompleto` → `_nome_completo` → `nome_completo` ✓
- `perfilUsuario` → `_perfil_usuario` → `perfil_usuario` ✓
- `instituicao_nome` → `_instituicao_nome` → `instituicao_nome` ✓ (mas já está em snake_case!)

**Problema Potencial:**
- Campos que já estão em `snake_case` (vindos de `profileSpecificFields`) podem ter conversão incorreta
- Exemplo: `instituicao_nome` → `_instituicao_nome` → `instituicao_nome` (OK, mas tem underscore extra no início)

**Solução:**
Verificar se a chave já está em snake_case antes de converter:

```php
private function camelToSnakeCase($str) {
    // Se já está em snake_case, retornar como está
    if (preg_match('/^[a-z][a-z0-9_]*(_[a-z0-9]+)*$/', $str)) {
        return $str;
    }
    
    // Converter camelCase para snake_case
    $str = preg_replace('/[A-Z]/', '_$0', $str);
    return strtolower(trim($str, '_'));
}
```

---

## 🧪 Testes de Diagnóstico Recomendados

### Teste 1: Verificar Dados no Frontend

Adicionar em `registrationService.ts` após `mapFormData()`:

```typescript
console.log('FPSE: Payload completo ANTES de enviar:', JSON.stringify(payload, null, 2));
```

### Teste 2: Verificar Dados no Backend

Adicionar em `RegistrationController.php` após receber dados:

```php
error_log('FPSE: Dados recebidos do frontend: ' . wp_json_encode($body, JSON_PRETTY_PRINT));
```

### Teste 3: Verificar DTO

Adicionar em `RegistrationController.php` após criar DTO:

```php
error_log('FPSE: DTO criado - perfil: ' . $dto->perfilUsuario);
error_log('FPSE: DTO profileSpecificFields: ' . wp_json_encode($dto->profileSpecificFields));
error_log('FPSE: DTO toArray(): ' . wp_json_encode($dto->toArray(), JSON_PRETTY_PRINT));
```

### Teste 4: Verificar Salvamento

O log já existe em `storeUserMeta()` linha 219. Verificar se os campos listados estão corretos.

### Teste 5: Verificar no Banco de Dados

```sql
-- Ver todos os meta do usuário
SELECT meta_key, meta_value 
FROM wp_usermeta 
WHERE user_id = 493
ORDER BY meta_key;

-- Ver especificamente campos FPSE
SELECT meta_key, meta_value 
FROM wp_usermeta 
WHERE user_id = 493 
AND meta_key LIKE 'fpse_%'
ORDER BY meta_key;
```

---

## ✅ Checklist de Verificação

### No Frontend

- [ ] Verificar console.log do payload completo antes de enviar
- [ ] Verificar se todos os campos estão presentes no payload
- [ ] Verificar se campos específicos do perfil estão sendo incluídos

### No Backend

- [ ] Verificar log do `$body` recebido
- [ ] Verificar log do DTO criado
- [ ] Verificar log do `profileSpecificFields`
- [ ] Verificar log do `toArray()` completo
- [ ] Verificar log de `storeUserMeta()` mostrando campos que serão salvos
- [ ] Verificar no banco de dados se campos foram salvos

---

## 🔧 Correções Imediatas Recomendadas

### 1. Melhorar camelToSnakeCase()

```php
private function camelToSnakeCase($str) {
    // Se já está em snake_case, retornar como está
    if (preg_match('/^[a-z][a-z0-9_]*(_[a-z0-9]+)*$/', $str)) {
        return $str;
    }
    
    // Converter camelCase para snake_case
    $str = preg_replace('/[A-Z]/', '_$0', $str);
    return strtolower(trim($str, '_'));
}
```

### 2. Adicionar Logs Detalhados

Ver seção "Testes de Diagnóstico" acima.

### 3. Testar Salvamento Manual

Após cadastrar, testar salvar um campo manualmente via SQL ou código:

```php
// Testar salvamento manual
update_user_meta(493, 'fpse_cpf_teste', '12345678900');
$teste = get_user_meta(493, 'fpse_cpf_teste', true);
error_log('FPSE Teste: Valor salvo = ' . $teste);
```

---

**Última Atualização:** 16 de Janeiro de 2026
