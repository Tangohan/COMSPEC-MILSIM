<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Support\JnetSpaceTree;

/**
 * Réglages propres à un espace d'unité JNET et droits qui en découlent (logique pure, testée sans base).
 *
 * Règle de fond : un réglage d'espace ne change jamais les droits généraux de la plateforme. Il peut seulement
 * - restreindre (publication réservée, lecture limitée aux unités listées, droit « lecture seule » sur une unité),
 * - ou ouvrir les outils de CET espace (tâches, pages, commentaires, votes, réponses) à des gens qui voient déjà l'unité
 *   par l'ORBAT (le contrôleur refuse d'abord tout lecteur qui n'a pas l'unité dans son arbre filtré).
 * Publier un échange au nom de l'unité n'est jamais ouvert par un réglage : la règle de l'ORBAT (JnetSpaceTree::canPostIn)
 * reste le plafond, le réglage « publication réservée à » ne fait que la resserrer.
 */
final class JnetSpaceRules
{
    /** Droit par unité autorisée, du plus faible au plus fort. */
    public const ACCESS_LEVELS = [
        'read' => 'Lecture seule',
        'write' => 'Lecture et écriture',
        'lead' => 'Chef de module',
    ];

    /** Qui peut publier un échange au nom de l'espace. */
    public const POST_MIN = [
        'member' => 'Tous les membres',
        'team' => 'Chefs d’équipe et au-dessus',
        'unit' => 'Chefs d’unité uniquement',
    ];

    public const ACCESS_MAX = 40;
    public const MANAGERS_MAX = 20;

    /**
     * Réglages par défaut : rien de restreint, rien d'automatique.
     *
     * @return array{require_ack: bool, auto_relay: bool, post_min: string, access: array<int, string>, managers: list<int>}
     */
    public static function defaults(): array
    {
        return ['require_ack' => false, 'auto_relay' => false, 'post_min' => 'member', 'access' => [], 'managers' => []];
    }

    public static function postMin(mixed $raw): string
    {
        $raw = (string) $raw;

        return isset(self::POST_MIN[$raw]) ? $raw : 'member';
    }

    /**
     * Lignes d'accès nettoyées : unités connues de l'arbre (ou 0 = toute l'organisation), pas l'espace lui-même, droit
     * connu, au plus ACCESS_MAX lignes.
     *
     * @param array<int|string, mixed> $rows unité => droit
     * @return array<int, string>
     */
    public static function normalizeAccess(array $rows, JnetSpaceTree $tree, int $spaceId): array
    {
        $out = [];
        foreach ($rows as $unit => $level) {
            $unit = (int) $unit;
            $level = (string) $level;
            if ($unit === $spaceId || !isset(self::ACCESS_LEVELS[$level]) || count($out) >= self::ACCESS_MAX) {
                continue;
            }
            if ($unit !== JnetSpaceTree::ORG && !$tree->has($unit)) {
                continue;
            }
            $out[$unit] = $level;
        }
        ksort($out);

        return $out;
    }

    /**
     * Lignes d'accès à partir du formulaire : « access[unité] » (droit, ou « remove »), plus l'ajout éventuel.
     *
     * @return array<int, string>
     */
    public static function accessFromForm(mixed $posted, int|string|null $addUnit, mixed $addLevel, JnetSpaceTree $tree, int $spaceId): array
    {
        $rows = [];
        foreach (is_array($posted) ? $posted : [] as $unit => $level) {
            if ((string) $level !== 'remove') {
                $rows[(int) $unit] = (string) $level;
            }
        }
        if ($addUnit !== null && $addUnit !== '' && (string) $addUnit !== 'none' && isset(self::ACCESS_LEVELS[(string) $addLevel])) {
            $rows[(int) $addUnit] = (string) $addLevel;
        }

        return self::normalizeAccess($rows, $tree, $spaceId);
    }

    /**
     * Gestionnaires désignés par indicatif (séparés par virgule, point-virgule ou retour à la ligne), parmi les personnes
     * autorisées (membres de l'unité, de ses sous-unités et de son encadrement). Inconnus et doublons ignorés.
     *
     * @param array<int, string> $callsignsById identifiant => indicatif des personnes autorisées
     * @return array{ids: list<int>, unknown: list<string>}
     */
    public static function managersFromCallsigns(string $raw, array $callsignsById): array
    {
        $byCallsign = [];
        foreach ($callsignsById as $id => $callsign) {
            $key = self::callsignKey((string) $callsign);
            if ($key !== '' && !isset($byCallsign[$key])) {
                $byCallsign[$key] = (int) $id;
            }
        }
        $ids = [];
        $unknown = [];
        foreach (preg_split('/[,;\r\n]+/u', $raw) ?: [] as $part) {
            $label = trim($part);
            if ($label === '') {
                continue;
            }
            $key = self::callsignKey($label);
            if (isset($byCallsign[$key])) {
                $ids[$byCallsign[$key]] = $byCallsign[$key];
            } else {
                $unknown[] = mb_substr($label, 0, 40);
            }
        }

        return ['ids' => array_slice(array_values($ids), 0, self::MANAGERS_MAX), 'unknown' => array_values(array_unique($unknown))];
    }

