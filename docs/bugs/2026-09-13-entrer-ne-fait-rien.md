# Athena — Entrer ne faisait rien

## Contexte

Écran Connexion Athena : bandeau « Compte trouvé — Entrer », fiche Jake / TA1 visible.

## Symptôme

Clic sur Entrer (surtout le bandeau) : aucun changement d’écran, alors que le compte est bien trouvé.

## Cause

Le bandeau (IDC 9734) appelait `homeAction` → `authFocus`, qui sortait dès que l’auth était READY **sans** ouvrir le canal poste. Seul le bouton vert 9801 appelait vraiment `enter`.

## Correctif

- `homeAction` : si compte trouvé / prêt → `['enter']` authAction.
- `enter` : relecture bootstrap + `applyHomeLayout` pour passer à Connecté.

## Fichiers

- `fn_athena_homeAction.sqf`
- `fn_athena_authAction.sqf`
- Athena 1.0.110

## Statut

corrigé
