<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseAnalyticalCatalog;

/**
 * Persistance des modules analytiques d’un dossier SSE.
 * Les décisions sont append-only : une ancienne appréciation n’est jamais écrasée.
 */
final class SseAnalyticalRepository
{
    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        try {
            $migration = require base_path('bootstrap/atak_sse_analytical_migration.php');
            if (is_callable($migration)) {
                $migration(Database::getPdo());
            }
        } catch (\Throwable) {
            // Schéma appliqué par run-migrations.
        }
    }

    // ── Appréciations ──────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public function listAssessments(int $tenantId, int $caseId): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_case_assessments
                  WHERE tenant_id = :t AND case_id = :c AND status <> \'archived\'
                  ORDER BY FIELD(hypothesis_code, \'H1\',\'H2\',\'H3\'), id DESC',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateAssessment'], $rows);
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public function createAssessment(int $tenantId, int $caseId, array $data): array
    {
        $fact = trim((string) ($data['fact_text'] ?? ''));
        $assessment = trim((string) ($data['assessment_text'] ?? ''));
        $justification = trim((string) ($data['confidence_justification'] ?? ''));
        $confidence = (string) ($data['confidence'] ?? 'modere');
        if ($fact === '' || $assessment === '') {
            return ['ok' => false, 'error' => 'Le fait et l’appréciation sont obligatoires.'];
        }
        if ($justification === '') {
            return ['ok' => false, 'error' => 'La justification du niveau de confiance est obligatoire.'];
        }
        if (!isset(SseAnalyticalCatalog::CONFIDENCE[$confidence])) {
            return ['ok' => false, 'error' => 'Niveau de confiance non reconnu.'];
        }

        $origin = (string) ($data['source_origin'] ?? 'observation');
        if (!isset(SseAnalyticalCatalog::SOURCE_ORIGINS[$origin])) {
            $origin = 'observation';
        }
        $reliability = strtoupper((string) ($data['source_reliability'] ?? 'F'));
        if (!isset(SseAnalyticalCatalog::SOURCE_RELIABILITY[$reliability])) {
            $reliability = 'F';
        }
        $credibility = (int) ($data['info_credibility'] ?? 6);
        if (!isset(SseAnalyticalCatalog::INFO_CREDIBILITY[$credibility])) {
            $credibility = 6;
        }
        $hypothesis = strtoupper((string) ($data['hypothesis_code'] ?? 'H1'));
        if (!isset(SseAnalyticalCatalog::HYPOTHESIS_CODES[$hypothesis])) {
            $hypothesis = 'H1';
        }
        $temporality = (string) ($data['temporality'] ?? 'valable_a_date');
        if (!isset(SseAnalyticalCatalog::TEMPORALITY[$temporality])) {
            $temporality = 'valable_a_date';
        }
        $urgency = (string) ($data['urgency'] ?? '');
        if ($urgency !== '' && !isset(SseAnalyticalCatalog::URGENCY[$urgency])) {
            $urgency = '';
        }
        $divergence = (string) ($data['divergence_code'] ?? '');
        if ($divergence !== '' && !isset(SseAnalyticalCatalog::DIVERGENCES[$divergence])) {
            $divergence = '';
        }

        try {
            $this->db->execute(
                'INSERT INTO sse_case_assessments
                    (tenant_id, case_id, subject_label, fact_text, source_origin, source_reliability,
                     info_credibility, corroboration_text, assessment_text, confidence,
                     confidence_justification, hypothesis_code, hypothesis_text, temporality,
                     temporality_date, urgency, divergence_code, status, version,
                     author_label, reviewer_label, validator_label, created_by)
                 VALUES
                    (:t, :c, :subj, :fact, :orig, :rel, :cred, :corr, :assess, :conf,
                     :just, :hyp, :hypt, :temp, :tdate, :urg, :div, \'active\', 1,
                     :author, :reviewer, :validator, :uid)',
                [
                    't' => $tenantId,
                    'c' => $caseId,
                    'subj' => mb_substr(trim((string) ($data['subject_label'] ?? '')), 0, 200),
                    'fact' => $fact,
                    'orig' => $origin,
                    'rel' => $reliability,
                    'cred' => $credibility,
                    'corr' => trim((string) ($data['corroboration_text'] ?? '')) ?: null,
                    'assess' => $assessment,
                    'conf' => $confidence,
                    'just' => $justification,
                    'hyp' => $hypothesis,
                    'hypt' => trim((string) ($data['hypothesis_text'] ?? '')) ?: null,
                    'temp' => $temporality,
                    'tdate' => $this->nullableDate($data['temporality_date'] ?? null),
                    'urg' => $urgency !== '' ? $urgency : null,
                    'div' => $divergence !== '' ? $divergence : null,
                    'author' => ($data['author_label'] ?? null) ?: null,
                    'reviewer' => ($data['reviewer_label'] ?? null) ?: null,
                    'validator' => ($data['validator_label'] ?? null) ?: null,
                    'uid' => ((int) ($data['created_by'] ?? 0)) ?: null,
                ]
            );
            $id = (int) Database::getPdo()->lastInsertId();

            return ['ok' => true, 'id' => $id];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Enregistrement impossible pour le moment.'];
        }
    }

    /**
     * Révision : archive l’ancienne ligne (conservée) et crée une nouvelle version.
     *
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public function reviseAssessment(int $tenantId, int $caseId, int $assessmentId, array $data): array
    {
        $prev = $this->findAssessment($tenantId, $caseId, $assessmentId);
        if ($prev === null) {
            return ['ok' => false, 'error' => 'Appréciation introuvable.'];
        }

        $merged = array_merge($prev, $data);
        $merged['author_label'] = $data['author_label'] ?? $prev['author_label'] ?? null;
        $result = $this->createAssessment($tenantId, $caseId, $merged);
        if (!$result['ok']) {
            return $result;
        }

        try {
            $this->db->execute(
                'UPDATE sse_case_assessments SET status = \'superseded\', updated_by = :u WHERE id = :id AND tenant_id = :t',
                [
                    'id' => $assessmentId,
                    't' => $tenantId,
                    'u' => ((int) ($data['created_by'] ?? 0)) ?: null,
                ]
            );
            $this->db->execute(
                'UPDATE sse_case_assessments SET version = :v WHERE id = :id AND tenant_id = :t',
                [
                    'v' => ((int) ($prev['version'] ?? 1)) + 1,
                    'id' => (int) $result['id'],
                    't' => $tenantId,
                ]
            );
        } catch (\Throwable) {
            // Version déjà créée ; le statut précédent restera actif en pire cas.
        }

        $this->recordDecision($tenantId, $caseId, [
            'decision_domain' => 'confiance',
            'subject_label' => (string) ($prev['subject_label'] ?: 'Appréciation'),
            'value_before' => SseAnalyticalCatalog::label(SseAnalyticalCatalog::CONFIDENCE, (string) ($prev['confidence'] ?? '')),
            'value_after' => SseAnalyticalCatalog::label(SseAnalyticalCatalog::CONFIDENCE, (string) ($data['confidence'] ?? $prev['confidence'] ?? '')),
            'reason' => trim((string) ($data['confidence_justification'] ?? $data['reason'] ?? 'Révision analytique')),
            'assessment_id' => (int) $result['id'],
            'author_label' => $data['author_label'] ?? null,
            'decided_by' => $data['created_by'] ?? null,
        ]);

        return $result;
    }

    public function findAssessment(int $tenantId, int $caseId, int $id): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_case_assessments WHERE id = :id AND tenant_id = :t AND case_id = :c LIMIT 1',
                ['id' => $id, 't' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row ? $this->hydrateAssessment($row) : null;
    }

    // ── Lacunes / besoins ──────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public function listGaps(int $tenantId, int $caseId): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_case_intel_gaps
                  WHERE tenant_id = :t AND case_id = :c
                  ORDER BY FIELD(priority, \'critique\',\'prioritaire\',\'normale\',\'basse\'), id DESC',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateGap'], $rows);
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public function createGap(int $tenantId, int $caseId, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['ok' => false, 'error' => 'Le titre et le détail sont obligatoires.'];
        }
        $kind = (string) ($data['kind'] ?? 'lacune');
        if (!isset(SseAnalyticalCatalog::GAP_KINDS[$kind])) {
            $kind = 'lacune';
        }
        $priority = (string) ($data['priority'] ?? 'normale');
        if (!isset(SseAnalyticalCatalog::GAP_PRIORITIES[$priority])) {
            $priority = 'normale';
        }
        $status = (string) ($data['status'] ?? 'ouvert');
        if (!isset(SseAnalyticalCatalog::GAP_STATUSES[$status])) {
            $status = 'ouvert';
        }
        $hyp = strtoupper(trim((string) ($data['linked_hypothesis'] ?? '')));
        if ($hyp !== '' && !isset(SseAnalyticalCatalog::HYPOTHESIS_CODES[$hyp])) {
            $hyp = '';
        }

        try {
            $this->db->execute(
                'INSERT INTO sse_case_intel_gaps
                    (tenant_id, case_id, kind, title, body, priority, status, linked_hypothesis,
                     confirmation_criterion, assignee_label, due_at, author_label, created_by)
                 VALUES
                    (:t, :c, :kind, :title, :body, :prio, :st, :hyp, :crit, :assignee, :due, :author, :uid)',
                [
                    't' => $tenantId,
                    'c' => $caseId,
                    'kind' => $kind,
                    'title' => mb_substr($title, 0, 220),
                    'body' => $body,
                    'prio' => $priority,
                    'st' => $status,
                    'hyp' => $hyp !== '' ? $hyp : null,
                    'crit' => trim((string) ($data['confirmation_criterion'] ?? '')) ?: null,
                    'assignee' => trim((string) ($data['assignee_label'] ?? '')) ?: null,
                    'due' => $this->nullableDate($data['due_at'] ?? null),
                    'author' => ($data['author_label'] ?? null) ?: null,
                    'uid' => ((int) ($data['created_by'] ?? 0)) ?: null,
                ]
            );

            return ['ok' => true, 'id' => (int) Database::getPdo()->lastInsertId()];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Enregistrement impossible pour le moment.'];
        }
    }

    public function updateGapStatus(int $tenantId, int $caseId, int $gapId, string $status): bool
    {
        if (!isset(SseAnalyticalCatalog::GAP_STATUSES[$status])) {
            return false;
        }
        try {
            $this->db->execute(
                'UPDATE sse_case_intel_gaps
                    SET status = :st, closed_at = IF(:st2 IN (\'satisfait\',\'abandonne\'), NOW(), NULL)
                  WHERE id = :id AND tenant_id = :t AND case_id = :c',
                ['st' => $status, 'st2' => $status, 'id' => $gapId, 't' => $tenantId, 'c' => $caseId]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Décisions ──────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public function listDecisions(int $tenantId, int $caseId, int $limit = 80): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_case_analytical_decisions
                  WHERE tenant_id = :t AND case_id = :c
                  ORDER BY decided_at DESC, id DESC
                  LIMIT ' . max(1, min(200, $limit)),
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateDecision'], $rows);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function recordDecision(int $tenantId, int $caseId, array $data): bool
    {
        $domain = (string) ($data['decision_domain'] ?? 'autre');
        if (!isset(SseAnalyticalCatalog::DECISION_DOMAINS[$domain])) {
            $domain = 'autre';
        }
        $after = trim((string) ($data['value_after'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($after === '' || $reason === '') {
            return false;
        }

        try {
            $this->db->execute(
                'INSERT INTO sse_case_analytical_decisions
                    (tenant_id, case_id, decision_domain, subject_label, value_before, value_after,
                     reason, assessment_id, author_label, decided_by)
                 VALUES (:t, :c, :dom, :subj, :before, :after, :reason, :aid, :author, :uid)',
                [
                    't' => $tenantId,
                    'c' => $caseId,
                    'dom' => $domain,
                    'subj' => mb_substr(trim((string) ($data['subject_label'] ?? '')), 0, 220),
                    'before' => trim((string) ($data['value_before'] ?? '')) ?: null,
                    'after' => mb_substr($after, 0, 160),
                    'reason' => $reason,
                    'aid' => ((int) ($data['assessment_id'] ?? 0)) ?: null,
                    'author' => ($data['author_label'] ?? null) ?: null,
                    'uid' => ((int) ($data['decided_by'] ?? 0)) ?: null,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Relations entre dossiers ───────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public function listCaseLinks(int $tenantId, int $caseId): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT l.*, c.reference_code AS related_ref, c.title AS related_title, c.status AS related_status
                   FROM sse_case_links l
                   INNER JOIN sse_cases c ON c.id = l.related_case_id AND c.tenant_id = l.tenant_id
                  WHERE l.tenant_id = :t AND l.case_id = :c
                  ORDER BY l.id DESC',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $row['relation_label'] = SseAnalyticalCatalog::label(
                SseAnalyticalCatalog::CASE_RELATION_TYPES,
                (string) ($row['relation_type'] ?? '')
            );
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public function createCaseLink(int $tenantId, int $caseId, array $data): array
    {
        $relatedId = (int) ($data['related_case_id'] ?? 0);
        if ($relatedId < 1 || $relatedId === $caseId) {
            return ['ok' => false, 'error' => 'Choisissez un autre dossier valide.'];
        }
        $type = (string) ($data['relation_type'] ?? 'connexe');
        if (!isset(SseAnalyticalCatalog::CASE_RELATION_TYPES[$type])) {
            $type = 'connexe';
        }

        try {
            $exists = $this->db->fetchOne(
                'SELECT id FROM sse_cases WHERE id = :id AND tenant_id = :t LIMIT 1',
                ['id' => $relatedId, 't' => $tenantId]
            );
            if (!$exists) {
                return ['ok' => false, 'error' => 'Dossier cible introuvable.'];
            }

            $this->db->execute(
                'INSERT INTO sse_case_links
                    (tenant_id, case_id, related_case_id, relation_type, note, former_reference, author_label, created_by)
                 VALUES (:t, :c, :r, :type, :note, :former, :author, :uid)
                 ON DUPLICATE KEY UPDATE note = VALUES(note), former_reference = VALUES(former_reference)',
                [
                    't' => $tenantId,
                    'c' => $caseId,
                    'r' => $relatedId,
                    'type' => $type,
                    'note' => trim((string) ($data['note'] ?? '')) ?: null,
                    'former' => trim((string) ($data['former_reference'] ?? '')) ?: null,
                    'author' => ($data['author_label'] ?? null) ?: null,
                    'uid' => ((int) ($data['created_by'] ?? 0)) ?: null,
                ]
            );

            if (in_array($type, ['doublon_potentiel', 'fusionne_dans', 'dissocie_de'], true)) {
                $this->recordDecision($tenantId, $caseId, [
                    'decision_domain' => $type === 'fusionne_dans' ? 'fusion' : ($type === 'dissocie_de' ? 'dissociation' : 'autre'),
                    'subject_label' => 'Lien dossier #' . $relatedId,
                    'value_before' => null,
                    'value_after' => SseAnalyticalCatalog::label(SseAnalyticalCatalog::CASE_RELATION_TYPES, $type),
                    'reason' => trim((string) ($data['note'] ?? 'Relation enregistrée entre dossiers')),
                    'author_label' => $data['author_label'] ?? null,
                    'decided_by' => $data['created_by'] ?? null,
                ]);
            }

            return ['ok' => true, 'id' => (int) Database::getPdo()->lastInsertId()];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Lien impossible à enregistrer.'];
        }
    }

    public function deleteCaseLink(int $tenantId, int $caseId, int $linkId): bool
    {
        try {
            $this->db->execute(
                'DELETE FROM sse_case_links WHERE id = :id AND tenant_id = :t AND case_id = :c',
                ['id' => $linkId, 't' => $tenantId, 'c' => $caseId]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Synthèse exécutive générée depuis les données structurées.
     *
     * @param array<string,mixed> $case
     * @param list<array<string,mixed>> $people
     * @param list<array<string,mixed>> $sites
     */
    public function buildExecutiveBrief(
        array $case,
        array $people,
        array $sites,
        array $assessments,
        array $gaps,
        array $decisions,
        array $links
    ): string {
        $lines = [];
        $ref = (string) ($case['reference_code'] ?? '');
        $title = (string) ($case['title'] ?? '');
        $lines[] = 'SYNTHÈSE EXÉCUTIVE — ' . $ref . ($title !== '' ? ' — ' . $title : '');
        $lines[] = '';
        $lines[] = 'Objet. ' . (trim((string) ($case['summary'] ?? '')) !== ''
            ? trim((string) $case['summary'])
            : 'Aucune synthèse narrative n’a encore été portée à la chemise.');

        $active = array_values(array_filter(
            $assessments,
            static fn (array $a): bool => ($a['status'] ?? '') === 'active'
        ));
        if ($active !== []) {
            $lines[] = '';
            $lines[] = 'Appréciations en vigueur.';
            foreach (array_slice($active, 0, 5) as $a) {
                $conf = (string) ($a['confidence_label'] ?? $a['confidence'] ?? '');
                $lines[] = sprintf(
                    '— %s [%s] %s',
                    (string) ($a['hypothesis_code'] ?? 'H1'),
                    mb_strtoupper($conf),
                    $this->oneLine((string) ($a['assessment_text'] ?? ''))
                );
            }
        }

        $openGaps = array_values(array_filter(
            $gaps,
            static fn (array $g): bool => in_array(($g['status'] ?? ''), ['ouvert', 'en_cours'], true)
        ));
        if ($openGaps !== []) {
            $lines[] = '';
            $lines[] = 'Lacunes et besoins ouverts.';
            foreach (array_slice($openGaps, 0, 6) as $g) {
                $lines[] = sprintf(
                    '— [%s / %s] %s',
                    (string) ($g['kind_label'] ?? ''),
                    (string) ($g['priority_label'] ?? ''),
                    (string) ($g['title'] ?? '')
                );
            }
        }

        if ($people !== [] || $sites !== []) {
            $lines[] = '';
            $lines[] = 'Périmètre objet.';
            if ($people !== []) {
                $names = [];
                foreach (array_slice($people, 0, 8) as $p) {
                    $names[] = trim((string) ($p['full_name'] ?? $p['display_name'] ?? $p['codename'] ?? 'Personne'));
                }
                $lines[] = 'Personnes : ' . implode(', ', array_filter($names)) . '.';
            }
            if ($sites !== []) {
                $names = [];
                foreach (array_slice($sites, 0, 8) as $s) {
                    $names[] = trim((string) ($s['name'] ?? $s['designation'] ?? 'Site'));
                }
                $lines[] = 'Sites : ' . implode(', ', array_filter($names)) . '.';
            }
        }

        if ($links !== []) {
            $lines[] = '';
            $lines[] = 'Relations avec d’autres dossiers.';
            foreach (array_slice($links, 0, 6) as $l) {
                $lines[] = sprintf(
                    '— %s : %s (%s)',
                    (string) ($l['relation_label'] ?? ''),
                    (string) ($l['related_ref'] ?? ''),
                    (string) ($l['related_title'] ?? '')
                );
            }
        }

        if ($decisions !== []) {
            $last = $decisions[0];
            $lines[] = '';
            $lines[] = sprintf(
                'Dernière décision analytique (%s) : %s → %s — %s.',
                (string) ($last['decided_at_label'] ?? ''),
                (string) ($last['value_before'] ?? '—'),
                (string) ($last['value_after'] ?? ''),
                $this->oneLine((string) ($last['reason'] ?? ''))
            );
        }

        $lines[] = '';
        $lines[] = 'Document généré automatiquement à partir des données structurées du dossier. '
            . 'Il ne remplace pas une note validée.';

        return implode("\n", $lines);
    }

    // ── Hydration ──────────────────────────────────────────────────────────

    /** @param array<string,mixed> $row */
    private function hydrateAssessment(array $row): array
    {
        $row['source_origin_label'] = SseAnalyticalCatalog::label(
            SseAnalyticalCatalog::SOURCE_ORIGINS,
            (string) ($row['source_origin'] ?? '')
        );
        $row['source_reliability_label'] = SseAnalyticalCatalog::label(
            SseAnalyticalCatalog::SOURCE_RELIABILITY,
            (string) ($row['source_reliability'] ?? 'F')
        );
        $cred = (int) ($row['info_credibility'] ?? 6);
        $row['info_credibility_label'] = SseAnalyticalCatalog::label(
            SseAnalyticalCatalog::INFO_CREDIBILITY,
            $cred
        );
        $row['rating_label'] = SseAnalyticalCatalog::ratingLabel(
            (string) ($row['source_reliability'] ?? 'F'),
            $cred
        );
        $row['confidence_label'] = SseAnalyticalCatalog::label(
            SseAnalyticalCatalog::CONFIDENCE,
            (string) ($row['confidence'] ?? '')
        );
        $row['temporality_label'] = SseAnalyticalCatalog::label(
            SseAnalyticalCatalog::TEMPORALITY,
            (string) ($row['temporality'] ?? '')
        );
        $row['urgency_label'] = SseAnalyticalCatalog::label(
            SseAnalyticalCatalog::URGENCY,
            (string) ($row['urgency'] ?? ''),
            ''
        );
        $row['divergence_label'] = SseAnalyticalCatalog::label(
            SseAnalyticalCatalog::DIVERGENCES,
            (string) ($row['divergence_code'] ?? ''),
            ''
        );
        $row['hypothesis_label'] = SseAnalyticalCatalog::label(
            SseAnalyticalCatalog::HYPOTHESIS_CODES,
            (string) ($row['hypothesis_code'] ?? 'H1')
        );
        $row['banner'] = sprintf(
            'APPRÉCIATION ANALYTIQUE — CONFIANCE %s',
            mb_strtoupper((string) ($row['confidence_label'] ?? ''))
        );

        return $row;
    }

    /** @param array<string,mixed> $row */
    private function hydrateGap(array $row): array
    {
        $row['kind_label'] = SseAnalyticalCatalog::label(SseAnalyticalCatalog::GAP_KINDS, (string) ($row['kind'] ?? ''));
        $row['priority_label'] = SseAnalyticalCatalog::label(SseAnalyticalCatalog::GAP_PRIORITIES, (string) ($row['priority'] ?? ''));
        $row['status_label'] = SseAnalyticalCatalog::label(SseAnalyticalCatalog::GAP_STATUSES, (string) ($row['status'] ?? ''));
        $prefix = match ((string) ($row['kind'] ?? '')) {
            'besoin' => 'BESOIN' . ((($row['priority'] ?? '') === 'prioritaire' || ($row['priority'] ?? '') === 'critique') ? ' PRIORITAIRE' : ''),
            'critere' => 'CRITÈRE DE CONFIRMATION',
            default => 'LACUNE IDENTIFIÉE',
        };
        $row['banner'] = $prefix . ' — ' . (string) ($row['title'] ?? '');

        return $row;
    }

    /** @param array<string,mixed> $row */
    private function hydrateDecision(array $row): array
    {
        $row['domain_label'] = SseAnalyticalCatalog::label(
            SseAnalyticalCatalog::DECISION_DOMAINS,
            (string) ($row['decision_domain'] ?? '')
        );
        $ts = strtotime((string) ($row['decided_at'] ?? '')) ?: time();
        $row['decided_at_label'] = date('d/m/Y H:i', $ts);

        return $row;
    }

    private function nullableDate(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }

        return $v;
    }

    private function oneLine(string $text): string
    {
        $flat = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return mb_strlen($flat) > 220 ? mb_substr($flat, 0, 217) . '…' : $flat;
    }
}
