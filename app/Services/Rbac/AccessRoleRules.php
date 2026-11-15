<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Authorization\PermissionImplication;
use App\Authorization\SystemReservedPermissions;
use App\Services\AccessControl\FunctionBasedAccessCatalog;
use App\Authorization\TenantPermissionCatalog;

/**
 * Règles pures des niveaux d’accès (aucun accès base) : aperçu d’impact, comparaison, anti-escalade,
 * protection du dernier administrateur, modèles prêts à l’emploi.
 * Les entrées sont des tableaux déjà filtrés sur la communauté par l’appelant.
 */
final class AccessRoleRules
{
    /** Droit dont au moins une personne doit rester titulaire : sans lui, plus personne ne gère les accès. */
    public const ADMIN_KEY = 'admin.roles.manage';

    /**
     * Droits obtenus par chaque personne, tous niveaux confondus.
     *
     * @param array<int, list<string>> $rolePermissions roleId => slugs
     * @param array<int, list<int>> $roleMembers roleId => userIds
     * @return array<int, list<string>> userId => slugs
     */
    public static function rightsByUser(array $rolePermissions, array $roleMembers): array
    {
        $out = [];
        foreach ($roleMembers as $roleId => $userIds) {
            foreach ($userIds as $uid) {
                $out[(int) $uid] = array_values(array_unique(array_merge(
                    $out[(int) $uid] ?? [],
                    $rolePermissions[(int) $roleId] ?? []
                )));
            }
        }

        return $out;
    }

    /**
     * Ce que l’enregistrement ferait gagner ou perdre aux titulaires du niveau.
     * Un droit déjà donné par un autre niveau de la personne n’est ni gagné ni perdu.
     *
     * @param array<int, list<string>> $rolePermissions
     * @param array<int, list<int>> $roleMembers
     * @param list<string> $newSlugs droits voulus pour le niveau
     * @return array{gain: array<string, list<int>>, lose: array<string, list<int>>, members: int}
     */
    public static function impact(int $roleId, array $newSlugs, array $rolePermissions, array $roleMembers): array
    {
        $old = $rolePermissions[$roleId] ?? [];
        $added = array_values(array_diff($newSlugs, $old));
        $removed = array_values(array_diff($old, $newSlugs));
        $members = array_values(array_unique(array_map('intval', $roleMembers[$roleId] ?? [])));
        $others = $rolePermissions;
        unset($others[$roleId]);
        $otherRights = self::rightsByUser($others, array_diff_key($roleMembers, [$roleId => true]));
        $gain = [];
        $lose = [];
        foreach ($members as $uid) {
            $held = $otherRights[$uid] ?? [];
            foreach ($added as $slug) {
                if (!in_array($slug, $held, true)) {
                    $gain[$slug][] = $uid;
                }
            }
            foreach ($removed as $slug) {
                if (!in_array($slug, $held, true)) {
                    $lose[$slug][] = $uid;
                }
            }
        }
        ksort($gain);
        ksort($lose);

        return ['gain' => $gain, 'lose' => $lose, 'members' => count($members)];
    }

    /**
     * Comparaison de deux niveaux.
     *
     * @param list<string> $a
     * @param list<string> $b
     * @return array{only_a: list<string>, only_b: list<string>, common: list<string>}
     */
    public static function compare(array $a, array $b): array
    {
        $onlyA = array_values(array_diff($a, $b));
        $onlyB = array_values(array_diff($b, $a));
        $common = array_values(array_intersect($a, $b));
        sort($onlyA);
        sort($onlyB);
        sort($common);

        return ['only_a' => $onlyA, 'only_b' => $onlyB, 'common' => $common];
    }

    /**
     * Droits que la personne qui agit n’a pas et qu’elle ajouterait : escalade refusée.
     *
     * @param list<string> $oldSlugs
     * @param list<string> $newSlugs
     * @param callable(string): bool $actorHolds
     * @return list<string>
     */
    public static function escalation(array $oldSlugs, array $newSlugs, callable $actorHolds): array
    {
        $out = [];
        foreach (array_diff($newSlugs, $oldSlugs) as $slug) {
            if (!$actorHolds((string) $slug)) {
                $out[] = (string) $slug;
            }
        }
        sort($out);

        return $out;
    }

    /**
     * Personnes qui détiennent le droit-clé après un changement.
     *
     * @param array<int, list<string>> $rolePermissions
     * @param array<int, list<int>> $roleMembers
     * @param array{type: 'save'|'delete', role_id: int, slugs?: list<string>} $change
     * @return list<int>
     */
    public static function adminHoldersAfter(array $rolePermissions, array $roleMembers, array $change, string $key = self::ADMIN_KEY): array
    {
        if ($change['type'] === 'delete') {
            unset($rolePermissions[$change['role_id']], $roleMembers[$change['role_id']]);
        } else {
            $rolePermissions[$change['role_id']] = $change['slugs'] ?? [];
        }
        $holders = [];
        foreach (self::rightsByUser($rolePermissions, $roleMembers) as $uid => $slugs) {
            if (PermissionImplication::isGranted($slugs, $key)) {
                $holders[] = (int) $uid;
            }
        }
        sort($holders);

        return $holders;
    }

