# Carte noire ATAK et codes Athena refusés

- Date : 2026-09-04
- Statut : corrigé

## Contexte

Sur le téléphone ATAK in-game, la fenêtre centrale restait noire alors que les menus, la boussole et la télémétrie répondaient. La connexion Athena était refusée. Aucun code n’apparaissait en générant depuis Arma, et un code « Lier le jeu » du poste n’était jamais accepté comme code de secours.

## Symptôme

- Carte Arma absente (fond noir) dans Terrain.
- Connexion Athena impossible (Steam non lié, pas de session).
- Bouton « Générer un code » : rien n’est affiché.
- Code de secours : tous refusés (y compris le code à 6 caractères du poste).

## Cause

1. La carte native est recouverte par l’écran web. Le trou visuel est « transparent » en CSS, mais le navigateur Arma ne laisse pas voir ce qui est derrière. Il faut poser la carte **au-dessus** de l’écran, dans le trou.
2. Le téléphone appelait des routes d’appairage qui n’existaient pas sur le poste. Le seul code réel était « Lier le jeu » (6 caractères). Le téléphone exigeait 12 caractères et parlait à une autre adresse.

## Correctif

- Recréer la carte native après l’affichage de l’écran web, pile dans la fenêtre centrale.
- Accepter le code « Lier le jeu » du poste dans Code de secours / Valider.
- Permettre de générer un code dans Arma puis de le valider sur le poste (Carte ATAK → Compte → Lier le jeu).

## Fichiers touchés

- `mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_webMapRaise.sqf`
- `fn_webMapShow.sqf`, `fn_webLayout.sqf`, `fn_webPageLoaded.sqf`, `phone.html`
- `fn_networkRecoveryCode.sqf`, `fn_networkRedeemPairingCode.sqf`
- `app/Services/Game/GameAtakPairingService.php`, `GameAtakPairingApiController.php`
- `app/Core/ContainerIntegrations.php` (enregistrement des services, sinon le poste refusait toute génération de code)
- `routes/web.php`, `config/tactical_api.php`, `views/atak.php`

## Vérification

- Tests unitaires `GameAtakPairingAssetTest`, `DevDispatchCatalogTest`.
- Contrôle visuel après rebuild du pack : carte visible, code du poste accepté.

## Statut

Corrigé — relancer Arma complètement après le nouveau pack. Le poste doit recevoir cette mise à jour pour que la génération et la validation des codes fonctionnent.
