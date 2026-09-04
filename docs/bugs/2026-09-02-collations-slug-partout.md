# Comparaisons d’identifiants courts — mélange de collations partout

## Contexte

Après le correctif des fiches publiques et de l’accueil, le même mélange de collations (erreur 1267) pouvait encore fermer des pages d’administration, de formations, de rôles et des scripts de mise à jour.

## Symptôme

Une recherche par identifiant court (formation, rôle, permission, mission, modèle de document, etc.) échoue avec un mélange illégal de collations, alors que la valeur saisie est pourtant la bonne.

## Cause

Même socle que l’accueil et le tableau de bord : des colonnes d’identifiants courts en collation binaire sont comparées à une valeur PHP (ou à un libellé figé) qui suit la collation de session. MariaDB refuse l’égalité.

Les affectations (`SET identifiant = …`) ne sont pas concernées : seul le filtre de recherche (`WHERE` / jointure) mélange les collations.

## Correctif

Toutes les comparaisons restantes d’identifiants courts (et les filtres de statut / adresse associés dans les mêmes requêtes) passent par le helper unique `SqlText` : égalité paramétrée, égalité à un libellé figé, listes `IN`, et recherche `LIKE` le cas échéant.

## Fichiers touchés

- `app/Support/SqlText.php` (`inPlaceholders`, `likeLiteral`, identifiants avec point ou tiret)
- Repositories runtime : vestiaire, organigramme, modèles courrier, postes, messages internes, formations legacy, missions inter-équipes, médailles, pédagogie, probation, etc.
- Services : bascule de profil communauté, bootstrap, catalogue de rôles, public cible doctrine
- Contrôleur administration des fonctions
- Scripts et migrations bootstrap / `run-migrations.php` (recherche d’identifiant, pas les écritures)
- `app/Support/DevDispatchCatalog.php` (UPDATE #385)
- Tests : `SqlTextTest`, `TenantPublicListingCollationTest`, `DevDispatchCatalogTest`

## Vérification

- Grep : plus de `slug = ?` en filtre ; il reste uniquement les `SET … slug = ?`.
- Test d’actif : les repositories / services / contrôleurs runtime n’ont plus de comparaison brute.
- PHP lint des fichiers modifiés.
- Tests unitaires `SqlText` (IN / LIKE) et journal UPDATE #385.

## Statut

Corrigé (à déployer en production).
