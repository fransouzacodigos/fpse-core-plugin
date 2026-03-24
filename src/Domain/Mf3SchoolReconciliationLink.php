<?php
/**
 * Reconciled analytical school link contract for an MF3 panel user.
 *
 * @package FortaleceePSE
 * @subpackage Domain
 */

namespace FortaleceePSE\Core\Domain;

class Mf3SchoolReconciliationLink {
    /**
     * @var array
     */
    private $data;

    /**
     * @param array $data
     */
    public function __construct(array $data) {
        $this->data = [
            'user_id' => (int) ($data['user_id'] ?? 0),
            'perfil_usuario' => (string) ($data['perfil_usuario'] ?? ''),
            'estado_historico' => strtoupper(trim((string) ($data['estado_historico'] ?? ''))),
            'municipio_historico' => (string) ($data['municipio_historico'] ?? ''),
            'escola_nome_historico' => (string) ($data['escola_nome_historico'] ?? ''),
            'escola_inep_historico' => isset($data['escola_inep_historico']) && $data['escola_inep_historico'] !== ''
                ? (string) $data['escola_inep_historico']
                : null,
            'rede_escola_historica' => isset($data['rede_escola_historica']) && $data['rede_escola_historica'] !== ''
                ? (string) $data['rede_escola_historica']
                : null,
            'school_canonical_id' => isset($data['school_canonical_id']) && $data['school_canonical_id'] !== ''
                ? (string) $data['school_canonical_id']
                : null,
            'status_reconciliacao' => (string) ($data['status_reconciliacao'] ?? 'sem_vinculo'),
            'nivel_confianca' => (string) ($data['nivel_confianca'] ?? 'nenhuma'),
            'criterio_match' => (string) ($data['criterio_match'] ?? 'insufficient_data'),
            'observacoes' => isset($data['observacoes']) && $data['observacoes'] !== ''
                ? (string) $data['observacoes']
                : null,
            'reconciled_at' => isset($data['reconciled_at']) && $data['reconciled_at'] !== ''
                ? (string) $data['reconciled_at']
                : gmdate('c'),
        ];
    }

    /**
     * @return string
     */
    public function getStatus() {
        return $this->data['status_reconciliacao'];
    }

    /**
     * @return string|null
     */
    public function getSchoolCanonicalId() {
        return $this->data['school_canonical_id'];
    }

    /**
     * @return array
     */
    public function toArray() {
        return $this->data;
    }
}
