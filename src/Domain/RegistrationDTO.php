<?php
/**
 * Registration Data Transfer Object
 *
 * Type-safe representation of registration data
 *
 * @package FortaleceePSE
 * @subpackage Domain
 */

namespace FortaleceePSE\Core\Domain;

class RegistrationDTO {
    // Personal Information
    public $nomeCompleto;
    public $cpf;
    public $email;
    public $emailLogin;
    public $senhaLogin;
    public $telefone;
    public $dataNascimento;
    public $genero;
    public $racaCor;

    // Institutional Information
    public $perfilUsuario;
    public $vinculoInstitucional;

    // Location
    public $estado;
    public $municipio;
    public $logradouro;
    public $cep;
    public $complemento;
    public $numero;
    public $bairro;

    // Accessibility
    public $acessibilidade;
    public $descricaoAcessibilidade;

    // Profile-specific fields (dynamic)
    public $profileSpecificFields = [];

    /**
     * Create DTO from array (snake_case keys)
     *
     * Maps snake_case API field names to camelCase properties
     *
     * @param array $data Input data with snake_case keys
     * @return static New DTO instance
     */
    public static function fromArray($data) {
        $dto = new static();

        // Mapping of snake_case input to camelCase properties
        $mapping = [
            'nome_completo' => 'nomeCompleto',
            'cpf' => 'cpf',
            'email' => 'email',
            'email_login' => 'emailLogin',
            'senha_login' => 'senhaLogin',
            'telefone' => 'telefone',
            'data_nascimento' => 'dataNascimento',
            'genero' => 'genero',
            'raca_cor' => 'racaCor',
            'perfil_usuario' => 'perfilUsuario',
            'vinculo_institucional' => 'vinculoInstitucional',
            'estado' => 'estado',
            'municipio' => 'municipio',
            'logradouro' => 'logradouro',
            'cep' => 'cep',
            'complemento' => 'complemento',
            'numero' => 'numero',
            'bairro' => 'bairro',
            'acessibilidade' => 'acessibilidade',
            'descricao_acessibilidade' => 'descricaoAcessibilidade',
        ];

        // Map standard fields
        foreach ($mapping as $snakeKey => $camelKey) {
            if (isset($data[$snakeKey])) {
                $dto->$camelKey = $data[$snakeKey];
            }
        }

        // Store any remaining fields as profile-specific
        foreach ($data as $key => $value) {
            if (!isset($mapping[$key]) && $key !== 'fpse_nonce') {
                // Store with snake_case key for profile-specific fields
                $dto->profileSpecificFields[$key] = $value;
            }
        }

        return $dto;
    }

    /**
     * Convert DTO to array (snake_case keys)
     *
     * Returns all non-null properties with snake_case keys
     *
     * @return array Array representation with snake_case keys
     */
    public function toArray() {
        $reverse_mapping = [
            'nomeCompleto' => 'nome_completo',
            'cpf' => 'cpf',
            'email' => 'email',
            'emailLogin' => 'email_login',
            'senhaLogin' => 'senha_login',
            'telefone' => 'telefone',
            'dataNascimento' => 'data_nascimento',
            'genero' => 'genero',
            'racaCor' => 'raca_cor',
            'perfilUsuario' => 'perfil_usuario',
            'vinculoInstitucional' => 'vinculo_institucional',
            'estado' => 'estado',
            'municipio' => 'municipio',
            'logradouro' => 'logradouro',
            'cep' => 'cep',
            'complemento' => 'complemento',
            'numero' => 'numero',
            'bairro' => 'bairro',
            'acessibilidade' => 'acessibilidade',
            'descricaoAcessibilidade' => 'descricao_acessibilidade',
        ];

        $result = [];

        // Map standard fields
        foreach ($reverse_mapping as $camelKey => $snakeKey) {
            if (isset($this->$camelKey) && !is_null($this->$camelKey)) {
                $result[$snakeKey] = $this->$camelKey;
            }
        }

        // Include profile-specific fields
        foreach ($this->profileSpecificFields as $key => $value) {
            if (!is_null($value)) {
                $result[$key] = $value;
            }
        }
        
        // Debug: Log what's being returned
        error_log('FPSE DTO: toArray() - Total de campos: ' . count($result));
        error_log('FPSE DTO: toArray() - Chaves: ' . wp_json_encode(array_keys($result)));
        error_log('FPSE DTO: toArray() - profileSpecificFields count: ' . count($this->profileSpecificFields));
        if (!empty($this->profileSpecificFields)) {
            error_log('FPSE DTO: toArray() - profileSpecificFields: ' . wp_json_encode($this->profileSpecificFields));
        }

        return $result;
    }

    /**
     * Get minimum required fields validation
     *
     * Campos mínimos obrigatórios conforme especificação:
     * - email_login
     * - perfil_usuario
     * - estado
     * - municipio
     * - nome_completo
     *
     * @return array Validation result with keys: valid (bool), missing (array)
     */
    public function getMinimumRequiredFields() {
        $required = [
            'emailLogin' => 'email_login',
            'perfilUsuario' => 'perfil_usuario',
            'estado' => 'estado',
            'municipio' => 'municipio',
            'nomeCompleto' => 'nome_completo',
        ];

        $missing = [];
        foreach ($required as $property => $fieldName) {
            if (empty($this->$property)) {
                $missing[] = $fieldName;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }
}
