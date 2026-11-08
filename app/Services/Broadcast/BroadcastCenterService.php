<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Repositories\AtakAdminControlRepository;
use App\Repositories\AtakOrderRepository;
use App\Repositories\FireTeamRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantAlertRepository;
use App\Repositories\TenantEmailRecipientGroupRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserAlertDismissalRepository;
use App\Repositories\UserRepository;
use App\Services\Atak\AtakPlayerMessageSender;
use App\Services\Audit\AuditService;
use App\Services\Integrations\DiscordEventRelayService;
use App\Services\Tactical\AtakCommunityBriefService;
use App\Support\AtakCommunityBrief;
use App\Support\BroadcastCenter as BC;
use App\Support\DiscordWebhookCatalog;
use App\Support\TenantAlertFeatures;

/**
 * Centre de diffusion du back-office : une liste et un formulaire pour tout ce que la communauté diffuse.
 *
 * Aucune donnée n'est recopiée : la liste est lue à chaque affichage dans les tables de chaque canal (voir
 * App\Support\BroadcastCenter), et chaque envoi écrit là où la page historique du canal écrit déjà. Les consignes,
 * ordres et tips du téléphone restent ainsi ceux de /back-office/ressources/atak-consignes, et le format lu par le mod
 * (GET /api/atak/community-brief, GET /api/atak/orders, messages du parc ATAK) ne change pas.
 *
 * Toutes les méthodes reçoivent la communauté de la session : rien n'est lu ni écrit ailleurs.
 */
final class BroadcastCenterService
{
    /** Ordres web et messages en jeu affichés : ceux des 60 derniers jours. */
    public const HISTORY_DAYS = 60;

    /** @var (callable(int, int, string, string): array{target: string, target_ref: string, target_label: string, error: ?string})|null */
    private $orderTarget;

    public function __construct(
        private TenantAlertRepository $alerts,
        private UserAlertDismissalRepository $dismissals,
        private AtakCommunityBriefService $brief,
        private AtakAdminControlRepository $fleet,
        private AtakOrderRepository $orders,
        private DiscordEventRelayService $discord,
        private UnitRepository $units,
        private UserRepository $users,
        private RoleRepository $roles,
        private TenantEmailRecipientGroupRepository $groups,
        private FireTeamRepository $fireTeams,
        private AuditService $audit,
        ?callable $orderTarget = null,
    ) {
        $this->orderTarget = $orderTarget;
    }

    // ── Formulaire ────────────────────────────────────────────────────────

    /**
     * Ce que le formulaire propose : cibles de la communauté et canaux réellement en état de marche.
     *
     * @return array<string, mixed>
     */
    public function options(int $tenantId): array
    {
        $safe = static function (callable $f, mixed $fallback): mixed {
            try {
                return $f();
            } catch (\Throwable $e) {
                error_log('[broadcast-center] options: ' . $e->getMessage());

                return $fallback;
            }
        };
        $members = [];
        foreach ($safe(fn (): array => $this->users->allForTenant($tenantId), []) as $u) {
            if ((string) ($u['status'] ?? 'active') !== 'active') {
                continue;
            }
            $members[] = ['id' => (int) $u['id'], 'label' => self::personLabel($u)];
        }
        usort($members, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));
        $units = array_map(
            static fn (array $u): array => ['id' => (int) $u['id'], 'label' => (string) $u['name']],
            array_values(array_filter($safe(fn (): array => $this->units->listFlatForStructure($tenantId), []), static fn (array $u): bool => (int) ($u['id'] ?? 0) > 0))
        );
        $roles = [];
        foreach ($safe(fn (): array => $this->roles->forTenantOrganization($tenantId), []) as $r) {
            $slug = (string) ($r['slug'] ?? '');
            if ($slug !== '') {
                $roles[] = ['id' => $slug, 'label' => (string) ($r['name'] ?? $slug)];
            }
        }
        $groups = array_map(
            static fn (array $g): array => ['id' => (int) $g['id'], 'label' => (string) $g['name']],
            $safe(fn (): array => $this->groups->listForTenant($tenantId), [])
        );
        $teams = [];
        foreach ($safe(fn (): array => $this->fireTeams->tablesReady() ? $this->fireTeams->listForTenant($tenantId) : [], []) as $t) {
            $teams[] = ['id' => (int) $t['id'], 'label' => trim((string) ($t['label'] ?? '')) . ' (' . (int) ($t['member_count'] ?? 0) . ')'];
        }

