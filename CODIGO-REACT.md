# 📝 Código React para Integração com Plugin

Este arquivo contém todo o código necessário para integrar o formulário React com o plugin WordPress FPSE Core.

## 1️⃣ Serviço de API (registrationService.ts)

**Arquivo:** `src/services/registrationService.ts`

```typescript
import axios, { AxiosError } from 'axios';

// ============================================================================
// TIPOS DE DADOS
// ============================================================================

export interface RegistrationResponse {
  success: boolean;
  message: string;
  user_id?: number;
  perfil?: string;
  estado?: string;
  errors?: string[];
}

export interface NonceResponse {
  success: boolean;
  nonce: string;
  nonce_name: string;
  nonce_action: string;
}

// ============================================================================
// CONFIGURAÇÃO
// ============================================================================

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost/wp-json';
const API_ENDPOINT = `${API_BASE_URL}/fpse/v1`;

// Timeout para requisições
const REQUEST_TIMEOUT = 30000; // 30 segundos

// ============================================================================
// SERVIÇO DE REGISTRO
// ============================================================================

export const registrationService = {
  /**
   * Obtém token de segurança (nonce) do servidor
   * Este token é necessário para proteger contra ataques CSRF
   */
  async getNonce(): Promise<string> {
    try {
      const response = await axios.get<NonceResponse>(
        `${API_ENDPOINT}/nonce`,
        { timeout: REQUEST_TIMEOUT }
      );

      if (!response.data.success) {
        throw new Error('Falha ao obter token de segurança');
      }

      return response.data.nonce;
    } catch (error) {
      console.error('Erro ao obter nonce:', error);
      throw new Error('Falha ao obter token de segurança');
    }
  },

  /**
   * Submete o formulário de cadastro
   * Valida o nonce e envia os dados para o servidor
   */
  async submitRegistration(
    data: Record<string, any>
  ): Promise<RegistrationResponse> {
    try {
      // Obter nonce fresco
      const nonce = await this.getNonce();

      // Preparar payload com nonce
      const payload = {
        fpse_nonce: nonce,
        ...data,
      };

      // Enviar requisição POST
      const response = await axios.post<RegistrationResponse>(
        `${API_ENDPOINT}/register`,
        payload,
        {
          headers: {
            'Content-Type': 'application/json',
          },
          timeout: REQUEST_TIMEOUT,
        }
      );

      return response.data;
    } catch (error) {
      // Extrair erro da resposta
      const axiosError = error as AxiosError<RegistrationResponse>;

      if (axiosError.response?.data) {
        return axiosError.response.data;
      }

      // Erros específicos de rede
      if (axiosError.code === 'ECONNABORTED') {
        return {
          success: false,
          message: 'Requisição expirou. O servidor demorou demais para responder.',
        };
      }

      if (axiosError.response?.status === 429) {
        return {
          success: false,
          message:
            'Você atingiu o limite de cadastros. Tente novamente em 1 hora.',
        };
      }

      if (axiosError.response?.status === 403) {
        return {
          success: false,
          message: 'Acesso negado. Registros podem estar desativados.',
        };
      }

      return {
        success: false,
        message: 'Erro ao enviar formulário. Verifique sua conexão com a internet.',
      };
    }
  },

  /**
   * Obtém dados de um usuário já cadastrado
   * Requer permissão adequada no WordPress
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
        { headers, timeout: REQUEST_TIMEOUT }
      );

      return response.data;
    } catch (error) {
      console.error('Erro ao obter dados do usuário:', error);
      throw error;
    }
  },

  /**
   * Testa se a conexão com a API está funcionando
   * Útil para health checks
   */
  async testConnection(): Promise<boolean> {
    try {
      await axios.get(`${API_BASE_URL}/`, { timeout: 5000 });
      return true;
    } catch {
      return false;
    }
  },
};
```

---

## 2️⃣ Atualizar Componente ResumoStep

**Arquivo:** `src/components/ResumoStep.tsx`

