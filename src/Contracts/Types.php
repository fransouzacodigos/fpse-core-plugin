<?php
/**
 * API Contract Types for Payload Contract Alignment
 * These types define the structure of data exchanged between frontend and backend
 */

namespace FortaleceePSE\Core\Contracts;

/**
 * Field definition for backend validation
 * Requirement 2.3: Field names must match contract
 */
class FieldDefinition {
    public string $name;
    public string $type;
    public bool $required;
    public int $step;
    public ?callable $validation_callback;
    
    public function __construct(
        string $name,
        string $type,
        bool $required,
        int $step,
        ?callable $validation_callback = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->required = $required;
        $this->step = $step;
        $this->validation_callback = $validation_callback;
    }
    
    /**
     * Convert to array for export
     */
    public function to_array(): array {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'step' => $this->step
        ];
    }
}

/**
 * Validation result structure
 */
class ValidationResult {
    public bool $valid;
    public array $errors;
    
    public function __construct(bool $valid, array $errors = []) {
        $this->valid = $valid;
        $this->errors = $errors;
    }
    
    /**
     * Create a successful validation result
     */
    public static function success(): self {
        return new self(true, []);
    }
    
    /**
     * Create a failed validation result
     */
    public static function failure(array $errors): self {
        return new self(false, $errors);
    }
    
    /**
     * Add an error to the result
     */
    public function add_error(string $field, string $message): void {
        $this->valid = false;
        $this->errors[] = [
            'field' => $field,
            'message' => $message
        ];
    }
    
    /**
     * Convert to array for JSON response
     */
    public function to_array(): array {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors
        ];
    }
}

/**
 * REST API Response structure
 * Requirement 5.4, 5.5: HTTP status codes and success/error responses
 */
class RestResponse {
    public bool $success;
    public ?string $message;
    public array $errors;
    public ?int $next_step;
    public int $http_status;
    
    public function __construct(
        bool $success,
        ?string $message = null,
        array $errors = [],
        ?int $next_step = null,
        int $http_status = 200
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->errors = $errors;
        $this->next_step = $next_step;
        $this->http_status = $http_status;
    }
    
    /**
     * Create a success response
     * Requirement 5.5: Success response with next step
     */
    public static function success(string $message, int $next_step): self {
        return new self(
            true,
            $message,
            [],
            $next_step,
            200
        );
    }
    
    /**
     * Create a validation error response
     * Requirement 5.4: Validation errors return HTTP 400
     */
    public static function validation_error(string $message, array $errors): self {
        return new self(
            false,
            $message,
            $errors,
            null,
            400
        );
    }
    
    /**
     * Create a server error response
     * Requirement 5.4: Server errors return HTTP 500
     */
    public static function server_error(string $message): self {
        return new self(
            false,
            $message,
            [],
            null,
            500
        );
    }
    
    /**
     * Convert to array for JSON response
     */
    public function to_array(): array {
        $response = [
            'success' => $this->success,
        ];
        
        if ($this->message !== null) {
            $response['message'] = $this->message;
        }
        
        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }
        
        if ($this->next_step !== null) {
            $response['nextStep'] = $this->next_step;
        }
        
        return $response;
    }
}

/**
 * Payload structure received from frontend
 * Requirement 2.2: Must include etapaAtual
 */
class ReceivedPayload {
    public int $etapa_atual;
    public array $data;
    
    public function __construct(int $etapa_atual, array $data) {
        $this->etapa_atual = $etapa_atual;
        $this->data = $data;
    }
    
    /**
     * Create from request array
     */
    public static function from_request(array $request): ?self {
        if (!isset($request['etapaAtual']) || !isset($request['data'])) {
            return null;
        }
        
        return new self(
            (int) $request['etapaAtual'],
            (array) $request['data']
        );
    }
    
    /**
     * Validate basic structure
     * Requirement 3.3: Validate etapaAtual is between 1-6
     */
    public function validate_structure(): ValidationResult {
        $result = new ValidationResult(true);
        
        if ($this->etapa_atual < 1 || $this->etapa_atual > 6) {
            $result->add_error(
                'etapaAtual',
                sprintf('Etapa deve estar entre 1 e 6. Recebido: %d', $this->etapa_atual)
            );
        }
        
        if (!is_array($this->data)) {
            $result->add_error('data', 'Campo data deve ser um objeto');
        }
        
        return $result;
    }
}

/**
 * Contract export structure for audit
 * Requirement 7.1, 7.2: Export structures for comparison
 */
class ContractExport {
    public string $version;
    public string $timestamp;
    public array $steps;
    
    public function __construct(string $version, array $steps) {
        $this->version = $version;
        $this->timestamp = current_time('mysql');
        $this->steps = $steps;
    }
    
    /**
     * Convert to array for JSON export
     */
    public function to_array(): array {
        $steps_array = [];
        foreach ($this->steps as $step => $fields) {
            $steps_array[$step] = array_map(
                fn($field) => $field->to_array(),
                $fields
            );
        }
        
        return [
            'version' => $this->version,
            'timestamp' => $this->timestamp,
            'steps' => $steps_array
        ];
    }
}
