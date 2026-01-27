# FPSE Core - Fortalece PSE Plugin

Institutional WordPress plugin for Fortalece PSE registration system. Provides REST API registration endpoint, audit trail tracking, user management, and report preparation infrastructure.

## Features

- **REST API Registration**: Public endpoint for user registration with WordPress nonce security
- **Rate Limiting**: IP-based rate limiting (5 req/hour for registration, 100 req/hour default)
- **Audit Trail**: Complete event tracking in custom `wp_fpse_events` table
- **User Management**: Create, update, and retrieve user registration data
- **Profile Resolution**: 13 user profiles with category-based organization
- **Permission System**: Role-based access control with custom capabilities
- **Logging**: Sensitive field masking for audit logs
- **Report Preparation**: Query builders for common report patterns (no export implementation)

## Installation

### ⚡ Quick Install (Without Composer)

The plugin works **with or without Composer**. The autoloader automatically detects which one is available.

```bash
# 1. Copy plugin to WordPress
cp -r fpse-core /var/www/html/wp-content/plugins/

# 2. Activate plugin
wp plugin activate fpse-core

# Done! 🎉
```

### Option 1: Without Composer (Recommended for Shared Hosting)

**Requirements**: PHP 5.4+, no additional dependencies

```bash
# Copy plugin folder
cp -r fpse-core wp-content/plugins/

# Activate via WordPress
wp plugin activate fpse-core
# OR via WordPress admin: Plugins > FPSE Core > Activate
```

**How it works**: Uses `autoload.php` (PSR-4 compatible) to load classes automatically.

### Option 2: With Composer (Recommended for Production)

**Requirements**: Composer installed on server

```bash
cd wp-content/plugins
git clone <repository-url> fpse-core
cd fpse-core
composer install
wp plugin activate fpse-core
```

**Benefits**: Dependency management, version control, production-grade setup.

### Option 3: For Developers (Local Dev + Composer)

```bash
# Local development
composer install

# Production (gitignore vendor/)
# Server uses autoload.php automatically

# .gitignore
/vendor/
/composer.lock
```

### Which Option to Choose?

| Scenario | Option |
|----------|--------|
| Shared hosting (no SSH) | Option 1 (no Composer) |
| VPS/Dedicated + Composer available | Option 2 (with Composer) |
| Team development + Git | Option 3 (dev + prod split) |
| Production simple setup | Option 1 (no Composer) |

**See [INSTALACAO-SEM-COMPOSER.md](INSTALACAO-SEM-COMPOSER.md) for detailed troubleshooting.**

## Configuration

All configuration is file-based in the `config/` directory:

### config/states.php
27 Brazilian states with UF codes (AC, AL, AP, ... TO)

### config/profiles.php
13 user profiles organized by category:
- **EAA**: estudante-eaa, professor-eaa, gestor-eaa
- **IES**: estudante-ies, professor-ies, pesquisador
- **NAP**: gestor-nap, assistente-nap
- **GTI**: gestor-gti, tecnico-gti
- **Governance**: coordenador-institucional, monitor-programa

Each profile defines:
- `label`: Display name
- `category`: Profile group
- `description`: Purpose
- `specific_fields`: Fields required for this profile

### config/report_fields.php
50+ field definitions with metadata:
- `label`: Display name
- `type`: text, email, enum, date, datetime, boolean
- `required`: Whether field is required
- `searchable`: Whether field is indexed for searches
- `sensitive`: Whether to mask in logs
- `auto_filled`: Whether field is auto-filled (e.g., from ViaCEP)

### config/permissions.php
- **capabilities**: Custom WordPress capabilities
- **admin_roles**: Roles that get FPSE capabilities
- **endpoint_permissions**: Public/protected/capability-based
- **rate_limits**: Requests per hour by endpoint

### config/debug.php
- **enable_debug**: Respects WP_DEBUG constant
- **log_file**: Path to fpse-core.log
- **mask_sensitive_fields**: Fields to mask in logs
- **track_events**: Event types to record

## REST API

### POST /wp-json/fpse/v1/register

Register a new user or update existing user.

