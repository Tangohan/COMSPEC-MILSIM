<?php

declare(strict_types=1);

namespace App\Services\Intel;

use App\Core\Database;
use App\Repositories\DiscordBotRepository;
use PDO;

/**
 * Notifications Discord des remontées, par le bot « ATHENA Companion » déjà relié à la communauté.
 *
 * Le site n'appelle jamais Discord : le bot vient chercher ses messages (DiscordBotBureau::reminders), les envoie puis
 * les acquitte. Les messages à envoyer se déduisent donc de l'état des remontées, sans file à part :
 *   - anti-doublon et journal d'envoi : discord_bot_reminders (une ligne par message, clé unique communauté + genre + référence) ;
 *   - une panne de Discord ou du bot ne bloque rien : le message reste dû et repart au passage suivant ;
 *   - les demandes à la main sont notées dans le fil de la remontée (événement « discord »).
 *
 * Réglage par communauté, dans les réglages de la communauté (integrations.discord_intake) : salon, événements, rôle
 * mentionné. Rien n'est envoyé pour ce qui date d'avant l'activation.
 *
 * Contenu d'un message : référence, type, urgence, émetteur (indicatif, seulement au plus bas niveau de diffusion),
 * lien vers la fiche. Jamais le texte ; le titre seulement si la diffusion est au plus bas niveau et que rien n'est
 * caviardé ; jamais de nom, d'identifiant Steam ni de passage caviardé.
 */
class IntelIntakeDiscord
{
    public const KIND = 'remontee';

    public const EVENTS = [
        'new' => 'Nouvelle remontée',
        'urgent' => 'Remontée urgente ou critique',
        'ack' => 'Prise en compte',
        'forward' => 'Transmise',
        'late' => 'En retard',
    ];

    /** Au-delà, un événement non envoyé est abandonné (bot resté éteint). */
    public const MAX_AGE_SECONDS = 172800;

    /** Délai entre deux notifications à la main de la même remontée. */
    public const MANUAL_COOLDOWN_SECONDS = 600;

