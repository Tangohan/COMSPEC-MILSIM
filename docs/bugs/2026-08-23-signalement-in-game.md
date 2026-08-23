# Signalement in-game inopérant

## Contexte

Échap → gestion du mod → **Signaler un problème** (ou ACE) n’envoyait rien, ou n’ouvrait pas le bon formulaire.

## Symptôme

Clic sans fenêtre, fenêtre qui n’envoie pas, ou message d’échec générique. Le joueur croit souvent que ça part sur Discord.

## Cause

1. Le formulaire était ouvert **en enfant du panneau HTML** (idd 9979, navigateur) : `createDisplay` y échoue souvent.
2. Le JSON du journal (guillemets, retours ligne) était collé brut dans le POST → requête invalide, refus côté service.
3. Payload `callExtension` trop gros (journal 12 Ko + contexte).
4. Les textes renvoyaient vers ACE, alors que les menus ACE Overwatch sont **désactivés** par défaut.

## Correctif

- Fermer le panneau gestion, ouvrir le formulaire sur le menu Échap / le jeu.
- Journal lu par la DLL (échappé) ; payload SQF court.
- Message d’échec actionnable ; textes : Échap → gestion du mod.

## Fichiers touchés

- `connect/functions/fn_bugReportShow.sqf`, `fn_bugReportSubmit.sqf`, `fn_reportDiag.sqf`
- `connect/web/pause_manager.html`, `display_bug_report.hpp`
- `COMSPECExtension/Extension.cs`

## Vérification

1. Rebuild DLL + PBO connect, quitter Arma.
2. Échap → gestion du mod → Signaler un problème → formulaire « Signaler un problème ».
3. Envoyer un texte court → accusé « transmis à l’équipe ».
4. Côté portail : journal **Signalements mod**.

## Statut

`corrigé à vérifier en jeu`
