<?php
/**
 * Canonical analytical school contract for the MF3 panel.
 *
 * @package FortaleceePSE
 * @subpackage Domain
 */

namespace FortaleceePSE\Core\Domain;

class Mf3SchoolCanonical {
    /**
     * @var array
     */
    private $data;

    /**
     * @param array $data
     */
    public function __construct(array $data) {
        $this->data = [
            'school_canonical_id' => (string) ($data['school_canonical_id'] ?? ''),
            'school_canonical_key' => (string) ($data['school_canonical_key'] ?? ''),
            'inep_preferencial' => isset($data['inep_preferencial']) && $data['inep_preferencial'] !== ''
                ? (string) $data['inep_preferencial']
                : null,
            'nome_canonico' => (string) ($data['nome_canonico'] ?? ''),
            'estado_canonico' => strtoupper(trim((string) ($data['estado_canonico'] ?? ''))),
            'municipio_canonico' => (string) ($data['municipio_canonico'] ?? ''),
            'aliases' => array_values(array_unique(array_filter(array_map('strval', (array) ($data['aliases'] ?? []))))),
            'match_strategy_default' => isset($data['match_strategy_default']) && $data['match_strategy_default'] !== ''
                ? (string) $data['match_strategy_default']
                : null,
            'quality_status' => (string) ($data['quality_status'] ?? 'unknown'),
            'coverage_flags' => (array) ($data['coverage_flags'] ?? []),
            'source_tag' => (string) ($data['source_tag'] ?? 'mf3_panel_derived_service'),
            'updated_at' => isset($data['updated_at']) && $data['updated_at'] !== ''
                ? (string) $data['updated_at']
                : gmdate('c'),
        ];
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->data['school_canonical_id'];
    }

    /**
     * @return string
     */
    public function getKey() {
        return $this->data['school_canonical_key'];
    }

    /**
     * @return string|null
     */
    public function getInepPreferencial() {
        return $this->data['inep_preferencial'];
    }

    /**
     * @return array
     */
    public function toArray() {
        return $this->data;
    }
}
