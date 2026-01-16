# FPSE Core - API Reference

Complete API documentation for Fortalece PSE Core plugin.

## REST API Endpoints

### POST /wp-json/fpse/v1/register

Register a new user or update an existing user with complete profile information.

**Authentication**: Public endpoint (nonce required for CSRF protection)

**Rate Limit**: 5 requests per hour per IP address

**Request Body** (application/json):

```json
{
  "fpse_nonce": "string",                    // Required: WordPress nonce
  "nome_completo": "string",                 // Required: Full name
  "cpf": "string",                           // Required: Brazilian tax ID (format: ###.###.###-##)
  "email": "string",                         // Required: Email address
  "email_login": "string",                   // Required: Login email (unique)
  "senha_login": "string",                   // Required: Password (min 8 chars)
  "telefone": "string",                      // Required: Phone (format: (XX) XXXXX-XXXX)
  "data_nascimento": "string",               // Required: Birth date (YYYY-MM-DD)
  "genero": "string",                        // Required: Gender (masculino|feminino|outro|prefiro-nao)
  "raca_cor": "string",                      // Required: Race/Color (branca|preta|parda|amarela|indigena)
  "perfil_usuario": "string",                // Required: User profile (see profiles list)
  "vinculo_institucional": "string",         // Required: Institutional link
  "estado": "string",                        // Required: State code (AC, AL, AP, ..., TO)
  "municipio": "string",                     // Required: City name
  "logradouro": "string",                    // Required: Street name
  "cep": "string",                           // Required: Postal code (format: XXXXX-XXX)
  "numero": "string",                        // Required: Street number
  "bairro": "string",                        // Required: Neighborhood
  "complemento": "string",                   // Optional: Address complement
  "acessibilidade": "boolean",               // Required: Has accessibility needs
  "descricao_acessibilidade": "string|null", // Optional: Accessibility description
  
  // Profile-specific fields (vary by perfil_usuario)
  "rede_escola": "string",                   // For EAA profiles
  "escola_nome": "string",                   // For EAA profiles
  "funcao_eaa": "string",                    // For EAA profiles
  "instituicao_nome": "string",              // For IES profiles
  "curso_nome": "string",                    // For IES profiles
  "departamento": "string",                  // For IES profiles
  "nap_nome": "string",                      // For NAP profiles
  "setor_gti": "string",                     // For GTI profiles
  "matricula": "string",                     // For institutional users
  // ... (other profile-specific fields)
}
```

**Responses:**

**Success (201 Created)**:
```json
{
  "success": true,
  "message": "Usuário criado com sucesso",
  "user_id": 42,
  "perfil": "estudante-ies",
  "estado": "CE"
}
```

**Success (200 OK - Updated existing user)**:
```json
{
  "success": true,
  "message": "Usuário atualizado com sucesso",
  "user_id": 42,
  "perfil": "estudante-ies",
  "estado": "CE"
}
```

**Invalid JSON (400 Bad Request)**:
```json
{
  "success": false,
  "message": "JSON inválido"
}
```

**Missing Required Fields (400 Bad Request)**:
```json
{
  "success": false,
  "message": "Campos obrigatórios faltando: nome_completo, email"
}
```

**Invalid Nonce (400 Bad Request)**:
```json
{
  "success": false,
  "message": "Token de segurança inválido ou expirado"
}
```

**Invalid Profile (400 Bad Request)**:
```json
{
  "success": false,
  "message": "Validação de perfil falhou",
  "errors": [
    "Campos específicos do perfil faltando: instituicao_nome, curso_nome"
  ]
}
```

**Invalid State (400 Bad Request)**:
```json
{
  "success": false,
  "message": "Estado inválido"
}
```

**Email Already Exists (400 Bad Request)**:
```json
{
  "success": false,
  "message": "Email de login já cadastrado"
}
```

**Rate Limited (429 Too Many Requests)**:
```json
{
  "success": false,
  "message": "Limite de requisições excedido. Tente novamente mais tarde."
}
```

---

### GET /wp-json/fpse/v1/nonce

Get a fresh CSRF protection token for registration form submission.

