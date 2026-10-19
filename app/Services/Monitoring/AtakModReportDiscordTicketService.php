<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Repositories\AtakModReportRepository;
use App\Services\Atak\AtakStartupCheckService;
use App\Services\Security\FileRateLimiter;

/**
 * Signalements du mod en mode ticket dans Discord : chaque nouveau rapport ouvre un post dans un salon forum
 * (un fil par ticket), puis chaque étape (statut, réponse au joueur, nouvelle occurrence, suppression) est postée
 * dans ce fil et le premier message du ticket est réécrit avec le statut courant.
 *
 * Salon (facultatif, réservé à l'équipe de développement de la plateforme) : réglé par l'administrateur
 * dans Intégrations > Plateforme (salon forum de préférence ; un salon texte marche aussi, sans fil).
 * Sans lui, rien n'est envoyé. Chaque communauté reçoit en plus les rapports de ses propres joueurs
 * si elle choisit un salon pour « Signalements du mod » (DiscordWebhookCatalog::KEY_ATAK_MOD_REPORTS). Rapports de même empreinte : un seul ticket (30 jours).
 * Les rapports de faible gravité n'ouvrent pas de ticket. Aucun Steam, e-mail ni adresse IP n'est posté.
 */
final class AtakModReportDiscordTicketService
{
    public const ENV_WEBHOOK = 'DISCORD_MOD_REPORTS_WEBHOOK';

    /** Un rapport de même empreinte rejoint le ticket existant pendant 30 jours. */
    private const LINK_WINDOW_DAYS = 30;
    /** En-tête réécrit au plus toutes les 10 min pour un simple compteur (×N), « nouvelle occurrence » idem. */
    private const QUIET_SECONDS = 600;
    /** Au plus 20 nouveaux tickets par heure en tout (tempête d'erreurs). */
    private const NEW_TICKETS_PER_HOUR = 20;
    /** Actions groupées : au plus 40 tickets mis à jour par requête (limite de débit Discord). */
    private const BULK_TICKETS_MAX = 40;

    private const STATUS_ICONS = [
        AtakModReportRepository::STATUS_NEW => '🔴',
        AtakModReportRepository::STATUS_IN_PROGRESS => '🟠',
        AtakModReportRepository::STATUS_FIXED => '🟢',
        AtakModReportRepository::STATUS_DISMISSED => '⚪',
    ];
    private const STATUS_EN = [
        AtakModReportRepository::STATUS_NEW => 'New',
        AtakModReportRepository::STATUS_IN_PROGRESS => 'In progress',
        AtakModReportRepository::STATUS_FIXED => 'Fixed',
        AtakModReportRepository::STATUS_DISMISSED => 'Closed',
    ];
    private const STATUS_COLORS = [
        AtakModReportRepository::STATUS_NEW => 0xE5483A,
        AtakModReportRepository::STATUS_IN_PROGRESS => 0xF2AB33,
        AtakModReportRepository::STATUS_FIXED => 0x5CC76B,
        AtakModReportRepository::STATUS_DISMISSED => 0x8A9099,
    ];

    private \PDO $pdo;

    /** @var \Closure(string, string, array<string, mixed>): array{status:int, body:?array<string, mixed>} */
    private \Closure $transport;

    /** @var \Closure(): int horloge (tests) */
    private \Closure $clock;

    /** Espace les appels (5 requêtes / 2 s par webhook côté Discord) ; pas avec un transport de test. */
    private bool $paced;
    private bool $called = false;

    /**
     * @param (\Closure(string, string, array<string, mixed>): array{status:int, body:?array<string, mixed>})|null $transport
     *   appel HTTP Discord (méthode, URL, charge JSON) ; tests
     */
    public function __construct(
        ?\PDO $pdo = null,
        private ?string $webhookUrl = null,
        ?\Closure $transport = null,
        private ?FileRateLimiter $limiter = null,
        ?\Closure $clock = null
    ) {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->paced = $transport === null;
        $this->transport = $transport ?? self::curlTransport(...);
        $this->clock = $clock ?? static fn (): int => time();
        if ($pdo === null) {
            $migration = dirname(__DIR__, 3) . '/bootstrap/atak_mod_report_discord_migration.php';
            if (is_file($migration)) {
                require_once $migration;
                run_atak_mod_report_discord_migration($this->pdo);
            }
        }
    }

