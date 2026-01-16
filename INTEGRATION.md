# Integration Guide: React Frontend + FPSE Core Backend

Complete guide for integrating the React form from `/src/` with the FPSE Core WordPress plugin backend.

## Architecture Overview

```
┌─────────────────────────────────┐
│   React Form (Port 5176)        │
│   - 6-step registration form    │
│   - ViaCEP address lookup       │
│   - Client-side validation      │
│   - Input masking               │
└────────────────────┬────────────┘
                     │ HTTPS/HTTP
                     ↓
┌─────────────────────────────────┐
│   FPSE Core REST API            │
│   POST /wp-json/fpse/v1/register│
│   GET /wp-json/fpse/v1/nonce    │
│   GET /wp-json/fpse/v1/...      │
└────────────────────┬────────────┘
                     │ WordPress
                     ↓
┌─────────────────────────────────┐
│   WordPress Users & Meta        │
│   - User account created        │
│   - Registration data stored    │
│   - Audit events recorded       │
└─────────────────────────────────┘
```

## Prerequisites

1. **React Form**: Running on http://localhost:5176 (or your domain)
2. **WordPress**: Running with FPSE Core plugin activated
3. **CORS**: Configured for cross-origin requests (see below)

## CORS Configuration

### For Development (Same Server, Different Port)

Create file: `wp-content/plugins/fpse-core/src/REST/RegistrationController.php` already includes CORS headers via WordPress.

Add this to WordPress `functions.php` or FPSE Core plugin:

```php
add_filter('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        
        return $value;
    });
}, 15);
```

### For Production (Different Domain)

Update to specific domain:

```php
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = [
    'https://example.com',
    'https://admin.example.com',
];

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
```

## React Component Integration

### 1. Create API Service

File: `src/services/registrationService.ts`

```typescript
import axios, { AxiosError } from 'axios';

interface RegistrationResponse {
  success: boolean;
  message: string;
  user_id?: number;
  perfil?: string;
  estado?: string;
  errors?: string[];
}

interface NonceResponse {
  success: boolean;
  nonce: string;
  nonce_name: string;
  nonce_action: string;
}

// Change this to your WordPress installation URL
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost/wp-json';
const API_ENDPOINT = `${API_BASE_URL}/fpse/v1`;

export const registrationService = {
  /**
   * Get nonce token from backend
   */
  async getNonce(): Promise<string> {
    try {
      const response = await axios.get<NonceResponse>(`${API_ENDPOINT}/nonce`);
      return response.data.nonce;
    } catch (error) {
      console.error('Failed to get nonce:', error);
      throw new Error('Falha ao obter token de segurança');
    }
  },

  /**
   * Submit registration form
   */
  async submitRegistration(data: Record<string, any>): Promise<RegistrationResponse> {
    try {
      // Get fresh nonce
      const nonce = await this.getNonce();

      // Add nonce to form data
      const payload = {
        fpse_nonce: nonce,
        ...data
      };

      const response = await axios.post<RegistrationResponse>(
        `${API_ENDPOINT}/register`,
        payload,
        {
          headers: {
            'Content-Type': 'application/json',
          },
        }
      );

      return response.data;
    } catch (error) {
      const axiosError = error as AxiosError<RegistrationResponse>;
      
      if (axiosError.response?.data) {
        return axiosError.response.data;
      }

      return {
        success: false,
        message: 'Erro ao enviar formulário. Tente novamente.',
      };
    }
  },

  /**
   * Get user registration data
   */
  async getUserData(userId: number, authToken?: string): Promise<any> {
    try {
      const headers: Record<string, string> = {
        'Content-Type': 'application/json',
      };

      if (authToken) {
        headers['Authorization'] = `Bearer ${authToken}`;
      }

      const response = await axios.get(
        `${API_ENDPOINT}/registration/${userId}`,
        { headers }
      );

      return response.data;
    } catch (error) {
      console.error('Failed to get user data:', error);
      throw error;
    }
  },
};
```

### 2. Update Environment Configuration

File: `.env`

