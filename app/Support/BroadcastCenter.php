<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centre de diffusion du back-office (/back-office/alerts) : règles communes, sans base de données.
 *
 * Le centre n'a pas de table à lui : chaque canal écrit là où il écrit déjà.
 *   site    → tenant_alerts (bandeau du portail, TenantAlertRepository)
 *   phone   → community_notes de l'expérience ATAK (app Communauté, AtakCommunityBriefService), consigne, ordre ou tip
 *   game    → atak_admin_messages (message imposé, un par joueur, comme le message global du parc ATAK)
 *   tasks   → atak_orders (app Tâches : ordre suivi reçu, accusé, en cours, terminé ; alerte NOTIFY / NOTIFY_FULL)
 *   discord → salon « Annonces » de la communauté (DiscordEventRelayService), seulement si un webhook est réglé
 *
 * Une diffusion qui part sur plusieurs canaux porte une clé de 12 caractères (broadcast_key du bandeau et des
 * messages, identifiant ORD-W-<clé> des ordres) : 1er caractère = type, 2e = d si Discord a été demandé, sinon n.
 */
final class BroadcastCenter
{
    public const TYPES = [
        'annonce' => 'Annonce du site',
        'consigne' => 'Consigne',
        'ordre' => 'Ordre',
        'tip' => 'Tip',
        'message' => 'Message aux joueurs',
        'alerte' => 'Alerte tactique',
    ];

    public const IMPORTANCE = [
        'info' => 'Info',
        'important' => 'Important',
        'urgent' => 'Urgent',
    ];

    public const CHANNELS = [
        'site' => 'Site',
        'phone' => 'Téléphone · Communauté',
        'tasks' => 'Téléphone · Tâches',
        'game' => 'Message en jeu',
        'discord' => 'Discord',
    ];

    public const TARGETS = [
        'all' => 'Toute la communauté',
        'unit' => 'Une ou plusieurs unités',
        'role' => 'Un ou plusieurs rôles',
        'group' => 'Un groupe de destinataires',
        'fire_team' => 'Une équipe (fire team)',
        'person' => 'Des personnes',
    ];

    /** Canaux possibles pour chaque type, le premier étant proposé par défaut. */
    public const TYPE_CHANNELS = [
        'annonce' => ['site', 'game', 'discord'],
        'consigne' => ['phone', 'site', 'game', 'discord'],
        'ordre' => ['tasks', 'phone', 'game', 'site', 'discord'],
        'tip' => ['phone', 'site', 'game', 'discord'],
        'message' => ['game', 'site', 'discord'],
        'alerte' => ['tasks', 'game', 'discord'],
    ];

    /** Cibles que chaque canal sait viser (le reste n'existe pas côté canal). */
    public const CHANNEL_TARGETS = [
        'site' => ['all'],
        'phone' => ['all'],
        'discord' => ['all'],
        'game' => ['all', 'unit', 'role', 'group', 'fire_team', 'person'],
        'tasks' => ['all', 'fire_team', 'person'],
    ];

    public const STATES = [
        'scheduled' => 'Programmé',
        'active' => 'Actif',
        'done' => 'Terminé',
        'expired' => 'Expiré',
        'withdrawn' => 'Retiré',
    ];

    public const MAX_TITLE = 120;

    public const MAX_BODY = 2000;

    /** Message Discord : 2 000 caractères, titre et lien compris. */
    public const MAX_DISCORD = 1900;

    /** Un ordre suivi par personne visée. */
    public const MAX_TASK_PERSONS = 20;

    /** Message en jeu : 90 jours au plus, comme le parc ATAK ; 3 jours sans date de fin. */
    public const GAME_MAX_HOURS = 24 * 90;

    public const GAME_DEFAULT_HOURS = 72;

    private const TYPE_CODES = ['annonce' => 'a', 'consigne' => 'c', 'ordre' => 'o', 'tip' => 't', 'message' => 'm', 'alerte' => 'x'];

    public static function newKey(string $type, bool $discord): string
    {
        return (self::TYPE_CODES[$type] ?? 'm') . ($discord ? 'd' : 'n') . bin2hex(random_bytes(5));
    }

    /** @return array{type: string, discord: bool}|null */
    public static function parseKey(?string $key): ?array
    {
        $key = (string) $key;
        if (preg_match('/^([acotmx])([dn])[0-9a-f]{10}$/', $key, $m) !== 1) {
            return null;
        }

        return ['type' => (string) array_search($m[1], self::TYPE_CODES, true), 'discord' => $m[2] === 'd'];
    }

    public static function isKey(string $key): bool
    {
        return self::parseKey($key) !== null;
    }

