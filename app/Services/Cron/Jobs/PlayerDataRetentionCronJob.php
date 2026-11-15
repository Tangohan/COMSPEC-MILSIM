<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Admin\PlayerTracePurgeService;
use App\Services\Cron\CronJobInterface;
use PDO;

/**
 * Entretien automatique des données de joueurs :
 *  1. rattrapage : traces de jeu des comptes déjà supprimés / anonymisés / fusionnés (carte, registre, mods…) ;
 *  2. rétention : données de jeu périmées (unités hors ligne depuis longtemps, sessions mortes, demandes de
 *     liaison expirées, relevés et statistiques au-delà de leur durée utile).
 */
final class PlayerDataRetentionCronJob implements CronJobInterface
{
    /** Règles de rétention : table, condition, libellé. Durées volontairement larges. */
    public const RULES = [
        ['atak_units', 'updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)', 'Unités sur la carte sans signe de vie depuis 30 jours'],
        ['game_sessions', '(revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 60 DAY)) OR refresh_expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY)', 'Sessions de jeu fermées ou expirées depuis 60 jours'],
        ['game_device_pairings', 'revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 60 DAY)', 'Appairages révoqués depuis 60 jours'],
        ['game_atak_pair_challenges', 'expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)', 'Demandes de liaison expirées depuis 7 jours'],
        ['tactical_game_link_codes', 'expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)', 'Codes de liaison expirés depuis 7 jours'],
        ['atak_send_queue_snapshots', 'received_at < DATE_SUB(NOW(), INTERVAL 30 DAY)', 'Relevés de files d’envoi de plus de 30 jours'],
        ['tenant_game_server_presence', 'seen_at < DATE_SUB(NOW(), INTERVAL 30 DAY)', 'Présences serveur de plus de 30 jours'],
        ['operator_game_events', 'occurred_at < DATE_SUB(NOW(), INTERVAL 180 DAY)', 'Événements du registre opérateur de plus de 6 mois'],
        ['operator_game_snapshots', 'observed_at < DATE_SUB(NOW(), INTERVAL 180 DAY)', 'Instantanés du registre opérateur de plus de 6 mois'],
        ['atak_ingest_player_daily', 'day < DATE_SUB(CURDATE(), INTERVAL 400 DAY)', 'Débit par joueur de plus de 13 mois'],
        ['atak_ingest_error_daily', 'day < DATE_SUB(CURDATE(), INTERVAL 400 DAY)', 'Rejets d’ingestion de plus de 13 mois'],
        ['atak_web_commands', "status NOT IN ('pending', 'accepted') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)", 'Commandes du poste vers les téléphones terminées depuis 30 jours'],
        ['atak_toc_phone_events', "status NOT IN ('queued', 'delivered', 'running') AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)", 'Journal des actions du poste TOC sur les téléphones de plus de 6 mois'],
        ['atak_unit_motion_samples', 'sampled_at < DATE_SUB(NOW(), INTERVAL 1 DAY)', 'Échantillons de mouvement des unités de plus d’un jour'],
        ['atak_unit_motion', 'computed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)', 'État de mouvement des unités non recalculé depuis 7 jours'],
        ['atak_vehicle_position_history', 'recorded_at < DATE_SUB(NOW(), INTERVAL 365 DAY)', 'Historique de positions des véhicules de plus d’un an'],
        ['atak_chat_messages', 'created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)', 'Messages du tchat ATAK de plus d’un an'],
        ['player_sessions', 'started_at < DATE_SUB(NOW(), INTERVAL 400 DAY)', 'Sessions de jeu sur le serveur de plus de 13 mois'],
    ];

    private const BATCH = 5000;
    private const CATCHUP_USERS = 100;

    public function __construct(private PDO $pdo, private ?PlayerTracePurgeService $purge = null)
    {
        $this->purge ??= new PlayerTracePurgeService($pdo);
    }

    public function key(): string
    {
        return 'player_data_retention';
    }

    public function label(): string
    {
        return 'Entretien des données de joueurs';
    }

