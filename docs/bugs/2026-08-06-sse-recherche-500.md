# 500 sur `/atak/sse/recherche`

## Contexte

Recherche globale du bureau SSE (`?q=test`) → page d’erreur 500 « Incident technique ».

## Symptôme

- URL : `/public/atak/sse/recherche?q=…`
- Page 500 Athena (réf. incident visible)
- Le reste du portail pouvait encore s’afficher (barre de recherche utilisable)

## Cause

`SseWorkspaceService::globalSearch()` interroge plusieurs sources (identités, sites, dossiers, toiles, Pré-SSE) **sans isolation**. Une source indisponible (table absente, migration d’intérêt non tolérante, etc.) faisait échouer **toute** la page.

## Correctif

- Isolation `try/catch` par source dans `globalSearch`
- Filet dans `SsePortalController::search` (flash métier, page résultats vide plutôt que 500)
- Migration Pré-SSE encapsulée (ne bloque plus le boot du dépôt)
- Retrait des listes décoratives Théâtre / Mission / Classification (aucune filtre réel) + message « aucun résultat » corrigé

## Fichiers touchés

- `app/Services/Sse/SseWorkspaceService.php`
- `app/Controllers/Web/SsePortalController.php`
- `app/Repositories/SseInterestCaseRepository.php`
- `views/atak/sse/search.php`
- `views/atak/sse/_layout.php`

## Vérification

- [ ] Déployer PHP sur athena
- [ ] `/atak/sse/recherche?q=test` → 200 (liste ou « Aucun résultat », pas 500)
- [ ] Plus de listes Théâtre/Mission/Classification dans la barre haute

## Statut

corrigé — déploiement serveur requis
