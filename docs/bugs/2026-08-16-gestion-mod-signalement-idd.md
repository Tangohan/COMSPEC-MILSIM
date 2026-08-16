# Panneau « Gestion du mod » inerte + signalement cassé

## Contexte

Menu Échap → **COMSPEC Overwatch — gestion du mod** : liaison Athena « Hors ligne », outils sans effet apparent. **Signaler un problème** (ACE) n’ouvre pas / n’envoie pas correctement. Capture : version affichée **1.4.14**, extension OK.

## Symptôme

- Resync / marqueurs / menus ACE semblent « ne rien faire » (surtout hors ligne).
- Formulaire de signalement absent, mauvais écran, ou envoi impossible.

## Cause

1. **Collision d’`idd` 9989** : `COMSPEC_BugReport_Dialog` et `COMSPEC_OrderCompose_Dialog` partageaient le même idd. En Arma, le second écrase le premier → le signalement charge la mauvesse fenêtre (ou des contrôles absents).
2. **Collision d’`idd` 9988** : SALUTE vs demande d’appui aérien (même risque).
3. Hors ligne : les outils de sync ne reconnectent pas Athena ; aucun bouton « Reconnecter » ni « Signaler » dans le panneau ; feedback trop faible.
4. Version **1.4.14** en jeu = PBO non rechargé (sources déjà en 1.4.15+).

## Correctif (1.4.16)

- Bug report → `idd = 9992` ; SALUTE → `idd = 9993`.
- Ouverture signalement : parent = panneau gestion / tablette, sinon `createDialog`.
- Panneau gestion : **Reconnecter Athena**, **Signaler un problème**, messages clairs si hors ligne, détail de statut, âge de dernière sync corrigé.
- Réactivation Overwatch / Menus ACE déclenche reconnect / `initACE`.

## Fichiers touchés

- `display_bug_report.hpp`, `display_salute.hpp`
- `fn_bugReportShow.sqf`, `fn_bugReportSubmit.sqf`, `fn_saluteDialogSubmit.sqf`
- `fn_pauseManagerJSDialog.sqf`, `fn_pauseManagerPageLoaded.sqf`
- `web/pause_manager.html`
- `connect/config.cpp` (1.4.16)

## Vérification

1. Rebuild `comspec_overwatch_connect.pbo`, redémarrer Arma avec le mod à jour.
2. Gestion du mod → version **1.4.16**.
3. **Signaler un problème** (ACE ou panneau) → formulaire « Signaler un problème » (pas la rédaction d’ordre).
4. Hors ligne → « Reconnecter Athena » ; puis resync / marqueurs.
5. Envoi signalement → accusé Athena (réseau + extension OK).

## Statut

corrigé (sources — recharger le PBO connect)
