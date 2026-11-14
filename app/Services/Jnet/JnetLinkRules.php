<?php

declare(strict_types=1);

namespace App\Services\Jnet;

/**
 * Règles pures des liaisons JNET (testées sans base).
 *
 * Deux objets, un même principe : relier n'ouvre jamais rien.
 *
 * - Liaison d'unités (appui, partenaire, rattachement temporaire) : un lien entre deux unités hors filiation de l'ORBAT,
 *   décidé par le chef de l'espace, avec des dates et un statut (à venir, active, échue). Elle ne donne aucun droit de
 *   plus que le modèle : elle marque l'autre unité dans les listes, permet de lui partager une information (jamais un
 *   ordre) et, si le chef coche explicitement la case, donne à ses membres la lecture de cet espace.
 * - Lien entre contenus (échange, page, tâche, opération, cible, document, unité, membre) : une paire source → cible. Le
 *   lien n'est montré à un lecteur qu'après contrôle de SES droits sur l'objet lié ; sinon « Lien masqué », sans titre.
 */
final class JnetLinkRules
{
    /** Types de contenus reliables. */
    public const TYPES = [
        'echange' => 'Échange',
        'page' => 'Page',
        'tache' => 'Tâche',
        'operation' => 'Opération',
        'cible' => 'Cible',
        'document' => 'Document',
        'unite' => 'Unité',
        'membre' => 'Membre',
    ];

    /** Types qu'on peut citer dans le texte d'une page (saisie « @ » ou « [[ »). */
    public const MENTION_TYPES = ['unite', 'membre', 'operation', 'page'];

    public const LINKS_PER_OBJECT_MAX = 60;
    public const MENTIONS_PER_SAVE_MAX = 25;
    public const LABEL_MAX = 80;

    /** « [[type:identifiant|libellé]] » : le libellé n'est qu'un repli, le titre affiché vient toujours du serveur. */
    private const MENTION_RE = '/\[\[(unite|membre|operation|page):(\d{1,10})\|([^\[\]|\r\n]{0,80})\]\]/u';

    public const UNIT_KINDS = [
        'appui' => 'Appui',
        'partenaire' => 'Partenaire',
        'rattachement' => 'Rattachement temporaire',
    ];

    /** Rôle de l'espace qui a créé la liaison. */
    public const UNIT_DIRECTIONS = ['out', 'in', 'mutual'];

    public const STATUS_LABELS = ['planned' => 'À venir', 'active' => 'Active', 'expired' => 'Échue'];

    public const UNIT_LINKS_PER_SPACE_MAX = 30;
    public const NOTE_MAX = 200;

    // ——— Références de contenus ———

    /** Un identifiant valide pour ce type : entier pour la plupart, « di-12 » ou « wl-5 » pour une cible. */
    public static function validId(string $type, string $id): bool
    {
        if (!isset(self::TYPES[$type])) {
            return false;
        }
        if ($type === 'cible') {
            return preg_match('/^(di|wl)-[1-9]\d{0,9}$/', $id) === 1;
        }

        return preg_match('/^[1-9]\d{0,9}$/', $id) === 1;
    }

    /** « operation:12 » → ['type' => 'operation', 'id' => '12'], ou null si la référence n'est pas valide. */
    public static function parseRef(string $ref): ?array
    {
        $parts = explode(':', trim($ref), 2);
        if (count($parts) !== 2 || !self::validId($parts[0], $parts[1])) {
            return null;
        }

        return ['type' => $parts[0], 'id' => $parts[1]];
    }

    public static function ref(string $type, string|int $id): string
    {
        return $type . ':' . $id;
    }