        return [
            'members' => $members,
            'units' => $units,
            'roles' => $roles,
            'groups' => $groups,
            'fire_teams' => $teams,
            'ready' => [
                'site' => true,
                'phone' => $safe(fn (): bool => $this->brief->ready(), false),
                'game' => $safe(fn (): bool => $this->fleet->ready(), false),
                'tasks' => $safe(fn (): bool => $this->orders->tablesReady(), false),
                'discord' => $safe(fn (): bool => $this->discord->resolveUrl($tenantId, DiscordWebhookCatalog::KEY_ANNOUNCEMENTS) !== null, false),
            ],
        ];
    }

    /**
     * Ce que chaque cible recevra, et ce qui empêcherait l'envoi (aucun joueur, téléphone plein…). Rien n'est écrit.
     *
     * @param array<string, mixed> $data BroadcastCenter::validate()['data']
     * @return array{errors: list<string>, channels: array<string, array<string, mixed>>}
     */
    public function plan(int $tenantId, array $data): array
    {
        $errors = [];
        $out = [];
        $options = $this->options($tenantId);
        foreach ($data['channels'] as $channel) {
            if (empty($options['ready'][$channel])) {
                $errors[] = match ($channel) {
                    'discord' => 'Discord n’est pas relié : choisissez un salon pour les annonces dans Intégrations externes, ou décochez Discord.',
                    default => sprintf('« %s » n’est pas disponible : la base de données n’est pas à jour. Contactez le support plateforme.', BC::CHANNELS[$channel]),
                };
                continue;
            }
            try {
                $out[$channel] = match ($channel) {
                    'site' => $this->planSite($data),
                    'phone' => $this->planPhone($tenantId, $data),
                    'game' => $this->planGame($tenantId, $data),
                    'tasks' => $this->planTasks($tenantId, $data),
                    'discord' => ['audience' => 'Salon « Annonces » du Discord de la communauté', 'text' => self::discordText($data['title'], $data['body'])],
                };
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            } catch (\Throwable $e) {
                error_log('[broadcast-center] plan ' . $channel . ': ' . $e->getMessage());
                $errors[] = sprintf('« %s » : impossible de préparer l’envoi pour le moment. Réessayez dans un instant.', BC::CHANNELS[$channel]);
            }
        }

        return ['errors' => $errors, 'channels' => $out];
    }

    /** @return array<string, mixed> */
    private function planSite(array $data): array
    {
        $state = $data['starts_at'] !== null && $data['starts_at'] > time() + 60 ? 'scheduled' : 'active';

        return [
            'audience' => 'Tous les membres connectés au portail',
            'state' => $state,
            'kind' => BC::siteKind($data['type'], $data['importance']),
        ];
    }

    /** @return array<string, mixed> */
    private function planPhone(int $tenantId, array $data): array
    {
        $state = $this->brief->state($tenantId);
        if (count($state['notes']) >= AtakCommunityBrief::MAX_NOTES) {
            throw new \DomainException(sprintf('App Communauté : plafond de %d notes atteint. Retirez-en une avant d’en ajouter.', AtakCommunityBrief::MAX_NOTES));
        }
        $notes = array_merge($state['notes'], [$data['note']]);
        $fit = AtakCommunityBrief::phoneFit($notes, $state['kit']);
        if (!$fit['fits']) {
            throw new \DomainException(sprintf('App Communauté : le téléphone reçoit %d octets au plus et cette note dépasserait ce volume. Raccourcissez-la ou retirez une note.', AtakCommunityBrief::MAX_PHONE_BYTES));
        }

        return ['audience' => 'Tous les joueurs, onglet ' . mb_strtoupper(BC::TYPES[$data['type']]) . ' de l’app Communauté', 'note' => $data['note'], 'bytes' => $fit['bytes']];
    }

    /** @return array<string, mixed> */
    private function planGame(int $tenantId, array $data): array
    {
        $userIds = $this->resolveUserIds($tenantId, $data['target'], $data['refs']);
        $players = $this->players($tenantId, $userIds);
        if ($players === []) {
            throw new \DomainException($userIds === []
                ? 'Message en jeu : la cible choisie ne compte aucun membre actif.'
                : 'Message en jeu : aucune personne visée n’a encore d’ATAK connu. Il faut qu’elle soit entrée en partie avec le mod.');
        }
        $count = count(array_unique(array_map([AtakPlayerMessageSender::class, 'playerKey'], $players)));
        $audience = $count . ' joueur(s) avec un ATAK connu';
        if ($userIds !== null) {
            $audience .= ' sur ' . count($userIds) . ' personne(s) visée(s)';
        }

        return [
            'audience' => $audience,
            'players' => $players,
            'count' => $count,
            'level' => BC::gameLevel($data['importance']),
            'expires_at' => gmdate('Y-m-d H:i:s', (int) $data['game_expires_at']),
            'expires_local' => date('d/m/Y H:i', (int) $data['game_expires_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function planTasks(int $tenantId, array $data): array
    {
        $resolve = $this->orderTarget;
        if ($resolve === null) {
            throw new \DomainException('App Tâches : les destinataires d’un ordre ne peuvent pas être résolus pour le moment.');
        }
        $targets = match ($data['target']) {
            'all' => [['all', '']],
            'fire_team' => [['fire_team', (string) $data['refs'][0]]],
            default => array_map(static fn (int|string $id): array => ['user', (string) $id], $data['refs']),
        };
        $resolved = [];
        foreach ($targets as [$type, $ref]) {
            if ($type === 'user' && !$this->isMember($tenantId, (int) $ref)) {
                throw new \DomainException('App Tâches : une personne choisie n’est pas membre de la communauté.');
            }
            $r = $resolve($tenantId, (int) $data['map_id'], $type, $ref);
            if (($r['error'] ?? null) !== null) {
                throw new \DomainException('App Tâches : ' . $r['error']);
            }
            $resolved[] = ['target_type' => $type] + $r;
        }

        return [
            'audience' => implode(', ', array_map(static fn (array $r): string => (string) $r['target_label'], $resolved)) . ' · carte de l’opération n° ' . (int) $data['map_id'],
            'targets' => $resolved,
            'order_type' => BC::orderType($data['type'], $data['importance']),
            'priority' => BC::orderPriority($data['importance']),
        ];
    }

    // ── Envoi ─────────────────────────────────────────────────────────────

    /**
     * Diffuse sur chaque canal. Tout est vérifié avant la première écriture ; si un canal échoue malgré tout,
     * les autres restent envoyés et le résultat le dit canal par canal (jamais un succès d'ensemble).
     *
     * @param array<string, mixed> $data BroadcastCenter::validate()['data']
     * @param array{id: ?int, label: string} $actor
     * @return array{ok: bool, errors: list<string>, results: list<array{channel: string, ok: bool, message: string}>, key: string}
     */
    public function send(int $tenantId, array $data, array $actor): array
    {
        $plan = $this->plan($tenantId, $data);
        if ($plan['errors'] !== []) {
            return ['ok' => false, 'errors' => $plan['errors'], 'results' => [], 'key' => ''];
        }
        $key = BC::newKey($data['type'], in_array('discord', $data['channels'], true));
        $results = [];
        foreach ($data['channels'] as $channel) {
            try {
                $message = match ($channel) {
                    'site' => $this->sendSite($tenantId, $data, $key),
                    'phone' => $this->sendPhone($tenantId, $data),
                    'game' => $this->sendGame($plan['channels']['game'], $data, $key, $actor),
                    'tasks' => $this->sendTasks($tenantId, $plan['channels']['tasks'], $data, $key, $actor),
                    'discord' => $this->sendDiscord($tenantId, $data),
                };
                $results[] = ['channel' => $channel, 'ok' => true, 'message' => $message];
            } catch (\DomainException $e) {
                $results[] = ['channel' => $channel, 'ok' => false, 'message' => $e->getMessage()];
            } catch (\Throwable $e) {
                error_log('[broadcast-center] send ' . $channel . ': ' . $e->getMessage());
                $results[] = ['channel' => $channel, 'ok' => false, 'message' => 'Échec de l’envoi. Réessayez dans un instant.'];
            }
        }
        $this->log('broadcast_center.send', $tenantId, $actor, [
            'key' => $key,
            'type' => $data['type'],
            'importance' => $data['importance'],
            'title' => $data['title'],
            'target' => $data['target'],
            'refs' => $data['refs'],
            'channels' => array_map(static fn (array $r): string => $r['channel'] . ($r['ok'] ? '' : ' (échec)'), $results),
        ]);
        $ok = array_filter($results, static fn (array $r): bool => !$r['ok']) === [];

        return ['ok' => $ok, 'errors' => [], 'results' => $results, 'key' => $key];
    }

    private function sendSite(int $tenantId, array $data, string $key): string
    {
        $features = TenantAlertFeatures::resolve(null);
        $features[TenantAlertFeatures::HIGHLIGHT] = $data['importance'] === 'important';
        $features[TenantAlertFeatures::NOTIFY_DISCORD] = in_array('discord', $data['channels'], true);
        $id = $this->alerts->insert($tenantId, [
            'kind' => BC::siteKind($data['type'], $data['importance']),
            'display_style' => \App\Support\AlertDisplayStyle::CLASSIC,
            'title' => $data['title'],
            'body' => $data['body'] === '' ? null : $data['body'],
            'starts_at' => $data['starts_at'] !== null ? date('Y-m-d H:i:s', (int) $data['starts_at']) : null,
            'ends_at' => $data['ends_at'] !== null ? date('Y-m-d H:i:s', (int) $data['ends_at']) : null,
            'sort_order' => $features[TenantAlertFeatures::HIGHLIGHT] ? -1 : 0,
            'is_active' => true,
            'features_json' => TenantAlertFeatures::encodeJson($features),
        ]);
        if ($id < 1) {
            throw new \DomainException('Le bandeau n’a pas été enregistré.');
        }
        $this->alerts->setBroadcastKey($id, $tenantId, $key);

        return $data['starts_at'] !== null && $data['starts_at'] > time() + 60
            ? 'Bandeau programmé pour le ' . date('d/m/Y H:i', (int) $data['starts_at']) . '.'
            : 'Bandeau publié sur le portail.';
    }

    private function sendPhone(int $tenantId, array $data): string
    {
        $state = $this->brief->state($tenantId);
        $note = $data['note'];
        $result = $this->brief->change($tenantId, AtakCommunityBriefService::NOTES, $state['notes_rev'], static fn (array $notes): array => AtakCommunityBriefService::put(
            $notes,
            null,
            $note,
            AtakCommunityBrief::MAX_NOTES,
            sprintf('Plafond atteint : %d notes au plus. Retirez-en une avant d’en ajouter.', AtakCommunityBrief::MAX_NOTES)
        ));
        if (!$result['ok']) {
            throw new \DomainException('App Communauté : ' . $result['message']);
        }

        return 'Note ajoutée à l’app Communauté (relue par les téléphones toutes les 5 minutes environ).';
    }

    /** @param array<string, mixed> $plan */
    private function sendGame(array $plan, array $data, string $key, array $actor): string
    {
        $done = (new AtakPlayerMessageSender($this->fleet))->send($plan['players'], [
            'title' => $data['title'],
            'body' => $data['body'],
            'level' => $plan['level'],
            'require_ack' => (bool) $data['require_ack'],
            'expires_at' => $plan['expires_at'],
            'broadcast_key' => $key,
        ], $actor['id'], $actor['label']);
        if ($done < 1) {
            throw new \DomainException('Aucun message en jeu n’a été enregistré.');
        }

        return $done . ' message(s) en jeu enregistré(s) : chaque joueur le verra sur son ATAK à sa prochaine présence en jeu.';
    }

    /** @param array<string, mixed> $plan */
    private function sendTasks(int $tenantId, array $plan, array $data, string $key, array $actor): string
    {
        $done = 0;
        foreach ($plan['targets'] as $i => $target) {
            $row = $this->orders->upsertByExternalId($tenantId, (int) $data['map_id'], [
                'external_id' => BC::orderExternalId($key, $i),
                'order_type' => $plan['order_type'],
                'type_label' => $data['title'],
                'target' => $target['target'],
                'target_type' => $target['target_type'],
                'target_ref' => $target['target_ref'],
                'target_label' => $target['target_label'],
                'payload' => $data['body'],
                'priority' => $plan['priority'],
                'issuer' => $actor['label'] !== '' ? $actor['label'] : 'Poste',
                'issuer_user_id' => $actor['id'],
                'status' => 'PENDING',
                'source' => 'web',
                'radio_sim' => $plan['order_type'] === 'CUSTOM',
            ]);
            if ($row !== null) {
                ++$done;
            }
        }
        if ($done < 1) {
            throw new \DomainException('App Tâches : l’ordre n’a pas été enregistré.');
        }

        return $done . ' ordre(s) envoyé(s) à l’app Tâches, suivi(s) ici : reçu, accepté, en cours, exécuté.';
    }

    private function sendDiscord(int $tenantId, array $data): string
    {
        $r = $this->discord->notify($tenantId, DiscordWebhookCatalog::KEY_ANNOUNCEMENTS, self::discordText($data['title'], $data['body']));
        if (!empty($r['skipped'])) {
            throw new \DomainException('Discord : aucun salon réglé pour les annonces, rien n’a été publié.');
        }
        if (empty($r['ok'])) {
            throw new \DomainException('Discord : publication refusée' . (($r['error'] ?? '') !== '' ? ' (' . $r['error'] . ')' : '') . '.');
        }

        return 'Publié sur Discord.';
    }

    public static function discordText(string $title, string $body): string
    {
        $lines = ['📣 **' . $title . '**'];
        if (trim($body) !== '') {
            $lines[] = trim($body);
        }

        return implode("\n", $lines);
    }

    // ── Liste ─────────────────────────────────────────────────────────────

    /**
     * Tout ce que la communauté diffuse, d'après les tables de chaque canal. Une diffusion du centre envoyée sur
     * plusieurs canaux forme une seule ligne.
     *
     * @return array{items: list<array<string, mixed>>, notes_rev: string, phone_ready: bool}
     */
    public function items(int $tenantId): array
    {
        $now = time();
        $items = [];
        $notesRev = '';
        $phoneReady = false;

        try {
            $alerts = $this->alerts->allForTenantOrdered($tenantId);
            $counts = $this->dismissals->countsForTenantAlerts(array_map(static fn (array $a): int => (int) $a['id'], $alerts));
            foreach ($alerts as $a) {
                $items[] = $this->siteItem($a, $counts[(int) $a['id']] ?? 0, $now);
            }
        } catch (\Throwable $e) {
            error_log('[broadcast-center] site: ' . $e->getMessage());
        }

        try {
            if ($this->brief->ready()) {
                $phoneReady = true;
                $state = $this->brief->state($tenantId);
                $notesRev = $state['notes_rev'];
                $updated = strtotime((string) $state['updated_at']) ?: 0;
                foreach ($state['notes'] as $i => $n) {
                    $items[] = [
                        'ref' => 'note:' . $i,
                        'key' => null,
                        'type' => $n['kind'],
                        'title' => $n['title'] !== '' ? $n['title'] : mb_substr($n['body'], 0, 60),
                        'body' => $n['body'],
                        'importance' => null,
                        'channels' => ['phone'],
                        'state' => 'active',
                        'ts' => $updated,
                        'starts' => null,
                        'ends' => null,
                        'target' => BC::TARGETS['all'],
                        'author' => '',
                        'receipts' => [],
                        'tracking' => [],
                        'parts' => ['note' => ['index' => $i, 'kind' => $n['kind'], 'title' => $n['title'], 'body' => $n['body']]],
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('[broadcast-center] phone: ' . $e->getMessage());
        }

        try {
            foreach (self::groupMessages($this->fleet->broadcastMessagesForTenant($tenantId, self::HISTORY_DAYS)) as $group) {
                $items[] = $this->gameItem($group, $now);
            }
        } catch (\Throwable $e) {
            error_log('[broadcast-center] game: ' . $e->getMessage());
        }

        try {
            $orders = $this->orders->listWebForTenant($tenantId, self::HISTORY_DAYS);
            $responses = $this->orders->responsesForOrders($tenantId, array_map(static fn (array $o): int => (int) $o['id'], $orders));
            foreach ($orders as $o) {
                $items[] = $this->orderItem($o, $responses[(int) $o['id']] ?? []);
            }
        } catch (\Throwable $e) {
            error_log('[broadcast-center] tasks: ' . $e->getMessage());
        }

        return ['items' => self::merge($items), 'notes_rev' => $notesRev, 'phone_ready' => $phoneReady];
    }

    /** @return array<string, mixed> */
    private function siteItem(array $a, int $closedBy, int $now): array
    {
        $features = TenantAlertFeatures::resolve(TenantAlertFeatures::decodeJson($a['features_json'] ?? null));
        $key = BC::parseKey((string) ($a['broadcast_key'] ?? '')) !== null ? (string) $a['broadcast_key'] : null;
        $parsed = BC::parseKey($key);
        $kind = (string) ($a['kind'] ?? 'info');
        $channels = ['site'];
        if (!empty($features[TenantAlertFeatures::NOTIFY_DISCORD])) {
            $channels[] = 'discord';
        }
        $receipts = [];
        if (!empty($features[TenantAlertFeatures::DISMISSIBLE])) {
            $receipts[] = 'Fermée par ' . $closedBy . ' membre(s)';
        }

        return [
            'ref' => 'site:' . (int) $a['id'],
            'key' => $key,
            'type' => $parsed['type'] ?? ($kind === 'notice' ? 'consigne' : 'annonce'),
            'title' => (string) ($a['title'] ?? ''),
            'body' => (string) ($a['body'] ?? ''),
            'importance' => BC::siteImportance($kind, $features),
            'channels' => $channels,
            'state' => BC::siteState($a, $now),
            'ts' => strtotime((string) ($a['created_at'] ?? '')) ?: 0,
            'starts' => !empty($a['starts_at']) ? strtotime((string) $a['starts_at']) : null,
            'ends' => !empty($a['ends_at']) ? strtotime((string) $a['ends_at']) : null,
            'target' => BC::TARGETS['all'],
            'author' => '',
            'receipts' => $receipts,
            'tracking' => [],
            'parts' => ['site' => [
                'id' => (int) $a['id'],
                'title' => (string) ($a['title'] ?? ''),
                'body' => (string) ($a['body'] ?? ''),
                'starts_at' => (string) ($a['starts_at'] ?? ''),
                'ends_at' => (string) ($a['ends_at'] ?? ''),
                'active' => !empty($a['is_active']),
            ]],
        ];
    }

    /**
     * Messages en jeu regroupés par envoi : par clé du centre, sinon (envois du parc ATAK) même auteur, même texte,
     * même niveau, créés à moins de deux minutes d'écart.
     *
     * @param list<array<string, mixed>> $rows récents d'abord
     * @return list<list<array<string, mixed>>>
     */
    public static function groupMessages(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);
        $groups = [];
        $open = [];
        foreach ($rows as $r) {
            $key = (string) ($r['broadcast_key'] ?? '');
            if (BC::isKey($key)) {
                $groups['k:' . $key][] = $r;
                continue;
            }
            $sig = sha1((int) ($r['created_by_user_id'] ?? 0) . '|' . $r['title'] . '|' . $r['body'] . '|' . $r['level']);
            $t = strtotime((string) $r['created_at'] . ' UTC') ?: 0;
            if (isset($open[$sig]) && $t - $open[$sig]['t'] <= 120) {
                $groups[$open[$sig]['g']][] = $r;
                continue;
            }
            $g = 'm:' . (int) $r['id'];
            $open[$sig] = ['g' => $g, 't' => $t];
            $groups[$g] = [$r];
        }

        return array_values($groups);
    }

    /** @param list<array<string, mixed>> $rows */
    private function gameItem(array $rows, int $now): array
    {
        $first = $rows[0];
        $key = BC::isKey((string) ($first['broadcast_key'] ?? '')) ? (string) $first['broadcast_key'] : null;
        $parsed = BC::parseKey($key);
        $delivered = 0;
        $acked = 0;
        $cancelled = 0;
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int) $r['id'];
            $delivered += !empty($r['delivered_at']) ? 1 : 0;
            $acked += !empty($r['acked_at']) ? 1 : 0;
            $cancelled += !empty($r['cancelled_at']) ? 1 : 0;
        }
        $n = count($rows);
        $receipts = [$n . ' joueur(s)', $delivered . ' affiché(s)'];
        if (!empty($first['require_ack'])) {
            $receipts[] = $acked . ' validé(s)';
        }
        if ($cancelled > 0) {
            $receipts[] = $cancelled . ' retiré(s)';
        }
        $author = trim((string) ($first['actor_callsign'] ?? '')) ?: trim((string) ($first['actor_name'] ?? ''));

        return [
            'ref' => $key !== null ? 'bc:' . $key : 'msgs:' . (int) $first['id'],
            'key' => $key,
            'type' => $parsed['type'] ?? 'message',
            'title' => (string) $first['title'],
            'body' => (string) $first['body'],
            'importance' => BC::importanceFromLevel((string) $first['level']),
            'channels' => ['game'],
            'state' => BC::gameState($rows, $now),
            'ts' => strtotime((string) $first['created_at'] . ' UTC') ?: 0,
            'starts' => null,
            'ends' => !empty($first['expires_at']) ? (strtotime((string) $first['expires_at'] . ' UTC') ?: null) : null,
            'target' => $n . ' joueur(s)',
            'author' => $author,
            'receipts' => $receipts,
            'tracking' => [],
            'parts' => ['game' => ['ids' => $ids, 'pending' => $n - $acked - $cancelled]],
        ];
    }

    /** @param list<array{by: string, status: string, note: string, at: string}> $responses */
    private function orderItem(array $o, array $responses): array
    {
        $ext = (string) ($o['external_id'] ?? '');
        $key = BC::keyFromOrderExternalId($ext);
        $parsed = BC::parseKey($key);
        $status = strtoupper((string) ($o['status'] ?? 'PENDING'));
        $byStep = [];
        foreach ($responses as $r) {
            $label = BC::orderStepLabel($r['status']);
            $byStep[$label] = ($byStep[$label] ?? 0) + 1;
        }
        $tracking = ['État : ' . BC::orderStepLabel($status)];
        foreach ($byStep as $label => $count) {
            $tracking[] = $count . ' ' . mb_strtolower($label);
        }
        $title = trim((string) ($o['type_label'] ?? ''));

        return [
            'ref' => 'order:' . (int) $o['id'],
            'key' => $key,
            'type' => $parsed['type'] ?? BC::typeFromOrder((string) ($o['order_type'] ?? '')),
            'title' => $title !== '' ? $title : 'Ordre ' . (string) ($o['order_type'] ?? ''),
            'body' => (string) ($o['payload'] ?? ''),
            'importance' => BC::importanceFromPriority((string) ($o['priority'] ?? '')),
            'channels' => ['tasks'],
            'state' => BC::orderState($status),
            'ts' => strtotime((string) ($o['created_at'] ?? '')) ?: 0,
            'starts' => null,
            'ends' => null,
            'target' => trim((string) ($o['target_label'] ?? '')) ?: BC::TARGETS['all'],
            'author' => (string) ($o['issuer'] ?? ''),
            'receipts' => [],
            'tracking' => $tracking,
            'parts' => ['orders' => [['id' => (int) $o['id'], 'map' => (int) ($o['map_id'] ?? 1), 'ext' => $ext, 'status' => $status]]],
        ];
    }

    /**
     * Une ligne par diffusion : les morceaux qui portent la même clé sont réunis.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function merge(array $items): array
    {
        $out = [];
        $byKey = [];
        foreach ($items as $item) {
            $key = $item['key'] ?? null;
            if ($key === null) {
                $out[] = $item;
                continue;
            }
            if (!isset($byKey[$key])) {
                $item['ref'] = 'bc:' . $key;
                $item['states'] = [$item['state']];
                if ((BC::parseKey($key)['discord'] ?? false) && !in_array('discord', $item['channels'], true)) {
                    $item['channels'][] = 'discord';
                }
                $byKey[$key] = count($out);
                $out[] = $item;
                continue;
            }
            $m = &$out[$byKey[$key]];
            $m['channels'] = array_values(array_unique(array_merge($m['channels'], $item['channels'])));
            $m['states'][] = $item['state'];
            $m['state'] = BC::mergeState($m['states']);
            $m['receipts'] = array_merge($m['receipts'], $item['receipts']);
            $m['tracking'] = array_merge($m['tracking'], $item['tracking']);
            $m['ts'] = min((int) $m['ts'], (int) $item['ts']) ?: max((int) $m['ts'], (int) $item['ts']);
            $m['ends'] ??= $item['ends'];
            $m['starts'] ??= $item['starts'];
            $m['author'] = $m['author'] !== '' ? $m['author'] : $item['author'];
            if (($m['target'] ?? '') === BC::TARGETS['all'] && $item['target'] !== BC::TARGETS['all']) {
                $m['target'] = $item['target'];
            }
            foreach ($item['parts'] as $part => $value) {
                if ($part === 'orders' && isset($m['parts']['orders'])) {
                    $m['parts']['orders'] = array_merge($m['parts']['orders'], $value);
                } else {
                    $m['parts'][$part] ??= $value;
                }
            }
            unset($m);
        }
        foreach ($out as &$item) {
            unset($item['states']);
        }
        unset($item);

        return $out;
    }

    /**
     * Filtres et tri de la liste (sans base de données).
     *
     * @param list<array<string, mixed>> $items
     * @param array{type?: string, canal?: string, etat?: string, importance?: string, tri?: string} $f
     * @return list<array<string, mixed>>
     */
    public static function filter(array $items, array $f): array
    {
        $items = array_values(array_filter($items, static function (array $i) use ($f): bool {
            if (($f['type'] ?? '') !== '' && $i['type'] !== $f['type']) {
                return false;
            }
            if (($f['canal'] ?? '') !== '' && !in_array($f['canal'], $i['channels'], true)) {
                return false;
            }
            if (($f['etat'] ?? '') !== '' && $i['state'] !== $f['etat']) {
                return false;
            }

            return ($f['importance'] ?? '') === '' || $i['importance'] === $f['importance'];
        }));
        $live = static fn (array $i): int => in_array($i['state'], ['active', 'scheduled'], true) ? 0 : 1;
        usort($items, ($f['tri'] ?? '') === 'importance'
            ? static fn (array $a, array $b): int => [$live($a), BC::rank($a['importance']), -(int) $a['ts']] <=> [$live($b), BC::rank($b['importance']), -(int) $b['ts']]
            : static fn (array $a, array $b): int => [-(int) $a['ts'], $a['ref']] <=> [-(int) $b['ts'], $b['ref']]);

        return $items;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, string $ref): ?array
    {
        foreach ($this->items($tenantId)['items'] as $item) {
            if ($item['ref'] === $ref) {
                return $item;
            }
            // Un morceau demandé par son ancienne référence (site:12, order:4) se retrouve dans sa diffusion.
            if (str_starts_with($ref, 'site:') && (int) ($item['parts']['site']['id'] ?? 0) === (int) substr($ref, 5)) {
                return $item;
            }
            if (str_starts_with($ref, 'order:')) {
                foreach ($item['parts']['orders'] ?? [] as $o) {
                    if ($o['id'] === (int) substr($ref, 6)) {
                        return $item;
                    }
                }
            }
        }

        return null;
    }

    // ── Retrait et modification ───────────────────────────────────────────

    /**
     * Retire une diffusion sur tous ses canaux : bandeau désactivé (gardé), note retirée du téléphone, messages pas
     * encore validés annulés, ordres annulés. Discord ne se retire pas.
     *
     * @param list<string> $allowed canaux permis à la personne connectée
     * @param array{id: ?int, label: string} $actor
     * @return array{ok: bool, status: int, message: string, conflict?: bool}
     */
    public function withdraw(int $tenantId, string $ref, string $notesRev, array $allowed, array $actor): array
    {
        $item = $this->find($tenantId, $ref);
        if ($item === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Diffusion introuvable : elle a peut-être déjà été retirée. La liste a été actualisée.'];
        }
        $needed = array_values(array_diff(array_intersect(array_keys($item['parts']), ['site', 'note', 'game', 'orders']), []));
        $map = ['site' => 'site', 'note' => 'phone', 'game' => 'game', 'orders' => 'tasks'];
        foreach ($needed as $part) {
            if (!in_array($map[$part], $allowed, true)) {
                return ['ok' => false, 'status' => 403, 'message' => sprintf('Vous n’avez pas le droit de retirer ce qui est diffusé sur « %s ».', BC::CHANNELS[$map[$part]])];
            }
        }
        $done = [];
        if (isset($item['parts']['note'])) {
            $index = (int) $item['parts']['note']['index'];
            $r = $this->brief->change($tenantId, AtakCommunityBriefService::NOTES, $notesRev, static fn (array $notes): array => AtakCommunityBriefService::remove($notes, $index));
            if (!$r['ok']) {
                return $r;
            }
            $done[] = 'note retirée du téléphone';
        }
        if (isset($item['parts']['site'])) {
            $this->alerts->setActive((int) $item['parts']['site']['id'], $tenantId, false);
            $done[] = 'bandeau retiré du site';
        }
        if (isset($item['parts']['game'])) {
            $n = $this->fleet->cancelMessagesForTenant($tenantId, $item['parts']['game']['ids']);
            $done[] = $n . ' message(s) en jeu annulé(s)';
        }
        $failed = 0;
        foreach ($item['parts']['orders'] ?? [] as $o) {
            if (in_array($o['status'], ['DONE', 'FAILED', 'CANCELLED'], true)) {
                continue;
            }
            try {
                $this->orders->updateStatus($tenantId, $o['map'], $o['ext'], 'CANCELLED', $actor['label'], 'Retiré depuis le centre de diffusion');
                $done[] = 'ordre ' . $o['ext'] . ' annulé';
            } catch (\Throwable $e) {
                error_log('[broadcast-center] withdraw order: ' . $e->getMessage());
                ++$failed;
            }
        }
        $this->log('broadcast_center.withdraw', $tenantId, $actor, ['ref' => $ref, 'title' => $item['title'], 'done' => $done, 'failed_orders' => $failed]);
        if ($failed > 0) {
            return ['ok' => false, 'status' => 500, 'message' => 'Retrait incomplet : ' . $failed . ' ordre(s) n’ont pas pu être annulés. ' . ($done !== [] ? 'Fait : ' . implode(', ', $done) . '.' : '')];
        }
        $message = $done === [] ? 'Rien à retirer : tout était déjà terminé ou retiré.' : 'Retiré : ' . implode(', ', $done) . '.';
        if (in_array('discord', $item['channels'], true)) {
            $message .= ' Le message Discord reste dans le salon : supprimez-le depuis Discord si besoin.';
        }

        return ['ok' => true, 'status' => 200, 'message' => $message];
    }

    /**
     * Modifie ce qui peut encore l'être : le bandeau du site (titre, texte, importance, dates) et la note du téléphone
     * (genre, titre, texte). Un message déjà parti vers les joueurs ne se modifie pas : il se retire puis se duplique.
     *
     * @param array<string, mixed> $in
     * @param list<string> $allowed
     * @param array{id: ?int, label: string} $actor
     * @return array{ok: bool, status: int, message: string, conflict?: bool}
     */
    public function update(int $tenantId, string $ref, array $in, array $allowed, array $actor): array
    {
        $item = $this->find($tenantId, $ref);
        if ($item === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Diffusion introuvable : elle a peut-être été retirée. La liste a été actualisée.'];
        }
        if (isset($item['parts']['note'])) {
            if (!in_array('phone', $allowed, true)) {
                return ['ok' => false, 'status' => 403, 'message' => 'Vous n’avez pas le droit de modifier les notes du téléphone.'];
            }
            $index = (int) $item['parts']['note']['index'];
            try {
                $note = AtakCommunityBrief::validNote(self::s($in['kind'] ?? ''), self::s($in['title'] ?? ''), (string) ($in['body'] ?? ''));
            } catch (\DomainException $e) {
                return ['ok' => false, 'status' => 422, 'message' => $e->getMessage()];
            }
            $r = $this->brief->change($tenantId, AtakCommunityBriefService::NOTES, self::s($in['rev'] ?? ''), static fn (array $notes): array => AtakCommunityBriefService::put($notes, $index, $note, AtakCommunityBrief::MAX_NOTES, ''));
            if ($r['ok']) {
                $r['message'] = 'Note modifiée : les téléphones la reliront dans les 5 minutes environ.';
                $this->log('broadcast_center.update', $tenantId, $actor, ['ref' => $ref, 'note' => $note]);
            }

            return $r;
        }
        if (!isset($item['parts']['site'])) {
            return ['ok' => false, 'status' => 409, 'message' => 'Déjà parti vers les joueurs : ce message ne se modifie plus. Retirez-le, puis dupliquez-le pour envoyer la version corrigée.'];
        }
        if (!in_array('site', $allowed, true)) {
            return ['ok' => false, 'status' => 403, 'message' => 'Vous n’avez pas le droit de modifier les annonces du site.'];
        }
        $row = $this->alerts->findByIdForTenant((int) $item['parts']['site']['id'], $tenantId);
        if ($row === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Annonce introuvable.'];
        }
        $title = BC::oneLine((string) ($in['title'] ?? ''));
        $body = BC::text((string) ($in['body'] ?? ''));
        $importance = isset(BC::IMPORTANCE[self::s($in['importance'] ?? '')]) ? self::s($in['importance']) : 'info';
        if ($title === '' || mb_strlen($title) > BC::MAX_TITLE) {
            return ['ok' => false, 'status' => 422, 'message' => $title === '' ? 'Le titre est obligatoire.' : sprintf('Titre trop long : %d caractères au plus.', BC::MAX_TITLE)];
        }
        if (mb_strlen($body) > BC::MAX_BODY) {
            return ['ok' => false, 'status' => 422, 'message' => sprintf('Texte trop long : %d caractères au plus.', BC::MAX_BODY)];
        }
        $starts = self::localDate($in['starts_at'] ?? '');
        $ends = self::localDate($in['ends_at'] ?? '');
        if ($starts === false || $ends === false) {
            return ['ok' => false, 'status' => 422, 'message' => 'Date illisible : utilisez le sélecteur de date.'];
        }
        if ($starts !== null && $ends !== null && $ends <= $starts) {
            return ['ok' => false, 'status' => 422, 'message' => 'La fin doit être après le début.'];
        }
        $features = TenantAlertFeatures::resolve(TenantAlertFeatures::decodeJson($row['features_json'] ?? null));
        $features[TenantAlertFeatures::HIGHLIGHT] = $importance === 'important';
        $kind = (string) ($row['kind'] ?? 'info');
        if ($importance === 'urgent') {
            $kind = 'urgent';
        } elseif ($kind === 'urgent') {
            $kind = BC::siteKind($item['type'], $importance);
        }
        $sort = (int) ($row['sort_order'] ?? 0);
        if ($features[TenantAlertFeatures::HIGHLIGHT] && $sort >= 0) {
            $sort = -1;
        }
        $this->alerts->update((int) $row['id'], $tenantId, [
            'kind' => $kind,
            'title' => $title,
            'body' => $body === '' ? null : $body,
            'starts_at' => $starts !== null ? date('Y-m-d H:i:s', $starts) : null,
            'ends_at' => $ends !== null ? date('Y-m-d H:i:s', $ends) : null,
            'sort_order' => $sort,
            'features_json' => TenantAlertFeatures::encodeJson($features),
        ] + $row);
        $this->log('broadcast_center.update', $tenantId, $actor, ['ref' => $ref, 'title' => $title, 'importance' => $importance]);
        $message = 'Annonce du site modifiée.';
        if (array_diff($item['channels'], ['site', 'discord']) !== []) {
            $message .= ' Ce qui est déjà parti vers les joueurs ne change pas.';
        }

        return ['ok' => true, 'status' => 200, 'message' => $message];
    }

    // ── Cibles ────────────────────────────────────────────────────────────

    /**
     * Comptes visés dans la communauté, null pour toute la communauté.
     *
     * @param list<int|string> $refs
     * @return list<int>|null
     */
    public function resolveUserIds(int $tenantId, string $target, array $refs): ?array
    {
        $ids = [];
        switch ($target) {
            case 'all':
                return null;
            case 'unit':
                $known = array_column($this->units->listFlatForStructure($tenantId, true), 'id');
                $roots = array_values(array_intersect(array_map('intval', $refs), array_map('intval', $known)));
                $ids = $roots === [] ? [] : $this->units->listActiveUserIdsForUnits($tenantId, $this->units->expandUnitIdsWithDescendants($tenantId, $roots));
                break;
            case 'role':
                $ids = $this->users->listActiveUserIdsWithOrganizationRoleSlugs($tenantId, array_map('strval', $refs));
                break;
            case 'group':
                $group = $this->groups->findById((int) ($refs[0] ?? 0), $tenantId);
                if ($group === null) {
                    throw new \DomainException('Groupe de destinataires introuvable.');
                }
                $def = json_decode((string) ($group['definition_json'] ?? ''), true);
                $def = is_array($def) ? $def : [];
                if (!empty($def['all_members'])) {
                    return null;
                }
                $unitRefs = array_values(array_filter(array_map('intval', (array) ($def['unit_ids'] ?? []))));
                if ($unitRefs !== []) {
                    $scope = !array_key_exists('include_descendants', $def) || !empty($def['include_descendants'])
                        ? $this->units->expandUnitIdsWithDescendants($tenantId, $unitRefs) : $unitRefs;
                    $ids = array_merge($ids, $this->units->listActiveUserIdsForUnits($tenantId, $scope));
                }
                $roleRefs = array_values(array_filter(array_map('strval', (array) ($def['role_slugs'] ?? []))));
                if ($roleRefs !== []) {
                    $ids = array_merge($ids, $this->users->listActiveUserIdsWithOrganizationRoleSlugs($tenantId, $roleRefs));
                }
                $ids = array_merge($ids, array_map('intval', (array) ($def['extra_user_ids'] ?? [])));
                break;
            case 'fire_team':
                $team = $this->fireTeams->tablesReady() ? $this->fireTeams->findByIdForTenant((int) ($refs[0] ?? 0), $tenantId) : null;
                if ($team === null) {
                    throw new \DomainException('Équipe introuvable.');
                }
                foreach ($this->fireTeams->listForTenant($tenantId) as $t) {
                    if ((int) $t['id'] === (int) $team['id']) {
                        $ids = array_map(static fn (array $m): int => (int) ($m['user_id'] ?? 0), $t['members'] ?? []);
                    }
                }
                break;
            case 'person':
                $ids = array_map('intval', $refs);
                break;
        }
        $members = $this->memberIds($tenantId);
        $out = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0 && isset($members[$id]))));
        sort($out);

        return $out;
    }

    /**
     * Fiches ATAK des joueurs visés (toute la communauté si $userIds vaut null).
     *
     * @param list<int>|null $userIds
     * @return list<array<string, mixed>>
     */
    private function players(int $tenantId, ?array $userIds): array
    {
        $want = $userIds === null ? null : array_flip($userIds);

        return array_values(array_filter(
            $this->fleet->listTerminals('', $tenantId, 2000),
            static fn (array $t): bool => (int) $t['tenant_id'] === $tenantId && ($want === null || isset($want[(int) ($t['user_id'] ?? 0)]))
        ));
    }

    /** @var array<int, array<int, true>> */
    private array $memberCache = [];

    /** @return array<int, true> */
    private function memberIds(int $tenantId): array
    {
        if (!isset($this->memberCache[$tenantId])) {
            $this->memberCache[$tenantId] = [];
            foreach ($this->users->allForTenant($tenantId) as $u) {
                if ((string) ($u['status'] ?? 'active') === 'active') {
                    $this->memberCache[$tenantId][(int) $u['id']] = true;
                }
            }
        }

        return $this->memberCache[$tenantId];
    }

    private function isMember(int $tenantId, int $userId): bool
    {
        return isset($this->memberIds($tenantId)[$userId]);
    }

    /** @param array<string, mixed> $u */
    public static function personLabel(array $u): string
    {
        $name = trim((string) ($u['display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($u['first_name'] ?? '') . ' ' . (string) ($u['last_name'] ?? ''));
        }
        $cs = trim((string) ($u['callsign'] ?? ''));
        if ($name === '') {
            return $cs !== '' ? $cs : 'Membre n° ' . (int) ($u['id'] ?? 0);
        }

        return $cs !== '' && strcasecmp($cs, $name) !== 0 ? $name . ' (' . $cs . ')' : $name;
    }

    /** @param array<string, mixed> $details */
    private function log(string $action, int $tenantId, array $actor, array $details): void
    {
        try {
            $this->audit->logChange($action, $tenantId, $actor['id'], 'broadcast', null, [], $details);
        } catch (\Throwable $e) {
            error_log('[broadcast-center] audit: ' . $e->getMessage());
        }
    }

    /** @return int|null|false */
    private static function localDate(mixed $raw): int|null|false
    {
        $s = self::s($raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $s) !== 1) {
            return false;
        }

        return strtotime(str_replace('T', ' ', $s));
    }

    private static function s(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }
}
