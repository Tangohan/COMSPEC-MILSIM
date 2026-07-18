<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\ModerationDecisionRepository;
use App\Repositories\UserRepository;
use App\Services\Cron\CronJobInterface;
use PDO;

/**
 * Rejette les contenus en quarantaine dont le délai est dépassé.
 */
final class ModerationQuarantineExpireCronJob implements CronJobInterface
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $userRepository,
        private ModerationDecisionRepository $decisionRepository,
        private string $projectRoot,
    ) {}

    public function key(): string
    {
        return 'moderation_quarantine_expire';
    }

    public function label(): string
    {
        return 'Quarantaine modération';
    }

    public function description(): string
    {
        return 'Ferme automatiquement les contenus en quarantaine dont le délai est dépassé.';
    }

    public function run(): array
    {
        $chk = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_artifacts' LIMIT 1");
        if (!$chk || !$chk->fetchColumn()) {
            return ['ok' => true, 'summary' => 'Aucune à faire (module absent).', 'details' => ['processed' => 0]];
        }

        $before = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $sel = $this->pdo->prepare(
            "SELECT * FROM moderation_artifacts
             WHERE state IN ('quarantined','pending_scan') AND expires_at IS NOT NULL AND expires_at < ?"
        );
        $sel->execute([$before]);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $n = 0;
        foreach ($rows as $row) {
            $rel = (string) ($row['file_path'] ?? '');
            if ($rel !== '') {
                $full = str_starts_with($rel, 'public/')
                    ? $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel)
                    : $this->projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_file($full)) {
                    @unlink($full);
                }
            }
            $artifactId = (int) ($row['id'] ?? 0);
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            $upd = $this->pdo->prepare('UPDATE moderation_artifacts SET state = ? WHERE id = ?');
            $upd->execute(['rejected', $artifactId]);

            $actorId = $tenantId > 0 ? $this->userRepository->ensureSystemModeratorUser($tenantId) : null;
            if ($actorId !== null && $actorId > 0) {
                try {
                    $this->decisionRepository->insert($artifactId, $actorId, 'reject', 'quarantine_expired', 'Expiration délai quarantaine (cron)');
                } catch (\Throwable) {
                }
            }
            $n++;
        }

        return [
            'ok' => true,
            'summary' => "Contenus expirés traités : {$n}",
            'details' => ['processed' => $n],
        ];
    }
}
