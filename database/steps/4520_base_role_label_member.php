<?php

declare(strict_types=1);

/**
 * Étape « Niveau d'accès de base : libellé « Membre » au lieu de « Opérateur » » (Données).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo. La migration 20260903090000 avait nommé le niveau
 * `member` « Opérateur » ; le code et les nouvelles communautés disent « Membre ». Seuls les niveaux `member`
 * encore nommés exactement « Opérateur » sont renommés : un nom choisi par la communauté est conservé.
 * Slug, droits et attributions ne changent pas. Idempotente (une relance ne trouve plus rien).
 * Retour arrière : App\Services\Community\BaseRoleLabelAlignment::revert($pdo).
 */

if (!(new \App\Support\Migrations\SchemaInspector($pdo))->tableExists('roles')) {
    echo "  [OK] Pas de table roles : rien à renommer.\n";
} else {
    $baseRoleRenamed = \App\Services\Community\BaseRoleLabelAlignment::apply($pdo);
    echo "  [OK] Niveau de base « Membre » : {$baseRoleRenamed} communauté(s) renommée(s).\n";
}
