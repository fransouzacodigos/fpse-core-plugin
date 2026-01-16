# FPSE Core - Quick Start Guide

Get the plugin up and running in 5 minutes.

## Prerequisites

- WordPress 5.9 or higher
- PHP 8.0 or higher
- Database with write permissions
- **Optional**: Composer (but not required!)

## 1. Installation (Choose One Option)

### ⚡ Option A: Without Composer (FASTEST - 30 seconds)

```bash
# 1. Copy plugin folder
cp -r fpse-core wp-content/plugins/
# OR: Upload via SFTP/FTP to wp-content/plugins/fpse-core

# 2. Done! Activate via WordPress admin
# Dashboard > Plugins > FPSE Core > Activate
```

**That's it!** The autoloader works without Composer.

### Option B: With Composer (RECOMMENDED for production)

```bash
cd wp-content/plugins
git clone <repository> fpse-core
cd fpse-core
composer install
```

### Option C: Manual File Includes

```bash
# If you prefer, manually load files without autoloader
# See INSTALACAO-SEM-COMPOSER.md for details
```

**Recommendation**: Use **Option A** for simplicity, **Option B** for production.

## 2. Activate Plugin

### Via CLI:
```bash
wp plugin activate fpse-core
```

### Via WordPress Admin:
1. Go to **Plugins** in WordPress dashboard
2. Find **Fortalece PSE Core**
3. Click **Activate**

## 3. Verify Installation

```bash
# Check plugin is active
wp plugin list --field=name | grep fpse-core

# Check database table was created
wp db query "SHOW TABLES LIKE '%fpse_events%';"

# Check log file exists
ls -la /path/to/wp-content/fpse-core.log
```

## 4. Test Registration Endpoint

```bash
# Get nonce token
NONCE=$(curl -s http://localhost/wp-json/fpse/v1/nonce | jq -r '.nonce')

# Submit registration
curl -X POST http://localhost/wp-json/fpse/v1/register \
  -H "Content-Type: application/json" \
  -d '{
    "fpse_nonce": "'$NONCE'",
    "nome_completo": "João da Silva",
    "cpf": "123.456.789-00",
    "email": "joao@example.com",
    "email_login": "joao@example.com",
    "senha_login": "SecurePass123!",
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
    "acessibilidade": false,
    "instituicao_nome": "UECE",
    "curso_nome": "Pedagogia"
  }'
```

## 5. Retrieve User Data

```bash
# Get user ID from previous response (or use wp cli)
USER_ID=42

# Get registration data (must be logged in or own data)
curl -X GET http://localhost/wp-json/fpse/v1/registration/$USER_ID \
  -H "Authorization: Bearer <jwt_token>"
```

## 6. Query Reports

Via PHP (in plugin context):

```php
use FortaleceePSE\Core\Reports\ReportRegistry;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$reports = new ReportRegistry($plugin);

// Get all registrations from CE state
$ceUsers = $reports->byState('CE');

// Get counts by profile
$byProfile = $reports->countByProfile();

// Get paginated results
$page = $reports->getAllRegistrations(1, 50);

foreach ($page['data'] as $event) {
    echo "Event: " . $event->event . "\n";
    echo "Profile: " . $event->perfil . "\n";
    echo "State: " . $event->estado . "\n";
}
```

## Available User Profiles

When registering, use one of these `perfil_usuario` values:

### EAA (Educação de Adolescentes e Adultos)
- `estudante-eaa`
- `professor-eaa`
- `gestor-eaa`

### IES (Instituição de Ensino Superior)
- `estudante-ies`
- `professor-ies`
- `pesquisador`

### NAP (Núcleo de Acessibilidade Pedagógica)
- `gestor-nap`
- `assistente-nap`

### GTI (Gestão Tecnológica Inclusiva)
- `gestor-gti`
- `tecnico-gti`

### Governance
- `coordenador-institucional`
- `monitor-programa`

## Configuration

All configuration files are in `fpse-core/config/`:

- **states.php**: Modify 27 Brazilian states
- **profiles.php**: Add/modify user profiles
- **report_fields.php**: Define field metadata
- **permissions.php**: Control access and rate limits
- **debug.php**: Logging configuration

## Common Tasks

