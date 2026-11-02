<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Données réelles lues pour les propositions d’avancement (lecture seule, par lot pour toute la communauté) :
 * membres actifs et date d’arrivée, présences et heures validées des sessions Arma, pointages des événements,
 * remontées de temps de jeu du mod, sanctions de la communauté, absences déclarées.
 * Chaque source renvoie null quand son module n’est pas installé : le critère est alors « non mesuré ».
 *
 * Tient aussi le suivi des propositions retenues ou écartées par campagne (advancement_proposal_decisions).
 */
final class AdvancementProposalRepository
{
    public const DECISION_KEPT = 'retenue';
    public const DECISION_DISMISSED = 'ecartee';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * @return array<int, array{id:int, display_name:string, callsign:string, email:string, joined_on:?string, last_login_at:?string}>
     */
    public function activeMembers(int $tenantId): array
    {
        $rows = [];
        foreach ([
            "SELECT id, display_name, callsign, email, created_at, last_login_at FROM users WHERE tenant_id = ? AND status = 'active'",
            "SELECT id, display_name, callsign, email, created_at FROM users WHERE tenant_id = ? AND status = 'active'",
        ] as $sql) {
            try {
                $st = $this->pdo->prepare($sql);
                $st->execute([$tenantId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                break;
            } catch (Throwable) {
                $rows = [];
            }
        }
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $out[$id] = [
                'id' => $id,
                'display_name' => trim((string) ($row['display_name'] ?? '')),
                'callsign' => trim((string) ($row['callsign'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'joined_on' => $this->day((string) ($row['created_at'] ?? '')),
                'last_login_at' => $this->day((string) ($row['last_login_at'] ?? '')),
            ];
        }
        if ($out === []) {
            return [];
        }
        // Date d’engagement saisie sur la fiche : prime sur la création du compte.
        try {
            foreach (array_chunk(array_keys($out), 500) as $chunk) {
                $st = $this->pdo->prepare(
                    'SELECT user_id, enlistment_date FROM personnel_profiles WHERE user_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
                );
                $st->execute($chunk);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $uid = (int) ($row['user_id'] ?? 0);
                    $day = $this->day((string) ($row['enlistment_date'] ?? ''));
                    if ($day !== null && isset($out[$uid])) {
                        $out[$uid]['joined_on'] = $day;
                    }
                }
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * Sessions Arma depuis $since : jours de présence (pointage ou présence validée, hors exclusion RH), secondes
     * validées et dernière présence par membre. measured = au moins une session dans la communauté sur la période.
     *
     * @return array{measured:bool, hours_measured:bool, by_user:array<int, array{days:array<string,true>, seconds_by_day:array<string,int>, last:?string}>}|null
     */
    public function sessionSignals(int $tenantId, string $since): ?array
    {
        try {
            $count = $this->pdo->prepare(
                'SELECT COUNT(*) FROM roleplay_game_sessions WHERE tenant_id = ? AND COALESCE(started_at, created_at) >= ?'
            );
            $count->execute([$tenantId, $since . ' 00:00:00']);
            $sessions = (int) $count->fetchColumn();
            $st = $this->pdo->prepare(
                'SELECT m.user_id, COALESCE(s.started_at, s.created_at) AS played_at, m.validated_seconds,
                        m.checked_in_at, m.attendance_valid, m.rh_excluded
                 FROM roleplay_game_session_members m
                 INNER JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
                 WHERE m.tenant_id = ? AND m.user_id IS NOT NULL AND COALESCE(s.started_at, s.created_at) >= ?'
            );
            $st->execute([$tenantId, $since . ' 00:00:00']);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return null;
        }
        $byUser = [];
        $hoursMeasured = false;
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid < 1 || !empty($row['rh_excluded'])) {
                continue;
            }
            $day = $this->day((string) ($row['played_at'] ?? ''));
            $present = trim((string) ($row['checked_in_at'] ?? '')) !== '' || !empty($row['attendance_valid']);
            $byUser[$uid] ??= ['days' => [], 'seconds_by_day' => [], 'last' => null];
            if (!empty($row['attendance_valid']) && $day !== null) {
                $seconds = max(0, (int) ($row['validated_seconds'] ?? 0));
                $byUser[$uid]['seconds_by_day'][$day] = ($byUser[$uid]['seconds_by_day'][$day] ?? 0) + $seconds;
                $hoursMeasured = $hoursMeasured || $seconds > 0;
            }
            if ($present && $day !== null) {
                $byUser[$uid]['days'][$day] = true;
                if ($byUser[$uid]['last'] === null || strcmp($day, $byUser[$uid]['last']) > 0) {
                    $byUser[$uid]['last'] = $day;
                }
            }
        }

        return ['measured' => $sessions > 0, 'hours_measured' => $hoursMeasured, 'by_user' => $byUser];
    }

    /**
     * Pointages aux événements du calendrier de la communauté, entre $since et $until.
     *
     * @return array{measured:bool, by_user:array<int, array{days:array<string,true>, last:?string}>}|null
     */
    public function eventSignals(int $tenantId, string $since, string $until): ?array
    {
        try {
            $count = $this->pdo->prepare(
                'SELECT COUNT(*) FROM community_event_rsvps r
                 INNER JOIN community_events ce ON ce.id = r.event_id
                 WHERE ce.tenant_id = ? AND ce.cancelled_at IS NULL AND r.checked_in_at IS NOT NULL
                   AND ce.starts_at >= ? AND ce.starts_at <= ?'
            );
            $count->execute([$tenantId, $since . ' 00:00:00', $until . ' 23:59:59']);
            $checkins = (int) $count->fetchColumn();
            $st = $this->pdo->prepare(
                'SELECT r.user_id, ce.starts_at FROM community_event_rsvps r
                 INNER JOIN community_events ce ON ce.id = r.event_id
                 WHERE ce.tenant_id = ? AND ce.cancelled_at IS NULL AND r.checked_in_at IS NOT NULL
                   AND ce.starts_at >= ? AND ce.starts_at <= ?'
            );
            $st->execute([$tenantId, $since . ' 00:00:00', $until . ' 23:59:59']);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return null;
        }
        $byUser = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $day = $this->day((string) ($row['starts_at'] ?? ''));
            if ($uid < 1 || $day === null) {
                continue;
            }
            $byUser[$uid] ??= ['days' => [], 'last' => null];
            $byUser[$uid]['days'][$day] = true;
            if ($byUser[$uid]['last'] === null || strcmp($day, $byUser[$uid]['last']) > 0) {
                $byUser[$uid]['last'] = $day;
            }
        }

        return ['measured' => $checkins > 0, 'by_user' => $byUser];
    }

    /**
     * Dernière remontée de temps de jeu du mod (compte relié), par membre.
     *
     * @return array<int, string>|null
     */
    public function lastGameReports(int $tenantId): ?array
    {
        try {
            $st = $this->pdo->prepare('SELECT user_id, last_report_at FROM user_arma_playtime WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return null;
        }
        $out = [];
        foreach ($rows as $row) {
            $day = $this->day((string) ($row['last_report_at'] ?? ''));
            if ($day !== null) {
                $out[(int) $row['user_id']] = $day;
            }
        }

        return $out;
    }

    /**
     * Mesures disciplinaires de la communauté (module « Restrictions membres »), levées comprises.
     *
     * @return array<int, list<array{type:string, created_at:string, expires_at:?string, revoked_at:?string}>>|null
     */
    public function sanctions(int $tenantId): ?array
    {
        $rows = null;
        foreach ([
            "SELECT target_user_id, action_type, created_at, expires_at, revoked_at FROM moderation_actions
             WHERE tenant_id = ? AND (sanction_scope = 'tenant' OR sanction_scope IS NULL OR sanction_scope = '')",
            'SELECT target_user_id, action_type, created_at, expires_at, revoked_at FROM moderation_actions WHERE tenant_id = ?',
        ] as $sql) {
            try {
                $st = $this->pdo->prepare($sql);
                $st->execute([$tenantId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                break;
            } catch (Throwable) {
                $rows = null;
            }
        }
        if ($rows === null) {
            return null;
        }
        $out = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['target_user_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $out[$uid][] = [
                'type' => (string) ($row['action_type'] ?? 'warn'),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'expires_at' => $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
                'revoked_at' => $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
            ];
        }

        return $out;
    }

    /**
     * Absences déclarées en cours au jour donné : date de fin par membre (« » = sans date de fin).
     *
     * @return array<int, string>|null
     */
    public function activeAbsences(int $tenantId, string $day): ?array
    {
        try {
            $st = $this->pdo->prepare(
                "SELECT user_id, ends_on FROM personnel_absences
                 WHERE tenant_id = ? AND status = 'active' AND starts_on <= ? AND (ends_on IS NULL OR ends_on >= ?)"
            );
            $st->execute([$tenantId, $day, $day]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return null;
        }
        $out = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $end = $this->day((string) ($row['ends_on'] ?? '')) ?? '';
            if ($uid > 0 && (!isset($out[$uid]) || ($out[$uid] !== '' && ($end === '' || strcmp($end, $out[$uid]) > 0)))) {
                $out[$uid] = $end;
            }
        }

        return $out;
    }

    public function decisionsReady(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM advancement_proposal_decisions LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array{decision:string, score:int, justification:string, decided_at:string, decided_by:?int}>
     */
    public function listDecisions(int $tenantId, int $campaignId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT personnel_id, decision, score, justification, decided_at, decided_by
                 FROM advancement_proposal_decisions WHERE tenant_id = ? AND campaign_id = ?'
            );
            $st->execute([$tenantId, $campaignId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['personnel_id']] = [
                'decision' => (string) $row['decision'],
                'score' => (int) ($row['score'] ?? 0),
                'justification' => (string) ($row['justification'] ?? ''),
                'decided_at' => (string) ($row['decided_at'] ?? ''),
                'decided_by' => isset($row['decided_by']) ? (int) $row['decided_by'] : null,
            ];
        }

        return $out;
    }

    public function saveDecision(int $tenantId, int $campaignId, int $personnelId, string $decision, int $score, string $justification, int $actorId): void
    {
        $this->deleteDecision($tenantId, $campaignId, $personnelId);
        $st = $this->pdo->prepare(
            'INSERT INTO advancement_proposal_decisions (tenant_id, campaign_id, personnel_id, decision, score, justification, decided_by, decided_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $campaignId,
            $personnelId,
            $decision === self::DECISION_KEPT ? self::DECISION_KEPT : self::DECISION_DISMISSED,
            max(0, min(100, $score)),
            mb_substr(trim($justification), 0, 2000),
            $actorId > 0 ? $actorId : null,
            date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteDecision(int $tenantId, int $campaignId, int $personnelId): void
    {
        $st = $this->pdo->prepare(
            'DELETE FROM advancement_proposal_decisions WHERE tenant_id = ? AND campaign_id = ? AND personnel_id = ?'
        );
        $st->execute([$tenantId, $campaignId, $personnelId]);
    }

    private function day(string $raw): ?string
    {
        $raw = substr(trim($raw), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1 && $raw !== '0000-00-00' ? $raw : null;
    }
}