```typescript
import React, { useState } from 'react';
import { registrationService } from '../services/registrationService';

interface ResumoStepProps {
  data: any;
  onPrevious: () => void;
  onSuccess?: (response: any) => void;
  onError?: (message: string) => void;
}

export function ResumoStep({
  data,
  onPrevious,
  onSuccess,
  onError,
}: ResumoStepProps) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = async () => {
    setLoading(true);
    setError(null);

    try {
      // Converter dados para snake_case conforme esperado pelo API
      const apiData = convertToSnakeCase(data);

      // Enviar para WordPress
      const response = await registrationService.submitRegistration(apiData);

      if (response.success) {
        setSubmitted(true);

        // Guardar ID do usuário
        if (response.user_id) {
          localStorage.setItem('fpse_user_id', response.user_id.toString());
          localStorage.setItem('fpse_user_perfil', response.perfil || '');
          localStorage.setItem('fpse_user_estado', response.estado || '');
        }

        // Disparar callback de sucesso
        onSuccess?.(response);

        // Redirecionar após 2 segundos
        setTimeout(() => {
          window.location.href = '/sucesso';
        }, 2000);
      } else {
        setError(response.message || 'Erro ao registrar usuário');
        onError?.(response.message || 'Erro desconhecido');
      }
    } catch (err) {
      const errorMessage = 'Erro ao enviar formulário. Tente novamente.';
      setError(errorMessage);
      onError?.(errorMessage);
      console.error('Erro de submissão:', err);
    } finally {
      setLoading(false);
    }
  };

  if (submitted) {
    return (
      <div className="space-y-4">
        <div className="bg-green-50 border border-green-200 rounded-lg p-6">
          <div className="flex items-center gap-3 mb-2">
            <div className="text-2xl">✅</div>
            <h2 className="text-2xl font-bold text-green-900">
              Cadastro Realizado com Sucesso!
            </h2>
          </div>
          <p className="text-green-700 mb-4">
            Seu cadastro foi enviado para análise. Você receberá um email de
            confirmação em breve.
          </p>
          <p className="text-sm text-green-600">
            ID do Cadastro: <strong>{localStorage.getItem('fpse_user_id')}</strong>
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="bg-white border border-gray-200 rounded-lg p-6">
        <h2 className="text-2xl font-bold text-gray-900 mb-6">
          Resumo do Cadastro
        </h2>

        {/* Exibir todos os dados em grupos */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          {/* Dados Pessoais */}
          <section className="p-4 bg-blue-50 rounded-lg">
            <h3 className="font-semibold text-blue-900 mb-3">
              📋 Dados Pessoais
            </h3>
            <dl className="space-y-2 text-sm">
              <div>
                <dt className="text-gray-600">Nome Completo</dt>
                <dd className="text-gray-900 font-medium">
                  {data.nomeCompleto}
                </dd>
              </div>
              <div>
                <dt className="text-gray-600">CPF</dt>
                <dd className="text-gray-900 font-medium">{data.cpf}</dd>
              </div>
              <div>
                <dt className="text-gray-600">Data de Nascimento</dt>
                <dd className="text-gray-900 font-medium">
                  {new Date(data.dataNascimento).toLocaleDateString('pt-BR')}
                </dd>
              </div>
              <div>
                <dt className="text-gray-600">Gênero</dt>
                <dd className="text-gray-900 font-medium">{data.genero}</dd>
              </div>
              <div>
                <dt className="text-gray-600">Raça/Cor</dt>
                <dd className="text-gray-900 font-medium">{data.racaCor}</dd>
              </div>
            </dl>
          </section>

          {/* Contato */}
          <section className="p-4 bg-green-50 rounded-lg">
            <h3 className="font-semibold text-green-900 mb-3">📞 Contato</h3>
            <dl className="space-y-2 text-sm">
              <div>
                <dt className="text-gray-600">Email Principal</dt>
                <dd className="text-gray-900 font-medium">{data.email}</dd>
              </div>
              <div>
                <dt className="text-gray-600">Email de Login</dt>
                <dd className="text-gray-900 font-medium">{data.emailLogin}</dd>
              </div>
              <div>
                <dt className="text-gray-600">Telefone</dt>
                <dd className="text-gray-900 font-medium">{data.telefone}</dd>
              </div>
            </dl>
          </section>

          {/* Endereço */}
          <section className="p-4 bg-purple-50 rounded-lg">
            <h3 className="font-semibold text-purple-900 mb-3">📍 Endereço</h3>
            <dl className="space-y-2 text-sm">
              <div>
                <dt className="text-gray-600">Estado</dt>
                <dd className="text-gray-900 font-medium">{data.estado}</dd>
              </div>
              <div>
                <dt className="text-gray-600">Município</dt>
                <dd className="text-gray-900 font-medium">{data.municipio}</dd>
              </div>
              <div>
                <dt className="text-gray-600">CEP</dt>
                <dd className="text-gray-900 font-medium">{data.cep}</dd>
              </div>
              <div>
                <dt className="text-gray-600">Logradouro</dt>
                <dd className="text-gray-900 font-medium">{data.logradouro}</dd>
              </div>
              <div>
                <dt className="text-gray-600">Número</dt>
                <dd className="text-gray-900 font-medium">{data.numero}</dd>
              </div>
            </dl>
          </section>

          {/* Perfil */}
          <section className="p-4 bg-orange-50 rounded-lg">
            <h3 className="font-semibold text-orange-900 mb-3">👤 Perfil</h3>
            <dl className="space-y-2 text-sm">
              <div>
                <dt className="text-gray-600">Perfil de Usuário</dt>
                <dd className="text-gray-900 font-medium">
                  {data.perfilUsuario}
                </dd>
              </div>
              <div>
                <dt className="text-gray-600">Vínculo Institucional</dt>
                <dd className="text-gray-900 font-medium">
                  {data.vinculoInstitucional}
                </dd>
              </div>
              {data.instituicaoNome && (
                <div>
                  <dt className="text-gray-600">Instituição</dt>
                  <dd className="text-gray-900 font-medium">
                    {data.instituicaoNome}
                  </dd>
                </div>
              )}
            </dl>
          </section>
        </div>

        {/* Mensagem de Erro */}
        {error && (
          <div className="mb-6 p-4 bg-red-100 border border-red-300 text-red-700 rounded-lg">
            <strong>Erro:</strong> {error}
          </div>
        )}

        {/* Aviso de Submissão */}
        <div className="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800 text-sm">
          <strong>⚠️ Atenção:</strong> Ao finalizar, você concorda com os
          termos de privacidade e os dados serão armazenados em nosso banco de
          dados.
        </div>

        {/* Botões de Ação */}
        <div className="flex gap-3 justify-between pt-6 border-t">
          <button
            onClick={onPrevious}
            disabled={loading}
            className="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 font-medium text-gray-700 transition"
          >
            ← Anterior
          </button>
          <button
            onClick={handleSubmit}
            disabled={loading}
            className="px-8 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 font-medium transition flex items-center gap-2"
          >
            {loading ? (
              <>
                <span className="animate-spin">⏳</span>
                Enviando...
              </>
            ) : (
              <>
                ✓ Finalizar Cadastro
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

/**
 * Converte objeto com propriedades em camelCase para snake_case
 * Necessário pois o API espera snake_case
 */
function convertToSnakeCase(obj: Record<string, any>): Record<string, any> {
  const result: Record<string, any> = {};

  const camelToSnake = (str: string): string => {
    return str.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`);
  };

  for (const [key, value] of Object.entries(obj)) {
    result[camelToSnake(key)] = value;
  }

  return result;
}
```

---

## 3️⃣ Configuração de Ambiente

**Arquivo:** `.env`

```bash
# API WordPress
REACT_APP_API_URL=http://localhost/wp-json