    public function __construct(
        private ?PDO $pdo = null,
        private ?DiscordBotRepository $repo = null,
        private ?IntelIntakeService $intake = null,
        private ?\Closure $siteUrl = null,
    ) {
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function repo(): DiscordBotRepository
    {
        return $this->repo ??= new DiscordBotRepository($this->db());
    }

    private function intake(): IntelIntakeService
    {
        return $this->intake ??= new IntelIntakeService($this->db());
    }

    private function url(string $path): string
    {
        return $this->siteUrl !== null ? ($this->siteUrl)($path) : url($path);
    }

    /** @return array<string, mixed> */
    private function tenantSettings(int $tenantId): array
    {
        try {
            $st = $this->db()->prepare('SELECT settings FROM tenants WHERE id = ?');
            $st->execute([$tenantId]);
            $decoded = json_decode((string) $st->fetchColumn(), true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{enabled: bool, channel_id: string, role_id: string, events: list<string>, enabled_at: string}
     */
    public function settings(int $tenantId): array
    {
        $raw = $this->tenantSettings($tenantId)['integrations']['discord_intake'] ?? [];
        $raw = is_array($raw) ? $raw : [];
        $events = array_values(array_intersect(array_keys(self::EVENTS), array_map('strval', (array) ($raw['events'] ?? ['urgent', 'late']))));

        return [
            'enabled' => !empty($raw['enabled']),
            'channel_id' => preg_match('/^\d{5,24}$/', (string) ($raw['channel_id'] ?? '')) === 1 ? (string) $raw['channel_id'] : '',
            'role_id' => preg_match('/^\d{5,24}$/', (string) ($raw['role_id'] ?? '')) === 1 ? (string) $raw['role_id'] : '',
            'events' => $events,
            'enabled_at' => (string) ($raw['enabled_at'] ?? ''),
        ];
    }

    /**
     * Enregistre le réglage. Le salon et le rôle doivent être ceux que le bot a relevés sur le serveur.
     *
     * @param array<string, mixed> $in enabled, channel_id, role_id, events[]
     * @return array{ok: bool, error: string}
     */
    public function save(int $tenantId, array $in, ?int $now = null): array
    {
        $current = $this->settings($tenantId);
        $channel = trim((string) ($in['channel_id'] ?? ''));
        $role = trim((string) ($in['role_id'] ?? ''));
        $enabled = !empty($in['enabled']);
        if ($channel !== '' && !isset($this->repo()->channels($tenantId)[$channel])) {
            return ['ok' => false, 'error' => 'Salon inconnu sur ce serveur : choisissez-le dans la liste.'];
        }
        if ($role !== '' && !isset($this->repo()->roles($tenantId)[$role])) {
            return ['ok' => false, 'error' => 'Rôle inconnu sur ce serveur : choisissez-le dans la liste.'];
        }
        if ($enabled && $channel === '') {
            return ['ok' => false, 'error' => 'Choisissez le salon qui recevra les notifications.'];
        }
        $events = array_values(array_intersect(array_keys(self::EVENTS), array_map('strval', (array) ($in['events'] ?? []))));
        $all = $this->tenantSettings($tenantId);
        $integrations = is_array($all['integrations'] ?? null) ? $all['integrations'] : [];
        $integrations['discord_intake'] = [
            'enabled' => $enabled,
            'channel_id' => $channel,
            'role_id' => $role,
            'events' => $events,
            // L'heure d'activation borne les envois : rien de ce qui précède ne part.
            'enabled_at' => $enabled ? ($current['enabled'] && $current['enabled_at'] !== '' ? $current['enabled_at'] : date('Y-m-d H:i:s', $now ?? time())) : '',
        ];
        $all['integrations'] = $integrations;
        try {
            $this->db()->prepare('UPDATE tenants SET settings = ? WHERE id = ?')->execute([json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $tenantId]);
            if ($this->pdo === null) {
                (new \App\Repositories\TenantRepository())->forgetCache();
            }
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Le réglage n’a pas pu être enregistré.'];
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Les notifications peuvent-elles partir ? Sinon, pourquoi (affiché à la place du bouton).
     *
     * @return array{ready: bool, reason: string, channel: string, linked: bool}
     */
    public function status(int $tenantId, ?int $now = null): array
    {
        $no = static fn (string $reason, bool $linked = false): array => ['ready' => false, 'reason' => $reason, 'channel' => '', 'linked' => $linked];
        try {
            if (!$this->repo()->schemaReady()) {
                return $no('Le bot Discord n’est pas installé sur cette plateforme.');
            }
            $bot = $this->repo()->status($tenantId, $now);
            if ($bot === 'none') {
                return $no('Aucun serveur Discord n’est relié à la communauté.');
            }
            $s = $this->settings($tenantId);
            if (!$s['enabled'] || $s['channel_id'] === '') {
                return $no('Les notifications des remontées ne sont pas activées (Intégrations › Bot Discord).', true);
            }
            $channels = $this->repo()->channels($tenantId);
            if (!isset($channels[$s['channel_id']])) {
                return $no('Le salon choisi n’existe plus sur le serveur.', true);
            }
            if ($bot !== 'connected') {
                return $no($bot === 'pending' ? 'Le bot Discord n’a pas encore rejoint le serveur.' : 'Le bot Discord ne répond plus : le message partira à son retour.', true);
            }

            return ['ready' => true, 'reason' => '', 'channel' => '#' . $channels[$s['channel_id']], 'linked' => true];
        } catch (\Throwable) {
            return $no('Le bot Discord n’est pas installé sur cette plateforme.');
        }
    }

    /**
     * Le message, sans donnée sensible. $item : remontée complète (find) ou ligne de liste.
     *
     * @param array<string, mixed> $item
     * @return array{event: string, ref: string, type: string, urgency: string, urgency_label: string, emitter: string, title: string, link: string}
     */
    public function message(array $item, string $event): array
    {
        $source = (string) $item['source'];
        $lowest = IntelIntakePresenter::lowestClassification((string) ($item['classification'] ?? ''));
        $redacted = !empty($item['redacted']) || (is_array($item['redactions'] ?? null) ? $item['redactions'] !== [] : (int) ($item['redactions'] ?? 0) > 0);
        $title = '';
        if ($lowest && !$redacted) {
            $row = is_array($item['row'] ?? null) ? $item['row'] : [];
            $title = trim((string) ($item['title'] ?? ($source === 'cr' ? ($row['summary'] ?? '') : ($row['title'] ?? ''))));
            $title = mb_substr(preg_replace('/\s+/u', ' ', $title) ?? '', 0, 120);
        }
        $level = IntelIntakePresenter::urgencyLevel((string) ($item['priority'] ?? ''));

        return [
            'event' => isset(self::EVENTS[$event]) || $event === 'manual' ? $event : 'manual',
            'ref' => (string) $item['ref'],
            'type' => trim((string) $item['type'] . ' · ' . (string) $item['type_label'], ' ·'),
            'urgency' => $level,
            'urgency_label' => IntelIntakePresenter::urgencyLabel($source, (string) ($item['priority'] ?? '')),
            'emitter' => $lowest ? mb_substr(trim((string) ($item['author'] ?? '')), 0, 40) : '',
            'title' => $title,
            'link' => $this->url('back-office/remontees/' . $source . '/' . (int) $item['id']),
        ];
    }

    /**
     * Demande à la main, depuis la fiche. Notée dans le fil ; le bot l'enverra à son prochain passage.
     *
     * @param array{id: int, label: string} $actor
     * @return array{ok: bool, error: string}
     */
    public function request(int $tenantId, string $source, int $id, array $actor, ?int $now = null): array
    {
        $now ??= time();
        $status = $this->status($tenantId, $now);
        if (!$status['ready']) {
            return ['ok' => false, 'error' => $status['reason']];
        }
        $item = $this->intake()->find($tenantId, $source, $id);
        if ($item === null || !empty($item['deleted'])) {
            return ['ok' => false, 'error' => 'Remontée introuvable.'];
        }
        foreach (array_reverse($item['events']) as $e) {
            if ($e['kind'] === 'discord' && ($now - (strtotime((string) $e['at']) ?: 0)) < self::MANUAL_COOLDOWN_SECONDS) {
                return ['ok' => false, 'error' => 'Une notification vient déjà d’être demandée pour cette remontée : attendez dix minutes.'];
            }
        }
        $this->intake()->journal($tenantId, $source, $id, 'discord', $actor, null, ['channel' => $status['channel']]);

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Messages dus, pour le bot. Chaque entrée : référence d'anti-doublon, salon, données du message.
     *
     * @return list<array{ref: string, to: string, data: array<string, mixed>}>
     */
    public function pending(int $tenantId, ?int $now = null, int $limit = 10): array
    {
        $now ??= time();
        $s = $this->settings($tenantId);
        if (!$s['enabled'] || $s['channel_id'] === '' || !$this->intake()->schemaReady()) {
            return [];
        }
        $since = max(strtotime($s['enabled_at']) ?: $now, $now - self::MAX_AGE_SECONDS);
        $sinceSql = date('Y-m-d H:i:s', $since);
        $on = array_flip($s['events']);
        $out = [];
        $add = function (string $ref, array $item, string $event) use (&$out, $tenantId, $s, $limit): void {
            if (count($out) >= $limit || $this->repo()->reminderSent($tenantId, self::KIND, $ref)) {
                return;
            }
            $out[] = ['ref' => $ref, 'to' => $s['channel_id'], 'data' => $this->message($item, $event) + ['role' => $s['role_id']]];
        };
        try {
            $items = $this->intake()->listItems($tenantId, ['state' => 'all'], 0, 400);
            $byKey = [];
            foreach ($items as $it) {
                $byKey[$it['source'] . ':' . $it['id']] = $it;
                $created = strtotime((string) $it['at']) ?: 0;
                if ($created < $since) {
                    continue;
                }
                $level = IntelIntakePresenter::urgencyLevel((string) $it['priority']);
                if (isset($on['urgent']) && in_array($level, ['critical', 'high'], true)) {
                    $add('new:' . $it['source'] . ':' . $it['id'], $it, 'urgent');
                } elseif (isset($on['new'])) {
                    $add('new:' . $it['source'] . ':' . $it['id'], $it, 'new');
                }
                if (isset($on['late']) && IntelIntakePresenter::delay($it, $now)['late']) {
                    $add('late:' . $it['source'] . ':' . $it['id'], $it, 'late');
                }
            }
            $st = $this->db()->prepare(
                "SELECT id, source, source_id, kind, meta_json FROM intel_intake_events
                 WHERE tenant_id = ? AND created_at >= ? AND kind IN ('ack', 'state', 'discord') ORDER BY id ASC LIMIT 300"
            );
            $st->execute([$tenantId, $sinceSql]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $e) {
                $it = $byKey[$e['source'] . ':' . $e['source_id']] ?? null;
                if ($it === null) {
                    continue;
                }
                $meta = json_decode((string) ($e['meta_json'] ?? ''), true);
                $to = is_array($meta) ? (string) ($meta['to'] ?? '') : '';
                $from = is_array($meta) ? (string) ($meta['from'] ?? '') : '';
                if ($e['kind'] === 'discord') {
                    $add('man:' . $e['id'], $it, 'manual');
                } elseif (isset($on['ack']) && ($e['kind'] === 'ack' || ($from === 'a_traiter' && in_array($to, ['prise_en_compte', 'en_cours'], true)))) {
                    $add('ack:' . $e['id'], $it, 'ack');
                } elseif (isset($on['forward']) && $e['kind'] === 'state' && $to === 'transmise') {
                    $add('fwd:' . $e['id'], $it, 'forward');
                }
            }
        } catch (\Throwable $e) {
            error_log('[remontees] discord : ' . mb_substr($e->getMessage(), 0, 300));
        }

        return $out;
    }

    /**
     * Journal des envois pour une remontée : ce qui est parti (avec l'heure) et ce qui attend le bot.
     *
     * @param array<string, mixed> $item remontée complète
     * @return list<array{event: string, label: string, sent_at: string}>
     */
    public function journal(int $tenantId, array $item): array
    {
        $refs = ['new:' . $item['source'] . ':' . $item['id'] => 'new', 'late:' . $item['source'] . ':' . $item['id'] => 'late'];
        foreach ((array) $item['events'] as $e) {
            if ($e['kind'] === 'discord') {
                $refs['man:' . $e['id']] = 'manual';
            } elseif ($e['kind'] === 'ack') {
                $refs['ack:' . $e['id']] = 'ack';
            } elseif ($e['kind'] === 'state' && ($e['meta']['to'] ?? '') === 'transmise') {
                $refs['fwd:' . $e['id']] = 'forward';
            } elseif ($e['kind'] === 'state' && ($e['meta']['from'] ?? '') === 'a_traiter') {
                $refs['ack:' . $e['id']] = 'ack';
            }
        }
        $out = [];
        try {
            $in = implode(',', array_fill(0, count($refs), '?'));
            $st = $this->db()->prepare("SELECT ref, sent_at FROM discord_bot_reminders WHERE tenant_id = ? AND kind = ? AND ref IN ({$in}) ORDER BY sent_at DESC");
            $st->execute(array_merge([$tenantId, self::KIND], array_keys($refs)));
            $sent = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $sent[(string) $r['ref']] = (string) $r['sent_at'];
            }
            foreach ($refs as $ref => $event) {
                if (isset($sent[$ref])) {
                    $out[] = ['event' => $event, 'label' => self::EVENTS[$event] ?? 'Notification demandée', 'sent_at' => $sent[$ref]];
                } elseif ($event === 'manual') {
                    $out[] = ['event' => $event, 'label' => 'Notification demandée', 'sent_at' => ''];
                }
            }
        } catch (\Throwable) {
        }

        return $out;
    }
}
