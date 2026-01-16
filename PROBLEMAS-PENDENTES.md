# Problemas Pendentes - Sistema de Cadastro FPSE

## Data: 16 de Janeiro de 2026

---

## 🚨 Problema Crítico #1: Dados Não Estão Sendo Salvos

### Descrição

Ao cadastrar um usuário via formulário React:
- ✅ Usuário WordPress é criado
- ✅ Email e senha são salvos
- ✅ Perfil (role) é atribuído
- ❌ **Todos os outros dados se perdem** (CPF, telefone, endereço, campos específicos do perfil, etc.)

### Evidência

- Apenas campos básicos aparecem no perfil do usuário no WordPress Admin
- Campos personalizados não estão em `wp_usermeta`

### Causa Provável

#### Hipótese 1: Campos sendo removidos no frontend

**Localização:** `src/services/registrationService.ts` linha 147-151

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

**Problema:** Campos podem estar sendo removidos mesmo quando têm valores válidos.

**Ação:**
1. Adicionar log antes e depois da remoção de campos vazios
2. Verificar se campos estão chegando no backend (log em `RegistrationController.php` linha 172)

---

#### Hipótese 2: `RegistrationDTO::toArray()` não inclui todos os campos

**Localização:** `fpse-core/src/Domain/RegistrationDTO.php`

**Problema:** O método `toArray()` pode não estar incluindo campos específicos do perfil que estão em `profileSpecificFields`.

**Análise:**
- Campos padrão são mapeados corretamente (linha 105-143)
- Campos específicos do perfil são armazenados em `profileSpecificFields` (linha 87-93)
- `toArray()` inclui `profileSpecificFields` (linha 138-143)

**Ação:**
1. Verificar se campos específicos estão sendo adicionados a `profileSpecificFields` em `fromArray()`
2. Adicionar log para ver quais campos estão em `profileSpecificFields`

---

#### Hipótese 3: `storeUserMeta()` não está salvando corretamente

**Localização:** `fpse-core/src/Services/UserService.php` linha 216-235

**Problema:** Método pode ter problema na conversão de chaves ou salvamento.

**Ação:**
1. Adicionar log detalhado mostrando:
   - Todos os campos que chegam em `$data`
   - Cada chave antes e depois da conversão
   - Se `update_user_meta()` está retornando sucesso
2. Verificar se campos estão sendo salvos com prefixo `fpse_` e sem prefixo

---

### Plano de Ação

1. **Adicionar logs detalhados em cada etapa:**
   ```php
   // Em RegistrationController.php, após criar DTO
   error_log('FPSE: DTO array completo - ' . wp_json_encode($dto->toArray()));
   
   // Em UserService.php, no início de storeUserMeta()
   error_log('FPSE: Campos para salvar - ' . wp_json_encode(array_keys($data)));
   
   // Dentro do loop de storeUserMeta()
   error_log("FPSE: Salvando {$fullMetaKey} = " . wp_json_encode($value));
   ```

2. **Verificar no banco de dados:**
   ```sql
   SELECT * FROM wp_usermeta 
   WHERE user_id = 493 
   AND (meta_key LIKE 'fpse_%' OR meta_key IN ('cpf', 'telefone', 'logradouro', ...));
   ```

3. **Testar salvamento manual:**
   ```php
   // Testar se update_user_meta funciona diretamente
   update_user_meta(493, 'fpse_test', 'valor_teste');
   update_user_meta(493, 'test', 'valor_teste');
   ```

---

## 🚨 Problema Crítico #2: Erro ao Finalizar Cadastro

### Descrição

Após cadastrar usuário com sucesso:
- ✅ Usuário é criado
- ✅ Dados básicos são salvos
- ✅ Perfil é atribuído
- ❌ **Retorna erro crítico do WordPress** (página HTML)
- ❌ Frontend não recebe resposta JSON de sucesso

### Evidência

- Frontend recebe erro 500 (Internal Server Error)
- Mensagem de erro HTML aparece: "Há um erro crítico no seu site"
- Cadastro é bem-sucedido, mas usuário não sabe

### Causa Provável

#### Hipótese 1: Erro após cadastro bem-sucedido

**Possíveis locais:**
1. `recordProfileAssigned()` - Registro de evento
2. `recordStateAssigned()` - Registro de evento
3. `assignBuddyBossMemberType()` - Atribuição de member type
4. `assignUserToStateGroup()` - Atribuição ao grupo do estado

**Ação:**
1. Adicionar try-catch em cada uma dessas operações
2. Verificar logs do WordPress após cadastro
3. Testar desabilitando temporariamente cada uma dessas operações

---

#### Hipótese 2: Erro no logger

**Localização:** `fpse-core/src/REST/RegistrationController.php` linha 282-286

```php
$this->plugin->getLogger()->info('User registered successfully', [...]);
```

**Ação:**
1. Envolver em try-catch
2. Verificar se logger está configurado corretamente

---

#### Hipótese 3: Problema ao retornar resposta

**Localização:** `fpse-core/src/REST/RegistrationController.php` linha 288-294

**Ação:**
1. Verificar se não há output antes da resposta
2. Verificar se headers estão corretos
3. Testar retornar resposta simples primeiro

---

### Plano de Ação

1. **Adicionar tratamento de erro mais robusto:**
   ```php
   try {
       // ... todo o processo de cadastro ...
       
       return new \WP_REST_Response([
           'success' => true,
           'message' => 'Cadastro realizado com sucesso!',
           'user_id' => $result['user_id'],
       ], 201);
   } catch (\Exception $e) {
       error_log('FPSE: Erro final no cadastro - ' . $e->getMessage());
       error_log('FPSE: Stack trace - ' . $e->getTraceAsString());
       
       return new \WP_REST_Response([
           'success' => false,
           'message' => 'Erro ao finalizar cadastro: ' . $e->getMessage(),
       ], 500);
   }
   ```

