<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Chronologie propre à chaque identité fictive (table sse_identity_events). Append-only : ce dépôt n'offre ni modification
 * ni suppression d'une ligne, par construction. Chaque ligne porte la section dont le lecteur doit avoir le droit
 * (« base » : quiconque ouvre la fiche ; « truth » : vérité de jeu, habilitation Confidentiel).
 */
final class SseIdentityEventRepository
{
    public const SECTIONS = ['base', 'truth'];

    public const SOURCES = ['web', 'game', 'system'];

    /** Champs d'une identité dont la modification est notée (champ => [français, anglais, vérité de jeu ?]). */
    public const TRACKED_FIELDS = [
        'last_name' => ['Nom', 'Last name', false],
        'first_name' => ['Prénom', 'First name', false],
        'sex' => ['Sexe', 'Sex', false],
        'birth_date' => ['Date de naissance', 'Date of birth', false],
        'birth_place' => ['Lieu de naissance', 'Place of birth', false],
        'nationality' => ['Nationalité', 'Nationality', false],
        'profession' => ['Profession', 'Occupation', false],
        'alias' => ['Alias', 'Alias', false],
        'arma_key' => ['Personnage en jeu', 'In-game character', false],
        'notes' => ['Notes', 'Notes', false],
        'category' => ['Catégorie', 'Category', false],
        'side' => ['Camp', 'Side', false],
        'faction' => ['Faction', 'Faction', false],
        'language' => ['Langue', 'Language', false],
        'zone' => ['Zone', 'Zone', false],
        'op_key' => ['Opération', 'Operation', false],
        'context_id' => ['Carte', 'Map', false],
        'kind' => ['Nature de l’identité', 'Identity kind', true],
        'person_id' => ['Fiche personne rattachée', 'Linked person record', true],
    ];

    private Database $db;

