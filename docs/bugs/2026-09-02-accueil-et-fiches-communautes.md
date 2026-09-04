# Accueil incomplet et fiches publiques en erreur

## Contexte

Signalé en production : l’accueil n’affiche pas toutes les communautés, et l’ouverture d’une fiche publique (`/c/{slug}`) échoue.

## Symptôme

1. L’accueil (et le registre) omettent certaines communautés pourtant visibles.
2. La fiche publique se ferme sur une erreur technique : mélange illégal de collations (`utf8mb4_bin` et `utf8mb4_unicode_ci`) sur une comparaison d’égalité.

## Cause

Deux causes distinctes, le même socle texte que le tableau de bord (erreur 1267) :

- Le registre excluait le tenant `id = 1` en le prenant pour le placeholder. En production, la première communauté réelle peut avoir cet identifiant. L’accueil ne gardait en plus que les logos, avec un plafond de dix.
- La fiche publique compare le slug (souvent en collation binaire) à une valeur PHP, puis compte les membres avec `COALESCE(statut profil, statut compte) = 'actif'`. Un `LOWER`/`TRIM`/`COALESCE` sur une colonne binaire n’est plus convertissable vers la collation de session.

## Correctif

- Le registre liste toutes les communautés sauf le tenant système `default` (filtre PHP, pas un identifiant figé).
- L’accueil affiche toutes les communautés du registre, logo ou initiales.
- Les comparaisons slug / e-mail / statut de la fiche publique (et les mêmes motifs ailleurs) passent par `SqlText`.
- La session MySQL des migrations applique la même collation que le portail.

## Fichiers touchés

- `app/Support/SqlText.php`
- `app/Repositories/TenantRepository.php`
- `app/Controllers/Web/HomeController.php`
- `views/home/index.php`
- `app/Repositories/UserRepository.php`
- Repositories / services avec `slug = ?`, `LOWER(TRIM(…))` ou e-mail lié
- `bootstrap/migration_pdo.php`, `run-migrations.php`, `bootstrap/migrations_web_ui.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE #384)

## Vérification

- Tests unitaires `SqlText` (nouveaux helpers) et listing registre (tenant id = 1 inclus, `default` exclu).
- Asset : `findBySlug` et `countActiveMembers` utilisent `SqlText`.
- Grep : plus de `LOWER(TRIM(…)) = ?` hors helper.
- PHP lint des fichiers modifiés.
- Tests journal UPDATE #384.

## Statut

Corrigé (à déployer en production). Suite : les mêmes comparaisons d’identifiants courts ailleurs (admin, formations, rôles) — voir `docs/bugs/2026-09-02-collations-slug-partout.md`.
