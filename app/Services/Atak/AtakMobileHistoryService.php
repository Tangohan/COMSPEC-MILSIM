<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Core\Database;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakReportRoutingRepository;
use App\Services\Personnel\MemberNameService;
use App\Support\MpMessageParser;
use App\Support\TacticalAlertParser;
use PDO;

/**
 * « Sur mon téléphone » : ce que le site sait vraiment renvoyer au joueur, en lecture seule.
 *
 * - Comptes rendus : ceux que le joueur a envoyés (atak_tactical_reports, par son compte ou son Steam) et ceux qui lui
 *   sont adressés (atak_report_routing_history). Lire un compte rendu reçu l'acquitte, comme dans l'ATAK.
 * - Tchat : canaux de la partie (atak_chat_messages) et messages privés cTab dont le joueur est l'auteur ou le
 *   destinataire. Les messages privés des autres ne sont jamais renvoyés.
 * - Rien d'autre : le jeu ne remonte ni SMS, ni état MPU5 détaillé, ni historique d'écoute musicale (voir unavailable()).
 * Toutes les lectures sont limitées à la communauté et à la partie du lien (tenant_id, map_id).
 */
final class AtakMobileHistoryService
{
    public const LIST_LIMIT = 40;

    public const CHAT_LIMIT = 60;

    /** Messages relus pour en tirer les messages privés du joueur. */
    private const PRIVATE_SCAN = 400;

    public function __construct(
        private ?PDO $pdo = null,
        private ?AtakDataRepository $atak = null,
        private ?AtakReportRoutingRepository $routing = null,
    ) {
    }

    /**
     * Ce que le jeu ne remonte pas (aucune donnée inventée) : intitulé, ce qu'il manque côté jeu.
     *
     * @return list<array{label: string, why: string}>
     */
    public static function unavailable(): array
    {
        return [
            ['label' => 'SMS entre joueurs', 'why' => 'Les SMS passent de téléphone en téléphone dans le jeu. Aucun n’est transmis à Athena, il n’y a donc rien à afficher.'],
            ['label' => 'Radio MPU5', 'why' => 'Le jeu indique seulement si un joueur porte une MPU5. Les canaux, les fréquences et qui émet ne sont pas transmis à Athena.'],
            ['label' => 'Lecteur de musique', 'why' => 'Athena connaît la bibliothèque et le nombre d’écoutes par morceau, pas ce que vous avez écouté ni quand.'],
        ];
    }