    private static function callsignKey(string $raw): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $raw)));
    }

    /**
     * Unités concernées par le droit le plus fort de ce lecteur dans la liste d'accès, ou null.
     *
     * @param list<int> $viewerUnitIds unités d'appartenance du lecteur
     * @param array<int, string> $access
     */
    public static function grantLevel(JnetSpaceTree $tree, array $viewerUnitIds, array $access): ?string
    {
        $best = null;
        $rank = ['read' => 1, 'write' => 2, 'lead' => 3];
        foreach ($access as $grantee => $level) {
            $matches = $grantee === JnetSpaceTree::ORG;
            if (!$matches) {
                foreach ($viewerUnitIds as $v) {
                    if ($tree->has($v) && $tree->isWithin($v, (int) $grantee)) {
                        $matches = true;
                        break;
                    }
                }
            }
            if ($matches && ($best === null || ($rank[$level] ?? 0) > $rank[$best])) {
                $best = $level;
            }
        }

        return $best;
    }

    /** Le lecteur est-il de la filiation de l'espace (membre de l'unité, d'une sous-unité ou d'une unité au-dessus) ? */
    public static function inLineage(JnetSpaceTree $tree, array $viewerUnitIds, int $spaceId): bool
    {
        foreach ($viewerUnitIds as $v) {
            if ($tree->has($v) && ($tree->isWithin($spaceId, $v) || $tree->isWithin($v, $spaceId))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Droits du lecteur dans l'espace, une fois les réglages appliqués.
     *
     * - read : peut ouvrir l'espace. Sans ligne d'accès, comme avant (tout lecteur qui voit l'unité par l'ORBAT) ; avec
     *   des lignes, seulement la filiation de l'espace, les lecteurs de toute l'organisation, le chef, les gestionnaires
     *   et les unités listées.
     * - write : peut écrire dans les outils (tâches, pages, commentaires, votes, réponse de présence). Droit de base
     *   (JnetSpaceTree::canPostIn) ; une ligne « lecture seule » le retire, une ligne « lecture et écriture » ou
     *   « chef de module » ou la désignation comme gestionnaire l'accorde.
     * - lead : modère les outils (retire les tâches et pages des autres).
     * - publish : peut publier un échange au nom de l'unité : droit de base, resserré par « publication réservée à ».
     *
     * @param list<int> $viewerUnitIds
     * @param list<int> $commandedUnitIds unités que le lecteur commande dans l'ORBAT
     * @param array{require_ack?: bool, auto_relay?: bool, post_min?: string, access?: array<int, string>, managers?: list<int>} $rules
     * @return array{read: bool, write: bool, lead: bool, publish: bool, chief: bool}
     */
    public static function rights(
        JnetSpaceTree $tree,
        int $spaceId,
        int $userId,
        array $viewerUnitIds,
        array $commandedUnitIds,
        bool $orgWriter,
        bool $orgReader,
        bool $isChief,
        array $rules
    ): array {
        $access = is_array($rules['access'] ?? null) ? $rules['access'] : [];
        $managers = array_map('intval', (array) ($rules['managers'] ?? []));
        $postMin = self::postMin($rules['post_min'] ?? 'member');
        $baseWrite = $tree->has($spaceId) && $tree->canPostIn($viewerUnitIds, $spaceId, $orgWriter);
        if ($isChief) {
            return ['read' => $tree->has($spaceId), 'write' => $baseWrite, 'lead' => true, 'publish' => $baseWrite, 'chief' => true];
        }
        $grant = self::grantLevel($tree, $viewerUnitIds, $access);
        $manager = in_array($userId, $managers, true);
        $lead = $manager || $grant === 'lead';
        $read = $tree->has($spaceId)
            && ($access === [] || $orgReader || $lead || $grant !== null || self::inLineage($tree, $viewerUnitIds, $spaceId));
        $write = $read && ($lead || ($grant === 'read' ? false : ($baseWrite || $grant === 'write')));

        return [
            'read' => $read,
            'write' => $write,
            'lead' => $read && $lead,
            'publish' => $baseWrite && $read && self::meetsPostMin($postMin, $tree, $spaceId, $commandedUnitIds, $orgWriter),
            'chief' => false,
        ];
    }

    /**
     * « Publication réservée à » : 'member' = qui peut déjà publier ; 'team' = commande l'espace, une de ses sous-unités
     * ou une unité au-dessus ; 'unit' = commande l'espace ou une unité au-dessus (le chef de l'unité).
     *
     * @param list<int> $commandedUnitIds
     */
    public static function meetsPostMin(string $postMin, JnetSpaceTree $tree, int $spaceId, array $commandedUnitIds, bool $orgWriter): bool
    {
        if ($postMin === 'member' || $orgWriter) {
            return true;
        }
        foreach ($commandedUnitIds as $c) {
            if (!$tree->has($c)) {
                continue;
            }
            if ($tree->isWithin($spaceId, $c) || ($postMin === 'team' && $tree->isWithin($c, $spaceId))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ordres que le lecteur doit prendre en compte avant d'utiliser l'espace : échanges à accuser, pas encore lus, publiés
     * par quelqu'un d'autre (l'auteur a toujours « lu » son échange), de type « ordre » : les ordres sont les informations
     * importantes de l'espace, ce sont les seuls échanges qui demandent un accusé de lecture.
     *
     * @param list<array<string, mixed>> $exchanges échanges décorés (reçus puis internes)
     * @return list<array<string, mixed>>
     */
    public static function pendingOrders(array $exchanges, int $limit = 5): array
    {
        $out = [];
        foreach ($exchanges as $e) {
            if (($e['kind'] ?? '') === 'ordre' && !empty($e['requiresAck']) && empty($e['readByMe']) && !isset($out[(int) $e['id']])) {
                $out[(int) $e['id']] = $e;
            }
        }

        return array_slice(array_values($out), 0, max(1, $limit));
    }
}