**Request:**
```json
{
  "fpse_nonce": "nonce_token",
  "nome_completo": "João Silva",
  "cpf": "123.456.789-00",
  "email": "joao@example.com",
  "email_login": "joao.silva@example.com",
  "senha_login": "SecurePassword123!",
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

**Response (Success 201):**
```json
{
  "success": true,
  "message": "Usuário criado com sucesso",
  "user_id": 42,
  "perfil": "estudante-ies",
  "estado": "CE"
}
```

**Response (Error 400/429):**
```json
{
  "success": false,
  "message": "Error description",
  "errors": ["optional error details"]
}
```

### GET /wp-json/fpse/v1/nonce

Get a fresh nonce for client-side form submission.

**Response:**
```json
{
  "success": true,
  "nonce": "abc123...",
  "nonce_name": "fpse_nonce",
  "nonce_action": "fpse_register_action"
}
```

### GET /wp-json/fpse/v1/registration/{id}

Get user registration data (authenticated).

**Permissions**: Own data or `view_fpse_registrations` capability

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "email": "joao@example.com",
    "display_name": "João Silva",
    "nome_completo": "João Silva",
    "cpf": "123.456.789-00",
    ...
  }
}
```

## PHP Usage Examples

### Register a User (from PHP code)

```php
use FortaleceePSE\Core\Services\UserService;
use FortaleceePSE\Core\Services\EventRecorder;
use FortaleceePSE\Core\Domain\RegistrationDTO;

$eventRecorder = new EventRecorder();
$userService = new UserService($eventRecorder);

// Parse registration data
$data = [
    'nome_completo' => 'João Silva',
    'cpf' => '123.456.789-00',
    'email' => 'joao@example.com',
    'email_login' => 'joao@example.com',
    'senha_login' => 'password',
    'perfil_usuario' => 'estudante-ies',
    'estado' => 'CE',
];

$dto = RegistrationDTO::fromArray($data);
$result = $userService->createOrUpdate($dto);

if ($result['success']) {
    echo "User created: " . $result['user_id'];
}
```

### Query Reports (from PHP code)

```php
use FortaleceePSE\Core\Reports\ReportRegistry;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$reports = new ReportRegistry($plugin);

// Get registrations by state
$ceRegistrations = $reports->byState('CE');

// Get count by profile
$counts = $reports->countByProfile();

// Get registrations by state and date
$registrations = $reports->byStateAndDate('CE', '2024-01-01', '2024-01-31');

// Get paginated results
$page = $reports->getAllRegistrations(1, 50);
echo "Total: " . $page['total'] . ", Page: " . $page['page'];
```

### Check Permissions

```php
use FortaleceePSE\Core\Services\PermissionService;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$permissions = new PermissionService($plugin);

// Check endpoint access
if ($permissions->canRegister()) {
    // Allow registration
}

// Check capability
if ($permissions->canManageRegistrations()) {
    // Show admin panel
}

// Check rate limit
if ($permissions->getRateLimit('register') === 5) {
    // 5 requests per hour
}
```

## Database Schema

### wp_fpse_events Table

```sql
CREATE TABLE wp_fpse_events (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT(20) UNSIGNED DEFAULT 0,
  event VARCHAR(100) NOT NULL,
  perfil VARCHAR(100) DEFAULT '',
  estado VARCHAR(2) DEFAULT '',
  metadata LONGTEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  
  KEY idx_user_id (user_id),
  KEY idx_event (event),
  KEY idx_estado (estado),
  KEY idx_created_at (created_at)
)
```

**Tracked Events:**
- `user_registered`: New user created
- `user_updated`: Existing user updated
- `profile_assigned`: Profile set for user
- `state_assigned`: State set for user
- `validation_error`: Registration data validation failed

## User Meta Storage

All registration data is stored as user meta with snake_case keys:

```
nome_completo        (from nomeCompleto)
cpf                  (from cpf)
email                (from email)
email_login          (from emailLogin)
telefone             (from telefone)
data_nascimento      (from dataNascimento)
genero               (from genero)
raca_cor             (from racaCor)
perfil_usuario       (from perfilUsuario)
vinculo_institucional (from vinculoInstitucional)
estado               (from estado)
municipio            (from municipio)
logradouro           (from logradouro)
cep                  (from cep)
numero               (from numero)
bairro               (from bairro)
complemento          (from complemento)
acessibilidade       (from acessibilidade)
descricao_acessibilidade (from descricaoAcessibilidade)
... (profile-specific fields)
```

## Logging

Debug logs are written to `/wp-content/fpse-core.log` when `WP_DEBUG` is enabled.

