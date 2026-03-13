# Renomeação Visual dos Grupos Estaduais para UF Simples

Data: 2026-03-13

## 1. Resumo executivo

Foi aplicado um patch no `fpse-core` para simplificar o nome visual dos grupos estaduais existentes, alterando o padrão de exibição de:

- `Estado - SP`
- `Estado - PE`
- `Estado - AL`

para:

- `SP`
- `PE`
- `AL`

O patch preserva os identificadores funcionais do sistema:

- `slug`
- `group_id`
- mapeamento territorial por UF

Além disso:

- grupos novos passam a ser criados já com nome visual simples (`UF`)
- grupos já existentes são renomeados por uma migração one-shot no `bp_init`

## 2. Como a renomeação foi aplicada

### 2.1 Nome padrão de grupos novos

No `StateGroupSeeder`, o nome padrão visual do grupo estadual passou de:

- `Estado - XX`

para:

- `XX`

Isso garante que grupos criados futuramente já nasçam no padrão simplificado.

### 2.2 Renomeação dos grupos já existentes

Foi adicionada a rotina:

- `StateGroupSeeder::syncStateGroupVisualNamesToUfLabels()`

Essa rotina:

1. percorre as UFs configuradas
2. resolve cada grupo por `slug estado-{uf}`
3. compara o nome atual com o nome desejado (`UF`)
4. atualiza apenas o `name`
5. preserva:
   - `group_id`
   - `slug`
   - `description`
   - `status`
   - `enable_forum`

### 2.3 Execução one-shot

Foi adicionada no `Plugin` a rotina:

- `maybeMigrateStateGroupVisualNames()`

Ela roda em `bp_init` e:

- executa a renomeação uma única vez
- grava resultado em:
  - `fpse_state_group_visual_names_last_result`
- marca conclusão em:
  - `fpse_state_group_visual_names_migrated_to_uf_v1`

Se houver erro, a migração não é marcada como concluída.

## 3. Quais grupos foram renomeados

A regra cobre todas as UFs do projeto definidas em `config/states.php`:

- `AC`
- `AL`
- `AP`
- `AM`
- `BA`
- `CE`
- `DF`
- `ES`
- `GO`
- `MA`
- `MT`
- `MS`
- `MG`
- `PA`
- `PB`
- `PR`
- `PE`
- `PI`
- `RJ`
- `RN`
- `RS`
- `RO`
- `RR`
- `SC`
- `SP`
- `SE`
- `TO`

Regra aplicada:

- `Estado - AC` → `AC`
- `Estado - AL` → `AL`
- `Estado - AP` → `AP`
- ...
- `Estado - SP` → `SP`
- ...

Importante:

- a migração atua apenas em grupos efetivamente existentes
- grupos ausentes entram em `missing` no resultado, sem erro

## 4. Confirmação de preservação de `slug` e `group_id`

O patch preserva explicitamente:

- `group_id`
- `slug`

Na atualização dos grupos existentes, o payload reenviado usa:

- `group_id` do grupo localizado
- `slug` já existente do grupo

Ou seja:

- o nome visual muda
- os identificadores funcionais permanecem os mesmos

## 5. Evidências de validação funcional

### 5.1 Validação estática/sintática

Arquivos validados com `php -l`:

- `src/Seeders/StateGroupSeeder.php`
- `src/Plugin.php`
- `src/Admin/SettingsPage.php`
- `templates/admin-dashboard.php`

Resultado:

- sem erros de sintaxe

### 5.2 Fluxo funcional preservado

Pontos que permanecem estáveis:

- cadastro continua delegando para `fpse-core`
- vínculo automático continua resolvendo o grupo por `slug estado-{uf}`
- o join do usuário continua por `group_id`
- o MU-plugin territorial continua usando `slug` e `group_id`
- rotinas administrativas seguem contando grupos por slug esperado

### 5.3 Evidência do antes/depois no código

Antes:

- nome padrão de grupos novos: `Estado - XX`
- grupos existentes não eram renomeados

Depois:

- nome padrão de grupos novos: `XX`
- grupos existentes são renomeados uma vez para `XX`

## 6. Arquivos alterados

- `src/Seeders/StateGroupSeeder.php`
- `src/Plugin.php`
- `.docs/RENOMEACAO_VISUAL_GRUPOS_ESTADUAIS_PARA_UF_2026-03-13.md`

## 7. Riscos residuais

### 7.1 Execução real depende do ambiente WordPress/BuddyBoss

A migração foi implementada no plugin, mas a renomeação efetiva dos registros depende da execução do `fpse-core` em um ambiente com BuddyBoss carregado.

### 7.2 Verificação manual ainda é recomendada

Após deploy, ainda é recomendável conferir:

- listagem de groups no BuddyBoss
- tela admin de grupos
- cadastro e vínculo automático com uma UF de teste

### 7.3 Grupos ausentes não são criados pela migração

Se alguma UF não tiver grupo existente:

- ela será reportada como `missing`
- isso não quebra a migração
- grupos novos continuarão sendo criados com nome simples `UF`

## 8. Conclusão

A renomeação visual foi implementada de forma compatível com a arquitetura atual do projeto.

O sistema continua operando pelos identificadores estáveis:

- `slug`
- `group_id`
- UF

Com isso, a camada visual dos grupos estaduais foi simplificada sem alterar o comportamento funcional esperado.
