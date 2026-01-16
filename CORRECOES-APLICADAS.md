# Correções Aplicadas e Pendências

## Data: 16 de Janeiro de 2026

## 📋 Resumo Executivo

Este documento registra todas as correções aplicadas ao sistema de cadastro do Fortalece PSE e identifica os problemas que ainda precisam ser resolvidos.

---

## ✅ Correções Aplicadas

### 1. Perfis Faltantes no Backend

**Problema:** O frontend tinha 13 perfis, mas o backend só reconhecia 12.

**Solução:**
- Adicionados todos os perfis faltantes em `config/profiles.php`
- Perfis adicionados: `bolsista-ies`, `voluntario-ies`, `coordenador-ies`, `jovem-mobilizador-nap`, `apoiador-pedagogico-nap`, `coordenacao-nap`, `gti-m`, `gti-e`, `coordenacao-fortalece-pse`, `representante-ms-mec`, `profissional-saude-eaa`, `profissional-educacao-eaa`

**Arquivos Modificados:**
- `fpse-core/config/profiles.php`

---

### 2. Validação de Perfil

**Problema:** A validação de perfil não estava acessando os campos específicos do perfil corretamente.

**Solução:**
- Alterado de `(array) $dto` para `$dto->toArray()` em `RegistrationController.php`
- Melhorada validação de campos vazios em `ProfileResolver.php`

**Arquivos Modificados:**
- `fpse-core/src/REST/RegistrationController.php`
- `fpse-core/src/Services/ProfileResolver.php`

---

### 3. Mapeamento de Senha

**Problema:** O formulário usava o campo `senha`, mas o serviço esperava `senhaLogin`.

**Solução:**
- Corrigido mapeamento em `registrationService.ts` de `data.senhaLogin` para `data.senha`
- Protegido campo `senha_login` para não ser removido quando vazio

**Arquivos Modificados:**
- `src/services/registrationService.ts`

---

### 4. Rate Limiting

**Problema:** Rate limit muito baixo (5 req/hora) bloqueando testes.

**Solução:**
- Aumentado limite para 1000 req/hora em desenvolvimento (WP_DEBUG)
- Adicionado botão na página de configurações para resetar rate limit
- Melhorado tratamento de IPs em desenvolvimento

**Arquivos Modificados:**
- `fpse-core/config/permissions.php`
- `fpse-core/src/Security/RateLimit.php`
- `fpse-core/src/Admin/SettingsPage.php`

---

### 5. Atribuição de Member Type do BuddyBoss

**Problema:** Member types não eram atribuídos aos usuários após cadastro.

**Solução:**
- Adicionado método `assignBuddyBossMemberType()` em `UserService.php`
- Integrado no fluxo de criação e atualização de usuários
- Adicionado método público `recordMemberTypeAssigned()` em `EventRecorder.php`

**Arquivos Modificados:**
- `fpse-core/src/Services/UserService.php`
- `fpse-core/src/Services/EventRecorder.php`

---

### 6. Campos Específicos do Perfil GTI-M

**Problema:** Perfil `gti-m` requer `setor_gti` e `sistema_responsavel`, mas campos não existiam no frontend.

**Solução:**
- Adicionados campos `setorGti` e `sistemaResponsavel` ao tipo `FormData`
- Criados campos no formulário `InformacoesEspecificasStep`
- Adicionada validação no schema
- Adicionado mapeamento no `registrationService.ts`
- Adicionado `gti-m` à lista de perfis que mostram Informações Específicas
- Corrigido perfil `gti-e` para incluir os mesmos campos

**Arquivos Modificados:**
- `src/types/index.ts`
- `src/schemas/index.ts`
- `src/components/InformacoesEspecificasStep.tsx`
- `src/services/registrationService.ts`
- `src/components/RegistrationForm.tsx`
- `fpse-core/config/profiles.php`

---

### 7. Navegação Entre Etapas

**Problema:** Formulário pulava da etapa 2 (Endereco) para etapa 5 (Resumo), ignorando etapas 3 e 4.