**Authentication**: Public endpoint (no authentication required)

**Rate Limit**: None

**Request Parameters**: None

**Response (200 OK)**:
```json
{
  "success": true,
  "nonce": "abc123def456ghi789jkl...",
  "nonce_name": "fpse_nonce",
  "nonce_action": "fpse_register_action"
}
```

**Usage**: Include the returned `nonce` value in `fpse_nonce` field when submitting the register endpoint.

---

### GET /wp-json/fpse/v1/registration/{id}

Retrieve user registration data and profile information.

**Authentication**: Required
- User can access own data (user_id = current user)
- OR user must have `view_fpse_registrations` capability

**Rate Limit**: 100 requests per hour per IP

**Request Parameters**:
- `id` (integer, required): User ID to retrieve

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "id": 42,
    "email": "joao@example.com",
    "display_name": "João Silva",
    "nome_completo": "João da Silva",
    "cpf": "123.456.789-00",
    "email_login": "joao@example.com",
    "telefone": "(85) 98765-4321",
    "data_nascimento": "1990-05-15",
    "genero": "masculino",
    "raca_cor": "branca",
    "perfil_usuario": "estudante-ies",
    "vinculo_institucional": "aluno",
    "estado": "CE",
    "municipio": "Fortaleza",
    "logradouro": "Rua Test",
    "cep": "60025-100",
    "numero": "123",
    "bairro": "Centro",
    "complemento": "Apt 101",
    "acessibilidade": false,
    "descricao_acessibilidade": null,
    "instituicao_nome": "UECE",
    "curso_nome": "Pedagogia",
    "matricula": "2024001234"
  }
}
```

**Not Found (404 Not Found)**:
```json
{
  "success": false,
  "message": "Usuário não encontrado"
}
```

**Unauthorized (403 Forbidden)**:
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do this."
}
```

---

## PHP Service API

### Plugin Class (Singleton)

```php
use FortaleceePSE\Core\Plugin;

// Get singleton instance
$plugin = Plugin::getInstance();

// Get configuration
$states = $plugin->getConfig('states', []);
$profiles = $plugin->getConfig('profiles', []);
$reportFields = $plugin->getConfig('report_fields', []);
$debug = $plugin->getConfig('debug', []);
$permissions = $plugin->getConfig('permissions', []);

// Get logger
$logger = $plugin->getLogger();
$logger->info('Registration started', ['user' => 'test@example.com']);

// Activate plugin (called automatically on activation)
$plugin->activate();

// Register REST routes (called automatically on plugins_loaded)
$plugin->registerRestRoutes();
```

### UserService

```php
use FortaleceePSE\Core\Services\UserService;
use FortaleceePSE\Core\Services\EventRecorder;
use FortaleceePSE\Core\Domain\RegistrationDTO;

$eventRecorder = new EventRecorder();
$userService = new UserService($eventRecorder);

// Create or update user
$dto = RegistrationDTO::fromArray([
    'nome_completo' => 'João Silva',
    'cpf' => '123.456.789-00',
    'email' => 'joao@example.com',
    'email_login' => 'joao@example.com',
    'senha_login' => 'password123',
    'perfil_usuario' => 'estudante-ies',
    'estado' => 'CE',
]);

$result = $userService->createOrUpdate($dto);
// Returns: ['success' => bool, 'user_id' => int, 'message' => string]

// Get user registration data
$userData = $userService->getUserData(42);
// Returns: array with all user meta

// Get users by profile
$iesStudents = $userService->getUsersByProfile('estudante-ies');
// Returns: array of WP_User objects

// Get users by state
$ceUsers = $userService->getUsersByState('CE');
// Returns: array of WP_User objects

// Get users by profile and state
$ceIesStudents = $userService->getUsersByProfileAndState('estudante-ies', 'CE');
// Returns: array of WP_User objects
```

### EventRecorder

