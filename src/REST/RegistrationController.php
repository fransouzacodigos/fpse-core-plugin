<?php
/**
 * REST Registration Controller
 *
 * Handles REST API endpoints for user registration
 *
 * @package FortaleceePSE
 * @subpackage REST
 */

namespace FortaleceePSE\Core\REST;

use FortaleceePSE\Core\Plugin;
use FortaleceePSE\Core\Domain\RegistrationDTO;
use FortaleceePSE\Core\Security\NonceMiddleware;
use FortaleceePSE\Core\Security\RateLimit;
use FortaleceePSE\Core\Services\UserService;
use FortaleceePSE\Core\Services\ProfileResolver;
use FortaleceePSE\Core\Services\PermissionService;
use FortaleceePSE\Core\Services\EventRecorder;

class RegistrationController {
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var NonceMiddleware
     */
    private $nonce;

    /**
     * @var RateLimit
     */
    private $rateLimit;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ProfileResolver
     */
    private $profileResolver;

    /**
     * @var PermissionService
     */
    private $permissionService;

    /**
     * @var EventRecorder
     */
    private $eventRecorder;

    /**
     * Constructor
     *
     * @param Plugin $plugin Main plugin instance
     */
    public function __construct(Plugin $plugin) {
        try {
            $this->plugin = $plugin;
            $this->nonce = new NonceMiddleware();
            $this->rateLimit = new RateLimit();
            $this->eventRecorder = new EventRecorder();
            $this->userService = new UserService($this->eventRecorder);
            $this->profileResolver = new ProfileResolver($this->plugin);
            $this->permissionService = new PermissionService($this->plugin);
        } catch (\Exception $e) {
            // Log error
            if (function_exists('error_log')) {
                error_log('FPSE Core - Error in RegistrationController constructor: ' . $e->getMessage());
                error_log('FPSE Core - Stack trace: ' . $e->getTraceAsString());
            }
            throw $e;
        }
    }

    /**
     * Register REST routes
     *
     * Called during plugin initialization
     */
    public function registerRoutes() {
        // Log route registration attempt (for debugging)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FPSE: RegistrationController::registerRoutes() executado');
        }

        try {
            // Registration endpoint
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FPSE: Registrando rota /fpse/v1/register...');
            }
            $registerResult = register_rest_route('fpse/v1', '/register', [
                'methods' => 'POST',
                'callback' => [$this, 'handleRegister'],
                'permission_callback' => '__return_true', // Handle in callback
                'args' => [
                    'fpse_nonce' => [
                        'type' => 'string',
                        'required' => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);

            error_log('FPSE: Rota /fpse/v1/register registrada: ' . ($registerResult ? 'SUCESSO' : 'FALHA'));

            // Get nonce endpoint (for frontend)
            error_log('FPSE: Registrando rota /fpse/v1/nonce...');
            $nonceResult = register_rest_route('fpse/v1', '/nonce', [
                'methods' => 'GET',
                'callback' => [$this, 'handleGetNonce'],
                'permission_callback' => '__return_true',
            ]);

            error_log('FPSE: Rota /fpse/v1/nonce registrada: ' . ($nonceResult ? 'SUCESSO' : 'FALHA'));

            // Get registration data (protected)
            $registrationResult = register_rest_route('fpse/v1', '/registration/(?P<id>\d+)', [
                'methods' => 'GET',
                'callback' => [$this, 'handleGetRegistration'],
                'permission_callback' => [$this, 'checkViewPermission'],
                'args' => [
                    'id' => [
                        'type' => 'integer',
                        'required' => true,
                    ],
                ],
            ]);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FPSE: Rota /fpse/v1/registration/(?P<id>\\d+) registrada: ' . ($registrationResult ? 'SUCESSO' : 'FALHA'));
            }
        } catch (\Exception $e) {
            // Log error
            if (function_exists('error_log')) {
                error_log('FPSE Core - Error in registerRoutes: ' . $e->getMessage());
                error_log('FPSE Core - Stack trace: ' . $e->getTraceAsString());
            }
            // Re-throw to ensure error is visible
            throw $e;
        }
    }