    public function description(): string
    {
        return 'Efface les traces de jeu des comptes supprimés (carte, registre opérateur, mods, statistiques) '
            . 'et les données de jeu périmées : unités sans signe de vie depuis 30 jours (24 heures dans la communauté '
            . 'par défaut des comptes sans communauté), sessions mortes, demandes de liaison expirées, relevés anciens.';
    }

    /** @return array{ok: bool, summary: string, details: array<string, mixed>} */
    public function run(): array
    {
        \App\Support\UsersDeletedAtSchema::ensure($this->pdo);
        $details = ['retention' => [], 'catchup_users' => 0, 'catchup_rows' => 0];

        foreach ($this->ghostUserIds() as $uid) {
            $done = $this->purge->purgeUser($uid, null);
            $details['catchup_users']++;
            $details['catchup_rows'] += array_sum($done);
        }

        foreach (self::RULES as [$table, $where]) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $n = 0;
            try {
                $st = $this->pdo->prepare("DELETE FROM {$table} WHERE {$where} LIMIT " . self::BATCH);
                for ($i = 0; $i < 40; $i++) {
                    $st->execute();
                    $c = $st->rowCount();
                    $n += $c;
                    if ($c < self::BATCH) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $details['errors'][$table] = mb_substr($e->getMessage(), 0, 160);
            }
            if ($n > 0) {
                $details['retention'][$table] = $n;
            }
        }

        // Communauté par défaut (comptes sans communauté) : pistes sans signe de vie depuis 24 h, et fichiers d'état
        // des cartes réservées aux parties. Les communautés réelles gardent la règle des 30 jours ci-dessus.
        try {
            $defaultTenants = \App\Support\AtakPartyScope::defaultTenantIds($this->pdo);
            $n = 0;
            foreach ($defaultTenants as $tenantId) {
                $n += \App\Support\AtakPartyScope::purgeStaleDefaultUnits($this->pdo, $tenantId, true)['deleted'];
            }
            if ($n > 0) {
                $details['retention']['atak_units_default_community'] = $n;
            }
            if ($defaultTenants !== [] && function_exists('base_path')) {
                $details['default_community_files'] = \App\Support\AtakPartyScope::sweepReservedMapFiles(
                    base_path('storage/cache'),
                    $defaultTenants,
                    true
                )['deleted'];
            }
        } catch (\Throwable $e) {
            $details['errors']['atak_units_default_community'] = mb_substr($e->getMessage(), 0, 160);
        }

        $ret = array_sum($details['retention']);

        return [
            'ok' => empty($details['errors']),
            'summary' => sprintf(
                'Comptes supprimés nettoyés : %d (%d ligne(s)) · Données périmées effacées : %d ligne(s)',
                $details['catchup_users'],
                $details['catchup_rows'],
                $ret
            ),
            'details' => $details,
        ];
    }

    /**
     * Comptes supprimés / anonymisés / fusionnés qui ont encore des traces de jeu.
     *
     * @return list<int>
     */
    public function ghostUserIds(int $limit = self::CATCHUP_USERS): array
    {
        $sources = [];
        foreach ([
            'operator_game_profiles' => 'user_id',
            'atak_terminals' => 'user_id',
            'game_player_mods' => null,
            'user_arma_playtime' => 'user_id',
            'player_sessions' => 'user_id',
            'atak_screen_time' => 'user_id',
            'game_phone_identities' => 'user_id',
            'atak_operator_ids' => 'user_id',
        ] as $table => $col) {
            if ($col !== null && $this->tableExists($table)) {
                $sources[] = "SELECT DISTINCT {$col} AS uid FROM {$table} WHERE {$col} IS NOT NULL";
            }
        }
        if ($sources === []) {
            return [];
        }
        try {
            $st = $this->pdo->query(
                'SELECT u.id FROM users u JOIN (' . implode(' UNION ', $sources) . ") g ON g.uid = u.id
                 WHERE u.deleted_at IS NOT NULL OR u.status IN ('merged', 'deleted')
                 LIMIT " . max(1, $limit)
            );

            return array_map('intval', $st ? ($st->fetchAll(PDO::FETCH_COLUMN) ?: []) : []);
        } catch (\Throwable) {
            return [];
        }
    }

    private function tableExists(string $t): bool
    {
        try {
            $st = $this->pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            $st->execute([$t]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
