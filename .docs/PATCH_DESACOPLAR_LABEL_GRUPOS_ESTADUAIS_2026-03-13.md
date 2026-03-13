# Patch para Desacoplar o Label Visual dos Grupos Estaduais

Data: 2026-03-13

## 1. Resumo executivo

Foi aplicado um patch pequeno e localizado no `fpse-core` para remover a dependência residual do label visual `Estado - XX`.

O fluxo principal já operava por:

- `slug`
- `group_id`
- mapeamento por UF

O patch atuou apenas nos pontos operacionais restantes:

1. o `StateGroupSeeder` deixou de sobrescrever o nome visual de grupos estaduais já existentes
2. as rotinas auxiliares/admin deixaram de depender de `search_terms = 'estado-'`

Com isso, o sistema fica preparado para uma futura renomeação visual dos grupos estaduais sem reintroduzir acoplamento ao nome textual atual.

## 2. Arquivos alterados

- `src/Seeders/StateGroupSeeder.php`
- `src/Admin/SettingsPage.php`
- `templates/admin-dashboard.php`

## 3. O que foi ajustado no `StateGroupSeeder`

### 3.1 Nome visual não é mais imposto em updates

Antes:

- o método `createOrUpdateStateGroup()` sempre montava `name = "Estado - {$uf}"`
- isso fazia com que grupos existentes pudessem ter o nome visual restaurado em futuras execuções do seeder

Agora:

- grupos existentes são localizados por `slug`
- o update continua usando `group_id`
- o `name` não é mais reenviado no fluxo de atualização

Efeito:

- o seeder não volta a acoplar o sistema ao label visual anterior
- renomeações futuras de grupos existentes não serão revertidas por um update normal do seeder

### 3.2 Criação de grupos novos continua compatível

Para ambientes onde o grupo ainda não existe:

- o seeder continua atribuindo um nome padrão inicial via `getDefaultStateGroupName($uf)`

Isso preserva compatibilidade com:

- instalações novas
- ambientes incompletos
- fallback de criação automática no cadastro

### 3.3 Enumeração de grupos estaduais passou a usar slugs esperados

Antes:

- `getAllStateGroups()` usava `groups_get_groups(['search_terms' => 'estado-'])`

Agora:

- `getAllStateGroups()` enumera os slugs canônicos a partir das UFs configuradas
- cada grupo é resolvido via `findGroupBySlug($slug)`

Foram adicionados:

- `countStateGroups()`
- `getExpectedStateGroupSlugs()`

Efeito:

- a identificação dos grupos estaduais passou a depender apenas do identificador estável `estado-{uf}`
- o nome visível pode mudar sem afetar essa resolução

## 4. O que foi ajustado nas rotinas administrativas

### 4.1 `SettingsPage.php`

Antes:

- a tela de configurações contava grupos estaduais com:

```php
groups_get_groups([
    'per_page' => 100,
    'search_terms' => 'estado-',
]);
```

Agora:

- a contagem usa `StateGroupSeeder($states)->countStateGroups()`

Efeito:

- a tela admin não depende mais de prefixo textual no nome do grupo

### 4.2 `templates/admin-dashboard.php`

Antes:

- o dashboard também usava `search_terms = 'estado-'`

Agora:

- o dashboard reutiliza o `StateGroupSeeder` para contar grupos por slug esperado

Efeito:

- a camada visual do admin fica consistente com a nova regra estável

## 5. Como o patch prepara a futura renomeação visual

Depois deste patch:

- grupos estaduais existentes podem ser renomeados visualmente para `SP`, `PE`, `AL` etc.
- o `fpse-core` não deve regravar automaticamente `Estado - XX` em updates de grupos já existentes
- rotinas admin não dependem mais de o nome conter `Estado` ou `estado-`

Em outras palavras:

- o nome visual passa a ser tratado como apresentação
- a identidade funcional do grupo continua baseada em `slug` e `group_id`

## 6. Evidências de validação

### 6.1 Validação sintática

Comandos executados:

- `php -l src/Seeders/StateGroupSeeder.php`
- `php -l src/Admin/SettingsPage.php`
- `php -l templates/admin-dashboard.php`

Resultado:

- sem erros de sintaxe nos três arquivos

### 6.2 Verificação de dependência textual residual

Foi verificado que não restaram ocorrências de:

```php
search_terms = 'estado-'
```

no `fpse-core`.

### 6.3 Preservação do fluxo principal

O fluxo principal permanece inalterado nos pontos críticos:

- o vínculo automático no `UserService` continua resolvendo o grupo por `slug`
- o join do usuário continua por `group_id`
- o MU-plugin territorial continua resolvendo grupos por `slug` e `group_id`

## 7. Riscos residuais

### 7.1 Criação inicial ainda usa nome padrão antigo

Para grupos novos, o nome padrão inicial continua sendo:

- `Estado - XX`

Isso é intencional nesta etapa para preservar compatibilidade de criação.

Implicação:

- o patch prepara a renomeação visual
- mas não altera automaticamente o padrão exibido em grupos criados do zero

### 7.2 Validação funcional em WordPress/BuddyBoss real ainda é recomendada

O patch foi validado de forma:

- estática
- sintática
- por auditoria de fluxo

Ainda é recomendável smoke test em ambiente WordPress/BuddyBoss para confirmar:

- cadastro com criação/vínculo por UF
- tela admin com contagem de grupos
- ausência de regressão operacional

## 8. Conclusão

O patch removeu o acoplamento residual mais relevante ao label visual `Estado - XX` sem alterar o fluxo principal do sistema.

Resultado prático:

- o `fpse-core` ficou mais estável e previsível
- a futura renomeação visual dos grupos estaduais passa a ser viável sem que o seeder restaure automaticamente o nome antigo
- as rotinas administrativas passaram a usar critério estável por slug/UF, e não busca textual frágil

Status final:

- **patch concluído**
- **sistema preparado para etapa futura de renomeação visual**
