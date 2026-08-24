<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AarCustomForm;
use PDO;

final class AarReportRepository
{
    private PDO $pdo;
    private bool $ensured = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->ensured) {
            return;
        }
        $this->ensured = true;
        try {
            (new TheatreMissionCycleRepository($this->pdo))->ensureSchema();
        } catch (\Throwable) {
        }
        $migration = dirname(__DIR__, 2) . '/bootstrap/aar_reports_migration.php';
        if (!is_file($migration)) {
            return;
        }
        try {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($this->pdo);
            }
        } catch (\Throwable) {
        }
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aar_reports' LIMIT 1");
            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }
        $where = ['r.tenant_id = ?'];
        $args = [$tenantId];
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '') {
            $where[] = 'r.status = ?';
            $args[] = $status;
        }
        $sql = 'SELECT r.*, u.display_name AS author_name, uv.display_name AS validator_name, m.title AS mission_title
                FROM aar_reports r
                LEFT JOIN users u ON u.id = r.author_user_id
                LEFT JOIN users uv ON uv.id = r.validated_by_user_id
                LEFT JOIN theatre_mission_cycles m ON m.id = r.mission_cycle_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY COALESCE(r.reported_at, r.created_at) DESC, r.id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $presented = array_map([$this, 'present'], $rows);
        if (!empty($filters['open_actions'])) {
            $presented = array_values(array_filter(
                $presented,
                static fn (array $row): bool => (int) (($row['totals']['open_actions'] ?? 0)) > 0
            ));
        }

        return $presented;
    }

    /**
     * Indexe les comptes rendus par intitulé d’opération (registre des opérations).
     *
     * @return array<string, array{id: int, status: string, status_label: string}>
     */
    public function operationStatusIndexForTenant(int $tenantId): array
    {
        $index = [];
        foreach ($this->listForTenant($tenantId, []) as $row) {
            $keys = array_unique(array_filter([
                self::normalizeOperationKey((string) ($row['operation_label'] ?? '')),
                self::normalizeOperationKey((string) ($row['title'] ?? '')),
                self::normalizeOperationKey((string) ($row['mission_title'] ?? '')),
            ]));
            foreach ($keys as $key) {
                $index[$key] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'status_label' => (string) ($row['status_label'] ?? 'En attente'),
                ];
            }
        }

        return $index;
    }

    public static function normalizeOperationKey(string $label): string
    {
        $label = mb_strtolower(trim($label), 'UTF-8');
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;

        return $label;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForTenant(int $tenantId, int $id): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT r.*, u.display_name AS author_name, uv.display_name AS validator_name, m.title AS mission_title
             FROM aar_reports r
             LEFT JOIN users u ON u.id = r.author_user_id
             LEFT JOIN users uv ON uv.id = r.validated_by_user_id
             LEFT JOIN theatre_mission_cycles m ON m.id = r.mission_cycle_id
             WHERE r.tenant_id = ? AND r.id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->present($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function save(int $tenantId, ?int $id, int $authorUserId, array $payload): array
    {
        $status = $this->allowed((string) ($payload['status'] ?? 'pending'));
        $missionId = (int) ($payload['mission_cycle_id'] ?? 0);
        $title = $this->clip((string) ($payload['title'] ?? ($payload['operation_label'] ?? 'Compte rendu post-op')), 200);
        $operationLabel = $this->nullableString($payload['operation_label'] ?? null, 200);
        $existing = ($id !== null && $id > 0) ? $this->findForTenant($tenantId, $id) : null;
        $reportedAt = $this->dateValue($payload['reported_at'] ?? null)
            ?? ($existing['reported_at'] ?? null)
            ?? gmdate('Y-m-d H:i:s');
        $validatedAt = $status === 'validated'
            ? ($this->dateValue($payload['validated_at'] ?? null) ?? ($existing['validated_at'] ?? null) ?? gmdate('Y-m-d H:i:s'))
            : null;
        $validatorId = $status === 'validated' ? $authorUserId : null;
        $summaryText = $this->nullableText($payload['summary_text'] ?? null, 20000);

        $scores = $this->normalizeScores($payload['scores'] ?? []);
        $metrics = $this->normalizeMetrics($payload['metrics'] ?? []);
        $strengths = $this->normalizeStringList($payload['strengths'] ?? []);
        $weaknesses = $this->normalizeStringList($payload['weaknesses'] ?? []);
        $openActions = $this->normalizeActionList($payload['open_actions'] ?? []);
        $closedActions = $this->normalizeActionList($payload['closed_actions'] ?? []);
        $templateId = (int) ($payload['template_id'] ?? ($existing['template_id'] ?? 0));
        $customAnswersJson = $this->encodeCustomAnswers($payload['custom_answers'] ?? ($existing['custom_answers_raw'] ?? null));

        $missionWindow = $this->missionWindow($tenantId, $missionId);
        $hasCustom = $this->hasCustomColumns();
        if ($id !== null && $id > 0) {
            if ($hasCustom) {
                $sql = 'UPDATE aar_reports
                        SET mission_cycle_id = ?, title = ?, operation_label = ?, status = ?, reported_at = ?, validated_at = ?,
                            validated_by_user_id = ?, mission_started_at = ?, mission_ended_at = ?, summary_text = ?,
                            strengths_json = ?, weaknesses_json = ?, open_actions_json = ?, closed_actions_json = ?,
                            scores_json = ?, metrics_json = ?, template_id = ?, custom_answers_json = ?, updated_at = UTC_TIMESTAMP()
                        WHERE tenant_id = ? AND id = ?';
                $this->pdo->prepare($sql)->execute([
                    $missionId > 0 ? $missionId : null,
                    $title !== '' ? $title : 'Compte rendu post-op',
                    $operationLabel,
                    $status,
                    $reportedAt,
                    $validatedAt,
                    $validatorId,
                    $missionWindow['mission_started_at'],
                    $missionWindow['mission_ended_at'],
                    $summaryText,
                    $this->json($strengths),
                    $this->json($weaknesses),
                    $this->json($openActions),
                    $this->json($closedActions),
                    $this->json($scores),
                    $this->json($metrics),
                    $templateId > 0 ? $templateId : null,
                    $customAnswersJson,
                    $tenantId,
                    $id,
                ]);
            } else {
                $sql = 'UPDATE aar_reports
                        SET mission_cycle_id = ?, title = ?, operation_label = ?, status = ?, reported_at = ?, validated_at = ?,
                            validated_by_user_id = ?, mission_started_at = ?, mission_ended_at = ?, summary_text = ?,
                            strengths_json = ?, weaknesses_json = ?, open_actions_json = ?, closed_actions_json = ?,
                            scores_json = ?, metrics_json = ?, updated_at = UTC_TIMESTAMP()
                        WHERE tenant_id = ? AND id = ?';
                $this->pdo->prepare($sql)->execute([
                    $missionId > 0 ? $missionId : null,
                    $title !== '' ? $title : 'Compte rendu post-op',
                    $operationLabel,
                    $status,
                    $reportedAt,
                    $validatedAt,
                    $validatorId,
                    $missionWindow['mission_started_at'],
                    $missionWindow['mission_ended_at'],
                    $summaryText,
                    $this->json($strengths),
                    $this->json($weaknesses),
                    $this->json($openActions),
                    $this->json($closedActions),
                    $this->json($scores),
                    $this->json($metrics),
                    $tenantId,
                    $id,
                ]);
            }

            return $this->findForTenant($tenantId, $id) ?? [];
        }

        if ($hasCustom) {
            $sql = 'INSERT INTO aar_reports
                    (tenant_id, mission_cycle_id, template_id, author_user_id, validated_by_user_id, title, operation_label, status,
                     reported_at, validated_at, mission_started_at, mission_ended_at, summary_text,
                     strengths_json, weaknesses_json, open_actions_json, closed_actions_json, scores_json, metrics_json,
                     custom_answers_json, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())';
            $this->pdo->prepare($sql)->execute([
                $tenantId,
                $missionId > 0 ? $missionId : null,
                $templateId > 0 ? $templateId : null,
                $authorUserId > 0 ? $authorUserId : null,
                $validatorId,
                $title !== '' ? $title : 'Compte rendu post-op',
                $operationLabel,
                $status,
                $reportedAt,
                $validatedAt,
                $missionWindow['mission_started_at'],
                $missionWindow['mission_ended_at'],
                $summaryText,
                $this->json($strengths),
                $this->json($weaknesses),
                $this->json($openActions),
                $this->json($closedActions),
                $this->json($scores),
                $this->json($metrics),
                $customAnswersJson,
            ]);
        } else {
            $sql = 'INSERT INTO aar_reports
                    (tenant_id, mission_cycle_id, author_user_id, validated_by_user_id, title, operation_label, status,
                     reported_at, validated_at, mission_started_at, mission_ended_at, summary_text,
                     strengths_json, weaknesses_json, open_actions_json, closed_actions_json, scores_json, metrics_json,
                     created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())';
            $this->pdo->prepare($sql)->execute([
                $tenantId,
                $missionId > 0 ? $missionId : null,
                $authorUserId > 0 ? $authorUserId : null,
                $validatorId,
                $title !== '' ? $title : 'Compte rendu post-op',
                $operationLabel,
                $status,
                $reportedAt,
                $validatedAt,
                $missionWindow['mission_started_at'],
                $missionWindow['mission_ended_at'],
                $summaryText,
                $this->json($strengths),
                $this->json($weaknesses),
                $this->json($openActions),
                $this->json($closedActions),
                $this->json($scores),
                $this->json($metrics),
            ]);
        }

        return $this->findForTenant($tenantId, (int) $this->pdo->lastInsertId()) ?? [];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $strengths = $this->decodeList($row['strengths_json'] ?? null);
        $weaknesses = $this->decodeList($row['weaknesses_json'] ?? null);
        $openActions = $this->decodeActions($row['open_actions_json'] ?? null);
        $closedActions = $this->decodeActions($row['closed_actions_json'] ?? null);
        $scores = $this->decodeArray($row['scores_json'] ?? null);
        $metrics = $this->decodeArray($row['metrics_json'] ?? null);
        $customBundle = AarCustomForm::unwrap($row['custom_answers_json'] ?? null);
        $pageCount = max(0, (int) ($metrics['page_count'] ?? 0));
        if ($pageCount < 1) {
            $pageCount = max(
                1,
                1
                + (trim((string) ($row['summary_text'] ?? '')) !== '' ? 1 : 0)
                + count($strengths)
                + count($weaknesses)
                + (trim((string) ($metrics['lessons_learned'] ?? '')) !== '' ? 1 : 0)
                + (trim((string) ($metrics['conclusion_text'] ?? '')) !== '' ? 1 : 0)
            );
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'mission_cycle_id' => isset($row['mission_cycle_id']) ? (int) $row['mission_cycle_id'] : null,
            'mission_plan_id' => isset($row['mission_plan_id']) ? (int) $row['mission_plan_id'] : null,
            'title' => (string) ($row['title'] ?? ''),
            'operation_label' => (string) ($row['operation_label'] ?? $row['mission_title'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'status_label' => $this->statusLabel((string) ($row['status'] ?? 'pending')),
            'reported_at' => $row['reported_at'] ?? null,
            'validated_at' => $row['validated_at'] ?? null,
            'mission_started_at' => $row['mission_started_at'] ?? null,
            'mission_ended_at' => $row['mission_ended_at'] ?? null,
            'summary_text' => $row['summary_text'] ?? null,
            'author_name' => (string) ($row['author_name'] ?? ''),
            'validator_name' => (string) ($row['validator_name'] ?? ''),
            'mission_title' => (string) ($row['mission_title'] ?? ''),
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'open_actions' => $openActions,
            'closed_actions' => $closedActions,
            'scores' => $scores,
            'metrics' => $metrics,
            'template_id' => isset($row['template_id']) ? (int) $row['template_id'] : 0,
            'custom_answers_raw' => $row['custom_answers_json'] ?? null,
            'custom_bundle' => $customBundle,
            'custom_fields' => $customBundle['fields'],
            'custom_answers' => $customBundle['answers'],
            'custom_rows' => AarCustomForm::presentAnswers($customBundle['fields'], $customBundle['answers']),
            'is_custom' => $customBundle['fields'] !== [],
            'summary_heading' => trim((string) ($metrics['summary_heading'] ?? '')),
            'lessons_learned' => trim((string) ($metrics['lessons_learned'] ?? '')),
            'conclusion_text' => trim((string) ($metrics['conclusion_text'] ?? '')),
            'lessons_context' => trim((string) ($metrics['lessons_context'] ?? '')),
            'page_count' => $pageCount,
            'reference_code' => $this->referenceCode((int) ($row['id'] ?? 0)),
            'totals' => [
                'open_actions' => count($openActions),
                'closed_actions' => count($closedActions),
                'points_releves' => count($strengths) + count($weaknesses),
                'rapports_traites' => ((string) ($row['status'] ?? 'pending')) === 'validated' ? 1 : 0,
            ],
        ];
    }

    private function missionWindow(int $tenantId, int $missionId): array
    {
        if ($missionId < 1) {
            return ['mission_started_at' => null, 'mission_ended_at' => null];
        }
        $repo = new TheatreMissionCycleRepository($this->pdo);
        $mission = $repo->findForTenant($tenantId, $missionId);
        if (!is_array($mission)) {
            return ['mission_started_at' => null, 'mission_ended_at' => null];
        }

        return [
            'mission_started_at' => $mission['started_at'] ?? null,
            'mission_ended_at' => $mission['ended_at'] ?? null,
        ];
    }

    private function allowed(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['pending', 'validated', 'missing', 'in_review'], true) ? $status : 'pending';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'validated' => 'Validé',
            'missing' => 'Manquant',
            'in_review' => 'En relecture',
            default => 'En attente',
        };
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $this->clip($value, $max);
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max);
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = $this->clip($item, 500);
            }
        }

        return array_values(array_slice($out, 0, 30));
    }

    /**
     * @return list<array{label:string,status:string,owner:?string,due_at:?string}>
     */
    private function normalizeActionList(mixed $value): array
    {
        if (is_string($value)) {
            $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
            $value = array_map(static fn ($line) => ['label' => $line], $lines);
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                $item = ['label' => (string) $item];
            }
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $status = strtolower(trim((string) ($item['status'] ?? 'open')));
            $out[] = [
                'label' => $this->clip($label, 500),
                'status' => in_array($status, ['open', 'closed'], true) ? $status : 'open',
                'owner' => $this->nullableString($item['owner'] ?? null, 160),
                'due_at' => $this->dateValue($item['due_at'] ?? null),
            ];
        }

        return array_values(array_slice($out, 0, 30));
    }

    /**
     * @return array<string, int|float>
     */
    private function normalizeScores(mixed $value): array
    {
        $data = is_array($value) ? $value : [];
        $out = [];
        foreach (['commandement', 'coordination', 'discipline_feu', 'transmissions'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $out[$key] = max(0, min(10, (float) $data[$key]));
        }

        return $out;
    }

    /**
     * @return array<string, int|float|string>
     */
    private function normalizeMetrics(mixed $value): array
    {
        $data = is_array($value) ? $value : [];
        $out = [];
        foreach (['average_delay_minutes', 'points_releves', 'actions_open', 'actions_closed', 'reports_processed'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $out[$key] = max(0, (float) $data[$key]);
        }
        foreach (['summary_heading', 'lessons_learned', 'lessons_context', 'conclusion_text'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $text = trim((string) $data[$key]);
            $max = $key === 'lessons_learned' || $key === 'conclusion_text' ? 5000 : 200;
            $out[$key] = $text === '' ? '' : $this->clip($text, $max);
        }
        if (array_key_exists('page_count', $data)) {
            $out['page_count'] = max(0, (int) $data['page_count']);
        }

        return $out;
    }

    private function json(mixed $value): ?string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    /**
     * @return list<string>
     */
    private function decodeList(mixed $json): array
    {
        $data = $this->decodeArray($json);
        $out = [];
        foreach ($data as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeActions(mixed $json): array
    {
        $data = $this->decodeArray($json);
        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * @return array<mixed>
     */
    private function decodeArray(mixed $json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return gmdate('Y-m-d H:i:s', $ts);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByMissionPlanId(int $tenantId, int $planId): ?array
    {
        if (!$this->tablesReady() || !$this->hasMissionPlanColumn() || $tenantId < 1 || $planId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT r.*, u.display_name AS author_name, uv.display_name AS validator_name, m.title AS mission_title
             FROM aar_reports r
             LEFT JOIN users u ON u.id = r.author_user_id
             LEFT JOIN users uv ON uv.id = r.validated_by_user_id
             LEFT JOIN theatre_mission_cycles m ON m.id = r.mission_cycle_id
             WHERE r.tenant_id = ? AND r.mission_plan_id = ?
             ORDER BY r.id DESC LIMIT 1'
        );
        $st->execute([$tenantId, $planId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->present($row) : null;
    }

    /**
     * Ouvre un brouillon de compte rendu pour un plan clôturé (sans le publier).
     *
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>|null
     */
    public function ensureDraftForPlan(
        int $tenantId,
        int $planId,
        int $authorUserId,
        string $title,
        string $operationLabel,
        string $summary,
        array $metrics,
    ): ?array {
        if (!$this->tablesReady() || !$this->hasMissionPlanColumn() || $tenantId < 1 || $planId < 1) {
            return null;
        }
        $existing = $this->findByMissionPlanId($tenantId, $planId);
        if ($existing !== null) {
            return $existing;
        }
        $sql = 'INSERT INTO aar_reports
                (tenant_id, mission_plan_id, author_user_id, title, operation_label, status,
                 reported_at, summary_text, metrics_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, \'pending\', UTC_TIMESTAMP(), ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())';
        $this->pdo->prepare($sql)->execute([
            $tenantId,
            $planId,
            $authorUserId > 0 ? $authorUserId : null,
            $this->clip($title !== '' ? $title : 'Compte rendu de mission', 200),
            $this->nullableString($operationLabel !== '' ? $operationLabel : null, 200),
            $this->nullableText($summary, 20000),
            $this->json($metrics),
        ]);

        return $this->findForTenant($tenantId, (int) $this->pdo->lastInsertId());
    }

    private function hasMissionPlanColumn(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aar_reports' AND COLUMN_NAME = 'mission_plan_id' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasCustomColumns(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aar_reports' AND COLUMN_NAME = 'custom_answers_json' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function encodeCustomAnswers(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (!is_array($value) || $value === []) {
            return null;
        }
        $bundle = isset($value['fields']) || isset($value['answers'])
            ? AarCustomForm::unwrap($value)
            : ['fields' => [], 'answers' => $value];
        if ($bundle['fields'] === [] && $bundle['answers'] === []) {
            return null;
        }

        return $this->json($bundle);
    }

    private function referenceCode(int $id): string
    {
        if ($id < 1) {
            return 'AAR-0000';
        }

        return 'AAR-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }
}