```bash
# WordPress API Configuration
REACT_APP_API_URL=http://localhost/wp-json
REACT_APP_REGISTRATION_ENDPOINT=/fpse/v1/register
```

File: `.env.production`

```bash
# Production WordPress
REACT_APP_API_URL=https://api.example.com/wp-json
REACT_APP_REGISTRATION_ENDPOINT=/fpse/v1/register
```

### 3. Update Form Submission

Modify `src/components/ResumoStep.tsx`:

```typescript
import { registrationService } from '../services/registrationService';

export function ResumoStep({ 
  data, 
  onPrevious, 
  onSuccess 
}: ResumoStepProps) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async () => {
    setLoading(true);
    setError(null);

    try {
      // Convert form data to snake_case for API
      const apiData = {
        nome_completo: data.nomeCompleto,
        cpf: data.cpf?.replace(/\D/g, ''),
        email: data.email,
        email_login: data.emailLogin,
        senha_login: data.senhaLogin,
        telefone: data.telefone?.replace(/\D/g, ''),
        data_nascimento: data.dataNascimento,
        genero: data.genero,
        raca_cor: data.racaCor,
        perfil_usuario: data.perfilUsuario,
        vinculo_institucional: data.vinculoInstitucional,
        estado: data.estado,
        municipio: data.municipio,
        logradouro: data.logradouro,
        cep: data.cep?.replace(/\D/g, ''),
        numero: data.numero,
        bairro: data.bairro,
        complemento: data.complemento,
        acessibilidade: data.acessibilidade,
        descricao_acessibilidade: data.descricaoAcessibilidade,
        // Include profile-specific fields
        ...data,
      };

      const response = await registrationService.submitRegistration(apiData);

      if (response.success) {
        onSuccess?.(response);
      } else {
        setError(response.message || 'Erro ao registrar usuário');
      }
    } catch (err) {
      console.error('Submission error:', err);
      setError('Erro ao enviar formulário. Tente novamente.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="bg-blue-50 border border-blue-200 rounded p-4">
        <h2 className="text-lg font-semibold text-blue-900 mb-4">
          Resumo do Cadastro
        </h2>
        
        {/* Display all form data */}
        <dl className="grid grid-cols-2 gap-4">
          <div>
            <dt className="text-sm font-medium text-gray-600">Nome Completo</dt>
            <dd className="text-gray-900">{data.nomeCompleto}</dd>
          </div>
          {/* ... more fields ... */}
        </dl>

        {error && (
          <div className="mt-4 p-3 bg-red-100 border border-red-300 text-red-700 rounded">
            {error}
          </div>
        )}

        <div className="mt-6 flex gap-3">
          <button
            onClick={onPrevious}
            disabled={loading}
            className="flex-1 px-4 py-2 border rounded hover:bg-gray-50"
          >
            Anterior
          </button>
          <button
            onClick={handleSubmit}
            disabled={loading}
            className="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
          >
            {loading ? 'Enviando...' : 'Finalizar Cadastro'}
          </button>
        </div>
      </div>
    </div>
  );
}
```

### 4. Handle Success Response

```typescript
const handleSuccess = (response: RegistrationResponse) => {
  // Show success message
  alert(`Cadastro realizado com sucesso! ID: ${response.user_id}`);
  
  // Store user ID for future reference
  localStorage.setItem('fpse_user_id', response.user_id?.toString() || '');
  
  // Redirect to success page
  window.location.href = '/success';
  
  // Or trigger a callback
  props.onRegistrationComplete?.(response);
};
```

## API Configuration Best Practices

### Development Setup

**WordPress running locally:**
```
http://localhost/
wp-json endpoint: http://localhost/wp-json/fpse/v1
```

**React running on Vite:**
```
http://localhost:5176
```

**CORS headers**: Configure WordPress to allow localhost:5176

### Production Setup

**WordPress on subdomain:**
```
https://api.example.com/wp-json/fpse/v1
```

**React on main domain:**
```
https://example.com
```

**CORS headers**: Configure WordPress to allow example.com

## Error Handling