### Enable Debug Logging

Edit `wp-config.php`:
```php
define('WP_DEBUG', true);
```

Log file location: `/wp-content/fpse-core.log`

### Change Rate Limit

Edit `config/permissions.php`:
```php
'rate_limits' => [
    'register' => 10,    // Changed from 5 to 10 requests/hour
    'default' => 100,
],
```

### Add Custom Profile

Edit `config/profiles.php`:
```php
'meu-perfil' => [
    'label' => 'Meu Perfil',
    'category' => 'Custom',
    'description' => 'Meu perfil personalizado',
    'specific_fields' => ['campo1', 'campo2'],
],
```

### Grant Capabilities to Role

Via PHP/WordPress:
```php
use FortaleceePSE\Core\Services\PermissionService;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$permissions = new PermissionService($plugin);

// Grant to editor role
$permissions->grantCapabilitiesToRole('editor');
```

Or via CLI:
```bash
wp plugin install capability-manager-enhanced --activate
wp cap-manager add-cap editor view_fpse_registrations
wp cap-manager add-cap editor view_fpse_reports
```

## Troubleshooting

### "Plugin requires PHP 8.0+"

You're running an older PHP version. Upgrade your server:
```bash
php -v  # Check current version
```

### "Fatal error: Failed opening required vendor/autoload.php"

Run Composer installation:
```bash
cd wp-content/plugins/fpse-core
composer install
```

### "Database error: Table 'wpX_fpse_events' doesn't exist"

Reactivate the plugin to recreate the table:
```bash
wp plugin deactivate fpse-core
wp plugin activate fpse-core
```

### "Limit of 5 requests per hour exceeded"

Rate limit is working correctly. Either:
- Wait 1 hour for limit to reset
- Use different IP/client
- Modify rate limit in `config/permissions.php`

### "Invalid or expired nonce token"

Get a fresh nonce:
```bash
curl -s http://localhost/wp-json/fpse/v1/nonce | jq
```

Nonces expire after 1 day. Generate new one in your form.

## Integration with Frontend

### React/TypeScript Example

```typescript
// Get nonce
const getNonce = async () => {
  const response = await fetch('/wp-json/fpse/v1/nonce');
  const data = await response.json();
  return data.nonce;
};

// Register user
const register = async (formData) => {
  const nonce = await getNonce();
  
  const response = await fetch('/wp-json/fpse/v1/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      fpse_nonce: nonce,
      ...formData
    })
  });
  
  return await response.json();
};
```

### JavaScript Example

```javascript
// Get nonce
async function getNonce() {
  const response = await fetch('/wp-json/fpse/v1/nonce');
  const data = await response.json();
  return data.nonce;
}

// Submit form
document.getElementById('registerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const nonce = await getNonce();
  const formData = new FormData(e.target);
  const data = Object.fromEntries(formData);
  
  const response = await fetch('/wp-json/fpse/v1/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ fpse_nonce: nonce, ...data })
  });
  
  const result = await response.json();
  if (result.success) {
    alert(`Usuário ${result.user_id} criado!`);
  } else {
    alert(result.message);
  }
});
```

## Performance Tips

1. **Database Indexes**: Table already has indexes on user_id, event, estado, created_at
2. **Caching**: Use WordPress object cache for repeated queries
3. **Transients**: Rate limits use WordPress transients (1-hour expiry)
4. **Lazy Loading**: Services only instantiated when needed

## Next Steps

1. ✅ Install and activate plugin
2. ✅ Test registration endpoint
3. → Integrate with frontend form
4. → Set up custom profiles/fields
5. → Configure logging
6. → Create report queries
7. → (Future) Implement report exports

## Getting Help

- Read [README.md](README.md) for full documentation
- Check WordPress error logs: `/wp-content/debug.log`
- Check plugin logs: `/wp-content/fpse-core.log`
- Review code comments in source files

## What's Next?

The plugin is ready for production use:
- ✅ User registration via REST API
- ✅ Audit trail tracking
- ✅ Permission system
- ✅ Rate limiting
- ✅ Logging with masking

Future enhancements (not implemented):
- JWT authentication
- Dashboard UI
- Report exports (CSV/PDF)
- Webhook notifications
- Batch import