2. **Testar cada etapa isoladamente:**
   - Testar apenas criação de usuário (sem eventos, sem BuddyBoss)
   - Adicionar cada funcionalidade uma por vez
   - Identificar qual está causando o erro

3. **Verificar logs do WordPress:**
   - Verificar `wp-content/debug.log` após cada cadastro
   - Procurar por "Fatal error", "Warning", ou "Notice"

---

## 🔧 Correções Recomendadas

### 1. Adicionar Logs Detalhados

**Arquivo:** `fpse-core/src/Services/UserService.php`

```php
private function storeUserMeta($userId, RegistrationDTO $dto) {
    $data = $dto->toArray();
    
    error_log('FPSE: Total de campos para salvar: ' . count($data));
    error_log('FPSE: Chaves dos campos: ' . wp_json_encode(array_keys($data)));
    
    foreach ($data as $key => $value) {
        if ($value === '' || $value === null) {
            error_log("FPSE: Pulando campo vazio: {$key}");
            continue;
        }
        
        $metaKey = $this->camelToSnakeCase($key);
        $fullMetaKey = 'fpse_' . $metaKey;
        
        error_log("FPSE: Salvando {$fullMetaKey} = " . wp_json_encode($value));
        error_log("FPSE: Também salvando {$metaKey} = " . wp_json_encode($value));
        
        $result1 = update_user_meta($userId, $fullMetaKey, $value);
        $result2 = update_user_meta($userId, $metaKey, $value);
        
        if (!$result1 && !$result2) {
            error_log("FPSE: ATENÇÃO - Falha ao salvar {$metaKey}");
        }
    }
    
    // Verificar se foi salvo
    $saved = get_user_meta($userId, 'fpse_perfil_usuario', true);
    error_log('FPSE: Verificação - perfil_usuario salvo = ' . wp_json_encode($saved));
}
```

---

### 2. Verificar DTO::toArray()

**Arquivo:** `fpse-core/src/Domain/RegistrationDTO.php`

Adicionar log no método `toArray()`:

```php
public function toArray() {
    // ... código existente ...
    
    // Debug
    error_log('FPSE DTO: Campos específicos do perfil = ' . wp_json_encode($this->profileSpecificFields));
    error_log('FPSE DTO: Total de campos no array = ' . count($result));
    error_log('FPSE DTO: Chaves no array = ' . wp_json_encode(array_keys($result)));
    
    return $result;
}
```

---

### 3. Melhorar Tratamento de Erros

**Arquivo:** `fpse-core/src/REST/RegistrationController.php`

```php
public function handleRegister($request) {
    try {
        // ... validações ...
        
        // Create or update user
        $result = $this->userService->createOrUpdate($dto);
        
        if (!$result['success']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }
        
        // Tentar registrar eventos, mas não falhar se der erro
        try {
            $this->eventRecorder->recordProfileAssigned(...);
        } catch (\Exception $e) {
            error_log('FPSE: Erro ao registrar profile_assigned: ' . $e->getMessage());
        }
        
        try {
            $this->eventRecorder->recordStateAssigned(...);
        } catch (\Exception $e) {
            error_log('FPSE: Erro ao registrar state_assigned: ' . $e->getMessage());
        }
        
        // Retornar sucesso SEMPRE, mesmo se eventos falharem
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Cadastro realizado com sucesso! Bem-vindo ao Fortalece PSE.',
            'user_id' => $result['user_id'],
            'perfil' => $dto->perfilUsuario,
            'estado' => $dto->estado,
            'redirect_url' => home_url('/'),
        ], 201);
        
    } catch (\Exception $e) {
        error_log('FPSE: Erro crítico no handleRegister: ' . $e->getMessage());
        error_log('FPSE: Stack trace: ' . $e->getTraceAsString());
        
        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Erro ao processar cadastro. Por favor, tente novamente.',
            'error' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : null,
        ], 500);
    }
}
```

---

## 📋 Checklist de Testes

### Para Problema #1 (Dados Não Salvos)

- [ ] Verificar se todos os campos estão sendo enviados do frontend (console.log no `registrationService.ts`)
- [ ] Verificar se campos chegam no backend (log em `RegistrationController.php`)
- [ ] Verificar se DTO está convertendo corretamente (log em `RegistrationDTO::toArray()`)
- [ ] Verificar se `storeUserMeta()` está recebendo todos os campos (log no início do método)
- [ ] Verificar se campos estão sendo salvos (log dentro do loop)
- [ ] Verificar no banco de dados se campos foram salvos (query SQL)
- [ ] Testar salvamento manual de um campo específico

### Para Problema #2 (Erro ao Finalizar)

- [ ] Testar cadastro desabilitando registro de eventos
- [ ] Testar cadastro desabilitando atribuição de member type
- [ ] Testar cadastro desabilitando atribuição de grupo
- [ ] Verificar logs do WordPress após cada teste
- [ ] Testar retornar resposta simples antes de todas as operações
- [ ] Verificar se há output antes da resposta JSON
- [ ] Verificar se headers CORS estão corretos

---

## 🎯 Resultado Esperado

### Após Corrigir Problema #1

- Todos os campos do formulário devem ser salvos em `wp_usermeta`
- Campos devem estar disponíveis com prefixo `fpse_` e sem prefixo
- Campos devem aparecer no perfil do usuário no WordPress Admin

### Após Corrigir Problema #2

- Cadastro deve retornar JSON de sucesso (status 201)
- Frontend deve mostrar mensagem de boas-vindas
- Redirecionamento deve funcionar corretamente
- Sem erros críticos do WordPress

---

**Última Atualização:** 16 de Janeiro de 2026
