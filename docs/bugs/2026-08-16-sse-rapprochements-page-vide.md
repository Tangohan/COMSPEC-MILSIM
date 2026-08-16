# Rapprochements moteur — page vide

## Contexte

URL `/atak/sse/rapprochements` (nav « Rapprochements moteur »).

## Symptôme

La page « ne marche pas » : coque SSE visible éventuelle, **contenu principal vide**.

## Cause

`views/atak/sse/suggestions.php` faisait `ob_get_clean()` dans `$content`,
alors que `_layout.php` n’injecte que `$sseContent`.

## Correctif

Assigner `$sseContent` avant d’inclure le layout.

## Fichiers touchés

- `views/atak/sse/suggestions.php`

## Vérification

Ouvrir `/atak/sse/rapprochements` : titre « Rapprochements moteur », file 25.01, signaux 25.02.

## Statut

corrigé (à déployer)