```php
use FortaleceePSE\Core\Services\EventRecorder;

$recorder = new EventRecorder();

// Record registration
$eventId = $recorder->recordRegistration(
    $userId,           // int
    'estudante-ies',   // string: profile
    'CE',              // string: state
    ['action' => 'user_created']  // array: metadata
);

// Record user update
$recorder->recordUpdate($userId, 'estudante-ies', 'CE', ['action' => 'user_updated']);

// Record profile assignment
$recorder->recordProfileAssigned($userId, 'estudante-ies', 'CE');

// Record state assignment
$recorder->recordStateAssigned($userId, 'estudante-ies', 'CE');

// Record validation error
$recorder->recordValidationError('estudante-ies', 'CE', [
    'errors' => ['nome_completo' => 'Name is required']
]);

// Get user events
$events = $recorder->getUserEvents(42, 100);  // user_id, limit
// Returns: array of stdClass objects with id, user_id, event, perfil, estado, metadata, created_at

// Get events by type
$registrations = $recorder->getEventsByType('user_registered', 100);
// Returns: array of event objects

// Get events by state
$ceEvents = $recorder->getEventsByState('CE', 100);
// Returns: array of event objects
```

### ProfileResolver

```php
use FortaleceePSE\Core\Services\ProfileResolver;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$profileResolver = new ProfileResolver($plugin);

// Get all profiles
$profiles = $profileResolver->getAllProfiles();
// Returns: array with profile configs

// Get specific profile
$profile = $profileResolver->getProfile('estudante-ies');
// Returns: array ['label' => '...', 'category' => '...', 'specific_fields' => [...]]

// Validate profile exists
if ($profileResolver->isValidProfile('estudante-ies')) {
    // Profile exists
}

// Get profile label
$label = $profileResolver->getProfileLabel('estudante-ies');
// Returns: "Estudante - IES"

// Get profile category
$category = $profileResolver->getProfileCategory('estudante-ies');
// Returns: "IES"

// Get profile description
$desc = $profileResolver->getProfileDescription('estudante-ies');

// Get profile-specific required fields
$fields = $profileResolver->getProfileSpecificFields('estudante-ies');
// Returns: ['instituicao_nome', 'curso_nome', 'matricula']

// Get profiles by category
$iesProfiles = $profileResolver->getProfilesByCategory('IES');
// Returns: array of IES profiles

// Get all available categories
$categories = $profileResolver->getAllCategories();
// Returns: ['EAA', 'IES', 'NAP', 'GTI', 'Governance']

// Validate profile-specific fields
$validation = $profileResolver->validateProfileSpecificFields('estudante-ies', [
    'instituicao_nome' => 'UECE',
    'curso_nome' => 'Pedagogia',
    'matricula' => '2024001234'
]);
// Returns: ['valid' => bool, 'missing' => array, 'required' => array]

// Validate complete profile
$validation = $profileResolver->validateProfile('estudante-ies', $data);
// Returns: ['valid' => bool, 'errors' => array]

// Get field metadata for profile
$metadata = $profileResolver->getProfileFieldMetadata('estudante-ies');
// Returns: array of field definitions from report_fields.php
```

### PermissionService

```php
use FortaleceePSE\Core\Services\PermissionService;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$permissions = new PermissionService($plugin);

// Check registration access
if ($permissions->canRegister()) {
    // Can register (public endpoint)
}

// Check view registrations
if ($permissions->canViewRegistrations()) {
    // User is logged in and has capability
}

// Check manage registrations
if ($permissions->canManageRegistrations()) {
    // User has manage_fpse_registrations capability
}

// Check view reports
if ($permissions->canViewReports()) {
    // User has view_fpse_reports capability
}

// Check endpoint access
if ($permissions->canAccessEndpoint('register')) {
    // User can access this endpoint
}

// Get endpoint capability requirement
$cap = $permissions->getEndpointCapability('registration');
// Returns: 'manage_fpse_registrations' or null

// Get all capabilities
$caps = $permissions->getCapabilities();
// Returns: ['manage_fpse_registrations', 'view_fpse_registrations', 'view_fpse_reports', 'export_fpse_reports']

// Get admin roles
$roles = $permissions->getAdminRoles();
// Returns: ['administrator', 'fpse_admin']

// Grant capabilities to role
$permissions->grantCapabilitiesToRole('editor');

// Revoke capabilities from role
$permissions->revokeCapabilitiesFromRole('editor');

// Get rate limit for endpoint
$limit = $permissions->getRateLimit('register');
// Returns: 5 (requests per hour)

// Check state access
if ($permissions->canAccessState('CE')) {
    // User can access this state
}

// Get accessible states
$states = $permissions->getAccessibleStates();
// Returns: array of state codes
```