    /**
     * Deux contenus peuvent-ils être reliés ? Pas à eux-mêmes ; pas deux unités entre elles (c'est la liaison d'unités, qui
     * a ses règles) ni deux membres.
     */
    public static function canPair(string $srcType, string $srcId, string $dstType, string $dstId): bool
    {
        if (!self::validId($srcType, $srcId) || !self::validId($dstType, $dstId)) {
            return false;
        }
        if ($srcType === $dstType && $srcId === $dstId) {
            return false;
        }

        return !($srcType === $dstType && in_array($srcType, ['unite', 'membre'], true));
    }

    /** Rubrique d'un contenu dans « Tout ce qui touche cette opération ». */
    public static function groupOf(string $type, string $kind = '', bool $photo = false): string
    {
        return match ($type) {
            'echange' => match ($kind) {
                'ordre' => 'ordres',
                'compte_rendu' => 'comptes_rendus',
                default => 'echanges',
            },
            'tache' => 'taches',
            'page' => 'pages',
            'document' => $photo ? 'photos' : 'documents',
            'cible' => 'cibles',
            'unite' => 'unites',
            'membre' => 'membres',
            default => 'autres',
        };
    }

    /** Rubriques, dans l'ordre d'affichage de la vue d'ensemble d'une opération. */
    public const GROUPS = [
        'ordres' => 'Ordres',
        'comptes_rendus' => 'Comptes rendus',
        'echanges' => 'Autres échanges',
        'taches' => 'Tâches',
        'pages' => 'Pages',
        'photos' => 'Photos',
        'documents' => 'Documents',
        'cibles' => 'Cibles',
        'unites' => 'Unités',
        'membres' => 'Membres',
    ];

    /**
     * Qui retire un lien : son auteur, le chef de l'espace d'où il a été posé, ou — pour un lien posé hors espace —
     * l'encadrement de l'organisation. Personne d'autre.
     *
     * @param array{created_by: int, space_id: int} $link
     */
    public static function canRemove(array $link, int $userId, bool $chiefOfSpace, bool $orgManager): bool
    {
        if ($userId > 0 && (int) $link['created_by'] === $userId) {
            return true;
        }

        return (int) $link['space_id'] > 0 ? $chiefOfSpace : $orgManager;
    }

    /**
     * Mise en forme d'un lien pour un lecteur : visible (titre, adresse) ou masqué (rien d'autre que « Lien masqué »).
     * $seen vaut null quand le lecteur ne peut pas voir l'objet, ou qu'il n'existe plus : aucune différence visible.
     *
     * @param array<string, mixed>|null $seen résultat du contrôle de droits sur l'objet lié
     * @return array<string, mixed>
     */
    public static function present(int $linkId, ?array $seen, bool $canRemove, string $by, string $when): array
    {
        if ($seen === null) {
            return ['id' => $linkId, 'hidden' => true, 'canRemove' => $canRemove];
        }

        return [
            'id' => $linkId,
            'hidden' => false,
            'canRemove' => $canRemove,
            'type' => (string) $seen['type'],
            'typeLabel' => self::TYPES[(string) $seen['type']] ?? '',
            'title' => (string) $seen['title'],
            'sub' => (string) ($seen['sub'] ?? ''),
            'href' => (string) $seen['href'],
            'group' => (string) ($seen['group'] ?? 'autres'),
            'by' => $by,
            'when' => $when,
        ];
    }

    // ——— Citations dans une page ———

    public static function cleanLabel(string $raw): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', str_replace(['[', ']', '|', "\r", "\n"], ' ', strip_tags($raw))));

