# Mises à jour du site : la version choisie ne s'ouvrait jamais

## Contexte
Administration du site, page « Mises à jour plateforme » (`/admin/system/updates`).

## Symptôme
Cliquer sur une version (ou revenir sur la page après un envoi, un déploiement, un retour arrière) n'affichait jamais son détail : fichiers, journal propre à la version.

## Cause
Le contrôleur lisait `?release=` via le tableau privé de la requête au lieu de `Request::query()`. L'opérateur `??` masquait l'erreur d'accès et renvoyait toujours 0.

## Correctif
`(int) $request->query('release', 0)`. Les envois, déploiements et retours arrière sont aussi inscrits au journal d'audit.

## Fichiers touchés
- `app/Controllers/Admin/System/SystemUpdatesController.php`

## Vérification
`tests/Unit/AdminPlatformToolsTest.php` (le motif fautif ne doit plus apparaître). À vérifier en ligne : ouvrir une version depuis la liste.

## Statut
Corrigé.