### ReportRegistry

```php
use FortaleceePSE\Core\Reports\ReportRegistry;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$reports = new ReportRegistry($plugin);

// Get registrations by state
$ceData = $reports->byState('CE');
// Returns: array of event records

// Get registrations by profile
$iesData = $reports->byProfile('estudante-ies');
// Returns: array of event records

// Get registrations by state and profile
$ceIes = $reports->byStateAndProfile('CE', 'estudante-ies');
// Returns: array of event records

// Get registrations in date range
$dateRange = $reports->byDateRange('2024-01-01', '2024-01-31');
// Returns: array of event records

// Get registrations by state and date
$stateDate = $reports->byStateAndDate('CE', '2024-01-01', '2024-01-31');
// Returns: array of event records

// Get paginated registrations
$page = $reports->getAllRegistrations(1, 50);
// Returns: array with 'data', 'total', 'page', 'per_page', 'total_pages'

// Count registrations by state
$counts = $reports->countByState();
// Returns: array with state codes as keys, count objects as values

// Count registrations by profile
$counts = $reports->countByProfile();
// Returns: array with profile names as keys, count objects as values

// Count registrations by state and profile
$counts = $reports->countByStateAndProfile();
// Returns: array of objects with estado, perfil, count

// Get registrations per day (last 30 days)
$daily = $reports->registrationsPerDay(30);
// Returns: array with date and count for each day

// Get validation error statistics
$stats = $reports->validationErrorStats();
// Returns: ['total_errors' => int, 'note' => string]

// Get user audit trail
$audit = $reports->userAuditTrail(42);
// Returns: array of all events for user ID 42

// Execute raw query
$results = $reports->raw("SELECT * FROM {$reports->getTableName()} WHERE perfil = 'estudante-ies'");
// Returns: array of query results

// Get table name
$table = $reports->getTableName();
// Returns: "wp_fpse_events"

// Get wpdb instance for advanced queries
$wpdb = $reports->getDatabase();
// Returns: global $wpdb instance
```

### RegistrationDTO

```php
use FortaleceePSE\Core\Domain\RegistrationDTO;

// Create DTO from array (API input)
$dto = RegistrationDTO::fromArray([
    'nome_completo' => 'João Silva',
    'cpf' => '123.456.789-00',
    'email' => 'joao@example.com',
    'email_login' => 'joao@example.com',
    'senha_login' => 'password123',
    'perfil_usuario' => 'estudante-ies',
    'estado' => 'CE',
    'instituicao_nome' => 'UECE',  // Profile-specific
    'curso_nome' => 'Pedagogia',     // Profile-specific
]);

// Access properties
echo $dto->nomeCompleto;      // "João Silva"
echo $dto->email;              // "joao@example.com"
echo $dto->perfilUsuario;      // "estudante-ies"

// Convert to array (for storage)
$data = $dto->toArray();
// Returns: array with snake_case keys

// Validate minimum required fields
$validation = $dto->getMinimumRequiredFields();
// Returns: ['valid' => bool, 'missing' => array]
```

### Logger

```php
use FortaleceePSE\Core\Utils\Logger;

$logger = new Logger([
    'enable_debug' => true,
    'log_file' => WP_CONTENT_DIR . '/fpse-core.log',
    'log_levels' => ['error', 'warning', 'info'],
    'mask_sensitive_fields' => ['cpf', 'email', 'matricula'],
]);

// Log error
$logger->error('User creation failed', [
    'email' => 'joao@example.com',
    'cpf' => '123.456.789-00',
    'error' => 'Email already exists'
]);
// Output: [TIMESTAMP] [ERROR] User creation failed | Context: {"email":"***MASKED***","cpf":"***MASKED***",...}

// Log warning
$logger->warning('High registration rate detected', ['count' => 50]);

// Log info
$logger->info('User registered successfully', [
    'user_id' => 42,
    'perfil' => 'estudante-ies',
    'estado' => 'CE'
]);

// Log debug
$logger->debug('Processing registration', ['data' => $dto->toArray()]);

// Get log file path
$path = $logger->getLogFile();
// Returns: "/var/www/html/wp-content/fpse-core.log"
```

