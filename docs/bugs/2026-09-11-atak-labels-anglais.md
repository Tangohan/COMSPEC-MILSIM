# ATAK — libellés encore en anglais

**Date** : 2026-09-11  
**Statut** : corrigé (polish UI)

## Contexte

Après le recalage des comptes-rendus et du bandeau de liaison, plusieurs écrans ATAK affichaient encore des libellés anglais (From/Grid, TASK, Notifs écran, Remonter le temps).

## Symptôme

Sur le téléphone : détail d’ordre avec From/Grid/Time/Priority ; raccourci bureau et tiroir « TASK » ; menu ACE « Ordres C2 (TASK) » ; microcopy Sons / État / Athena en anglais ou jargon.

## Cause

Textes hérités d’IceMan / cTab / premiers libellés COMSPEC, non alignés sur le langage métier du portail.

## Correctif

Traduction ciblée des libellés visibles (sans changer les codes techniques sous-jacents) :

- détail ordre miroir : De / Grille / Heure / Type / Priorité ;
- raccourci bureau, tiroir et ACE : Ordres reçus ;
- Sons : Alertes à l’écran non/oui ;
- État : Dernière sync. terminal ; liste vide : Aucun ordre pour le moment ;
- Athena : Envoyer le temps de mission ;
- Comptes-rendus : types de formulaire en français (lbData inchangé) ;
- bandeau liaison un peu plus compact.

## Fichiers touchés

- `fn_athena_onOrderReceived.sqf`, `fn_athena_installDesktopShortcut.sqf`, `fn_initACE.sqf`
- `atak_athena/config.cpp`, `athena_page.hpp`, `sound_page.hpp`
- `fn_athena_updateSound.sqf`, `fn_athena_updateStatus.sqf`, `fn_athena_updateTask.sqf`
- `fn_athena_fixReportsLayout.sqf`, `fn_athena_updateLinkStrip.sqf`, `fn_updateStatusBadges.sqf`
- `DevDispatchCatalog.php` (UPDATE #508)

## Vérification

PHPUnit `DevDispatchCatalogTest`, `AtakReportsLayoutAssetTest` ; rebuild pack 1.5.45 / Athena 1.0.91.
