# Resumo Executivo: Normalizacao dos Campos Nativos WordPress/BuddyBoss

Data: 2026-03-14

## Objetivo
Corrigir a composicao dos campos nativos do usuario no cadastro FPSE para que `nome_completo` seja a fonte de verdade de:

- `first_name`
- `last_name`
- `nickname`
- `display_name`

Tambem foi criada uma rotina segura de saneamento para usuarios legados com valores derivados de login/e-mail.

## Causa raiz
O fluxo de cadastro do plugin `fpse-core` criava o usuario WordPress sem definir explicitamente `first_name`, `last_name` e `nickname`. Em seguida, atualizava apenas `display_name`.

Efeito pratico:

- WordPress/BuddyBoss preenchia defaults a partir do `user_login` ou do e-mail
- `nickname` ficava visualmente inconsistente
- `first_name` podia exceder o limite aceito pelo admin do BuddyBoss
- a edicao do perfil no admin passava a falhar com `First Name must be shorter than 32 characters.`

## Regra implementada
Fonte de verdade: `nome_completo` salvo pelo cadastro FPSE.

Composicao:

- `first_name`: primeiro token do nome completo, com truncamento seguro em 32 caracteres
- `last_name`: ultimo token do nome completo
- `nickname`: primeiro nome + ultimo sobrenome, em minusculo, sem acentos, sem caracteres especiais, com espacos convertidos para underscore
- `display_name`: nome completo humano, preservando a grafia original

Fallbacks:

- nome de uma unica palavra gera `last_name` vazio
- nickname vazio cai para `usuario`
- colisao de nickname gera sufixo incremental: `_2`, `_3`, etc.

## Escopo da correcao
### Criacao de usuario
O fluxo agora usa `wp_insert_user()` ja com os campos nativos normalizados.

### Atualizacao de usuario
O fluxo de update recompõe os mesmos campos nativos toda vez que recebe `nome_completo`.

### Saneamento legado
Foi adicionado um saneamento em lote que:

- percorre usuarios em batches
- resolve `nome_completo` via user meta (`fpse_nome_completo`, `nome_completo`) e xProfile
- recalcula os campos nativos esperados
- aplica `wp_update_user()` apenas quando encontra divergencia real

## Arquivos alterados
- `src/Services/UserService.php`
- `src/Admin/SettingsPage.php`
- `templates/admin-dashboard.php`

## Evidencias objetivas
Validacoes executadas:

- `php -l src/Services/UserService.php`
- `php -l src/Admin/SettingsPage.php`
- `php -l templates/admin-dashboard.php`

Simulacoes da regra:

- `Adriana Tania Monteiro` -> `Adriana` / `Monteiro` / `adriana_monteiro`
- `Ayla Sophia Tatiane Teixeira` -> `Ayla` / `Teixeira` / `ayla_teixeira`
- `Joao Victor da Silva` -> `Joao` / `Silva` / `joao_silva`
- `Cher` -> `Cher` / `` / `cher`

Simulacoes adicionais:

- colisao de nickname: `adriana_monteiro` -> `adriana_monteiro_2`
- truncamento do primeiro nome acima de 32 caracteres

## Impacto nas outras camadas
- `form-fpse`: sem alteracao
- `fpse-rest-bridge`: sem alteracao
- `mu-plugins`: sem alteracao
- grupos/member types/xProfile: sem alteracao de regra de dominio

## Risco residual
Usuarios legados sem `nome_completo` persistido em user meta ou xProfile nao entram no saneamento automatico. Esses casos sao pulados por seguranca.