**Sensitive fields are automatically masked:**
- cpf
- email
- email_login
- matricula
- telefone

Example log entry:
```
[2024-01-15 10:30:45] [INFO] User registered successfully | Context: {"user_id":42,"perfil":"estudante-ies","estado":"CE"}
[2024-01-15 10:31:20] [ERROR] Failed to create user | Context: {"error":"Email already exists","cpf":"***MASKED***"}
```

## Architecture

### PSR-4 Namespaces
```
FortaleceePSE\Core\
├── Plugin.php                    # Main plugin class (singleton)
├── Domain\RegistrationDTO.php    # Data transfer object
├── REST\RegistrationController   # REST endpoints
├── Services\
│   ├── UserService              # User creation/updates
│   ├── EventRecorder            # Audit trail
│   ├── ProfileResolver          # Profile validation
│   └── PermissionService        # Access control
├── Reports\ReportRegistry       # Query builders
├── Security\
│   ├── NonceMiddleware          # WordPress nonce handling
│   └── RateLimit                # IP-based rate limiting
└── Utils\Logger                 # Logging with masking
```

### Design Patterns

**Singleton**: Plugin class for global access
```php
$plugin = Plugin::getInstance();
```

**Dependency Injection**: Services receive dependencies
```php
$userService = new UserService($eventRecorder);
```

**Data Transfer Object**: RegistrationDTO for type safety
```php
$dto = RegistrationDTO::fromArray($requestData);
```

**Configuration-Driven**: All business logic in PHP config files
- No hardcoded values
- Easy to modify without code changes
- Clear audit trail

## Security

- **Nonces**: WordPress security tokens for form submission (1-day expiry)
- **Rate Limiting**: IP-based throttling per endpoint (stored in WordPress transients)
- **Input Sanitization**: All user inputs sanitized via WordPress functions
- **Capability Checks**: Role-based access control
- **Sensitive Masking**: Automatic masking in logs
- **Prepared Statements**: All database queries use wpdb prepared statements

## Performance

- **Transient Caching**: Rate limit counts expire after 1 hour
- **Database Indexes**: Four indexes on wp_fpse_events table (user_id, event, estado, created_at)
- **Lazy Loading**: Services instantiated only when needed
- **PSR-4 Autoloading**: Efficient class loading via Composer

## Testing

```bash
# Activate plugin
wp plugin activate fpse-core

# Verify plugin loaded
wp plugin list --field=name | grep fpse-core

# Check database table created
wp db query "SHOW TABLES LIKE '%fpse_events%';"

# Get nonce (for testing registration)
curl -X GET http://localhost/wp-json/fpse/v1/nonce

# Register user
curl -X POST http://localhost/wp-json/fpse/v1/register \
  -H "Content-Type: application/json" \
  -d '{
    "fpse_nonce": "token_here",
    "nome_completo": "Test User",
    "cpf": "123.456.789-00",
    "email": "test@example.com",
    "email_login": "test@example.com",
    "senha_login": "Password123!",
    "perfil_usuario": "estudante-ies",
    "estado": "CE"
  }'
```

## Troubleshooting

### Plugin Not Activating
- Check PHP version >= 8.0
- Verify `vendor/autoload.php` exists (run `composer install`)
- Check WordPress error log for permission issues

### Rate Limit Not Working
- Verify WordPress transients are enabled
- Check X-Forwarded-For header is being passed (proxy environments)

### Database Table Not Created
- Run activation: `wp plugin activate fpse-core`
- Check database permissions
- Verify charset/collation is utf8mb4_unicode_ci

### Logs Not Writing
- Check `/wp-content/` directory permissions
- Verify `WP_DEBUG` is set to `true`
- Check disk space in `/wp-content/`

## Future Enhancements

- [ ] JWT authentication (future)
- [ ] State-based access control (future)
- [ ] Report exports (CSV/PDF) - ReportRegistry prepared but not implemented
- [ ] Dashboard/admin UI (future)
- [ ] Webhook notifications (future)
- [ ] Batch import/export (future)

## License

GPL v3 or later. See LICENSE file for details.

## Support

For issues, questions, or contributions:
- Repository: https://github.com/fortalecepse/fpse-core
- Email: support@fortalecepse.org
- Documentation: https://docs.fortalecepse.org/fpse-core

---

**Last Updated**: 2026-01-27 - Webhook auto-deploy WORKING! 🎉