        return mb_substr($label, 0, self::LABEL_MAX);
    }

    public static function mentionToken(string $type, string|int $id, string $label): string
    {
        return '[[' . $type . ':' . $id . '|' . self::cleanLabel($label) . ']]';
    }

    /**
     * Citations d'un texte.
     *
     * @return list<array{raw: string, type: string, id: string, label: string}>
     */
    public static function mentions(string $text): array
    {
        if (!str_contains($text, '[[') || preg_match_all(self::MENTION_RE, $text, $m, PREG_SET_ORDER) < 1) {
            return [];
        }
        $out = [];
        foreach ($m as $hit) {
            $out[] = ['raw' => $hit[0], 'type' => $hit[1], 'id' => $hit[2], 'label' => trim($hit[3])];
        }

        return $out;
    }

    /**
     * Texte à l'enregistrement : une citation que l'auteur ne peut pas voir (ou au-delà du plafond) redevient son libellé
     * en clair. On ne grave jamais dans une page un lien que son auteur n'aurait pas pu poser.
     *
     * @param callable(string, string): bool $authorCanSee
     */
    public static function sanitizeMentions(string $text, callable $authorCanSee): string
    {
        $kept = 0;

        return (string) preg_replace_callback(self::MENTION_RE, static function (array $m) use ($authorCanSee, &$kept): string {
            $label = trim($m[3]);
            if ($kept >= self::MENTIONS_PER_SAVE_MAX || !$authorCanSee($m[1], $m[2])) {
                return $label;
            }
            ++$kept;

            return '[[' . $m[1] . ':' . $m[2] . '|' . self::cleanLabel($label) . ']]';
        }, $text);
    }

    /** Texte sans les marques de citation : chaque « [[type:id|libellé]] » redevient son libellé (étiquettes d'accessibilité). */
    public static function plain(string $text): string
    {
        return (string) preg_replace(self::MENTION_RE, '$3', $text);
    }

    /**
     * Texte d'une page en HTML, citations résolues pour CE lecteur : lien vers l'objet s'il le voit, « Lien masqué »
     * sinon. Le titre vient du serveur (jamais du libellé tapé), le reste du texte est échappé.
     *
     * @param array<string, array{title: string, href: string}|null> $resolved clé « type:id »
     */
    public static function renderMentions(string $text, array $resolved): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $plain = static fn (string $s): string => nl2br($esc($s));
        if (!str_contains($text, '[[') || preg_match_all(self::MENTION_RE, $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return $plain($text);
        }
        $out = '';
        $pos = 0;
        foreach ($m as $hit) {
            [$raw, $offset] = $hit[0];
            $out .= $plain(substr($text, $pos, $offset - $pos));
            $seen = $resolved[$hit[1][0] . ':' . $hit[2][0]] ?? null;
            $out .= $seen === null
                ? '<span class="jl-mention is-hidden" title="Lien masqué">Lien masqué</span>'
                : '<a class="jl-mention" href="' . $esc($seen['href']) . '">' . $esc($seen['title']) . '</a>';
            $pos = $offset + strlen($raw);
        }

        return $out . $plain(substr($text, $pos));
    }

    // ——— Liaisons d'unités ———

    /** Date « AAAA-MM-JJ » réelle, ou null. */
    public static function date(mixed $raw): ?string
    {
        $raw = trim((string) $raw);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $p) !== 1 || !checkdate((int) $p[2], (int) $p[3], (int) $p[1])) {
            return null;
        }

        return $raw;
    }

    /**
     * Formulaire de liaison nettoyé. Le partenaire et les droits du lecteur sont contrôlés ailleurs (JnetLinkService).
     *
     * @param array<string, mixed> $in kind, direction, starts_on, ends_on, note, grant_read
     * @return array{error: ?string, values: array{kind: string, direction: string, starts_on: ?string, ends_on: ?string, note: string, grant_read: bool}}
     */
    public static function normalizeUnitLink(array $in, string $today): array
    {
        $kind = (string) ($in['kind'] ?? '');
        $values = ['kind' => $kind, 'direction' => 'mutual', 'starts_on' => null, 'ends_on' => null, 'note' => '', 'grant_read' => false];
        if (!isset(self::UNIT_KINDS[$kind])) {
            return ['error' => 'Choisissez le type de liaison.', 'values' => $values];
        }
        $direction = (string) ($in['direction'] ?? 'mutual');
        if ($kind === 'partenaire') {
            $direction = 'mutual';
        } elseif (!in_array($direction, ['out', 'in'], true)) {
            return ['error' => 'Précisez le sens de la liaison.', 'values' => $values];
        }
        $values['direction'] = $direction;
        $start = trim((string) ($in['starts_on'] ?? ''));
        $end = trim((string) ($in['ends_on'] ?? ''));
        if ($start !== '' && ($values['starts_on'] = self::date($start)) === null) {
            return ['error' => 'La date de début n’est pas valide.', 'values' => $values];
        }
        if ($end !== '' && ($values['ends_on'] = self::date($end)) === null) {
            return ['error' => 'La date de fin n’est pas valide.', 'values' => $values];
        }
        if ($values['ends_on'] !== null && $values['ends_on'] < $today) {
            return ['error' => 'La date de fin est déjà passée.', 'values' => $values];
        }
        if ($values['starts_on'] !== null && $values['ends_on'] !== null && $values['ends_on'] < $values['starts_on']) {
            return ['error' => 'La fin ne peut pas précéder le début.', 'values' => $values];
        }
        $values['note'] = mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($in['note'] ?? '')))), 0, self::NOTE_MAX);
        $values['grant_read'] = !empty($in['grant_read']);

        return ['error' => null, 'values' => $values];
    }

    /**
     * Statut d'une liaison à une date : « échue » dès que la date de fin est passée ou que la liaison a été terminée à la
     * main ; « à venir » avant la date de début ; sinon « active ».
     *
     * @param array{starts_on?: ?string, ends_on?: ?string, ended_at?: ?string} $row
     */
    public static function statusOf(array $row, string $today): string
    {
        if (trim((string) ($row['ended_at'] ?? '')) !== '') {
            return 'expired';
        }
        $end = trim((string) ($row['ends_on'] ?? ''));
        if ($end !== '' && $end < $today) {
            return 'expired';
        }
        $start = trim((string) ($row['starts_on'] ?? ''));

        return $start !== '' && $start > $today ? 'planned' : 'active';
    }

    /** Seule une liaison active produit des effets (marquage, partage d'une information, lecture accordée). */
    public static function isEffective(string $status): bool
    {
        return $status === 'active';
    }

    /**
     * Phrase de la liaison vue de l'espace $self : « 1re Section appuie 2e Section ». $role est le rôle de $self
     * (out : il appuie, accueille ; in : il est appuyé, rattaché ; mutual : partenaire).
     */
    public static function sentence(string $kind, string $role, string $self, string $other): string
    {
        $verb = match ([$kind, $role]) {
            ['appui', 'out'] => 'appuie',
            ['appui', 'in'] => 'est appuyée par',
            ['rattachement', 'out'] => 'accueille, en rattachement temporaire,',
            ['rattachement', 'in'] => 'est rattachée temporairement à',
            default => 'est partenaire de',
        };

        return $self . ' ' . $verb . ' ' . $other;
    }

    /** Le rôle de l'autre extrémité : out et in s'inversent, mutual reste. */
    public static function oppositeRole(string $role): string
    {
        return match ($role) {
            'out' => 'in',
            'in' => 'out',
            default => 'mutual',
        };
    }

    /** « du 12/11/2026 au 20/11/2026 », « depuis le … », « jusqu'au … » ou « sans date de fin ». */
    public static function periodLabel(?string $start, ?string $end): string
    {
        $fmt = static fn (string $d): string => substr($d, 8, 2) . '/' . substr($d, 5, 2) . '/' . substr($d, 0, 4);
        $start = (string) $start;
        $end = (string) $end;

        return match (true) {
            $start !== '' && $end !== '' => 'du ' . $fmt($start) . ' au ' . $fmt($end),
            $start !== '' => 'depuis le ' . $fmt($start) . ', sans date de fin',
            $end !== '' => 'jusqu’au ' . $fmt($end),
            default => 'sans date de fin',
        };
    }
}
