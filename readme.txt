=== Fortalece PSE Core ===
Contributors: Fortalece Team
Tags: rest-api, registration, institutional
Requires at least: 5.9
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Plugin institucional para o programa Fortalece PSE (governo federal). Gerencia registro de usuários via REST API, eventos de auditoria e preparação para relatórios oficiais.

== Description ==

O plugin Fortalece PSE Core é uma solução backend para o programa institucional Fortalece PSE. Fornece uma REST API robusta e segura para registro de usuários, com foco em auditoria, rastreabilidade e preparação para geração de relatórios oficiais.

**Características principais:**

* REST API customizada para registro de usuários
* Sistema de eventos para auditoria completa
* Validação de perfis e estados via configuração
* Proteção por nonce WordPress e rate limiting
* Preparação para relatórios futuros (sem dashboards/export ainda)
* Código estruturado e auditável

**Estrutura:**

O plugin segue padrões PSR-4 e está organizado em:
* `/config` - Arquivos de configuração (perfis, estados, permissões, campos de relatório)
* `/src` - Código fonte organizado por namespace
* `REST/` - Controladores de endpoints
* `Services/` - Lógica de negócio
* `Domain/` - DTOs e modelos
* `Security/` - Middlewares de segurança
* `Reports/` - Sistema de registro de eventos (preparado para relatórios)
* `Utils/` - Utilitários (logger)

**Endpoint REST:**

POST `/wp-json/fpse/v1/register`

Campos mínimos obrigatórios:
* `email_login` - Email usado como login
* `perfil_usuario` - Perfil do usuário (deve estar em config/profiles.php)
* `estado` - UF válida (deve estar em config/states.php)
* `municipio` - Nome do município
* `nome_completo` - Nome completo do usuário

**Segurança:**

* Proteção por nonce WordPress (obrigatório)
* Rate limiting por IP
* Sanitização total de inputs
* Preparado para futura autenticação JWT

**Auditoria:**

Todos os eventos são registrados na tabela `wp_fpse_events`:
* `registered` - Novo registro de usuário
* `profile_assigned` - Atribuição de perfil
* `state_assigned` - Atribuição de estado
* `validation_error` - Erros de validação

**Banco de dados:**

O plugin cria automaticamente a tabela `wp_fpse_events` na ativação:
* `id` (bigint) - ID do evento
* `user_id` (bigint) - ID do usuário WordPress
* `event` (varchar) - Tipo do evento
* `perfil` (varchar) - Perfil do usuário
* `estado` (char(2)) - UF
* `metadata` (longtext) - Metadados adicionais (JSON)
* `created_at` (datetime) - Data/hora do evento

**Configuração:**

Todos os arquivos de configuração estão em `/config`:
* `profiles.php` - Perfis oficiais e campos específicos
* `permissions.php` - Regras de acesso e rate limits
* `states.php` - Lista de UFs válidas
* `report_fields.php` - Campos disponíveis para relatórios
* `debug.php` - Configurações de log e debug

== Installation ==

1. Faça upload do plugin para `/wp-content/plugins/fpse-core/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. O plugin criará automaticamente a tabela `wp_fpse_events` na ativação

**Requisitos:**

* WordPress 5.9 ou superior
* PHP 8.0 ou superior
* Permissões para criar tabelas no banco de dados

**Nota sobre Composer:**

O plugin pode funcionar com ou sem Composer. Se não houver `vendor/autoload.php`, o autoload manual será usado.

== Frequently Asked Questions ==

= Como obter um nonce para fazer requisições? =

Use o endpoint GET `/wp-json/fpse/v1/nonce` que retorna um nonce válido.

= O que acontece se eu tentar registrar um usuário que já existe? =

O plugin atualiza os dados do usuário existente baseado no email fornecido.

= Os dados são salvos em user_meta? =

Sim, todos os campos relevantes são salvos como user_meta normalizado (snake_case).

= Como desabilitar registros? =

Edite `config/permissions.php` e altere `endpoint_permissions.register` para algo diferente de `'public'`.

= Onde vejo os logs? =

Se debug estiver habilitado, os logs ficam em `WP_CONTENT_DIR/fpse-core.log` (configurável em `config/debug.php`).

== Screenshots ==

1. Estrutura de diretórios do plugin
2. Arquivo de configuração de perfis
3. Tabela de eventos no banco de dados

== Changelog ==

= 1.0.0 =
* Versão inicial
* Endpoint REST `/wp-json/fpse/v1/register`
* Sistema de eventos e auditoria
* Validação de perfis e estados
* Proteção por nonce e rate limiting
* Tabela customizada `wp_fpse_events`
* Preparação para relatórios futuros

== Upgrade Notice ==

= 1.0.0 =
Versão inicial do plugin. Instale e ative normalmente.

== Arquitetura ==

**Decisões de design:**

1. **Separação de responsabilidades**: Cada serviço tem uma responsabilidade clara (UserService, ProfileResolver, PermissionService, EventRecorder)

2. **Configuração centralizada**: Todas as regras de negócio estão em arquivos de config, não hardcoded no código

3. **DTO para validação**: RegistrationDTO normaliza e valida dados de entrada

4. **Eventos para auditoria**: Todos os eventos importantes são registrados na tabela customizada

5. **Preparação para relatórios**: ReportRegistry fornece métodos de consulta, mas não implementa export ainda (conforme especificação)

6. **Segurança em camadas**: Nonce, rate limiting, sanitização e preparação para JWT futuro

**Padrões utilizados:**

* PSR-4 autoloading
* Namespaces organizados
* Singleton para Plugin principal
* Dependency Injection nos serviços
* WordPress coding standards

== Suporte ==

Este é um plugin institucional para o programa Fortalece PSE.
Para suporte, entre em contato com a equipe do projeto.

== Licença ==

Este plugin é licenciado sob GPLv3 ou posterior.
