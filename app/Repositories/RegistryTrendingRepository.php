<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Analytics\AnalyticsEventName;
use App\Support\SqlText;
use PDO;

/**
 * Signaux de l'annuaire public (/communities) calculés sur des données déjà collectées :
 * visiteurs de la fiche publique (événement tenant_public_view), nouvelles recrues et membres connectés.
 * Une requête groupée par signal, pour toutes les communautés demandées.
 */
class RegistryTrendingRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    private function hasTable(string $table): bool
    {
        try {
            $st = $this->pdo->query('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $this->pdo->quote($table) . ' LIMIT 1');

            return $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<int> $tenantIds
     * @return array<int, array{visitors: int, visitors_prev: int, recruits: int, active: int}>
     */
    public function signals(array $tenantIds, int $days): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $tenantIds), static fn (int $id): bool => $id > 1)));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['visitors' => 0, 'visitors_prev' => 0, 'recruits' => 0, 'active' => 0];
        }
        if ($ids === []) {
            return $out;
        }
        $days = max(1, min(90, $days));
        $now = new \DateTimeImmutable('now');
        $since = $now->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
        $sincePrev = $now->modify('-' . (2 * $days) . ' days')->format('Y-m-d H:i:s');
        $in = implode(',', $ids);

        if ($this->hasTable('usage_analytics_events')) {
            // Un visiteur = une session (ou un compte) distincte sur la fiche publique.
            $eventName = SqlText::equalsLiteral($this->pdo, 'name', AnalyticsEventName::TENANT_PUBLIC_VIEW);
            $visitor = "COALESCE(session_hash, CONCAT('u:', actor_user_id), CONCAT('e:', id))";
            $st = $this->pdo->prepare(
                "SELECT tenant_id,
                        COUNT(DISTINCT CASE WHEN created_at >= ? THEN {$visitor} END) AS cur,
                        COUNT(DISTINCT CASE WHEN created_at < ? THEN {$visitor} END) AS prev
                 FROM usage_analytics_events
                 WHERE {$eventName} AND created_at >= ? AND tenant_id IN ({$in})
                 GROUP BY tenant_id"
            );
            $st->execute([$since, $since, $sincePrev]);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $tid = (int) $row['tenant_id'];
                if (isset($out[$tid])) {
                    $out[$tid]['visitors'] = (int) $row['cur'];
                    $out[$tid]['visitors_prev'] = (int) $row['prev'];
                }
            }
        }

        $human = UserRepository::sqlLiteralExcludeTechnicalInternalEmails('u');
        $activeMember = SqlText::equalsLiteral($this->pdo, 'm.status', 'active');
        if ($this->hasTable('user_community_memberships')) {
            $recruits = "SELECT m.tenant_id, COUNT(*) AS n FROM user_community_memberships m
                         JOIN users u ON u.id = m.user_id
                         WHERE m.tenant_id IN ({$in}) AND {$activeMember} AND m.joined_at >= ? AND {$human}
                         GROUP BY m.tenant_id";
            $active = "SELECT m.tenant_id, COUNT(DISTINCT u.id) AS n FROM user_community_memberships m
                       JOIN users u ON u.id = m.user_id
                       WHERE m.tenant_id IN ({$in}) AND {$activeMember} AND u.last_login_at >= ? AND {$human}
                       GROUP BY m.tenant_id";
        } else {
            $recruits = "SELECT u.tenant_id, COUNT(*) AS n FROM users u
                         WHERE u.tenant_id IN ({$in}) AND u.created_at >= ? AND {$human}
                         GROUP BY u.tenant_id";
            $active = "SELECT u.tenant_id, COUNT(*) AS n FROM users u
                       WHERE u.tenant_id IN ({$in}) AND u.last_login_at >= ? AND {$human}
                       GROUP BY u.tenant_id";
        }
        foreach (['recruits' => $recruits, 'active' => $active] as $key => $sql) {
            try {
                $st = $this->pdo->prepare($sql);
                $st->execute([$since]);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $tid = (int) $row['tenant_id'];
                    if (isset($out[$tid])) {
                        $out[$tid][$key] = (int) $row['n'];
                    }
                }
            } catch (\Throwable) {
                // signal indisponible : la tendance se calcule sans lui
            }
        }

        return $out;
    }
}