    public static function orderExternalId(string $key, int $n = 0): string
    {
        return 'ORD-W-' . $key . ($n > 0 ? '-' . $n : '');
    }

    /** Clé d'une diffusion du centre lue dans l'identifiant d'un ordre (ORD-W-<clé> ou ORD-W-<clé>-n). */
    public static function keyFromOrderExternalId(string $externalId): ?string
    {
        if (preg_match('/^ORD-W-([acotmx][dn][0-9a-f]{10})(?:-\d+)?$/', $externalId, $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    public static function rank(?string $importance): int
    {
        return match ($importance) {
            'urgent' => 0,
            'important' => 1,
            'info' => 2,
            default => 3,
        };
    }

    // ── Correspondance avec ce que chaque canal sait déjà stocker ─────────────

    /** Genre du bandeau du site (TenantAlertVisuals) ; l'importance « important » met aussi le bandeau en avant. */
    public static function siteKind(string $type, string $importance): string
    {
        if ($importance === 'urgent') {
            return 'urgent';
        }

        return in_array($type, ['consigne', 'ordre', 'tip'], true) ? 'notice' : 'info';
    }

    /** @param array<string, bool> $features */
    public static function siteImportance(string $kind, array $features): string
    {
        if ($kind === 'urgent' || $kind === 'security') {
            return 'urgent';
        }

        return !empty($features[TenantAlertFeatures::HIGHLIGHT]) ? 'important' : 'info';
    }

    public static function gameLevel(string $importance): string
    {
        return match ($importance) {
            'urgent' => 'critical',
            'important' => 'warning',
            default => 'info',
        };
    }

    public static function importanceFromLevel(string $level): string
    {
        return match ($level) {
            'critical' => 'urgent',
            'warning' => 'important',
            default => 'info',
        };
    }

    public static function orderPriority(string $importance): string
    {
        return match ($importance) {
            'urgent' => 'URGENT',
            'important' => 'IMPORTANT',
            default => 'ROUTINE',
        };
    }

    public static function importanceFromPriority(string $priority): string
    {
        return match (strtoupper($priority)) {
            'URGENT', 'CONTACT' => 'urgent',
            'IMPORTANT' => 'important',
            default => 'info',
        };
    }

    /** Ordre de l'app Tâches : texte libre pour un ordre, notification (plein écran si urgent) pour une alerte. */
    public static function orderType(string $type, string $importance): string
    {
        if ($type === 'alerte') {
            return $importance === 'urgent' ? 'NOTIFY_FULL' : 'NOTIFY';
        }

        return 'CUSTOM';
    }

    /** Type affiché pour un ordre web qui ne vient pas du centre. */
    public static function typeFromOrder(string $orderType): string
    {
        return in_array(strtoupper($orderType), ['NOTIFY', 'NOTIFY_FULL'], true) ? 'alerte' : 'ordre';
    }

    /** État du suivi d'un ordre : reçu, accepté, en cours, exécuté (statuts de l'app Tâches). */
    public static function orderStepLabel(string $status): string
    {
        return match (strtoupper($status)) {
            'DELIVERED' => 'Reçu',
            'ACK' => 'Accepté',
            'EXEC' => 'En cours',
            'DONE' => 'Exécuté',
            'FAILED' => 'Échec',
            'CANCELLED' => 'Retiré',
            default => 'Transmis',
        };
    }

    public static function orderState(string $status): string
    {
        return match (strtoupper($status)) {
            'DONE', 'FAILED' => 'done',
            'CANCELLED' => 'withdrawn',
            default => 'active',
        };
    }

    /**
     * État d'un bandeau du site.
     *
     * @param array<string, mixed> $row ligne tenant_alerts
     */
    public static function siteState(array $row, int $now): string
    {
        if (empty($row['is_active'])) {
            return 'withdrawn';
        }
        $starts = !empty($row['starts_at']) ? strtotime((string) $row['starts_at']) : false;
        $ends = !empty($row['ends_at']) ? strtotime((string) $row['ends_at']) : false;
        if ($ends !== false && $ends < $now) {
            return 'expired';
        }

        return $starts !== false && $starts > $now ? 'scheduled' : 'active';
    }

    /**
     * État d'un envoi de messages en jeu, d'après ses lignes (dates UTC).
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function gameState(array $rows, int $now): string
    {
        $pending = 0;
        $acked = 0;
        $cancelled = 0;
        foreach ($rows as $r) {
            if (!empty($r['cancelled_at'])) {
                ++$cancelled;
            } elseif (!empty($r['acked_at'])) {
                ++$acked;
            } else {
                $exp = !empty($r['expires_at']) ? strtotime((string) $r['expires_at'] . ' UTC') : false;
                if ($exp === false || $exp > $now) {
                    ++$pending;
                }
            }
        }
        if ($pending > 0) {
            return 'active';
        }
        if ($rows !== [] && $cancelled === count($rows)) {
            return 'withdrawn';
        }

        return $acked > 0 && $acked + $cancelled === count($rows) ? 'done' : 'expired';
    }

    /** État d'une diffusion sur plusieurs canaux : le plus vivant l'emporte. */
    public static function mergeState(array $states): string
    {
        foreach (['active', 'scheduled', 'done', 'expired', 'withdrawn'] as $state) {
            if (in_array($state, $states, true)) {
                return $state;
            }
        }

        return 'expired';
    }

    // ── Saisie ────────────────────────────────────────────────────────────

    /**
     * Contrôle d'un nouveau message, avant tout envoi. Rien n'est coupé en silence : un texte trop long est refusé.
     *
     * @param array<string, mixed> $in      champs du formulaire
     * @param list<string> $allowedChannels canaux que la personne connectée peut utiliser
     * @return array{errors: list<string>, warnings: list<string>, data: array<string, mixed>}
     */
    public static function validate(array $in, array $allowedChannels, int $now): array
    {
        $errors = [];
        $warnings = [];
        $type = self::str($in['type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            $errors[] = 'Choisissez le type du message.';
            $type = 'message';
        }
        $importance = self::str($in['importance'] ?? 'info');
        if (!isset(self::IMPORTANCE[$importance])) {
            $importance = 'info';
        }

        $channels = [];
        foreach (is_array($in['channels'] ?? null) ? $in['channels'] : [] as $c) {
            $c = self::str($c);
            if (!isset(self::CHANNELS[$c])) {
                continue;
            }
            if (!in_array($c, self::TYPE_CHANNELS[$type], true)) {
                $errors[] = sprintf('Le canal « %s » n’existe pas pour le type « %s ».', self::CHANNELS[$c], self::TYPES[$type]);
                continue;
            }
            if (!in_array($c, $allowedChannels, true)) {
                $errors[] = sprintf('Vous n’avez pas le droit de diffuser sur « %s ».', self::CHANNELS[$c]);
                continue;
            }
            $channels[$c] = true;
        }
        $channels = array_keys($channels);
        if ($channels === [] && $errors === []) {
            $errors[] = 'Choisissez au moins un canal de diffusion.';
        }
        $toGame = array_intersect($channels, ['phone', 'game', 'tasks']) !== [];

        $title = self::oneLine((string) ($in['title'] ?? ''));
        $body = self::text((string) ($in['body'] ?? ''));
        if (!mb_check_encoding($title . $body, 'UTF-8')) {
            $errors[] = 'Texte illisible : encodage invalide.';
            $title = '';
            $body = '';
        }
        if ($title === '') {
            $errors[] = 'Le titre est obligatoire.';
        } elseif (mb_strlen($title) > self::MAX_TITLE) {
            $errors[] = sprintf('Titre trop long : %d caractères au plus.', self::MAX_TITLE);
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            $errors[] = sprintf('Texte trop long : %d caractères au plus.', self::MAX_BODY);
        }
        if ($toGame && (strip_tags($title) !== $title || strip_tags($body) !== $body)) {
            $errors[] = 'Retirez les balises : le jeu affiche du texte brut, et le caractère < suivi d’un mot serait lu comme une balise.';
        }
        if ($body === '' && array_intersect($channels, ['game', 'tasks']) !== []) {
            $errors[] = 'Écrivez le texte du message : le joueur le lit en jeu.';
        }
        if (in_array('discord', $channels, true) && mb_strlen($title) + mb_strlen($body) > self::MAX_DISCORD) {
            $errors[] = sprintf('Trop long pour Discord : %d caractères au plus, titre et texte compris.', self::MAX_DISCORD);
        }
        $note = null;
        if (in_array('phone', $channels, true) && $title !== '') {
            try {
                $note = AtakCommunityBrief::validNote(in_array($type, AtakCommunityBrief::KINDS, true) ? $type : 'consigne', $title, $body);
            } catch (\DomainException $e) {
                $errors[] = 'App Communauté du téléphone : ' . $e->getMessage();
            }
        }

        $target = self::str($in['target'] ?? 'all');
        if (!isset(self::TARGETS[$target])) {
            $target = 'all';
        }
        foreach ($channels as $c) {
            if (!in_array($target, self::CHANNEL_TARGETS[$c], true)) {
                $errors[] = sprintf('« %s » ne sait pas viser « %s » : ciblez %s, ou décochez ce canal.', self::CHANNELS[$c], mb_strtolower(self::TARGETS[$target]), implode(' ou ', array_map(static fn (string $t): string => mb_strtolower(self::TARGETS[$t]), self::CHANNEL_TARGETS[$c])));
            }
        }
        $refs = self::targetRefs($target, $in);
        if ($target !== 'all' && $refs === []) {
            $errors[] = 'Choisissez qui reçoit le message (' . mb_strtolower(self::TARGETS[$target]) . ').';
        }
        if ($target === 'person' && in_array('tasks', $channels, true) && count($refs) > self::MAX_TASK_PERSONS) {
            $errors[] = sprintf('Un ordre suivi par personne : %d personnes au plus.', self::MAX_TASK_PERSONS);
        }

        $starts = self::date($in['starts_at'] ?? '');
        $ends = self::date($in['ends_at'] ?? '');
        if ($starts === false || $ends === false) {
            $errors[] = 'Date illisible : utilisez le sélecteur de date.';
            $starts = $starts === false ? null : $starts;
            $ends = $ends === false ? null : $ends;
        }
        if ($starts !== null && $ends !== null && $ends <= $starts) {
            $errors[] = 'La fin doit être après le début.';
        }
        if ($ends !== null && $ends <= $now) {
            $errors[] = 'La date de fin est déjà passée.';
        }
        $scheduled = $starts !== null && $starts > $now + 60;
        if ($scheduled) {
            $now_ = array_values(array_diff($channels, ['site']));
            if ($now_ !== []) {
                $errors[] = 'Seul le site sait attendre une date de début. '
                    . implode(', ', array_map(static fn (string $c): string => '« ' . self::CHANNELS[$c] . ' »', $now_))
                    . ' part dès l’envoi : retirez la date de début ou ce canal.';
            }
        }
        if ($ends !== null && in_array('phone', $channels, true)) {
            $warnings[] = 'L’app Communauté n’a pas de date de fin : la note reste sur le téléphone jusqu’à son retrait.';
        }
        if ($ends !== null && in_array('tasks', $channels, true)) {
            $warnings[] = 'Un ordre de l’app Tâches n’expire pas : il reste jusqu’à son exécution ou son retrait.';
        }
        $hours = self::GAME_DEFAULT_HOURS;
        if ($ends !== null) {
            $hours = (int) ceil(($ends - $now) / 3600);
        }
        if (in_array('game', $channels, true) && $hours > self::GAME_MAX_HOURS) {
            $errors[] = 'Un message en jeu reste 90 jours au plus : rapprochez la date de fin.';
        }
        $mapId = (int) self::str($in['map_id'] ?? '1');
        if ($mapId < 1 || $mapId > 99) {
            $mapId = 1;
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => $warnings,
            'data' => [
                'type' => $type,
                'importance' => $importance,
                'channels' => $channels,
                'title' => $title,
                'body' => $body,
                'note' => $note,
                'target' => $target,
                'refs' => $refs,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'game_expires_at' => $ends ?? $now + self::GAME_DEFAULT_HOURS * 3600,
                'require_ack' => !array_key_exists('require_ack', $in) || self::str($in['require_ack']) !== '0',
                'map_id' => $mapId,
            ],
        ];
    }

    /**
     * Identifiants de la cible : entiers (unités, groupe, équipe, personnes) ou noms techniques des rôles.
     *
     * @param array<string, mixed> $in
     * @return list<int|string>
     */
    public static function targetRefs(string $target, array $in): array
    {
        $field = match ($target) {
            'unit' => 'unit_ids',
            'role' => 'role_slugs',
            'group' => 'group_id',
            'fire_team' => 'fire_team_id',
            'person' => 'user_ids',
            default => '',
        };
        if ($field === '') {
            return [];
        }
        $raw = $in[$field] ?? [];
        $raw = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($raw as $v) {
            $v = self::str($v);
            if ($target === 'role') {
                if (preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/i', $v) === 1) {
                    $out[$v] = $v;
                }
            } elseif (ctype_digit($v) && strlen($v) < 10 && (int) $v > 0) {
                $out[$v] = (int) $v;
            }
        }
        $out = array_values($out);

        return in_array($target, ['group', 'fire_team'], true) ? array_slice($out, 0, 1) : array_slice($out, 0, 500);
    }

    /** Texte d'une ligne : retours à la ligne et caractères de contrôle remplacés par une espace. */
    public static function oneLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)));
    }

    /** Texte libre : les retours à la ligne restent, les autres caractères de contrôle partent. */
    public static function text(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return trim((string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', $value));
    }

    /** @return int|null|false horodatage, null si vide, false si illisible */
    private static function date(mixed $raw): int|null|false
    {
        $s = self::str($raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $s) !== 1) {
            return false;
        }
        $t = strtotime(str_replace('T', ' ', $s));

        return $t === false ? false : $t;
    }

    private static function str(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }
}
