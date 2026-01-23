<?php
/**
 * CONTRATO CANÔNICO - Perfis e Campos Obrigatórios/Opcionais por Etapa
 *
 * ⚠️ FONTE ÚNICA DA VERDADE ⚠️
 *
 * Este arquivo define o contrato de dados entre Frontend e Backend.
 * Qualquer alteração aqui DEVE ser espelhada no frontend.
 *
 * Estrutura:
 * - required_by_step: campos obrigatórios por etapa (backend valida apenas etapa atual)
 * - optional: campos opcionais (podem ser enviados mas não são obrigatórios)
 *
 * Etapas do formulário:
 * - 0: Dados Pessoais
 * - 1: Vínculo Institucional
 * - 2: Endereço
 * - 3: Informações Específicas
 * - 4: Criação de Login
 * - 5: Resumo (submit final - valida todos os campos)
 *
 * Nomes de campos são em snake_case (formato backend).
 *
 * @package FortaleceePSE
 * @subpackage Contracts
 */

namespace FortaleceePSE\Core\Contracts;

class PerfilCamposContract {
    /**
     * Contrato canônico de perfis e campos por etapa
     *
     * @return array Contrato estruturado por perfil
     */
    public static function getContract() {
        return [
            // ========================================================================
            // PERFIS EAA (Educação de Adolescentes e Adultos)
            // ========================================================================
            'estudante-eaa' => [
                'required_by_step' => [
                    3 => ['rede_escola', 'escola_nome'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],
            'profissional-saude-eaa' => [
                'required_by_step' => [
                    3 => ['rede_escola', 'escola_nome'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],
            'profissional-educacao-eaa' => [
                'required_by_step' => [
                    3 => ['rede_escola', 'escola_nome', 'funcao_eaa'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],

            // ========================================================================
            // PERFIS IES (Instituição de Ensino Superior)
            // ========================================================================
            'bolsista-ies' => [
                'required_by_step' => [
                    3 => ['instituicao_nome', 'curso_nome'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],
            'voluntario-ies' => [
                'required_by_step' => [
                    3 => ['instituicao_nome', 'curso_nome'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],
            'coordenador-ies' => [
                'required_by_step' => [
                    3 => ['instituicao_nome'], // Etapa 3: Informações Específicas
                ],
                'optional' => ['curso_nome', 'departamento'],
            ],

            // ========================================================================
            // PERFIS NAP (Núcleo de Acessibilidade Pedagógica)
            // ========================================================================
            'jovem-mobilizador-nap' => [
                'required_by_step' => [
                    3 => ['nap_nome'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],
            'apoiador-pedagogico-nap' => [
                'required_by_step' => [
                    3 => ['nap_nome'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],
            'coordenacao-nap' => [
                'required_by_step' => [
                    3 => ['nap_nome'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],

            // ========================================================================
            // PERFIS GTI (Gestão Tecnológica Inclusiva)
            // ========================================================================
            'gti-m' => [
                'required_by_step' => [
                    3 => ['setor_gti', 'sistema_responsavel'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],
            'gti-e' => [
                'required_by_step' => [
                    3 => ['setor_gti', 'sistema_responsavel', 'regiao_responsavel'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],

            // ========================================================================
            // PERFIS GOVERNANCE
            // ========================================================================
            'coordenacao-fortalece-pse' => [
                'required_by_step' => [],
                'optional' => ['regiao_responsavel'],
            ],
            'representante-ms-mec' => [
                'required_by_step' => [
                    3 => ['departamento'], // Etapa 3: Informações Específicas
                ],
                'optional' => [],
            ],
        ];
    }

    /**
     * Verifica se um perfil existe no contrato
     *
     * @param string $perfil Identificador do perfil
     * @return bool True se o perfil existe
     */
    public static function isPerfilValido($perfil) {
        $contract = self::getContract();
        return isset($contract[$perfil]);
    }

    /**
     * Obtém campos obrigatórios para um perfil (todos os campos, sem filtro de etapa)
     *
     * ⚠️ DEPRECATED: Use getCamposObrigatoriosPorEtapa() para validação por etapa
     *
     * @param string $perfil Identificador do perfil
     * @return array Lista de campos obrigatórios (de todas as etapas)
     */
    public static function getCamposObrigatorios($perfil) {
        $contract = self::getContract();
        $requiredByStep = $contract[$perfil]['required_by_step'] ?? [];
        
        // Retornar todos os campos de todas as etapas (compatibilidade)
        $allRequired = [];
        foreach ($requiredByStep as $step => $fields) {
            $allRequired = array_merge($allRequired, $fields);
        }
        
        // Remover duplicatas
        return array_unique($allRequired);
    }

    /**
     * Obtém campos obrigatórios para um perfil em uma etapa específica
     *
     * ⚠️ MÉTODO PRINCIPAL PARA VALIDAÇÃO POR ETAPA ⚠️
     *
     * @param string $perfil Identificador do perfil
     * @param int|null $etapa Número da etapa (0-5). Se null, retorna todos os campos
     * @return array Lista de campos obrigatórios da etapa
     */
    public static function getCamposObrigatoriosPorEtapa($perfil, $etapa = null) {
        $contract = self::getContract();
        $requiredByStep = $contract[$perfil]['required_by_step'] ?? [];
        
        // Se etapa não foi especificada, retornar todos os campos (compatibilidade)
        if ($etapa === null) {
            return self::getCamposObrigatorios($perfil);
        }
        
        // Retornar apenas campos da etapa especificada
        return $requiredByStep[$etapa] ?? [];
    }

    /**
     * Obtém campos opcionais para um perfil
     *
     * @param string $perfil Identificador do perfil
     * @return array Lista de campos opcionais
     */
    public static function getCamposOpcionais($perfil) {
        $contract = self::getContract();
        return $contract[$perfil]['optional'] ?? [];
    }

    /**
     * Valida se os dados estão de acordo com o contrato
     *
     * ⚠️ DEPRECATED: Use validatePorEtapa() para validação por etapa
     *
     * @param string $perfil Identificador do perfil
     * @param array $data Dados a validar
     * @return array Resultado da validação: ['valid' => bool, 'missing' => array, 'message' => string]
     */
    public static function validate($perfil, $data = []) {
        // Verificar se o perfil é válido
        if (!self::isPerfilValido($perfil)) {
            return [
                'valid' => false,
                'missing' => [],
                'message' => "[FPSE CONTRACT] Perfil inválido: {$perfil}",
            ];
        }

        // Obter campos obrigatórios (todos, sem filtro de etapa)
        $requiredFields = self::getCamposObrigatorios($perfil);

        // Se não há campos obrigatórios, está válido
        if (empty($requiredFields)) {
            return [
                'valid' => true,
                'missing' => [],
                'message' => '',
            ];
        }

        // Verificar campos obrigatórios
        $missing = [];

        foreach ($requiredFields as $field) {
            $value = $data[$field] ?? null;

            // Campo está ausente ou vazio (string vazia após trim)
            if ($value === null || $value === '' || 
                (is_string($value) && trim($value) === '')) {
                $missing[] = $field;
            }
        }

        // Se há campos faltando, retornar erro
        if (!empty($missing)) {
            $missingList = implode(', ', $missing);
            return [
                'valid' => false,
                'missing' => $missing,
                'message' => "[FPSE CONTRACT] Perfil {$perfil} inválido. Campos obrigatórios ausentes: {$missingList}",
            ];
        }

        // Validação passou
        return [
            'valid' => true,
            'missing' => [],
            'message' => '',
        ];
    }

    /**
     * Valida se os dados estão de acordo com o contrato para uma etapa específica
     *
     * ⚠️ MÉTODO PRINCIPAL PARA VALIDAÇÃO POR ETAPA ⚠️
     *
     * @param string $perfil Identificador do perfil
     * @param int|null $etapa Número da etapa (0-5). Se null, valida todos os campos
     * @param array $data Dados a validar
     * @return array Resultado da validação: ['valid' => bool, 'missing' => array, 'message' => string]
     */
    public static function validatePorEtapa($perfil, $etapa, $data = []) {
        // Verificar se o perfil é válido
        if (!self::isPerfilValido($perfil)) {
            return [
                'valid' => false,
                'missing' => [],
                'message' => "[FPSE CONTRACT] Perfil inválido: {$perfil}",
            ];
        }

        // Obter campos obrigatórios da etapa
        $requiredFields = self::getCamposObrigatoriosPorEtapa($perfil, $etapa);

        // Se não há campos obrigatórios nesta etapa, está válido
        if (empty($requiredFields)) {
            return [
                'valid' => true,
                'missing' => [],
                'message' => '',
            ];
        }

        // Verificar campos obrigatórios
        $missing = [];

        foreach ($requiredFields as $field) {
            $value = $data[$field] ?? null;

            // Campo está ausente ou vazio (string vazia após trim)
            if ($value === null || $value === '' || 
                (is_string($value) && trim($value) === '')) {
                $missing[] = $field;
            }
        }

        // Se há campos faltando, retornar erro
        if (!empty($missing)) {
            $missingList = implode(', ', $missing);
            return [
                'valid' => false,
                'missing' => $missing,
                'message' => "[FPSE CONTRACT] Perfil {$perfil}, Etapa {$etapa} inválido. Campos obrigatórios ausentes: {$missingList}",
            ];
        }

        // Validação passou
        return [
            'valid' => true,
            'missing' => [],
            'message' => '',
        ];
    }
}