    /** @var \WeakMap<\PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $readyByConnection = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** La table de la chronologie existe-t-elle ? (étape 4600 pas encore passée : les traces sont simplement omises) */
    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
            self::$readyByConnection ??= new \WeakMap();
            $known = self::$readyByConnection[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                $pdo->query('SELECT 1 FROM sse_identity_events WHERE 1 = 0');
                self::$readyByConnection[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                // Table absente (étape 4600 pas encore passée) : on ne retente pas à chaque appel.
                self::$readyByConnection[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    /** Oublie le résultat gardé (tests qui créent les tables après coup). */
    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    /**
     * Compte et libellé de la session en cours (rien hors d'une session : API du jeu, tâche planifiée).
     *
     * @return array{0: ?int, 1: string}
     */
    public static function currentActor(): array
    {
        try {
            $id = (int) \App\Core\Session::get('user_id');
            $label = trim((string) (\App\Core\Session::get('callsign') ?? \App\Core\Session::get('display_name') ?? ''));

            return [$id > 0 ? $id : null, $label !== '' ? $label : ($id > 0 ? '' : 'Système')];
        } catch (\Throwable) {
            return [null, 'Système'];
        }
    }

    /**
     * Écriture sans échec : une chronologie indisponible ne doit jamais empêcher l'action qu'elle raconte.
     *
     * @param array<string, mixed> $details
     */
    public function tryAdd(int $tenantId, int $identityId, string $type, string $summary, array $details = [], string $section = 'base', string $source = 'web'): void
    {
        if (!self::ready()) {
            return;
        }
        try {
            [$userId, $label] = self::currentActor();
            $this->add($tenantId, $identityId, $type, $summary, $details, $userId, $label, $section, $source);
        } catch (\Throwable $e) {
            error_log('Chronologie des identités : trace non écrite : ' . $e->getMessage());
        }
    }

    /**
     * Notes la modification d'une identité : une ligne par fiche modifiée avec, pour chaque champ suivi, l'ancienne et la
     * nouvelle valeur (les champs de vérité de jeu portent leur propre section).
     *
     * @param array<string, mixed> $before ligne avant
     * @param array<string, mixed> $after ligne après
     */
    public function traceChanges(int $tenantId, int $identityId, array $before, array $after): void
    {
        $base = [];
        $truth = [];
        foreach (self::TRACKED_FIELDS as $field => $meta) {
            $old = self::plain($before[$field] ?? null);
            $new = self::plain($after[$field] ?? null);
            if ($old === $new) {
                continue;
            }
            $change = ['field' => $field, 'label' => $meta[0], 'old' => $old, 'new' => $new];
            if ($meta[2]) {
                $truth[] = $change;
            } else {
                $base[] = $change;
            }
        }
        if ($base !== []) {
            $this->tryAdd($tenantId, $identityId, 'identity.updated', 'Fiche modifiée : ' . implode(', ', array_map(static fn (array $c): string => (string) $c['label'], $base)) . '.', ['changes' => $base]);
        }
        if ($truth !== []) {
            $this->tryAdd($tenantId, $identityId, 'identity.updated', 'Fiche modifiée (vérité de jeu).', ['changes' => $truth], 'truth');
        }
    }

    private static function plain(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return mb_substr(is_scalar($v) ? (string) $v : '', 0, 200);
    }

    /**
     * @param array<string, mixed> $details champs modifiés (ancien et nouveau) ou compléments, jamais de secret
     */
    public function add(
        int $tenantId,
        int $identityId,
        string $type,
        string $summary,
        array $details = [],
        ?int $actorUserId = null,
        ?string $actorLabel = null,
        string $section = 'base',
        string $source = 'web',
        ?int $noticeId = null,
    ): int {
        if ($tenantId < 1 || $identityId < 1 || $type === '') {
            return 0;
        }
        $json = $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $label = $actorLabel !== null ? mb_substr(trim(str_replace(["\r", "\n"], ' ', $actorLabel)), 0, 80) : null;

        return $this->db->insert(
            'INSERT INTO sse_identity_events (tenant_id, identity_id, event_type, section, summary, details_json, notice_id, actor_user_id,
                actor_label, source, created_at)
             VALUES (:t, :i, :type, :sec, :sum, :det, :n, :u, :lbl, :src, :now)',
            [
                't' => $tenantId, 'i' => $identityId, 'type' => mb_substr($type, 0, 40),
                'sec' => in_array($section, self::SECTIONS, true) ? $section : 'base',
                'sum' => mb_substr(str_replace(["\r", "\n"], ' ', $summary), 0, 255),
                'det' => is_string($json) && strlen($json) <= 20000 ? $json : null,
                'n' => $noticeId !== null && $noticeId > 0 ? $noticeId : null,
                'u' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
                'lbl' => $label !== null && $label !== '' ? $label : null,
                'src' => in_array($source, self::SOURCES, true) ? $source : 'web',
                'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Les plus récents d'abord. Les sections non autorisées au lecteur ne sont jamais lues.
     *
     * @param list<string> $types types d'événements voulus (vide : tous)
     * @param list<string> $sections sections autorisées au lecteur
     * @return list<array<string, mixed>>
     */
    public function listForIdentity(int $tenantId, int $identityId, array $types = [], array $sections = ['base'], int $limit = 200): array
    {
        $sections = array_values(array_intersect($sections, self::SECTIONS));
        if ($tenantId < 1 || $identityId < 1 || $sections === []) {
            return [];
        }
        $params = ['t' => $tenantId, 'i' => $identityId];
        $secMarks = [];
        foreach ($sections as $k => $s) {
            $secMarks[] = ':s' . $k;
            $params['s' . $k] = $s;
        }
        $sql = 'SELECT * FROM sse_identity_events WHERE tenant_id = :t AND identity_id = :i AND section IN (' . implode(', ', $secMarks) . ')';
        $types = array_slice(array_values(array_filter($types, 'is_string')), 0, 30);
        if ($types !== []) {
            $typeMarks = [];
            foreach ($types as $k => $ty) {
                $typeMarks[] = ':ty' . $k;
                $params['ty' . $k] = $ty;
            }
            $sql .= ' AND event_type IN (' . implode(', ', $typeMarks) . ')';
        }

        return array_map([self::class, 'hydrate'], $this->db->fetchAll($sql . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)), $params));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrate(array $row): array
    {
        $details = json_decode((string) ($row['details_json'] ?? ''), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'identity_id' => (int) ($row['identity_id'] ?? 0),
            'type' => (string) ($row['event_type'] ?? ''),
            'section' => (string) ($row['section'] ?? 'base'),
            'summary' => (string) ($row['summary'] ?? ''),
            'details' => is_array($details) ? $details : [],
            'notice_id' => ($row['notice_id'] ?? null) !== null ? (int) $row['notice_id'] : null,
            'actor_user_id' => ($row['actor_user_id'] ?? null) !== null ? (int) $row['actor_user_id'] : null,
            'actor_label' => ($row['actor_label'] ?? null) !== null ? (string) $row['actor_label'] : null,
            'source' => (string) ($row['source'] ?? 'web'),
            'at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