**Solução:**
- Reescrita completa da lógica de navegação em `handleNext()` e `handlePrev()`
- Adicionados logs de debug para rastreamento
- Garantida preservação do `perfilUsuario` entre etapas

**Arquivos Modificados:**
- `src/components/RegistrationForm.tsx`

---

### 8. Mensagem de Sucesso e Redirecionamento

**Problema:** Mensagem de erro genérica do WordPress aparecia após cadastro bem-sucedido.

**Solução:**
- Melhorado tratamento de erros HTML no frontend
- Adicionada mensagem de boas-vindas personalizada
- Implementado redirecionamento automático após 3 segundos
- Adicionados try-catch em todas as operações do backend

**Arquivos Modificados:**
- `src/components/SuccessMessage.tsx`
- `src/components/RegistrationForm.tsx`
- `src/services/registrationService.ts`
- `fpse-core/src/REST/RegistrationController.php`

---

## ⚠️ Problemas Pendentes

### 1. Erro Crítico ao Finalizar Cadastro

**Problema:**
- Ao finalizar o cadastro, ainda aparece erro crítico do WordPress
- Mesmo quando o cadastro é bem-sucedido (usuário criado, perfil atribuído)

**Possíveis Causas:**
- Erro após o cadastro ser concluído (ex: ao registrar eventos)
- Problema na resposta HTTP
- Erro ao atribuir member type ou grupo do BuddyBoss

**Investigação Necessária:**
- Verificar logs do WordPress após cadastro bem-sucedido
- Verificar se eventos estão sendo registrados corretamente
- Verificar se atribuição de member type e grupo está funcionando

---

### 2. Dados do Formulário Não Estão Sendo Salvos

**Problema:**
- Apenas email, perfil e senha são salvos no WordPress
- Todos os outros campos (CPF, telefone, endereço, etc.) se perdem

**Análise do Código:**

O método `storeUserMeta()` em `UserService.php` deveria salvar todos os campos:

```php
private function storeUserMeta($userId, RegistrationDTO $dto) {
    $data = $dto->toArray();
    
    foreach ($data as $key => $value) {
        if ($value === '' || $value === null) {
            continue; // Skip empty values
        }
        
        $metaKey = $this->camelToSnakeCase($key);
        $fullMetaKey = 'fpse_' . $metaKey;
        update_user_meta($userId, $fullMetaKey, $value);
        update_user_meta($userId, $metaKey, $value);
    }
}
```

**Possíveis Causas:**
1. O método `toArray()` do DTO pode não estar incluindo todos os campos
2. Os campos podem estar vazios quando chegam no backend
3. Os campos podem estar sendo removidos antes de salvar (lógica de remoção de campos vazios no frontend)
4. Problema na conversão camelCase para snake_case

**Próximos Passos:**
1. Adicionar logs detalhados no `storeUserMeta()` para ver quais campos estão chegando
2. Verificar se `$dto->toArray()` está retornando todos os campos corretamente
3. Verificar se campos não estão sendo removidos no frontend antes de enviar

---

## 🔍 Análise Técnica Detalhada

### Fluxo de Dados Atual

1. **Frontend (React)**
   - Usuário preenche formulário
   - `RegistrationForm.tsx` coleta dados de todas as etapas
   - `registrationService.ts` mapeia dados (camelCase → snake_case)
   - Remove campos vazios (exceto `senha_login`)
   - Envia para `/wp-json/fpse/v1/register`

2. **Backend (WordPress)**
   - `RegistrationController.php` recebe dados
   - Valida nonce e rate limit
   - Cria `RegistrationDTO` a partir dos dados
   - Valida perfil e estado
   - Chama `UserService::createOrUpdate()`
   - `UserService` cria usuário WordPress
   - `storeUserMeta()` deveria salvar todos os campos como `user_meta`

### Pontos de Falha Identificados

