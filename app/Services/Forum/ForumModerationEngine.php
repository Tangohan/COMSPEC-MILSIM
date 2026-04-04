<?php

declare(strict_types=1);

namespace App\Services\Forum;

use App\Core\Database;
use App\Repositories\ForumBannedWordRepository;
use App\Repositories\ForumBlacklistedDomainRepository;
use PDO;

/**
 * Analyse heuristique (mots bannis, domaines, densité de liens) + journalisation.
 */
final class ForumModerationEngine
{
    public function __construct(
        private ForumBannedWordRepository $bannedWordRepository,
        private ForumBlacklistedDomainRepository $blacklistedDomainRepository
    ) {
    }

    /**
     * @return array{action: string, score: float, reasons: list<string>}
     */
    public function analyze(int $tenantId, int $userId, ?int $postId, string $text): array
    {
        $reasons = [];
        $score = 0.0;
        $lower = mb_strtolower($text);
        foreach ($this->bannedWordRepository->listForTenant($tenantId) as $row) {
            $w = mb_strtolower(trim((string) ($row['word'] ?? '')));
            if ($w === '') {
                continue;
            }
            if (mb_strpos($lower, $w) !== false) {
                $reasons[] = 'banned_word:' . $w;
                $score += ($row['severity'] ?? '') === 'block' ? 1.0 : 0.4;
            }
        }
        $linkCount = preg_match_all('#https?://[^\s]+#i', $text) ?: 0;
        if ($linkCount >= 5) {
            $reasons[] = 'many_links';
            $score += 0.3;
        }
        foreach ($this->blacklistedDomainRepository->listForTenant($tenantId) as $dom) {
            $d = mb_strtolower(trim((string) ($dom['domain'] ?? '')));
            if ($d !== '' && mb_strpos($lower, $d) !== false) {
                $reasons[] = 'blacklisted_domain:' . $d;
                $score += 0.8;
            }
        }
        $action = $score >= 1.0 ? 'flag' : ($score >= 0.5 ? 'flag' : 'allow');
        $this->log($tenantId, $userId, $postId, $score, $action, $reasons);

        return ['action' => $action, 'score' => $score, 'reasons' => $reasons];
    }

    /** @param list<string> $reasons */
    private function log(int $tenantId, int $userId, ?int $postId, float $score, string $action, array $reasons): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_moderation_logs' LIMIT 1");
        if (!$stmt || !$stmt->fetchColumn()) {
            return;
        }
        if ($action === 'allow' && $score < 0.3) {
            return;
        }
        $detail = json_encode(['reasons' => $reasons], JSON_UNESCAPED_UNICODE);
        $st = $pdo->prepare('INSERT INTO forum_moderation_logs (tenant_id, user_id, post_id, rule_type, score, action_taken, detail_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $st->execute([$tenantId, $userId, $postId, 'heuristic', $score, $action, $detail]);
    }
}
