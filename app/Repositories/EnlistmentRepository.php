<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class EnlistmentRepository
{
    private PDO $pdo;

    private static ?bool $hasAccountColumns = null;

    private static ?bool $hasRecruitmentOpeningIdColumn = null;
    private static ?bool $hasCandidatePortalTables = null;

    private static ?bool $hasPortalAllowColumns = null;

    private static ?bool $hasPortalStatusDisplayColumns = null;

    private static ?bool $hasPortalAttachmentsTable = null;

    private static ?bool $hasPortalMessageActorColumn = null;

    private static ?bool $hasPipelineStageColumn = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasAccountColumns(): bool
    {
        if (self::$hasAccountColumns === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'submitted_via' LIMIT 1");
            self::$hasAccountColumns = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasAccountColumns;
    }

    private function hasPipelineStageColumn(): bool
    {
        if (self::$hasPipelineStageColumn === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'pipeline_stage' LIMIT 1");
            self::$hasPipelineStageColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasPipelineStageColumn;
    }

    private function hasRecruitmentOpeningIdColumn(): bool
    {
        if (self::$hasRecruitmentOpeningIdColumn === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'recruitment_opening_id' LIMIT 1");
            self::$hasRecruitmentOpeningIdColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasRecruitmentOpeningIdColumn;
    }

    private function hasCandidatePortalTables(): bool
    {
        if (self::$hasCandidatePortalTables === null) {
            $a = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_candidate_tokens' LIMIT 1");
            $b = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_candidate_messages' LIMIT 1");
            self::$hasCandidatePortalTables = ($a && (bool) $a->fetchColumn()) && ($b && (bool) $b->fetchColumn());
        }

        return self::$hasCandidatePortalTables;
    }

    private function hasPortalMessageActorColumn(): bool
    {
        if (self::$hasPortalMessageActorColumn === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_candidate_messages' AND COLUMN_NAME = 'actor_user_id' LIMIT 1");
            self::$hasPortalMessageActorColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasPortalMessageActorColumn;
    }

    private function hasPortalAllowColumns(): bool
    {
        if (self::$hasPortalAllowColumns === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'candidate_portal_allow_files' LIMIT 1");
            self::$hasPortalAllowColumns = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasPortalAllowColumns;
    }

    public function hasPortalAttachmentsTable(): bool
    {
        if (self::$hasPortalAttachmentsTable === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_candidate_attachments' LIMIT 1");
            self::$hasPortalAttachmentsTable = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasPortalAttachmentsTable;
    }

    /** Portail candidat : colonnes options + table pièces jointes présentes en base. */
    public function candidatePortalUploadsReady(): bool
    {
        return $this->hasPortalAllowColumns() && $this->hasPortalAttachmentsTable();
    }

    public function hasPortalStatusDisplayColumns(): bool
    {
        if (self::$hasPortalStatusDisplayColumns === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'candidate_portal_status_mode' LIMIT 1");
            self::$hasPortalStatusDisplayColumns = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasPortalStatusDisplayColumns;
    }

    public function updateCandidatePortalOptions(
        int $tenantId,
        int $enlistmentId,
        bool $allowFiles,
        bool $allowAudio,
        string $statusMode = 'steps',
        string $manualText = '',
        string $manualBand = 'amber',
    ): bool {
        if (!$this->hasPortalAllowColumns()) {
            return false;
        }
        $mode = $statusMode === 'manual' ? 'manual' : 'steps';
        $allowedBands = ['amber', 'emerald', 'rose', 'slate', 'sky'];
        $bandIn = strtolower(trim($manualBand));
        $band = in_array($bandIn, $allowedBands, true) ? $bandIn : 'amber';
        $manualDb = null;
        if ($mode === 'manual') {
            $t = trim($manualText);
            $manualDb = $t !== '' ? mb_substr($t, 0, 280) : null;
        }

        if ($this->hasPortalStatusDisplayColumns()) {
            $stmt = $this->pdo->prepare(
                'UPDATE enlistments SET candidate_portal_allow_files = ?, candidate_portal_allow_audio = ?, candidate_portal_status_mode = ?, candidate_portal_status_manual_text = ?, candidate_portal_status_manual_band = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ? LIMIT 1'
            );

            return $stmt->execute([
                $allowFiles ? 1 : 0,
                $allowAudio ? 1 : 0,
                $mode,
                $manualDb,
                $band,
                $tenantId,
                $enlistmentId,
            ]);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE enlistments SET candidate_portal_allow_files = ?, candidate_portal_allow_audio = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ? LIMIT 1'
        );

        return $stmt->execute([$allowFiles ? 1 : 0, $allowAudio ? 1 : 0, $tenantId, $enlistmentId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCandidatePortalAttachments(int $tenantId, int $enlistmentId): array
    {
        if (!$this->hasPortalAttachmentsTable()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, kind, original_name, mime, size_bytes, created_at FROM enlistment_candidate_attachments WHERE tenant_id = ? AND enlistment_id = ? ORDER BY created_at ASC, id ASC LIMIT 80'
        );
        $stmt->execute([$tenantId, $enlistmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCandidatePortalAttachment(int $tenantId, int $enlistmentId, int $attachmentId): ?array
    {
        if (!$this->hasPortalAttachmentsTable() || $attachmentId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM enlistment_candidate_attachments WHERE tenant_id = ? AND enlistment_id = ? AND id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $enlistmentId, $attachmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insertCandidatePortalAttachment(
        int $tenantId,
        int $enlistmentId,
        string $kind,
        string $originalName,
        string $mime,
        int $sizeBytes,
        string $storagePath
    ): int {
        if (!$this->hasPortalAttachmentsTable()) {
            return 0;
        }
        $k = $kind === 'audio' ? 'audio' : 'file';
        $stmt = $this->pdo->prepare(
            'INSERT INTO enlistment_candidate_attachments (tenant_id, enlistment_id, kind, original_name, mime, size_bytes, storage_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $enlistmentId,
            $k,
            mb_substr($originalName, 0, 255),
            mb_substr($mime, 0, 160),
            max(0, $sizeBytes),
            mb_substr($storagePath, 0, 512),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function create(int $tenantId, array $data): int
    {
        $status = $data['status'] ?? 'submitted';
        $baseParams = [
            $tenantId,
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['email'] ?? '',
            $data['callsign'] ?? null,
            $data['country'] ?? null,
            $data['experience'] ?? null,
            $data['specialty'] ?? null,
            $data['platform'] ?? null,
            $data['availability'] ?? null,
            $data['notes'] ?? null,
            $status,
        ];

        if ($this->hasAccountColumns()) {
            $shared = null;
            if (!empty($data['shared_fields']) && is_array($data['shared_fields'])) {
                $shared = json_encode($data['shared_fields'], JSON_UNESCAPED_UNICODE);
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistments (tenant_id, first_name, last_name, email, callsign, country, experience, specialty, platform, availability, notes, status, submitter_user_id, recruitment_preset_id, submitted_via, consent_sharing_at, shared_fields, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                ...$baseParams,
                isset($data['submitter_user_id']) && $data['submitter_user_id'] !== '' ? (int) $data['submitter_user_id'] : null,
                isset($data['recruitment_preset_id']) && $data['recruitment_preset_id'] !== '' ? (int) $data['recruitment_preset_id'] : null,
                $data['submitted_via'] ?? 'guest',
                $data['consent_sharing_at'] ?? null,
                $shared,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistments (tenant_id, first_name, last_name, email, callsign, country, experience, specialty, platform, availability, notes, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute($baseParams);
        }
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            $this->updateOlympusColumns($id, $data);
            if (!empty($data['recruitment_rp_snapshot']) && is_array($data['recruitment_rp_snapshot'])) {
                $this->updateRecruitmentRpJsonColumn($id, $data['recruitment_rp_snapshot']);
            }
            if (!empty($data['recruitment_opening_id'])) {
                $this->updateRecruitmentOpeningIdColumn($id, (int) $data['recruitment_opening_id']);
            }
            if (($data['form_channel'] ?? '') === 'discord') {
                $this->updateDiscordColumns($id, $data);
            }
            if (!empty($data['custom_answers']) && is_array($data['custom_answers'])) {
                $this->updateCustomAnswersColumn($id, $data['custom_answers']);
            }
            if (!empty($data['auto_rejected'])) {
                $this->markAutoRejected($id, isset($data['reviewer_comment']) ? (string) $data['reviewer_comment'] : null);
            }
        }
        return $id;
    }

    /**
     * @param list<array{question_id: string, label: string, widget: string, answer: string}>|array<string, mixed> $answers
     */
    private function updateCustomAnswersColumn(int $enlistmentId, array $answers): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE enlistments SET custom_answers_json = ? WHERE id = ?');
            $stmt->execute([json_encode(array_values($answers), JSON_UNESCAPED_UNICODE), $enlistmentId]);
        } catch (\Throwable) {
            // Colonne absente si migration non exécutée
        }
    }

    private function markAutoRejected(int $enlistmentId, ?string $reviewerComment): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE enlistments SET auto_rejected = 1, status = \'rejected\', reviewed_at = NOW(), reviewer_comment = COALESCE(?, reviewer_comment), updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$reviewerComment !== null && $reviewerComment !== '' ? $reviewerComment : null, $enlistmentId]);
        } catch (\Throwable) {
            try {
                $stmt = $this->pdo->prepare(
                    'UPDATE enlistments SET status = \'rejected\', reviewed_at = NOW(), reviewer_comment = COALESCE(?, reviewer_comment), updated_at = NOW() WHERE id = ?'
                );
                $stmt->execute([$reviewerComment !== null && $reviewerComment !== '' ? $reviewerComment : null, $enlistmentId]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Première candidature encore « en cours » (statut submitted) pour ce compte sur le tenant.
     *
     * @return array<string, mixed>|null
     */
    public function findOngoingSubmittedForAccount(int $tenantId, int $userId, string $userEmail): ?array
    {
        $rows = $this->listPendingSubmittedForSubmitter($tenantId, $userId, $userEmail);

        return $rows[0] ?? null;
    }

    /**
     * Candidature déposée via le formulaire de recrutement Discord : pseudo + réponses
     * aux questions custom du tenant. Sans effet si la migration n'est pas exécutée.
     *
     * @param array<string, mixed> $data
     */
    private function updateDiscordColumns(int $enlistmentId, array $data): void
    {
        try {
            $answers = !empty($data['discord_answers']) && is_array($data['discord_answers'])
                ? json_encode(array_values($data['discord_answers']), JSON_UNESCAPED_UNICODE)
                : null;
            $stmt = $this->pdo->prepare(
                'UPDATE enlistments SET form_channel = ?, discord_pseudo = ?, discord_answers_json = ? WHERE id = ?'
            );
            $stmt->execute([
                'discord',
                trim((string) ($data['discord_pseudo'] ?? '')) ?: null,
                $answers,
                $enlistmentId,
            ]);
        } catch (\Throwable) {
            // Colonnes discord_* absentes (migration non exécutée) — on ignore
        }
    }

    private function updateRecruitmentOpeningIdColumn(int $enlistmentId, int $openingId): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE enlistments SET recruitment_opening_id = ? WHERE id = ?');
            $stmt->execute([$openingId, $enlistmentId]);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function updateRecruitmentRpJsonColumn(int $enlistmentId, array $snapshot): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE enlistments SET recruitment_rp_json = ? WHERE id = ?');
            $stmt->execute([json_encode($snapshot, JSON_UNESCAPED_UNICODE), $enlistmentId]);
        } catch (\Throwable) {
            // Colonne absente si migration non exécutée
        }
    }

    public function findForTenant(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM enlistments WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['recruitment_rp_json'])) {
            if (is_string($row['recruitment_rp_json'])) {
                $d = json_decode($row['recruitment_rp_json'], true);
                $row['recruitment_rp_json'] = is_array($d) ? $d : null;
            } elseif (!is_array($row['recruitment_rp_json'])) {
                $row['recruitment_rp_json'] = null;
            }
        } else {
            $row['recruitment_rp_json'] = null;
        }

        return $row;
    }

    /** Met à jour les colonnes Olympus (ajoutées par ALTER) si elles existent. */
    private function updateOlympusColumns(int $enlistmentId, array $data): void
    {
        $columns = [
            'age' => isset($data['age']) && $data['age'] !== '' ? (int) $data['age'] : null,
            'timezone' => $data['timezone'] ?? null,
            'weekly_availability' => $data['weekly_availability'] ?? null,
            'system_config' => $data['system_config'] ?? null,
            'microphone_quality' => $data['microphone_quality'] ?? null,
            'past_milsim_experience' => $data['past_milsim_experience'] ?? null,
            'ace_acre_level' => $data['ace_acre_level'] ?? null,
            'motivation_why_join' => $data['motivation_why_join'] ?? null,
            'motivation_accountability' => $data['motivation_accountability'] ?? null,
            'commitment_effort' => $data['commitment_effort'] ?? null,
            'availability_wed_sat' => $data['availability_wed_sat'] ?? null,
            'no_ai_confirmed' => !empty($data['no_ai_confirmed']) ? 1 : 0,
        ];
        try {
            $sets = [];
            $params = [];
            foreach ($columns as $col => $val) {
                $sets[] = "`{$col}` = ?";
                $params[] = $val;
            }
            $params[] = $enlistmentId;
            $stmt = $this->pdo->prepare('UPDATE enlistments SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $stmt->execute($params);
        } catch (\Throwable) {
            // Colonnes Olympus absentes (ALTER non exécuté) — on ignore
        }
    }

    public function allForTenant(int $tenantId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM enlistments WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Dernière candidature liée au compte (prénom/nom souvent plus complets que le seul `users.display_name`). */
    public function findLatestBySubmitter(int $tenantId, int $userId): ?array
    {
        if (!$this->hasAccountColumns()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM enlistments WHERE tenant_id = ? AND submitter_user_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Candidatures « en attente » (statut submitted) pour l’utilisateur courant : compte lié ou même e-mail (invité).
     *
     * @return list<array<string, mixed>>
     */
    public function listPendingSubmittedForSubmitter(int $tenantId, int $userId, string $userEmail): array
    {
        $emailNorm = strtolower(trim($userEmail));
        if ($this->hasAccountColumns()) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM enlistments WHERE tenant_id = ? AND status = 'submitted'
                 AND (submitter_user_id = ? OR LOWER(TRIM(email)) = ?)
                 ORDER BY created_at DESC LIMIT 20"
            );
            $stmt->execute([$tenantId, $userId, $emailNorm !== '' ? $emailNorm : '__none__']);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($emailNorm === '') {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM enlistments WHERE tenant_id = ? AND status = 'submitted' AND LOWER(TRIM(email)) = ?
             ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->execute([$tenantId, $emailNorm]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * File d’attente recrutement du tenant (statut submitted).
     *
     * @return list<array<string, mixed>>
     */
    public function listPendingSubmittedForTenant(int $tenantId, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM enlistments WHERE tenant_id = ? AND status = 'submitted' ORDER BY created_at ASC LIMIT {$limit}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Comptages par statut (clé = status, valeur = effectif).
     *
     * @return array<string, int>
     */
    public function countsByStatusForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) AS c FROM enlistments WHERE tenant_id = ? GROUP BY status');
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) ($row['status'] ?? '')] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }

    /**
     * Dernières candidatures (tous statuts), pour tableau de bord org.
     * Colonnes larges (dossier, canal, affectation, portail) pour alimenter un tableau dense.
     *
     * @return list<array<string, mixed>>
     */
    public function recentForTenantDashboard(int $tenantId, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $cols = [
            'id', 'first_name', 'last_name', 'email', 'callsign', 'country', 'experience',
            'specialty', 'platform', 'availability', 'status', 'reviewed_by', 'reviewed_at',
            'reviewer_comment', 'submitter_user_id', 'created_at', 'updated_at',
        ];
        if ($this->hasAccountColumns()) {
            $cols[] = 'submitted_via';
            $cols[] = 'consent_sharing_at';
        }
        if ($this->hasRecruitmentOpeningIdColumn()) {
            $cols[] = 'recruitment_opening_id';
        }
        if ($this->hasPortalStatusDisplayColumns()) {
            $cols[] = 'candidate_portal_status_mode';
            $cols[] = 'candidate_portal_status_manual_text';
            $cols[] = 'candidate_portal_status_manual_band';
        }
        $select = implode(', ', $cols);
        $stmt = $this->pdo->prepare(
            "SELECT {$select}
             FROM enlistments WHERE tenant_id = ?
             ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Enregistre une décision sur une candidature encore « soumise » (statut submitted).
     *
     * @return bool true si une ligne a été mise à jour
     */
    public function applyDecision(int $tenantId, int $id, string $newStatus, int $reviewerUserId, ?string $reviewerComment): bool
    {
        $allowed = ['reviewed', 'rejected', 'blocked'];
        if (!in_array($newStatus, $allowed, true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE enlistments SET status = ?, reviewed_by = ?, reviewed_at = NOW(), reviewer_comment = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND status = \'submitted\''
        );
        $stmt->execute([$newStatus, $reviewerUserId, $reviewerComment, $tenantId, $id]);

        return $stmt->rowCount() > 0;
    }

    /** @var list<string> */
    private const PIPELINE_STAGES = ['submitted', 'interview_scheduled', 'on_hold', 'accepted', 'rejected', 'blocked', 'cancelled'];

    /**
     * Étape de pipeline explicite (visibilité recrutement), en complément de `status`.
     * Sans effet si la colonne n'existe pas encore (déploiement pas encore migré).
     */
    public function updatePipelineStage(int $tenantId, int $id, string $stage): bool
    {
        if (!in_array($stage, self::PIPELINE_STAGES, true) || !$this->hasPipelineStageColumn()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE enlistments SET pipeline_stage = ? WHERE tenant_id = ? AND id = ?'
        );
        $stmt->execute([$stage, $tenantId, $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Étape de pipeline effective pour affichage (retombe sur le statut si jamais fixée explicitement).
     *
     * @param array<string, mixed> $row
     */
    public static function effectivePipelineStage(array $row): string
    {
        $stage = trim((string) ($row['pipeline_stage'] ?? ''));
        if ($stage !== '' && in_array($stage, self::PIPELINE_STAGES, true)) {
            return $stage;
        }

        return match ((string) ($row['status'] ?? 'submitted')) {
            'reviewed' => 'accepted',
            'rejected' => 'rejected',
            'blocked' => 'blocked',
            'cancelled' => 'cancelled',
            default => 'submitted',
        };
    }

    /**
     * Enregistre (ou met à jour) le rendez-vous Discord, ses notes et la grille d'évaluation
     * staff (jamais exposée au candidat). Sans effet si la migration n'est pas exécutée.
     *
     * @param array<int, array{criterion: string, score: ?int, comment: string}>|null $evaluation
     */
    public function saveDiscordInterview(
        int $tenantId,
        int $id,
        ?string $interviewAt,
        ?string $notes,
        ?array $evaluation,
    ): bool {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE enlistments SET discord_interview_at = ?, discord_interview_notes = ?, discord_evaluation_json = ?, updated_at = NOW()
                 WHERE tenant_id = ? AND id = ?'
            );
            $stmt->execute([
                $interviewAt !== '' ? $interviewAt : null,
                $notes !== '' ? $notes : null,
                $evaluation !== null ? json_encode($evaluation, JSON_UNESCAPED_UNICODE) : null,
                $tenantId,
                $id,
            ]);

            return $stmt->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Marque la fiche comme transmise au candidat (portail de suivi). Le lien du portail
     * est généré séparément via ensureCandidatePortalToken().
     */
    public function markDiscordTransmitted(int $tenantId, int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE enlistments SET discord_transmitted_at = NOW() WHERE tenant_id = ? AND id = ?');
            $stmt->execute([$tenantId, $id]);

            return $stmt->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Annule et archive une candidature (statut dédié, la ligne est conservée pour l'audit —
     * pas de suppression réelle).
     */
    public function cancelEnlistment(int $tenantId, int $id, int $actorUserId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE enlistments SET status = \'cancelled\', reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND status NOT IN (\'reviewed\', \'cancelled\')'
        );
        $stmt->execute([$actorUserId, $tenantId, $id]);
        if ($stmt->rowCount() > 0) {
            $this->updatePipelineStage($tenantId, $id, 'cancelled');

            return true;
        }

        return false;
    }

    /**
     * Désigne (ou met à jour) le référent qui instruit le dossier, sans changer le statut.
     */
    public function assignReferent(int $tenantId, int $id, int $reviewerUserId): bool
    {
        if ($tenantId < 1 || $id < 1 || $reviewerUserId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE enlistments SET reviewed_by = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        $stmt->execute([$reviewerUserId, $tenantId, $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Ajoute un suivi interne sur un dossier encore en instruction (statut submitted), sans le clôturer.
     */
    public function appendInstructionFollowup(int $tenantId, int $id, int $reviewerUserId, ?string $note): bool
    {
        $note = $note !== null ? trim($note) : null;
        if ($note !== null && $note !== '') {
            $stmt = $this->pdo->prepare(
                "UPDATE enlistments
                 SET reviewer_comment = CASE
                        WHEN reviewer_comment IS NULL OR reviewer_comment = '' THEN ?
                        ELSE CONCAT(reviewer_comment, '\n\n', ?)
                     END,
                     reviewed_by = ?,
                     updated_at = NOW()
                 WHERE tenant_id = ? AND id = ? AND status = 'submitted'"
            );
            $stmt->execute([$note, $note, $reviewerUserId, $tenantId, $id]);
        } else {
            $stmt = $this->pdo->prepare(
                "UPDATE enlistments
                 SET reviewed_by = ?,
                     updated_at = NOW()
                 WHERE tenant_id = ? AND id = ? AND status = 'submitted'"
            );
            $stmt->execute([$reviewerUserId, $tenantId, $id]);
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Rattache une candidature acceptée à un utilisateur du tenant (colonne submitter_user_id).
     */
    public function linkSubmitterUserId(int $tenantId, int $enlistmentId, int $userId): bool
    {
        if (!$this->hasAccountColumns() || $userId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE enlistments SET submitter_user_id = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ? AND status = \'reviewed\''
        );
        $stmt->execute([$userId, $tenantId, $enlistmentId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Candidatures acceptées avec compte rattaché (outil debug / synchro).
     *
     * @return list<array<string, mixed>>
     */
    public function listReviewedWithSubmitterForTenant(int $tenantId): array
    {
        if (!$this->hasAccountColumns()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM enlistments WHERE tenant_id = ? AND status = \'reviewed\'
             AND submitter_user_id IS NOT NULL AND submitter_user_id > 0
             ORDER BY id ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Dossiers « soumis » dont l’âge (heures) dépasse le SLA interne.
     */
    public function countSubmittedExceedingSlaHours(int $tenantId, int $slaHours): int
    {
        $slaHours = max(1, min(720, $slaHours));
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM enlistments
             WHERE tenant_id = ? AND status = 'submitted'
             AND TIMESTAMPDIFF(HOUR, COALESCE(updated_at, created_at), UTC_TIMESTAMP()) > ?"
        );
        $stmt->execute([$tenantId, $slaHours]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Volume de candidatures par semaine calendaire (lundi comme début), sur une fenêtre glissante.
     *
     * @return list<array{week_start: string, c: int}>
     */
    public function countsCreatedByWeekForTenant(int $tenantId, int $weeks = 12): array
    {
        $weeks = max(1, min(52, $weeks));
        $days = $weeks * 7;
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(
                    DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(DATE(created_at)) DAY),
                    '%Y-%m-%d'
                ) AS week_start,
                COUNT(*) AS c
             FROM enlistments
             WHERE tenant_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY week_start
             ORDER BY week_start ASC"
        );
        $stmt->execute([$tenantId, $days]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ws = (string) ($row['week_start'] ?? '');
            if ($ws === '') {
                continue;
            }
            $out[] = ['week_start' => $ws, 'c' => (int) ($row['c'] ?? 0)];
        }

        return $out;
    }

    /**
     * Répartition par canal de dépôt (colonne absente sur anciennes bases : tableau vide).
     *
     * @return array<string, int>
     */
    public function countsBySubmittedViaForTenant(int $tenantId): array
    {
        if (!$this->hasAccountColumns()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(NULLIF(TRIM(submitted_via), \'\'), \'guest\') AS via, COUNT(*) AS c
             FROM enlistments WHERE tenant_id = ? GROUP BY via'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $k = (string) ($row['via'] ?? 'guest');
            $out[$k !== '' ? $k : 'guest'] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }

    /**
     * Offres liées les plus citées (nécessite colonne recruitment_opening_id et table recruitment_openings).
     *
     * @return list<array{opening_id: int, c: int, title: string}>
     */
    public function topLinkedOpeningsByVolume(int $tenantId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        if (!$this->hasRecruitmentOpeningIdColumn()) {
            return [];
        }
        $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_openings' LIMIT 1");
        if (!$st || !(bool) $st->fetchColumn()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT e.recruitment_opening_id AS opening_id, COUNT(*) AS c,
                    COALESCE(MAX(ro.title), MAX(ro.reference_public), CONCAT('Offre #', e.recruitment_opening_id)) AS title
             FROM enlistments e
             INNER JOIN recruitment_openings ro ON ro.id = e.recruitment_opening_id AND ro.tenant_id = e.tenant_id
             WHERE e.tenant_id = ? AND e.recruitment_opening_id IS NOT NULL AND e.recruitment_opening_id > 0
             GROUP BY e.recruitment_opening_id
             ORDER BY c DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'opening_id' => (int) ($row['opening_id'] ?? 0),
                'c' => (int) ($row['c'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
            ];
        }

        return $out;
    }

    public function ensureCandidatePortalToken(int $tenantId, int $enlistmentId, int $ttlHours = 168): ?string
    {
        if (!$this->hasCandidatePortalTables()) {
            return null;
        }
        $ttlHours = max(2, min(24 * 30, $ttlHours));
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            "INSERT INTO enlistment_candidate_tokens (tenant_id, enlistment_id, access_token, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW(), NOW())
             ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), expires_at = VALUES(expires_at), updated_at = NOW()"
        );
        $stmt->execute([$tenantId, $enlistmentId, $token, $ttlHours]);

        return $token;
    }

    public function findValidCandidatePortalTokenForEnlistment(int $tenantId, int $enlistmentId): ?string
    {
        $row = $this->findActiveCandidatePortalAccessRow($tenantId, $enlistmentId);

        return $row !== null ? (string) $row['access_token'] : null;
    }

    /**
     * Jeton de suivi invité encore valide (même URL que celle reçue par le candidat).
     *
     * @return array{access_token: string, expires_at: string}|null
     */
    public function findActiveCandidatePortalAccessRow(int $tenantId, int $enlistmentId): ?array
    {
        if (!$this->hasCandidatePortalTables()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT access_token, expires_at
             FROM enlistment_candidate_tokens
             WHERE tenant_id = ? AND enlistment_id = ? AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tenantId, $enlistmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $tok = trim((string) ($row['access_token'] ?? ''));
        if ($tok === '') {
            return null;
        }

        return ['access_token' => $tok, 'expires_at' => (string) ($row['expires_at'] ?? '')];
    }

    /**
     * Liste compacte des dossiers pour l’outil assistance site (sélection par communauté).
     *
     * @return list<array{id: int, email: string, status: string, first_name: string, last_name: string}>
     */
    public function listPortalAssistSelectSummariesForTenant(int $tenantId, int $limit = 400): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $lim = max(1, min(800, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT id, email, status, first_name, last_name
             FROM enlistments
             WHERE tenant_id = ?
             ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
             LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_values(array_map(static function (array $r): array {
            return [
                'id' => (int) ($r['id'] ?? 0),
                'email' => (string) ($r['email'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
                'first_name' => (string) ($r['first_name'] ?? ''),
                'last_name' => (string) ($r['last_name'] ?? ''),
            ];
        }, $rows));
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listRecentForSubmitterAcrossTenants(int $userId, string $userEmail, int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $emailNorm = strtolower(trim($userEmail));
        if ($this->hasAccountColumns()) {
            $stmt = $this->pdo->prepare(
                "SELECT e.*, t.name AS tenant_name, t.slug AS tenant_slug
                 FROM enlistments e
                 INNER JOIN tenants t ON t.id = e.tenant_id
                 WHERE e.submitter_user_id = ? OR LOWER(TRIM(e.email)) = ?
                 ORDER BY COALESCE(e.updated_at, e.created_at) DESC, e.id DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$userId, $emailNorm !== '' ? $emailNorm : '__none__']);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        if ($emailNorm === '') {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT e.*, t.name AS tenant_name, t.slug AS tenant_slug
             FROM enlistments e
             INNER JOIN tenants t ON t.id = e.tenant_id
             WHERE LOWER(TRIM(e.email)) = ?
             ORDER BY COALESCE(e.updated_at, e.created_at) DESC, e.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$emailNorm]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByCandidatePortalToken(string $token): ?array
    {
        if (!$this->hasCandidatePortalTables()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT e.*, t.expires_at AS candidate_portal_expires_at
             FROM enlistment_candidate_tokens t
             INNER JOIN enlistments e ON e.tenant_id = t.tenant_id AND e.id = t.enlistment_id
             WHERE t.access_token = ? AND t.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([trim($token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function enableDiscordPortalMessaging(int $tenantId, int $enlistmentId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE enlistments SET discord_portal_messaging_enabled = 1, discord_portal_messaging_enabled_at = NOW()
             WHERE tenant_id = ? AND id = ? AND discord_portal_messaging_enabled = 0'
        );
        $stmt->execute([$tenantId, $enlistmentId]);

        return $stmt->rowCount() > 0;
    }

    public function appendCandidatePortalMessage(int $tenantId, int $enlistmentId, string $entryKind, string $body, ?int $actorUserId = null): bool
    {
        if (!$this->hasCandidatePortalTables()) {
            return false;
        }
        $kind = $entryKind === 'staff' ? 'staff' : 'candidate';
        $payload = trim($body);
        if ($payload === '') {
            return false;
        }
        $actor = ($actorUserId !== null && $actorUserId > 0) ? $actorUserId : null;
        if ($this->hasPortalMessageActorColumn()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistment_candidate_messages (tenant_id, enlistment_id, entry_kind, actor_user_id, body, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$tenantId, $enlistmentId, $kind, $actor, mb_substr($payload, 0, 4000)]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistment_candidate_messages (tenant_id, enlistment_id, entry_kind, body, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$tenantId, $enlistmentId, $kind, mb_substr($payload, 0, 4000)]);
        }

        return $stmt->rowCount() > 0;
    }

    public function listCandidatePortalMessages(int $tenantId, int $enlistmentId): array
    {
        if (!$this->hasCandidatePortalTables()) {
            return [];
        }
        if ($this->hasPortalMessageActorColumn()) {
            $stmt = $this->pdo->prepare(
                'SELECT m.*, u.display_name AS actor_display_name, u.callsign AS actor_callsign, u.email AS actor_email
                 FROM enlistment_candidate_messages m
                 LEFT JOIN users u ON u.tenant_id = m.tenant_id AND u.id = m.actor_user_id
                 WHERE m.tenant_id = ? AND m.enlistment_id = ?
                 ORDER BY m.created_at ASC, m.id ASC
                 LIMIT 100'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM enlistment_candidate_messages WHERE tenant_id = ? AND enlistment_id = ? ORDER BY created_at ASC, id ASC LIMIT 100'
            );
        }
        $stmt->execute([$tenantId, $enlistmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
