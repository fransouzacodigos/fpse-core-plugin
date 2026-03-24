<?php
/**
 * Reconciles scoped MF3 users against the derived canonical school base.
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Domain\Mf3SchoolReconciliationLink;

class Mf3SchoolReconciliationService {
    private const STATUS_CONFIRMED_INEP = 'confirmado_por_inep';
    private const STATUS_CONFIRMED_NOMINAL = 'confirmado_por_nome_municipio_uf';
    private const STATUS_PENDING = 'pendente_reconciliacao';
    private const STATUS_CONFLICT = 'conflito';
    private const STATUS_NO_LINK = 'sem_vinculo';

    /**
     * @param array $rows
     * @param array $canonicalBase
     * @return array
     */
    public function reconcileRows(array $rows, array $canonicalBase) {
        $observability = [
            'total_rows' => 0,
            'confirmado_por_inep' => 0,
            'confirmado_por_nome_municipio_uf' => 0,
            'pendente_reconciliacao' => 0,
            'conflito' => 0,
            'sem_vinculo' => 0,
        ];

        $links = [];
        $enrichedRows = [];

        foreach ($rows as $row) {
            $link = $this->reconcileRow($row, $canonicalBase);
            $linkArray = $link->toArray();
            $canonical = null;
            $canonicalId = $link->getSchoolCanonicalId();

            if ($canonicalId !== null && isset($canonicalBase['items_by_id'][$canonicalId])) {
                $canonical = $canonicalBase['items_by_id'][$canonicalId];
            }

            $observability['total_rows']++;
            if (isset($observability[$linkArray['status_reconciliacao']])) {
                $observability[$linkArray['status_reconciliacao']]++;
            }

            $links[$linkArray['user_id']] = $linkArray;
            $row['school_reconciliation_link'] = $linkArray;
            $row['school_canonical'] = $canonical;
            $enrichedRows[] = $row;
        }

        return [
            'rows' => $enrichedRows,
            'links_by_user_id' => $links,
            'observability' => $observability,
        ];
    }

    /**
     * @param array $row
     * @param array $canonicalBase
     * @return Mf3SchoolReconciliationLink
     */
    private function reconcileRow(array $row, array $canonicalBase) {
        $historicInep = $this->sanitizeInep($row['escola_inep'] ?? '');
        $nominalKey = $this->buildNominalKey($row);
        $historicName = trim((string) ($row['escola_nome'] ?? ''));
        $canonicalId = null;
        $status = self::STATUS_NO_LINK;
        $confidence = 'nenhuma';
        $criteria = 'insufficient_data';
        $observations = null;

        if ($historicInep !== '' && isset($canonicalBase['index']['by_inep'][$historicInep])) {
            $canonicalId = $canonicalBase['index']['by_inep'][$historicInep];
            $status = self::STATUS_CONFIRMED_INEP;
            $confidence = 'alta';
            $criteria = 'inep';
        } elseif ($nominalKey !== null) {
            $candidateIds = $canonicalBase['index']['by_nominal_key'][$nominalKey] ?? [];
            $candidateCount = count($candidateIds);

            if ($candidateCount === 1) {
                $canonicalId = $candidateIds[0];
                $status = self::STATUS_CONFIRMED_NOMINAL;
                $confidence = 'media';
                $criteria = 'nome_municipio_uf';
            } elseif ($candidateCount > 1) {
                $status = self::STATUS_CONFLICT;
                $confidence = 'baixa';
                $criteria = 'ambiguous_nominal';
                $observations = sprintf(
                    'Mais de um candidato canônico encontrado para a chave nominal %s (%d candidatos).',
                    $nominalKey,
                    $candidateCount
                );
            } elseif ($historicName !== '') {
                $status = self::STATUS_PENDING;
                $confidence = 'baixa';
                $criteria = 'no_nominal_candidate';
                $observations = 'Sem candidato canônico seguro para o nome histórico no território informado.';
            }
        } elseif ($historicName !== '') {
            $status = self::STATUS_PENDING;
            $confidence = 'baixa';
            $criteria = 'partial_historical_signal';
            $observations = 'Há dado histórico de escola, mas faltam sinais territoriais suficientes para reconciliação automática.';
        }

        return new Mf3SchoolReconciliationLink([
            'user_id' => (int) ($row['user_id'] ?? 0),
            'perfil_usuario' => (string) ($row['perfil_usuario'] ?? ''),
            'estado_historico' => (string) ($row['estado'] ?? ''),
            'municipio_historico' => (string) ($row['municipio'] ?? ''),
            'escola_nome_historico' => $historicName,
            'escola_inep_historico' => $historicInep !== '' ? $historicInep : null,
            'rede_escola_historica' => (string) ($row['rede_escola'] ?? ''),
            'school_canonical_id' => $canonicalId,
            'status_reconciliacao' => $status,
            'nivel_confianca' => $confidence,
            'criterio_match' => $criteria,
            'observacoes' => $observations,
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
