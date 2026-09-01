# Connexion Athena — impossible à rouvrir une fois fermée

## Contexte

Fenêtre de connexion Athena au lancement de la session (e-mail, code, Steam, puis Environnement prêt).

## Symptôme

Fermer la fenêtre (Échap ou Entrer) ne laissait aucun moyen de la rouvrir. Il fallait relancer la mission.

## Cause

L’écran ne s’ouvrait qu’une fois au démarrage. Aucun bouton du menu pause, du téléphone ou d’ACE n’appelait à nouveau cette fenêtre.

## Correctif

- Menu pause : bouton **Connexion Athena**
- Gestion du pack : **Ouvrir la connexion Athena**
- Téléphone : **Compte Athena** rouvre la même fenêtre
- Menu ACE Overwatch (s’il est activé) : **Connexion Athena**

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_onInterruptLoad.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_openLogin.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_accountLinkShow.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pauseManagerJSDialog.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/web/pause_manager.html`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_showLinkDialog.sqf`
- `tests/Unit/OverwatchAthenaLoginReopenAssetTest.php`

## Vérification

Pack Overwatch à jour, relancer Arma. Fermer la connexion, puis Échap → Connexion Athena : la fenêtre revient.

## Statut

Corrigé (rebuild du pack jeu requis)