    /**
     * Comptes rendus envoyés par ce joueur, le plus récent d'abord.
     *
     * @param array<string, mixed> $link
     * @return list<array<string, mixed>>
     */
    public function sent(array $link, int $limit = self::LIST_LIMIT): array
    {
        $steam = trim((string) ($link['steam_uid'] ?? ''));
        $who = 'submitter_user_id = ?';
        $args = [(int) $link['tenant_id'], (int) $link['map_id'], (int) $link['user_id']];
        if ($steam !== '') {
            $who = '(submitter_user_id = ? OR submitter_steam_id = ?)';
            $args[] = $steam;
        }
        $st = $this->db()->prepare(
            'SELECT id, report_type, priority, submitter_callsign, grid_reference, summary, status, report_timestamp
             FROM atak_tactical_reports
             WHERE tenant_id = ? AND context_id = ? AND deleted_at IS NULL AND ' . $who . '
             ORDER BY report_timestamp DESC, id DESC
             LIMIT ' . max(1, min(100, $limit))
        );
        $st->execute($args);

        return array_map(self::card(...), $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Comptes rendus adressés à ce joueur (lui, ses rôles, ses unités), non lus d'abord.
     *
     * @param array<string, mixed> $link
     * @return list<array<string, mixed>>
     */
    public function received(array $link, int $limit = self::LIST_LIMIT): array
    {
        $rows = [];
        try {
            $rows = $this->routing()->listForRecipients((int) $link['tenant_id'], (int) $link['map_id'], $this->recipients($link), ['limit' => $limit]);
        } catch (\Throwable) {
        }
        $out = [];
        foreach ($rows as $r) {
            $card = self::card($r);
            $card['routing_id'] = (int) ($r['routing_id'] ?? 0);
            $card['read'] = !empty($r['acknowledged']);
            $out[] = $card;
        }
        usort($out, static fn (array $a, array $b): int => [(int) $a['read'], $b['at']] <=> [(int) $b['read'], $a['at']]);

        return $out;
    }

    /**
     * Un compte rendu à lire : seulement s'il est de ce joueur ou adressé à lui.
     *
     * @param array<string, mixed> $link
     * @return array<string, mixed>|null
     */
    public function report(array $link, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $st = $this->db()->prepare(
            'SELECT * FROM atak_tactical_reports WHERE id = ? AND tenant_id = ? AND context_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $st->execute([$id, (int) $link['tenant_id'], (int) $link['map_id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $steam = trim((string) ($link['steam_uid'] ?? ''));
        $mine = (int) ($row['submitter_user_id'] ?? 0) === (int) $link['user_id']
            || ($steam !== '' && (string) ($row['submitter_steam_id'] ?? '') === $steam);
        $routingId = 0;
        $read = true;
        if (!$mine) {
            $found = null;
            foreach ($this->received($link, 200) as $c) {
                if ($c['id'] === $id) {
                    $found = $c;
                    break;
                }
            }
            if ($found === null) {
                return null;
            }
            $routingId = (int) $found['routing_id'];
            $read = (bool) $found['read'];
        }
        try {
            $row = (new \App\Services\Intel\IntelIntakeService())->presentReports(
                (int) $link['tenant_id'],
                [$row],
                \App\Repositories\SseCaseRepository::CLASS_INTERNAL,
                (int) $link['user_id']
            )[0] ?? $row;
        } catch (\Throwable) {
        }
        $card = self::card($row);
        $card['details'] = mb_substr(trim((string) ($row['details'] ?? '')), 0, 4000);
        $card['remarks'] = mb_substr(trim((string) ($row['remarks'] ?? '')), 0, 2000);
        $card['lines'] = self::lines($row['structured_data'] ?? null);
        $card['mine'] = $mine;
        $card['routing_id'] = $routingId;
        $card['read'] = $read;

        return $card;
    }

    /** Marque un compte rendu reçu comme lu (accusé de lecture, comme dans l'ATAK), seulement s'il m'est adressé. */
    public function markRead(array $link, int $reportId, int $routingId): bool
    {
        $recipients = $this->recipients($link);
        if ($routingId < 1 || $reportId < 1 || $recipients === []) {
            return false;
        }
        $who = [];
        $args = [(int) $link['user_id'], gmdate('Y-m-d H:i:s'), $routingId, $reportId];
        foreach ($recipients as $r) {
            $who[] = '(routed_to_type = ? AND routed_to_identifier = ?)';
            $args[] = $r['type'];
            $args[] = $r['identifier'];
        }
        array_push($args, (int) $link['tenant_id'], (int) $link['map_id']);
        try {
            $st = $this->db()->prepare(
                'UPDATE atak_report_routing_history SET acknowledged = 1, acknowledged_by_user_id = ?, acknowledged_at = ?
                 WHERE id = ? AND report_id = ? AND acknowledged = 0 AND (' . implode(' OR ', $who) . ')
                   AND EXISTS (SELECT 1 FROM atak_tactical_reports r
                               WHERE r.id = atak_report_routing_history.report_id AND r.tenant_id = ? AND r.context_id = ?)'
            );
            $st->execute($args);

            return $st->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Nombre de comptes rendus reçus non lus (pastille de l'accueil). */
    public function unreadCount(array $link): int
    {
        $n = 0;
        foreach ($this->received($link, 100) as $c) {
            $n += $c['read'] ? 0 : 1;
        }

        return $n;
    }

    /**
     * Canaux de la partie avec le nombre de messages visibles dans les derniers messages.
     *
     * @param array<string, mixed> $link
     * @return list<array{key: string, label: string}>
     */
    public function channels(array $link): array
    {
        $tenant = (int) $link['tenant_id'];
        $map = (int) $link['map_id'];
        $out = [];
        try {
            $this->atak()->ensureSystemChatChannels($tenant, $map);
            $st = $this->db()->prepare('SELECT channel_key, label FROM atak_chat_channels WHERE tenant_id = ? AND map_id = ? ORDER BY kind, label');
            $st->execute([$tenant, $map]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $key = (string) $r['channel_key'];
                $out[] = ['key' => $key, 'label' => \App\Support\AtakChatChannel::labelFor($key, (string) ($r['label'] ?? ''))];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Messages d'un canal (anciens d'abord), sans les messages privés ni les lignes techniques.
     *
     * @param array<string, mixed> $link
     * @return list<array{id: int, author: string, text: string, at: string}>
     */
    public function channelMessages(array $link, string $key, int $limit = self::CHAT_LIMIT): array
    {
        try {
            $rows = $this->atak()->getChatMessages((int) $link['tenant_id'], (int) $link['map_id'], $limit * 2, $key);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $body = (string) ($r['body'] ?? '');
            if (MpMessageParser::parse($body) !== null || TacticalAlertParser::isHiddenSystemChatBody($body)) {
                continue;
            }
            $out[] = self::chatLine($r, $body);
        }

        return array_slice($out, -$limit);
    }

    /**
     * Messages privés (cTab) dont ce joueur est l'auteur ou le destinataire, anciens d'abord.
     *
     * @param array<string, mixed> $link
     * @return list<array{id: int, author: string, to: string, text: string, at: string, mine: bool}>
     */
    public function privateMessages(array $link, int $limit = self::CHAT_LIMIT): array
    {
        $names = $this->myNames($link);
        if ($names === []) {
            return [];
        }
        try {
            $rows = $this->atak()->getChatMessages((int) $link['tenant_id'], (int) $link['map_id'], self::PRIVATE_SCAN);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $mp = MpMessageParser::parse((string) ($r['body'] ?? ''));
            if ($mp === null) {
                continue;
            }
            $mine = false;
            $concerned = false;
            foreach ($names as $n) {
                if (MpMessageParser::concernsCallSign($mp, $n)) {
                    $concerned = true;
                    $mine = mb_strtoupper(trim($mp['from'])) === mb_strtoupper($n);
                    break;
                }
            }
            if (!$concerned) {
                continue;
            }
            $out[] = ['id' => (int) ($r['id'] ?? 0), 'author' => $mp['from'], 'to' => $mp['to'], 'text' => mb_substr($mp['text'], 0, 1000), 'at' => (string) ($r['created_at'] ?? ''), 'mine' => $mine];
        }

        return array_slice($out, -$limit);
    }

    /**
     * Fiche courte d'une ligne de compte rendu : jamais d'adresse e-mail, ni d'identifiant Steam.
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    public static function card(array $r): array
    {
        $prio = strtoupper((string) ($r['priority'] ?? 'ROUTINE'));

        return [
            'id' => (int) ($r['id'] ?? 0),
            'type' => self::typeLabel((string) ($r['report_type'] ?? 'OTHER')),
            'priority' => self::PRIORITY_LABELS[$prio] ?? 'Routine',
            'urgent' => in_array($prio, ['IMMEDIATE', 'FLASH'], true),
            'by' => mb_substr(trim((string) ($r['submitter_callsign'] ?? '')), 0, 60),
            'grid' => mb_substr(trim((string) ($r['grid_reference'] ?? '')), 0, 20),
            'summary' => mb_substr(trim((string) ($r['summary'] ?? '')), 0, 300),
            'status' => self::STATUS_LABELS[strtoupper((string) ($r['status'] ?? ''))] ?? '',
            'at' => (string) ($r['report_timestamp'] ?? ''),
        ];
    }

    private const PRIORITY_LABELS = ['ROUTINE' => 'Routine', 'PRIORITY' => 'Prioritaire', 'IMMEDIATE' => 'Immédiat', 'FLASH' => 'Flash'];

    private const STATUS_LABELS = [
        'DRAFT' => 'Brouillon',
        'SUBMITTED' => 'Reçu par Athena',
        'ACKNOWLEDGED' => 'Lu par le poste de commandement',
        'ACTIONED' => 'Traité',
        'ARCHIVED' => 'Archivé',
    ];

    private static function typeLabel(string $type): string
    {
        return match (strtoupper($type)) {
            'CONTACT' => 'Contact',
            'SALUTE' => 'SALUTE',
            'SPOTREP' => 'Observation',
            'SITREP' => 'Situation',
            default => 'Compte rendu',
        };
    }

    /**
     * Lignes « libellé : valeur » du détail structuré (scalaires seulement, bornées).
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function lines(mixed $data): array
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $out = [];
        foreach (is_array($data) ? $data : [] as $k => $v) {
            if (!is_scalar($v) || trim((string) $v) === '' || count($out) >= 30) {
                continue;
            }
            $label = ucfirst(str_replace('_', ' ', (string) $k));
            $out[] = [mb_substr($label, 0, 40), mb_substr(trim((string) $v), 0, 400)];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $r
     * @return array{id: int, author: string, text: string, at: string}
     */
    private static function chatLine(array $r, string $body): array
    {
        // Ancien format radio « [HH:MM:SS][CANAL][DE][À] texte » : on garde le texte.
        $text = (string) preg_replace('/^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*/u', '', trim($body));

        return ['id' => (int) ($r['id'] ?? 0), 'author' => mb_substr((string) ($r['author'] ?? ''), 0, 60), 'text' => mb_substr($text, 0, 1000), 'at' => (string) ($r['created_at'] ?? '')];
    }

    /** @return list<string> noms sous lesquels le joueur écrit ou reçoit des messages privés */
    private function myNames(array $link): array
    {
        $names = [trim((string) ($link['player_name'] ?? ''))];
        try {
            foreach ((new MemberNameService($this->db()))->identitiesFor((int) $link['tenant_id'], [(int) $link['user_id']]) as $identity) {
                $names[] = trim((string) ($identity['callsign'] ?? ''));
                $names[] = trim((string) ($identity['display_name'] ?? ''));
            }
        } catch (\Throwable) {
        }

        return array_values(array_unique(array_filter($names, static fn (string $n): bool => $n !== '')));
    }

    /**
     * Destinataires de comptes rendus : le joueur, ses rôles, ses unités (mêmes identités que l'ATAK en jeu).
     *
     * @return list<array{type: string, identifier: string}>
     */
    private function recipients(array $link): array
    {
        $tenantId = (int) $link['tenant_id'];
        $userId = (int) $link['user_id'];
        $out = [['type' => 'USER', 'identifier' => (string) $userId]];
        try {
            $roleIds = (new \App\Repositories\UserRepository($this->db()))->listOrganizationRoleIdsForUser($userId);
            foreach ((new \App\Repositories\RoleRepository())->allForTenant($tenantId) as $role) {
                if (!in_array((int) ($role['id'] ?? 0), $roleIds, true)) {
                    continue;
                }
                foreach (['id', 'slug', 'name'] as $f) {
                    $v = trim((string) ($role[$f] ?? ''));
                    if ($v !== '') {
                        $out[] = ['type' => 'ROLE', 'identifier' => $v];
                    }
                }
            }
            $units = new \App\Repositories\UnitRepository();
            foreach ($units->unitIdsForUser($tenantId, $userId) as $unitId) {
                $unit = $units->findById($unitId, $tenantId);
                foreach (['id', 'slug', 'code', 'name'] as $f) {
                    $v = trim((string) ($unit[$f] ?? ''));
                    if ($v !== '') {
                        $out[] = ['type' => 'UNIT', 'identifier' => $v];
                    }
                }
            }
        } catch (\Throwable) {
        }
        $unique = [];
        foreach ($out as $r) {
            $unique[$r['type'] . ':' . $r['identifier']] = $r;
        }

        return array_values($unique);
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function atak(): AtakDataRepository
    {
        return $this->atak ??= new AtakDataRepository();
    }

    private function routing(): AtakReportRoutingRepository
    {
        return $this->routing ??= new AtakReportRoutingRepository();
    }
}