```typescript
interface ApiError {
  code: string;
  message: string;
  details?: Record<string, string[]>;
}

// Handle different error scenarios
async function handleRegistrationError(error: AxiosError) {
  if (error.response?.status === 429) {
    // Rate limited
    alert('Você atingiu o limite de cadastros. Tente novamente em 1 hora.');
  } else if (error.response?.status === 400) {
    // Validation error
    const data = error.response.data as RegistrationResponse;
    if (data.errors) {
      alert(`Erro: ${data.errors.join(', ')}`);
    }
  } else if (error.response?.status === 403) {
    // Not allowed
    alert('Registros não estão disponíveis no momento.');
  } else {
    // Network or server error
    alert('Erro ao conectar com o servidor. Verifique sua conexão.');
  }
}
```

## Testing Integration

### 1. Manual Testing

```bash
# Terminal 1: Start React app
cd /Users/fransouzaweb/sites/form-fpse
npm run dev

# Terminal 2: Start WordPress dev server (if needed)
# WordPress should already be running

# Open browser
http://localhost:5176
```

### 2. Test Each Step

1. **Step 1 - Dados Pessoais**: Fill in personal data
2. **Step 2 - Vínculo Institucional**: Select institutional link
3. **Step 3 - Endereço**: Enter address (test ViaCEP lookup)
4. **Step 4 - Acessibilidade**: Select accessibility needs
5. **Step 5 - Criação de Login**: Create login credentials
6. **Step 6 - Resumo**: Review and submit

### 3. Verify Backend

```bash
# Check if user was created
wp user list

# Check if event was recorded
wp db query "SELECT * FROM wp_fpse_events ORDER BY created_at DESC LIMIT 5;"

# Check user meta
wp user meta get <user_id>

# Check logs
tail -f /path/to/wp-content/fpse-core.log
```

### 4. Test Error Cases

```bash
# Missing required field
curl -X POST http://localhost/wp-json/fpse/v1/register \
  -H "Content-Type: application/json" \
  -d '{"fpse_nonce": "invalid", "nome_completo": "Test"}'

# Invalid nonce
curl -X POST http://localhost/wp-json/fpse/v1/register \
  -H "Content-Type: application/json" \
  -d '{
    "fpse_nonce": "invalid_nonce_123",
    "nome_completo": "Test User",
    "email": "test@example.com"
  }'

# Rate limit (5 requests in quick succession)
for i in {1..6}; do
  curl -X POST http://localhost/wp-json/fpse/v1/register \
    -H "Content-Type: application/json" \
    -d '{...}'
done
```

## Security Considerations

### In React App

```typescript
// 1. Always get fresh nonce before submission
const nonce = await registrationService.getNonce();

// 2. Never store sensitive data in localStorage
localStorage.removeItem('password');
localStorage.removeItem('cpf');

// 3. Use HTTPS in production
const isProduction = process.env.NODE_ENV === 'production';
const protocol = isProduction ? 'https' : 'http';

// 4. Validate data client-side before sending
const validation = await validateFormData(data);
if (!validation.valid) {
  showErrors(validation.errors);
  return;
}

// 5. Implement rate limiting UI feedback
if (error.includes('Limite de requisições')) {
  disableSubmit();
  startCountdown(3600); // 1 hour
}
```

### In WordPress Plugin

✅ Nonce validation (already done)
✅ Input sanitization (already done)
✅ Rate limiting (already done)
✅ Prepared statements (already done)
✅ Capability checks (already done)

## Performance Optimization

### React Side

```typescript
// 1. Lazy load registration service
const registrationService = lazy(() => import('./services/registrationService'));

// 2. Cache nonce (valid for 1 day)
const nonceCache = {
  nonce: null,
  expires: 0,
  
  async getNonce() {
    const now = Date.now();
    if (this.nonce && this.expires > now) {
      return this.nonce;
    }
    this.nonce = await fetchNonce();
    this.expires = now + (24 * 60 * 60 * 1000); // 24 hours
    return this.nonce;
  }
};

// 3. Debounce address lookup (already in AddressAutocomplete)
```