    /**
     * Le changement retirerait-il la dernière personne capable de gérer les accès ?
     *
     * @param array<int, list<string>> $rolePermissions
     * @param array<int, list<int>> $roleMembers
     * @param array{type: 'save'|'delete', role_id: int, slugs?: list<string>} $change
     */
    public static function removesLastAdmin(array $rolePermissions, array $roleMembers, array $change): bool
    {
        $before = self::adminHoldersAfter($rolePermissions, $roleMembers, ['type' => 'save', 'role_id' => $change['role_id'], 'slugs' => $rolePermissions[$change['role_id']] ?? []]);
        if ($before === []) {
            return false;
        }

        return self::adminHoldersAfter($rolePermissions, $roleMembers, $change) === [];
    }

    /**
     * Plan de copie ou de déplacement de membres d'un niveau vers un autre.
     * Seuls les titulaires du niveau source sont retenus (un identifiant étranger est ignoré, jamais appliqué).
     *
     * @param list<int> $sourceMembers
     * @param list<int> $targetMembers
     * @param list<int>|null $selected null = tous les titulaires du niveau source
     * @return array{receive: list<int>, already: list<int>, ignored: list<int>, leave: list<int>}
     */
    public static function planTransfer(array $sourceMembers, array $targetMembers, ?array $selected, bool $move): array
    {
        $source = array_values(array_unique(array_map('intval', $sourceMembers)));
        $target = array_flip(array_map('intval', $targetMembers));
        $wanted = $selected === null ? $source : array_values(array_unique(array_map('intval', $selected)));
        $ignored = array_values(array_diff($wanted, $source));
        $picked = array_values(array_intersect($wanted, $source));
        $receive = [];
        $already = [];
        foreach ($picked as $uid) {
            if (isset($target[$uid])) {
                $already[] = $uid;
            } else {
                $receive[] = $uid;
            }
        }

        return ['receive' => $receive, 'already' => $already, 'ignored' => $ignored, 'leave' => $move ? $picked : []];
    }

    /**
     * Un déplacement retirerait-il le droit-clé à la dernière personne qui le tient ?
     *
     * @param array<int, list<string>> $rolePermissions
     * @param array<int, list<int>> $roleMembers avant
     * @param array<int, list<int>> $newRoleMembers après
     */
    public static function membersChangeRemovesLastAdmin(array $rolePermissions, array $roleMembers, array $newRoleMembers): bool
    {
        $holders = static function (array $members) use ($rolePermissions): array {
            $out = [];
            foreach (self::rightsByUser($rolePermissions, $members) as $uid => $slugs) {
                if (PermissionImplication::isGranted($slugs, self::ADMIN_KEY)) {
                    $out[] = (int) $uid;
                }
            }

            return $out;
        };

        return $holders($roleMembers) !== [] && $holders($newRoleMembers) === [];
    }

    /**
     * Incohérences d’une sélection : droit coché sans les droits qu’il suppose.
     *
     * @param list<string> $slugs
     * @return array<string, list<string>> droit => droits supposés manquants
     */
    public static function missingRequirements(array $slugs): array
    {
        $out = [];
        foreach ($slugs as $slug) {
            $missing = array_values(array_diff(AccessRightsCatalog::requiresFor($slug), $slugs));
            if ($missing !== []) {
                $out[$slug] = $missing;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Modèles de niveau prêts à l’emploi (hors modèles de départ déjà présents).
     *
     * @return array<string, array{key: string, name: string, description: string, slugs: list<string>}>
     */
    public static function templates(): array
    {
        $catalog = array_flip(TenantPermissionCatalog::allSlugs());
        $notes = [
            FunctionBasedAccessCatalog::TRAINER => 'Crée et corrige les formations, délivre les certifications. Lecture du reste.',
            FunctionBasedAccessCatalog::RECRUITER => 'Traite les candidatures, invite et suit l’intégration des nouveaux.',
            FunctionBasedAccessCatalog::HR_MANAGER => 'Tient les dossiers : grades, affectations, statuts, annuaire, numéros de membre.',
            FunctionBasedAccessCatalog::DEPUTY_MANAGER => 'Seconde le Gestionnaire : documents, membres, opérations, médias.',
        ];
        $out = [];
        foreach (FunctionBasedAccessCatalog::roles() as $key => $def) {
            if (!isset($notes[$key])) {
                continue;
            }
            $slugs = [];
            foreach ($def['permission_slugs'] as $slug) {
                if (isset($catalog[$slug]) && !SystemReservedPermissions::isReserved($slug)) {
                    $slugs[] = $slug;
                }
            }
            $out[$key] = ['key' => $key, 'name' => $def['label'], 'description' => $notes[$key], 'slugs' => array_values(array_unique($slugs)), 'rights' => array_values(array_unique($slugs))];
        }

        return $out;
    }
}