    /**
     * Handle registration request
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleRegister($request) {
        // LOG DE DIAGNÓSTICO: Endpoint acionado
        error_log('[FPSE DEBUG] handleRegister() acionado no fpse-core');
        
        // Check permissions
        if (!$this->permissionService->canRegister()) {
            error_log('[FPSE DEBUG] ❌ Registros não estão habilitados');
            return new \WP_REST_Response(
                ['success' => false, 'message' => 'Registros não estão habilitados'],
                403
            );
        }
        
        error_log('[FPSE DEBUG] ✅ Permissões OK - continuando...');

        // Check rate limit
        if (!$this->rateLimit->checkLimit('register', $this->permissionService->getRateLimit('register'))) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => 'Limite de requisições excedido. Tente novamente mais tarde.'],
                429
            );
        }

        // Validate nonce (if provided, validate it; otherwise require it)
        $nonce = $request->get_param('fpse_nonce');
        if (!$nonce || !$this->nonce->verifyNonce($nonce)) {
            // Log and return error
            $this->eventRecorder->recordValidationError(
                'unknown',
                'unknown',
                ['error' => 'Invalid or missing nonce']
            );

            return new \WP_REST_Response(
                ['success' => false, 'message' => 'Token de segurança inválido ou expirado'],
                400
            );
        }

        // Get JSON body
        $body = json_decode($request->get_body(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('FPSE Registration: JSON decode error - ' . json_last_error_msg());
            return new \WP_REST_Response(
                ['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()],
                400
            );
        }

        // Log received data for debugging
        $sanitizedBody = $this->maskSensitiveFields($body ?? []);
        error_log('FPSE Registration: Received data (keys only) - ' . wp_json_encode(array_keys($body ?? [])));
        error_log('FPSE Registration: Received data (full) - ' . wp_json_encode($sanitizedBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Parse DTO from request
        try {
            $dto = RegistrationDTO::fromArray($body ?? []);
            error_log('FPSE Registration: DTO parsed successfully. Profile: ' . ($dto->perfilUsuario ?? 'null'));
            
            // Log profile-specific fields
            if (!empty($dto->profileSpecificFields)) {
                error_log('FPSE Registration: DTO profileSpecificFields - ' . wp_json_encode($dto->profileSpecificFields));
            } else {
                error_log('FPSE Registration: DTO profileSpecificFields está VAZIO');
            }
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error('Failed to parse registration data', [
                'error' => $e->getMessage(),
                'body' => wp_json_encode($body),
            ]);
            error_log('FPSE Registration: DTO parse error - ' . $e->getMessage());

            return new \WP_REST_Response(
                ['success' => false, 'message' => 'Dados de registro inválidos: ' . $e->getMessage()],
                400
            );
        }

        // Validate profile
        // Use toArray() to include profile-specific fields in snake_case format
        $dtoArray = $dto->toArray();
        $sanitizedDtoArray = $this->maskSensitiveFields($dtoArray);
        error_log('FPSE Registration: DTO array (snake_case) - ' . wp_json_encode($sanitizedDtoArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $profileValidation = $this->profileResolver->validateProfile(
            $dto->perfilUsuario,
            $dtoArray
        );

        error_log('FPSE Registration: Profile validation result - ' . wp_json_encode($profileValidation));

        if (!$profileValidation['valid']) {
            $this->eventRecorder->recordValidationError(
                $dto->perfilUsuario ?? 'unknown',
                $dto->estado ?? 'unknown',
                $profileValidation['errors']
            );

            error_log('FPSE Registration: Profile validation failed - ' . wp_json_encode($profileValidation['errors']));

            return new \WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Validação de perfil falhou: ' . implode(', ', $profileValidation['errors']),
                    'errors' => $profileValidation['errors'],
                    'debug' => [
                        'profile' => $dto->perfilUsuario,
                        'received_fields' => array_keys($dtoArray),
                    ],
                ],
                400
            );
        }

        // Validate state
        $states = $this->plugin->getConfig('states', []);
        if (!isset($states[$dto->estado])) {
            $this->eventRecorder->recordValidationError(
                $dto->perfilUsuario,
                $dto->estado,
                ['error' => 'Invalid state code']
            );

            return new \WP_REST_Response(
                ['success' => false, 'message' => 'Estado inválido'],
                400
            );
        }

        // Create or update user
        error_log('[FPSE DEBUG] ✅ Antes de chamar userService->createOrUpdate()');
        error_log('[FPSE DEBUG] DTO perfilUsuario: ' . ($dto->perfilUsuario ?? 'NULL'));
        error_log('[FPSE DEBUG] DTO estado: ' . ($dto->estado ?? 'NULL'));
        
        try {
            $result = $this->userService->createOrUpdate($dto);
            error_log('[FPSE DEBUG] ✅ createOrUpdate() retornou: ' . wp_json_encode($result));
        } catch (\Exception $e) {
            error_log('[FPSE DEBUG] ❌ Exception em createOrUpdate: ' . $e->getMessage());
            error_log('FPSE: Erro crítico em createOrUpdate - ' . $e->getMessage());
            error_log('FPSE: Stack trace - ' . $e->getTraceAsString());
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro ao criar usuário: ' . (defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : 'Erro interno'),
            ], 500);
        }

        if (!$result['success']) {
            error_log('[FPSE DEBUG] ❌ createOrUpdate retornou success=false: ' . ($result['message'] ?? 'sem mensagem'));
            $this->eventRecorder->recordValidationError(
                $dto->perfilUsuario,
                $dto->estado,
                ['error' => $result['message']]
            );

            return new \WP_REST_Response(
                ['success' => false, 'message' => $result['message']],
                400
            );
        }
        
        error_log('[FPSE DEBUG] ✅ createOrUpdate sucesso - User ID: ' . ($result['user_id'] ?? 'NULL'));

        // Prepare success response first (to ensure we always return something)
        $response = [
            'success' => true,
            'message' => 'Cadastro realizado com sucesso! Bem-vindo ao Fortalece PSE.',
            'user_id' => $result['user_id'],
            'perfil' => $dto->perfilUsuario,
            'estado' => $dto->estado,
            'redirect_url' => home_url('/'), // URL para redirecionamento
        ];
        
        // Record profile assignment (with error handling - não falhar se der erro)
        try {
            $this->eventRecorder->recordProfileAssigned(
                $result['user_id'],
                $dto->perfilUsuario,
                $dto->estado
            );
        } catch (\Exception $e) {
            error_log('FPSE: Error recording profile assignment: ' . $e->getMessage());
            // Não falhar o cadastro por causa disso
        }

        // Record state assignment (with error handling - não falhar se der erro)
        try {
            $this->eventRecorder->recordStateAssigned(
                $result['user_id'],
                $dto->perfilUsuario,
                $dto->estado
            );
        } catch (\Exception $e) {
            error_log('FPSE: Error recording state assignment: ' . $e->getMessage());
            // Não falhar o cadastro por causa disso
        }

        // Log successful registration (with error handling - não falhar se der erro)
        try {
            if (method_exists($this->plugin, 'getLogger') && $this->plugin->getLogger()) {
                $this->plugin->getLogger()->info('User registered successfully', [
                    'user_id' => $result['user_id'],
                    'perfil' => $dto->perfilUsuario,
                    'estado' => $dto->estado,
                ]);
            }
        } catch (\Exception $e) {
            error_log('FPSE: Error logging registration: ' . $e->getMessage());
            // Não falhar o cadastro por causa disso
        }

        // Return success response (sempre retornar, mesmo se eventos falharem)
        error_log('FPSE: Retornando resposta de sucesso para user_id: ' . $result['user_id']);
        return new \WP_REST_Response($response, 201);
    }

    /**
     * Handle get nonce request
     *
     * Returns a fresh nonce for the form
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleGetNonce($request) {
        try {
            if (!$this->nonce) {
                $this->nonce = new NonceMiddleware();
            }

            $nonce = $this->nonce->generateNonce();

            return new \WP_REST_Response([
                'success' => true,
                'nonce' => $nonce,
                'nonce_name' => $this->nonce->getNonceName(),
                'nonce_action' => $this->nonce->getNonceAction(),
            ], 200);
        } catch (\Exception $e) {
            // Log error
            if (function_exists('error_log')) {
                error_log('FPSE Core - Error in handleGetNonce: ' . $e->getMessage());
            }

            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro ao gerar token de segurança',
                'error' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Handle get registration request
     *
     * Retrieves registration data for a user
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleGetRegistration($request) {
        $userId = $request->get_param('id');
        $userId = absint($userId);

        // Check if user exists
        $user = get_userdata($userId);
        if (!$user) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => 'Usuário não encontrado'],
                404
            );
        }

        // Get user data
        $data = $this->userService->getUserData($userId);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Check permission for viewing registration
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function checkViewPermission($request) {
        // Must be logged in and either be the user or have capability
        if (!is_user_logged_in()) {
            return false;
        }

        $userId = $request->get_param('id');
        $currentUserId = get_current_user_id();

        // Own data or admin
        if ($currentUserId == $userId || current_user_can('view_fpse_registrations')) {
            return true;
        }

        return false;
    }

    private function maskSensitiveFields(array $payload): array {
        $masked = $payload;
        $sensitiveKeys = ['senha_login', 'senha'];

        foreach ($sensitiveKeys as $key) {
            if (isset($masked[$key]) && $masked[$key]) {
                $masked[$key] = '***';
            }
        }

        return $masked;
    }
}