### WordPress Side

✅ Database indexes (already created)
✅ Query optimization (already done)
✅ Transient caching (already done)

## Monitoring & Debugging

### React Console Logging

```typescript
// Enable debug logging in development
const DEBUG = process.env.NODE_ENV === 'development';

if (DEBUG) {
  const originalFetch = fetch;
  window.fetch = function(...args) {
    console.log('[API Request]', args[0], args[1]);
    return originalFetch.apply(this, args);
  };
}
```

### WordPress Debug Logging

```bash
# Watch logs in real-time
tail -f /path/to/wp-content/fpse-core.log

# Filter by level
grep "\[ERROR\]" /path/to/wp-content/fpse-core.log

# Check recent events
wp db query "SELECT event, COUNT(*) as count FROM wp_fpse_events GROUP BY event ORDER BY created_at DESC;"
```

## Deployment Checklist

- [ ] React app builds successfully: `npm run build`
- [ ] WordPress plugin activated
- [ ] CORS configured for production domain
- [ ] Environment variables set (.env.production)
- [ ] Database migrated and tables created
- [ ] User permissions configured
- [ ] SSL/HTTPS enabled
- [ ] API endpoint accessible: `https://example.com/wp-json/fpse/v1/nonce`
- [ ] Logging directory writable: `/wp-content/`
- [ ] Rate limiting working
- [ ] Form data validation working end-to-end
- [ ] Success page ready
- [ ] Error handling tested
- [ ] Email notifications configured (future)

## Troubleshooting

### "Failed to fetch nonce"

**Problem**: React can't reach WordPress API

**Solutions**:
1. Check WordPress is running: `curl http://localhost/wp-json/`
2. Check CORS headers: Browser DevTools → Network → Response Headers
3. Check API_BASE_URL in .env
4. Check WordPress REST API enabled: `wp rest-api status`

### "Invalid nonce token"

**Problem**: Nonce is expired or invalid

**Solutions**:
1. Get fresh nonce: `curl http://localhost/wp-json/fpse/v1/nonce`
2. Check nonce isn't cached > 1 day
3. Check WordPress clock is correct: `date -u`

### "Rate limited (429)"

**Problem**: Too many registrations from same IP

**Solutions**:
1. Wait 1 hour for limit to reset
2. Change IP (VPN/proxy)
3. Modify rate limit in plugin config
4. Use different browser/client

### "Email already exists"

**Problem**: Email is already registered

**Solutions**:
1. Use different email
2. Delete user: `wp user delete <user_id> --reassign=admin`
3. Update existing user with same email

### Form not submitting

**Problem**: Silent failure or stuck loading state

**Solutions**:
1. Check browser console for errors
2. Check Network tab in DevTools
3. Check WordPress debug.log
4. Check fpse-core.log
5. Verify form data is valid

## Advanced Integration

### Integrating with Other WordPress Plugins

```php
// In another plugin or theme
do_action('fpse_user_registered', $user_id, $perfil, $estado);

// Listen for events
add_action('fpse_user_registered', function($user_id, $perfil, $estado) {
    // Send email, webhook, etc.
    send_welcome_email($user_id);
}, 10, 3);
```

### Custom Frontend UI

```typescript
// Create custom form component
import { useState } from 'react';
import { registrationService } from './services/registrationService';

export function CustomRegistrationForm() {
  const [formData, setFormData] = useState({});
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    
    try {
      const response = await registrationService.submitRegistration(formData);
      if (response.success) {
        window.location.href = `/registered/${response.user_id}`;
      }
    } finally {
      setLoading(false);
    }
  };

  return <form onSubmit={handleSubmit}>...</form>;
}
```

## Support & Resources

- **React Component Tests**: See `src/components/__tests__/`
- **WordPress Plugin Tests**: See `fpse-core/tests/` (not yet created)
- **API Postman Collection**: See `fpse-core/postman/` (example)
- **Documentation**: `README.md`, `QUICK_START.md`, `API.md`

---

**Last Updated**: 2024
**Version**: 1.0.0
