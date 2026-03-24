<?php
/**
 * Builds the minimal derived canonical analytical school base for the MF3 panel.
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Domain\Mf3SchoolCanonical;

class Mf3SchoolCanonicalService {
    private const SOURCE_TAG = 'mf3_panel_derived_service';

    /**
     * @param array $rows
     * @return array
     */
    public function buildCanonicalBase(array $rows) {
        $canonicalById = [];
        $canonicalIdsByNominalKey = [];
        $nominalOnlyRows = [];

        foreach ($rows as $row) {
            $inep = $this->sanitizeInep($row['escola_inep'] ?? '');
            $nominalKey = $this->buildNominalKey($row);

            if ($inep !== '') {
                if (!isset($canonicalById['inep:' . $inep])) {
                    $canonicalById['inep:' . $inep] = [];
                }
                $canonicalById['inep:' . $inep][] = $row;
                if ($nominalKey !== null) {
                    $canonicalIdsByNominalKey[$nominalKey][] = 'inep:' . $inep;
                }
                continue;
            }

            if ($nominalKey !== null) {
                if (!isset($nominalOnlyRows[$nominalKey])) {
                    $nominalOnlyRows[$nominalKey] = [];
                }
                $nominalOnlyRows[$nominalKey][] = $row;
            }
        }

        $itemsById = [];
        $indexByInep = [];
        $indexByNominalKey = [];

        foreach ($canonicalById as $canonicalId => $groupRows) {
            $canonical = $this->buildCanonicalFromInepGroup($canonicalId, $groupRows);
            $itemsById[$canonical->getId()] = $canonical->toArray();
            $indexByInep[$canonical->getInepPreferencial()] = $canonical->getId();

            $nominalKey = $this->buildNominalKey([
                'escola_nome' => $itemsById[$canonical->getId()]['nome_canonico'],
                'municipio' => $itemsById[$canonical->getId()]['municipio_canonico'],
                'estado' => $itemsById[$canonical->getId()]['estado_canonico'],
            ]);

            if ($nominalKey !== null) {
                if (!isset($indexByNominalKey[$nominalKey])) {
                    $indexByNominalKey[$nominalKey] = [];
                }
                $indexByNominalKey[$nominalKey][] = $canonical->getId();
            }
        }

        foreach ($nominalOnlyRows as $nominalKey => $groupRows) {
            if (!empty($indexByNominalKey[$nominalKey])) {
                continue;
            }

            $canonical = $this->buildCanonicalFromNominalGroup($nominalKey, $groupRows);
            $itemsById[$canonical->getId()] = $canonical->toArray();
            $indexByNominalKey[$nominalKey] = [$canonical->getId()];
        }

        foreach ($indexByNominalKey as $nominalKey => $canonicalIds) {
            $indexByNominalKey[$nominalKey] = array_values(array_unique($canonicalIds));
        }

        return [
            'items' => array_values($itemsById),
            'items_by_id' => $itemsById,
            'index' => [
                'by_inep' => $indexByInep,
                'by_nominal_key' => $indexByNominalKey,
            ],
            'summary' => [
                'total_entities' => count($itemsById),
                'with_inep' => count($indexByInep),
                'without_inep' => count($itemsById) - count($indexByInep),
                'with_aliases' => count(array_filter($itemsById, function ($item) {
                    return !empty($item['aliases']);
                })),
            ],
        ];
    }

    /**
     * @param string $canonicalId
     * @param array $rows
     * @return Mf3SchoolCanonical
     */
    private function buildCanonicalFromInepGroup($canonicalId, array $rows) {
        $inep = substr($canonicalId, 5);
        $nomeCanonico = $this->pickMostFrequentValue(array_column($rows, 'escola_nome'), 'Escola sem nome informado');
        $estadoCanonico = strtoupper($this->pickMostFrequentValue(array_column($rows, 'estado'), ''));
        $municipioCanonico = $this->pickMostFrequentValue(array_column($rows, 'municipio'), '');
        $aliases = $this->uniqueNonEmptyValues(array_column($rows, 'escola_nome'));

        return new Mf3SchoolCanonical([
            'school_canonical_id' => $canonicalId,
            'school_canonical_key' => 'inep:' . $inep,
            'inep_preferencial' => $inep,
            'nome_canonico' => $nomeCanonico,
            'estado_canonico' => $estadoCanonico,
            'municipio_canonico' => $municipioCanonico,
            'aliases' => array_values(array_diff($aliases, [$nomeCanonico])),
            'match_strategy_default' => 'inep',
            'quality_status' => 'canonical_with_inep',
            'coverage_flags' => [
                'has_inep' => true,
                'has_aliases' => count($aliases) > 1,
            ],
            'source_tag' => self::SOURCE_TAG,
        ]);
    }

    /**
     * @param string $nominalKey
     * @param array $rows
     * @return Mf3SchoolCanonical
     */
    private function buildCanonicalFromNominalGroup($nominalKey, array $rows) {
        $nomeCanonico = $this->pickMostFrequentValue(array_column($rows, 'escola_nome'), 'Escola sem nome informado');
        $estadoCanonico = strtoupper($this->pickMostFrequentValue(array_column($rows, 'estado'), ''));
        $municipioCanonico = $this->pickMostFrequentValue(array_column($rows, 'municipio'), '');
        $aliases = $this->uniqueNonEmptyValues(array_column($rows, 'escola_nome'));

        return new Mf3SchoolCanonical([
            'school_canonical_id' => 'nominal:' . md5($nominalKey),
            'school_canonical_key' => 'name:' . $nominalKey,
            'nome_canonico' => $nomeCanonico,
            'estado_canonico' => $estadoCanonico,
            'municipio_canonico' => $municipioCanonico,
            'aliases' => array_values(array_diff($aliases, [$nomeCanonico])),
            'match_strategy_default' => 'name_municipio_uf',
            'quality_status' => 'derived_without_inep',
            'coverage_flags' => [
                'has_inep' => false,
                'has_aliases' => count($aliases) > 1,
            ],
            'source_tag' => self::SOURCE_TAG,
        ]);
    }

    /**
     * @param array $row
     * @return string|null
     */
    private function buildNominalKey(array $row) {
        $school = $this->normalizeKey($row['escola_nome'] ?? '');
        $municipio = $this->normalizeKey($row['municipio'] ?? '');
        $estado = strtoupper(trim((string) ($row['estado'] ?? '')));

        if ($school === '' || $municipio === '' || $estado === '') {
            return null;
        }

        return $school . '|' . $municipio . '|' . $estado;
    }

    /**
     * @param array $values
     * @param string $fallback
     * @return string
     */
    private function pickMostFrequentValue(array $values, $fallback = '') {
        $counts = [];

        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if (!isset($counts[$value])) {
                $counts[$value] = 0;
            }
            $counts[$value]++;
        }

        if (empty($counts)) {
            return $fallback;
        }

        arsort($counts);
        return (string) array_key_first($counts);
    }

    /**
     * @param array $values
     * @return array
     */
    private function uniqueNonEmptyValues(array $values) {
        $result = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $result[$value] = true;
        }

        return array_keys($result);
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function sanitizeInep($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        return preg_match('/^\d{8}$/', $digits) ? $digits : '';
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function normalizeKey($value) {
        $value = remove_accents(strtolower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
