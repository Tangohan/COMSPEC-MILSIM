<?php

declare(strict_types=1);

namespace App\Services\Community;

use PDO;

/**
 * Libellé du niveau d'accès de base (slug `member`) : « Membre », comme le code, et non « Opérateur »,
 * posé par la migration 20260903090000_simplified_community_roles.
 *
 * Ne change ni le slug, ni les droits, ni la catégorie. Ne touche que les rôles dont le nom est encore
 * exactement celui de la semence : un nom choisi par la communauté n'est jamais écrasé.
 * Idempotent dans les deux sens.
 */
final class BaseRoleLabelAlignment
{
    public const SLUG = 'member';
    public const LABEL = 'Membre';
    public const LEGACY_LABEL = 'Opérateur';
    public const LABEL_EN = 'Member';
    public const LEGACY_LABEL_EN = 'Operator';

    /** @return int nombre de rôles renommés */
    public static function apply(PDO $pdo): int
    {
        return self::rename($pdo, self::LEGACY_LABEL, self::LABEL, self::LEGACY_LABEL_EN, self::LABEL_EN);
    }

    /** Remet le libellé de la semence d'origine (retour arrière). @return int nombre de rôles renommés */
    public static function revert(PDO $pdo): int
    {
        return self::rename($pdo, self::LABEL, self::LEGACY_LABEL, self::LABEL_EN, self::LEGACY_LABEL_EN);
    }

    private static function rename(PDO $pdo, string $fromName, string $toName, string $fromEn, string $toEn): int
    {
        $st = $pdo->prepare('UPDATE roles SET name = ? WHERE tenant_id IS NOT NULL AND slug = ? AND name = ?');
        $st->execute([$toName, self::SLUG, $fromName]);
        $renamed = $st->rowCount();

        if (TenantDefaultRoleDefinitions::rolesTableHasLabelEnColumn($pdo)) {
            $en = $pdo->prepare('UPDATE roles SET label_en = ? WHERE tenant_id IS NOT NULL AND slug = ? AND label_en = ?');
            $en->execute([$toEn, self::SLUG, $fromEn]);
        }

        return $renamed;
    }
}