# Outras variáveis
VITE_HOST=localhost
VITE_PORT=5176
```

**Arquivo:** `.env.production`

```bash
# API WordPress (Produção)
REACT_APP_API_URL=https://seudominio.com/wp-json
```

---

## 4️⃣ Atualizar FormRegistro (Principal)

**Arquivo:** `src/components/FormRegistro.tsx`

```typescript
import React, { useState } from 'react';
import { Step1 } from './steps/Step1';
import { Step2 } from './steps/Step2';
import { Step3 } from './steps/Step3';
import { Step4 } from './steps/Step4';
import { Step5 } from './steps/Step5';
import { ResumoStep } from './ResumoStep';

const TOTAL_STEPS = 6;

export function FormRegistro() {
  const [currentStep, setCurrentStep] = useState(1);
  const [formData, setFormData] = useState({});

  const handleNext = (stepData: any) => {
    setFormData({ ...formData, ...stepData });
    setCurrentStep((prev) => Math.min(prev + 1, TOTAL_STEPS));
  };

  const handlePrevious = () => {
    setCurrentStep((prev) => Math.max(prev - 1, 1));
  };

  const handleSuccess = (response: any) => {
    console.log('Cadastro bem-sucedido:', response);
    // Redirecionar ou mostrar mensagem
  };

  const handleError = (message: string) => {
    console.error('Erro ao registrar:', message);
    // Mostrar erro para o usuário
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white py-12 px-4">
      <div className="max-w-2xl mx-auto">
        {/* Barra de Progresso */}
        <div className="mb-8">
          <div className="flex justify-between items-center mb-4">
            <h1 className="text-3xl font-bold text-gray-900">
              Cadastro FPSE
            </h1>
            <span className="text-sm text-gray-500">
              Passo {currentStep} de {TOTAL_STEPS}
            </span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div
              className="bg-blue-600 h-2 rounded-full transition-all duration-300"
              style={{ width: `${(currentStep / TOTAL_STEPS) * 100}%` }}
            ></div>
          </div>
        </div>

        {/* Componente do Passo Atual */}
        <div className="bg-white rounded-lg shadow-lg p-8">
          {currentStep === 1 && (
            <Step1 data={formData} onNext={handleNext} />
          )}
          {currentStep === 2 && (
            <Step2
              data={formData}
              onNext={handleNext}
              onPrevious={handlePrevious}
            />
          )}
          {currentStep === 3 && (
            <Step3
              data={formData}
              onNext={handleNext}
              onPrevious={handlePrevious}
            />
          )}
          {currentStep === 4 && (
            <Step4
              data={formData}
              onNext={handleNext}
              onPrevious={handlePrevious}
            />
          )}
          {currentStep === 5 && (
            <Step5
              data={formData}
              onNext={handleNext}
              onPrevious={handlePrevious}
            />
          )}
          {currentStep === 6 && (
            <ResumoStep
              data={formData}
              onPrevious={handlePrevious}
              onSuccess={handleSuccess}
              onError={handleError}
            />
          )}
        </div>
      </div>
    </div>
  );
}
```

---

## 5️⃣ Exemplo de Uso Completo

**Fluxo do Usuário:**

```
1. User preenche Step 1-5
   └─ setFormData() atualiza estado