    /** Salon réglé dans Intégrations > Plateforme (ancien .env DISCORD_MOD_REPORTS_WEBHOOK en secours). */
    public static function configuredWebhook(): string
    {
        return \App\Services\Platform\PlatformIntegrationSettings::webhook(\App\Services\Platform\PlatformIntegrationSettings::MOD_REPORTS);
    }

    public function enabled(): bool
    {
        return self::validWebhook($this->url());
    }

    /**
     * Lance $job après la réponse HTTP (le jeu ou l'admin n'attend pas Discord). Jamais bloquant.
     *
     * @param \Closure(self): void $job
     */
    public static function defer(\Closure $job): void
    {
        if (!self::validWebhook(self::configuredWebhook())) {
            return;
        }
        register_shutdown_function(static function () use ($job): void {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                $job(new self());
            } catch (\Throwable $e) {
                error_log('[atak/mod-reports] discord ' . $e->getMessage());
            }
        });
    }

    /**
     * Rapport reçu du mod (nouveau ou compteur ×N) : ouvre le ticket, le rattache à un ticket existant
     * de même empreinte, ou rafraîchit le compteur de l'en-tête.
     *
     * @return string opened, linked, refreshed, quiet, skipped_low, throttled, disabled, missing, failed
     */
    public function onReport(int $reportId): string
    {
        if (!$this->enabled()) {
            return 'disabled';
        }
        $report = $this->report($reportId);
        if ($report === null) {
            return 'missing';
        }
        $ticket = $this->ticketForReport($reportId);
        if ($ticket !== null) {
            if (!$this->quietOver($ticket['header_synced_at'] ?? null)) {
                return 'quiet';
            }

            return $this->refreshHeader($ticket) ? 'refreshed' : 'failed';
        }
        if (!self::opensTicket($report)) {
            return 'skipped_low';
        }

        $existing = $this->ticketForFingerprint((string) ($report['fingerprint'] ?? ''));
        if ($existing !== null) {
            $this->linkReport($reportId, (int) $existing['id']);
            if ($existing['status'] === AtakModReportRepository::STATUS_FIXED) {
                // Revient après « corrigé » : le ticket est rouvert, toujours signalé.
                $this->setTicketStatus((int) $existing['id'], AtakModReportRepository::STATUS_NEW);
                $existing['status'] = AtakModReportRepository::STATUS_NEW;
                $this->postStep($existing, '🔁 **Rouvert : le problème est revenu après la correction** · Reopened, seen again after the fix'
                    . "\n" . self::occurrenceLine($report), self::STATUS_COLORS[AtakModReportRepository::STATUS_NEW], true);
                $this->refreshHeader($existing);
            } elseif ($existing['status'] !== AtakModReportRepository::STATUS_DISMISSED && $this->quietOver($existing['last_step_at'] ?? null)) {
                $this->postStep($existing, '➕ **Nouvelle occurrence** · New occurrence' . "\n" . self::occurrenceLine($report), null, true);
                $this->refreshHeader($existing);
            } elseif ($this->quietOver($existing['header_synced_at'] ?? null)) {
                // « Sans suite » ou occurrences rapprochées : seul le compteur de l'en-tête bouge.
                $this->refreshHeader($existing);
            }

            return 'linked';
        }

        $limiter = $this->limiter ?? new FileRateLimiter();
        if ($limiter->hit('atak_mod_report_discord_new', 3600) > self::NEW_TICKETS_PER_HOUR) {
            return 'throttled';
        }

        return $this->open($report) ? 'opened' : 'failed';
    }

    /**
     * Changement de statut depuis /admin/atak-mod-reports (une carte ou une action groupée) : une étape par ticket.
     *
     * @param list<int> $reportIds
     */
    public function onStatusChanged(array $reportIds, string $status, string $by): int
    {
        if (!$this->enabled()) {
            return 0;
        }
        if (!isset(self::STATUS_ICONS[$status])) {
            return 0;
        }
        $done = 0;
        foreach (array_slice($this->ticketsForReports($reportIds), 0, self::BULK_TICKETS_MAX) as $ticket) {
            if ($ticket['status'] === $status) {
                continue;
            }
            $previous = (string) $ticket['status'];
            $this->setTicketStatus((int) $ticket['id'], $status);
            $ticket['status'] = $status;
            $reopened = $status === AtakModReportRepository::STATUS_NEW
                && in_array($previous, [AtakModReportRepository::STATUS_FIXED, AtakModReportRepository::STATUS_DISMISSED], true);
            $label = $reopened ? 'Rouvert · Reopened' : AtakModReportRepository::statusLabel($status) . ' · ' . self::STATUS_EN[$status];
            $this->postStep(
                $ticket,
                self::STATUS_ICONS[$status] . ' **Statut : ' . $label . '**' . self::byLine($by),
                self::STATUS_COLORS[$status]
            );
            $this->refreshHeader($ticket);
            $done++;
        }

        return $done;
    }

    /** Réponse envoyée au joueur depuis la page : étape dans le ticket (texte de la réponse). */
    public function onReply(int $reportId, string $text, string $by, array $channels): bool
    {
        if (!$this->enabled()) {
            return false;
        }
        $ticket = $this->ticketForReport($reportId);
        if ($ticket === null) {
            return false;
        }
        $via = [];
        foreach ($channels as $c) {
            $via[] = $c === 'site' ? 'Athena' : ($c === 'email' ? 'e-mail' : (string) $c);
        }
        $body = '💬 **Réponse au joueur** · Reply to player' . self::byLine($by)
            . ($via !== [] ? ' · ' . implode(' + ', $via) : ' · non envoyée, pas de compte lié')
            . "\n> " . str_replace("\n", "\n> ", AtakStartupCheckService::clean(str_replace("\n", ' ¶ ', $text), 1500));
        $body = str_replace(' ¶ ', "\n> ", $body);

        return $this->postStep($ticket, $body, 0x4F8EF7);
    }

    /**
     * Rapports supprimés de la page : étape dans le ticket, liens retirés (le fil Discord reste).
     *
     * @param list<int> $reportIds
     */
    public function onDeleted(array $reportIds, string $by): int
    {
        if (!$this->enabled()) {
            return 0;
        }
        $done = 0;
        foreach (array_slice($this->ticketsForReports($reportIds), 0, self::BULK_TICKETS_MAX) as $ticket) {
            $this->postStep($ticket, '🗑️ **Rapport supprimé d’Athena** · Report deleted' . self::byLine($by), 0x8A9099);
            $done++;
        }
        $ids = self::cleanIds($reportIds);
        if ($ids !== []) {
            $this->pdo->prepare('DELETE FROM atak_mod_report_discord_links WHERE report_id IN (' . self::marks($ids) . ')')->execute($ids);
        }

        return $done;
    }

    /**
     * Gravité (AtakModReportGravity) : les rapports « Faible » n'ouvrent pas de ticket. Sans gravité connue,
     * la sévérité du mod décide (info = faible).
     *
     * @param array<string, mixed> $report
     */
    public static function opensTicket(array $report): bool
    {
        $gravity = AtakModReportGravity::normalize(isset($report['gravity']) ? (string) $report['gravity'] : null);
        if ($gravity !== null) {
            return $gravity !== AtakModReportGravity::LOW;
        }

        return strtolower(trim((string) ($report['severity'] ?? 'error'))) !== 'info';
    }

    /**
     * Premier message du ticket : statut courant en tête, puis le rapport (sans Steam, e-mail ni IP).
     *
     * @param array<string, mixed> $report rapport qui a ouvert le ticket
     * @param array{reports:int, hits:int, players:list<string>, last_seen:string} $stats
     * @return array<string, mixed>
     */
    public static function buildHeaderEmbed(array $report, string $status, array $stats, string $link = ''): array
    {
        $status = isset(self::STATUS_ICONS[$status]) ? $status : AtakModReportRepository::STATUS_NEW;
        $id = (int) ($report['id'] ?? 0);
        $player = self::playerLabel($report);
        $players = $stats['players'] !== [] ? $stats['players'] : [$player];
        $who = implode(', ', array_slice($players, 0, 4)) . (count($players) > 4 ? ' +' . (count($players) - 4) : '');
        $versions = 'Mod ' . (trim((string) ($report['mod_version'] ?? '')) ?: '—')
            . ' · Link ' . (trim((string) ($report['extension_version'] ?? '')) ?: '—');
        $kind = str_starts_with(strtolower((string) ($report['source'] ?? '')), 'player') ? 'Signalement joueur' : 'Détection automatique';
        $gravity = AtakModReportGravity::normalize(isset($report['gravity']) ? (string) $report['gravity'] : null);
        $detail = AtakStartupCheckService::clean((string) ($report['detail_text'] ?? ''), 900);

        $description = '## ' . self::STATUS_ICONS[$status] . ' ' . AtakModReportRepository::statusLabel($status) . ' · ' . self::STATUS_EN[$status]
            . "\n" . '**' . AtakStartupCheckService::clean((string) ($report['message'] ?? ''), 400) . '**'
            . ($detail !== '' ? "\n" . $detail : '');
        $fields = [
            ['name' => 'Joueur / Player', 'value' => $who, 'inline' => true],
            ['name' => 'Type', 'value' => $kind . ' · ' . AtakStartupCheckService::clean((string) ($report['channel'] ?? 'Core'), 40)
                . ($gravity !== null ? ' · gravité ' . AtakModReportGravity::label($gravity) : ''), 'inline' => true],
            ['name' => 'Versions', 'value' => $versions, 'inline' => true],
            ['name' => 'Occurrences', 'value' => '×' . max(1, $stats['hits'])
                . ($stats['reports'] > 1 ? ' · ' . $stats['reports'] . ' rapports' : ''), 'inline' => true],
            ['name' => 'Premier envoi / First seen', 'value' => self::discordTime((string) ($report['created_at'] ?? '')), 'inline' => true],
            ['name' => 'Dernier envoi / Last seen', 'value' => self::discordTime($stats['last_seen']), 'inline' => true],
        ];
        if ($link !== '') {
            $fields[] = ['name' => 'Athena', 'value' => '[Ouvrir le rapport #' . $id . '](' . $link . ')', 'inline' => false];
        }

        return [
            'title' => mb_substr('Ticket #' . $id . ' · ' . AtakStartupCheckService::clean((string) ($report['message'] ?? ''), 200), 0, 256),
            'description' => mb_substr($description, 0, 4000),
            'color' => self::STATUS_COLORS[$status],
            'fields' => $fields,
            'footer' => ['text' => 'Statut mis à jour par Athena · Status kept up to date by Athena'],
        ];
    }

    /** @param array<string, mixed> $report */
    public static function threadName(array $report): string
    {
        $name = '#' . (int) ($report['id'] ?? 0) . ' · ' . str_replace('\\', '', AtakStartupCheckService::clean((string) ($report['message'] ?? ''), 90));

        return mb_substr($name, 0, 100);
    }

    // ── Discord ──────────────────────────────────────────────────────────

    /** @param array<string, mixed> $report */
    private function open(array $report): bool
    {
        $embed = self::buildHeaderEmbed($report, (string) ($report['workflow_status'] ?? 'new'), $this->statsFor([(int) $report['id']]), $this->adminLink((int) $report['id']));
        $payload = ['embeds' => [$embed], 'allowed_mentions' => ['parse' => []], 'username' => 'Athena · Tickets', 'thread_name' => self::threadName($report)];
        $res = $this->call('POST', $this->url() . '?wait=true', $payload);
        if ($res['status'] === 400) {
            // Salon texte (pas un forum) : le ticket est un simple message, les étapes suivent dans le salon.
            unset($payload['thread_name']);
            $res = $this->call('POST', $this->url() . '?wait=true', $payload);
        }
        $messageId = (string) ($res['body']['id'] ?? '');
        if ($res['status'] < 200 || $res['status'] >= 300 || $messageId === '') {
            error_log('[atak/mod-reports] discord : ticket non créé (code ' . $res['status'] . ')');

            return false;
        }
        $channel = (string) ($res['body']['channel_id'] ?? '');
        $threadId = isset($payload['thread_name']) && $channel !== '' ? $channel : null;
        $now = $this->now();
        $this->pdo->prepare(
            'INSERT INTO atak_mod_report_discord_tickets
                (opened_report_id, fingerprint, thread_id, header_message_id, status, last_step_at, header_synced_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $report['id'],
            self::fingerprintOf($report),
            $threadId,
            $messageId,
            (string) ($report['workflow_status'] ?? 'new') ?: 'new',
            $now,
            $now,
            $now,
        ]);
        $this->linkReport((int) $report['id'], (int) $this->pdo->lastInsertId());

        return true;
    }

    /** @param array<string, mixed> $ticket */
    private function refreshHeader(array $ticket): bool
    {
        $opened = $this->report((int) $ticket['opened_report_id']);
        $reportIds = $this->linkedReportIds((int) $ticket['id']);
        if ($opened === null) {
            $opened = $reportIds !== [] ? $this->report($reportIds[0]) : null;
        }
        if ($opened === null) {
            return false;
        }
        $embed = self::buildHeaderEmbed($opened, (string) $ticket['status'], $this->statsFor($reportIds), $this->adminLink((int) $opened['id']));
        $url = $this->url() . '/messages/' . rawurlencode((string) $ticket['header_message_id'])
            . (!empty($ticket['thread_id']) ? '?thread_id=' . rawurlencode((string) $ticket['thread_id']) : '');
        $res = $this->call('PATCH', $url, ['embeds' => [$embed], 'allowed_mentions' => ['parse' => []]]);
        $this->pdo->prepare('UPDATE atak_mod_report_discord_tickets SET header_synced_at = ? WHERE id = ?')
            ->execute([$this->now(), (int) $ticket['id']]);

        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /** @param array<string, mixed> $ticket */
    private function postStep(array $ticket, string $text, ?int $color = null, bool $isOccurrence = false): bool
    {
        $thread = (string) ($ticket['thread_id'] ?? '');
        if ($thread === '') {
            // Salon texte : on rappelle le ticket concerné.
            $text = '**Ticket #' . (int) $ticket['opened_report_id'] . '** · ' . $text;
        }
        $embed = ['description' => mb_substr($text, 0, 4000), 'timestamp' => gmdate('c', ($this->clock)())];
        if ($color !== null) {
            $embed['color'] = $color;
        }
        $res = $this->call(
            'POST',
            $this->url() . '?wait=true' . ($thread !== '' ? '&thread_id=' . rawurlencode($thread) : ''),
            ['embeds' => [$embed], 'allowed_mentions' => ['parse' => []], 'username' => 'Athena · Tickets']
        );
        if ($isOccurrence) {
            $this->pdo->prepare('UPDATE atak_mod_report_discord_tickets SET last_step_at = ? WHERE id = ?')
                ->execute([$this->now(), (int) $ticket['id']]);
        }

        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:?array<string, mixed>}
     */
    private function call(string $method, string $url, array $payload): array
    {
        if ($this->paced && $this->called) {
            usleep(450_000);
        }
        $this->called = true;
        $res = ($this->transport)($method, $url, $payload);
        if ($res['status'] === 429) {
            $wait = (float) ($res['body']['retry_after'] ?? 1.0);
            usleep((int) (min(5.0, max(0.2, $wait)) * 1_000_000));
            $res = ($this->transport)($method, $url, $payload);
        }

        return $res;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:?array<string, mixed>}
     */
    private static function curlTransport(string $method, string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0) {
            return ['status' => 0, 'body' => null];
        }
        $body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return ['status' => $status, 'body' => is_array($body) ? $body : null];
    }

    // ── Base ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function report(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM atak_mod_reports WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function ticketForReport(int $reportId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT t.* FROM atak_mod_report_discord_links l
               JOIN atak_mod_report_discord_tickets t ON t.id = l.ticket_id
              WHERE l.report_id = ? LIMIT 1'
        );
        $st->execute([$reportId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function ticketForFingerprint(string $fingerprint): ?array
    {
        if ($fingerprint === '') {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM atak_mod_report_discord_tickets WHERE fingerprint = ? AND created_at >= ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$fingerprint, gmdate('Y-m-d H:i:s', ($this->clock)() - self::LINK_WINDOW_DAYS * 86400)]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<int> $reportIds
     * @return list<array<string, mixed>>
     */
    private function ticketsForReports(array $reportIds): array
    {
        $ids = self::cleanIds($reportIds);
        if ($ids === []) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT DISTINCT t.* FROM atak_mod_report_discord_links l
               JOIN atak_mod_report_discord_tickets t ON t.id = l.ticket_id
              WHERE l.report_id IN (' . self::marks($ids) . ') ORDER BY t.id'
        );
        $st->execute($ids);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<int> */
    private function linkedReportIds(int $ticketId): array
    {
        $st = $this->pdo->prepare('SELECT report_id FROM atak_mod_report_discord_links WHERE ticket_id = ? ORDER BY report_id');
        $st->execute([$ticketId]);

        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param list<int> $reportIds
     * @return array{reports:int, hits:int, players:list<string>, last_seen:string}
     */
    private function statsFor(array $reportIds): array
    {
        $ids = self::cleanIds($reportIds);
        if ($ids === []) {
            return ['reports' => 0, 'hits' => 0, 'players' => [], 'last_seen' => ''];
        }
        $st = $this->pdo->prepare('SELECT * FROM atak_mod_reports WHERE id IN (' . self::marks($ids) . ') ORDER BY id');
        $st->execute($ids);
        $hits = 0;
        $players = [];
        $last = '';
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
            $hits += max(1, (int) ($r['hit_count'] ?? 1));
            $players[self::playerLabel($r)] = true;
            $seen = (string) ($r['last_seen_at'] ?? '');
            $last = strcmp($seen, $last) > 0 ? $seen : $last;
        }

        return ['reports' => count($ids), 'hits' => $hits, 'players' => array_keys($players), 'last_seen' => $last];
    }

    private function linkReport(int $reportId, int $ticketId): void
    {
        $this->pdo->prepare('INSERT INTO atak_mod_report_discord_links (report_id, ticket_id, created_at) VALUES (?, ?, ?)')
            ->execute([$reportId, $ticketId, $this->now()]);
    }

    /** Lien vers la carte du rapport sur Athena (vide si APP_URL n'est pas en https). */
    private function adminLink(int $reportId): string
    {
        if (!function_exists('url')) {
            return '';
        }
        $href = url('admin/atak-mod-reports') . '#rapport-' . $reportId;

        return str_starts_with($href, 'https://') ? $href : '';
    }

    private function setTicketStatus(int $ticketId, string $status): void
    {
        $this->pdo->prepare('UPDATE atak_mod_report_discord_tickets SET status = ? WHERE id = ?')->execute([$status, $ticketId]);
    }

    private function quietOver(mixed $at): bool
    {
        $ts = is_string($at) && $at !== '' ? strtotime($at . ' UTC') : false;

        return $ts === false || ($this->clock)() - $ts >= self::QUIET_SECONDS;
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s', ($this->clock)());
    }

    private function url(): string
    {
        return rtrim($this->webhookUrl ?? self::configuredWebhook(), '/');
    }

    private static function validWebhook(string $url): bool
    {
        return (new \App\Services\Integrations\DiscordWebhookService())->isValidWebhookUrl($url);
    }

    // ── Texte ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $report */
    private static function playerLabel(array $report): string
    {
        $name = AtakStartupCheckService::clean((string) ($report['player_name'] ?? ''), 48);
        $callsign = AtakStartupCheckService::clean((string) ($report['callsign'] ?? ''), 32);
        if ($name === '') {
            return $callsign !== '' ? $callsign : 'Joueur inconnu';
        }

        return $name . ($callsign !== '' && $callsign !== $name ? ' (' . $callsign . ')' : '');
    }

    /** @param array<string, mixed> $report */
    private static function occurrenceLine(array $report): string
    {
        return self::playerLabel($report) . ' · rapport #' . (int) ($report['id'] ?? 0)
            . ' · Mod ' . (trim((string) ($report['mod_version'] ?? '')) ?: '—');
    }

    private static function byLine(string $by): string
    {
        $by = AtakStartupCheckService::clean($by, 64);

        return $by !== '' && !str_contains($by, '@') ? ' · par ' . $by : '';
    }

    /** @param array<string, mixed> $report */
    private static function fingerprintOf(array $report): ?string
    {
        $fp = trim((string) ($report['fingerprint'] ?? ''));

        return $fp !== '' ? mb_substr($fp, 0, 64) : null;
    }

    private static function discordTime(string $datetime): string
    {
        $ts = $datetime !== '' ? strtotime($datetime) : false;

        return $ts !== false ? '<t:' . $ts . ':f>' : '—';
    }

    /**
     * @param array<mixed> $ids
     * @return list<int>
     */
    private static function cleanIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /** @param list<mixed> $values */
    private static function marks(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