1. **Mapeamento de Campos no Frontend**
   - Campos podem estar sendo removidos antes de enviar
   - Verificar `registrationService.ts` linha 147-151

2. **Conversão DTO → Array**
   - `RegistrationDTO::toArray()` pode não estar incluindo todos os campos
   - Verificar se campos específicos do perfil estão em `profileSpecificFields`

3. **Salvamento de Meta**
   - `storeUserMeta()` pode ter problema na conversão de chaves
   - Verificar se campos com prefixo estão sendo salvos corretamente

---

## 📝 Próximos Passos Recomendados

### Prioridade Alta

1. **Corrigir Salvamento de Dados**
   - [ ] Adicionar logs detalhados em `storeUserMeta()` para ver exatamente quais campos estão chegando
   - [ ] Verificar se `$dto->toArray()` está incluindo todos os campos (incluindo `profileSpecificFields`)
   - [ ] Testar salvamento de cada tipo de campo individualmente
   - [ ] Verificar se campos não estão sendo removidos no frontend antes de enviar

2. **Corrigir Erro ao Finalizar**
   - [ ] Verificar logs do WordPress após cadastro bem-sucedido
   - [ ] Adicionar try-catch mais específicos em cada etapa do processo
   - [ ] Verificar se erro está acontecendo após salvar dados (ex: ao registrar eventos)
   - [ ] Testar desabilitando temporariamente registro de eventos

### Prioridade Média

3. **Melhorar Tratamento de Erros**
   - [ ] Retornar erros detalhados em formato JSON (não HTML)
   - [ ] Adicionar códigos de erro específicos para cada tipo de problema
   - [ ] Melhorar mensagens de erro no frontend

4. **Completar Campos de Outros Perfis**
   - [ ] Verificar se todos os perfis têm campos específicos configurados no frontend
   - [ ] Adicionar campos faltantes para perfis como `coordenador-ies`, `coordenacao-nap`, etc.

### Prioridade Baixa

5. **Otimizações e Melhorias**
   - [ ] Remover logs de debug em produção
   - [ ] Adicionar testes automatizados
   - [ ] Melhorar documentação da API

---

## 🧪 Testes Realizados

### ✅ Funcionando

- [x] Criação de usuário WordPress
- [x] Atribuição de perfil (role)
- [x] Atribuição de member type do BuddyBoss
- [x] Validação de campos obrigatórios
- [x] Validação de perfil
- [x] Rate limiting (com reset manual)
- [x] Navegação entre etapas do formulário
- [x] Exibição de campos específicos do perfil (gti-m)

### ❌ Não Funcionando

- [ ] Salvamento completo de dados (apenas email, perfil e senha)
- [ ] Mensagem de sucesso sem erro HTML
- [ ] Registro de eventos após cadastro (pode estar causando erro)

---

## 📚 Arquivos de Configuração Importantes

### Backend (WordPress)

- **Perfis:** `fpse-core/config/profiles.php`
- **Estados:** `fpse-core/config/states.php`
- **Campos de Relatório:** `fpse-core/config/report_fields.php`
- **Permissões:** `fpse-core/config/permissions.php`

### Frontend (React)

- **Tipos:** `src/types/index.ts`
- **Schemas de Validação:** `src/schemas/index.ts`
- **Serviço de API:** `src/services/registrationService.ts`

---

## 🔗 Recursos Úteis

- **Documentação da API:** `fpse-core/INTEGRACAO.md`
- **Configuração CORS:** `fpse-core/CONFIGURACAO-CORS.md`
- **Migração Produção:** `fpse-core/MIGRACAO-PRODUCAO.md`
- **Configuração Vercel:** `fpse-core/CONFIGURACAO-VERCEL.md`

---

## 📞 Contato e Suporte

Para dúvidas ou problemas:
1. Verificar logs do WordPress em `wp-content/debug.log`
2. Verificar logs do servidor web
3. Verificar console do navegador para erros JavaScript
4. Verificar Network tab para ver requisições HTTP

---

**Última Atualização:** 16 de Janeiro de 2026