2. User clica "Finalizar Cadastro" no Passo 6
   └─ ResumoStep.tsx chama handleSubmit()

3. handleSubmit() chama registrationService.submitRegistration()
   ├─ Chama getNonce() → GET /wp-json/fpse/v1/nonce
   ├─ Obtém token nonce
   └─ Chama axios.post() → POST /wp-json/fpse/v1/register

4. WordPress Plugin processa:
   ├─ Valida nonce
   ├─ Valida rate limit
   ├─ Valida dados
   ├─ Cria usuário
   ├─ Registra evento
   └─ Retorna resposta JSON

5. React recebe resposta:
   ├─ Se sucesso:
   │  ├─ Exibe mensagem de sucesso
   │  ├─ Guarda user_id no localStorage
   │  └─ Redireciona para /sucesso
   └─ Se erro:
      └─ Exibe mensagem de erro e permite reenvio

6. Usuário vê página de sucesso
   └─ Dados salvos no WordPress
```

---

## ✅ Checklist de Implementação

- [ ] Criar `src/services/registrationService.ts`
- [ ] Atualizar `src/components/ResumoStep.tsx`
- [ ] Criar/atualizar `.env` com `REACT_APP_API_URL`
- [ ] Atualizar `src/components/FormRegistro.tsx`
- [ ] Testar em desenvolvimento (`npm run dev`)
- [ ] Testar POST em `/wp-json/fpse/v1/register`
- [ ] Verificar dados em wp_users
- [ ] Verificar dados em wp_usermeta
- [ ] Verificar evento em wp_fpse_events
- [ ] Verificar mensagem de sucesso no React

---

## 🧪 Teste Manual

### 1. Inicie o servidor React
```bash
cd /Users/fransouzaweb/sites/form-fpse
npm run dev
# Acesse: http://localhost:5176
```

### 2. Preencha o formulário
```
Nome: João Silva
CPF: 123.456.789-00
Email: joao@example.com
... etc ...
```

### 3. Clique em "Finalizar Cadastro"
```
Esperado: Mensagem de sucesso
Mensagem: "Cadastro Realizado com Sucesso!"
```

### 4. Verifique no WordPress
```bash
wp user get <id>  # Verificar se usuário foi criado
wp db query "SELECT * FROM wp_usermeta WHERE user_id=<id> LIMIT 10;"  # Dados
wp db query "SELECT * FROM wp_fpse_events WHERE user_id=<id> LIMIT 10;"  # Eventos
```

---

**Status**: ✅ Código Completo e Pronto para Usar!