### Security Middleware

**NonceMiddleware**:
```php
use FortaleceePSE\Core\Security\NonceMiddleware;

$nonce = new NonceMiddleware();

// Generate nonce
$token = $nonce->generateNonce();
// Returns: WordPress nonce token string

// Verify nonce
if ($nonce->verifyNonce($token)) {
    // Valid nonce
}

// Get nonce name (field name in form)
$name = $nonce->getNonceName();
// Returns: "fpse_nonce"

// Get nonce action
$action = $nonce->getNonceAction();
// Returns: "fpse_register_action"
```

**RateLimit**:
```php
use FortaleceePSE\Core\Security\RateLimit;

$rateLimit = new RateLimit();

// Check if request is within limit
if ($rateLimit->checkLimit('register', 5)) {
    // Within limit - returns true and increments counter
} else {
    // Exceeded limit - returns false
}

// Reset limit for testing
$rateLimit->resetLimit('register');
```

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| - | 201 | Resource created successfully |
| - | 200 | Request successful |
| - | 400 | Bad request (invalid data, missing fields) |
| - | 403 | Forbidden (endpoint disabled, no permission) |
| - | 404 | Not found (user doesn't exist) |
| - | 429 | Too many requests (rate limited) |
| rest_forbidden | 403 | User not allowed to access resource |

## Data Types & Formats

### Field Formats

| Field | Format | Example |
|-------|--------|---------|
| cpf | ###.###.###-## | 123.456.789-00 |
| email | RFC 5322 | joao@example.com |
| telefone | (XX) XXXXX-XXXX | (85) 98765-4321 |
| cep | XXXXX-XXX | 60025-100 |
| data_nascimento | YYYY-MM-DD | 1990-05-15 |

### Enums

**genero**:
- masculino
- feminino
- outro
- prefiro-nao

**raca_cor**:
- branca
- preta
- parda
- amarela
- indigena

**estado**: AC, AL, AP, AM, BA, CE, DF, ES, GO, MA, MT, MS, MG, PA, PB, PR, PE, PI, RJ, RN, RS, RO, RR, SC, SP, SE, TO

**perfil_usuario**: See section "Available Profiles" in QUICK_START.md

## Rate Limits

| Endpoint | Limit | Per |
|----------|-------|-----|
| POST /register | 5 | 1 hour, IP address |
| GET /nonce | unlimited | - |
| GET /registration/{id} | 100 | 1 hour, IP address |

## Security

- All endpoints use WordPress sanitization/validation
- Passwords are hashed with WordPress `wp_create_user()`
- Nonces expire after 1 day
- Rate limits use WordPress transients (1-hour TTL)
- Sensitive fields (CPF, email, etc.) are masked in logs
- All database queries use prepared statements
- CORS handled by WordPress REST API defaults

## Webhooks (Future)

Planned for future release:
- `fpse_user_registered`
- `fpse_user_updated`
- `fpse_validation_error`
- `fpse_profile_assigned`

## Pagination

```php
$page = $reports->getAllRegistrations(2, 50);
// Page 2, 50 items per page
// Returns: total, page, per_page, total_pages, data array
```

Use `total_pages` to calculate pagination controls.

## Sorting

Currently not implemented in REST API. Use PHP services directly:

```php
$results = $reports->byState('CE');
// Manually sort in PHP if needed
usort($results, function($a, $b) {
    return strtotime($b->created_at) - strtotime($a->created_at);
});
```

## Filtering

API accepts exact matches. Use PHP services for advanced filtering:

```php
$all = $reports->getAllRegistrations(1, 10000);
$filtered = array_filter($all, function($event) {
    return $event->perfil === 'estudante-ies' && 
           $event->estado === 'CE';
});
```
