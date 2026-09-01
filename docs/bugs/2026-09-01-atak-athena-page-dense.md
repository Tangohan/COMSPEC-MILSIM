# 2026-09-01 — Page Athena du téléphone illisible

## Contexte
Sur le téléphone ATAK, l’application Athena sert de journal, d’alertes rapides, de comptes-rendus et de liaison.

## Symptôme
Tout était empilé sur un seul écran : huit filtres identiques, trois zones vides (notifications, journal, détail), puis une file de boutons dont une partie restait hors écran.

## Cause
Le panneau voulait tout montrer en même temps. Les filtres n’ouvraient pas d’écrans distincts ; les actions restaient toujours visibles.

## Correctif
Quatre vues : Journal, Alerter, Rapporter, Poste. Un seul fil de lecture, un filtre en liste, et les boutons seulement là où ils servent.

## Fichiers touchés
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_selectHome.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_selectFilter.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_selectTab.sqf`

## Vérification
Ouvrir Athena sur le téléphone : quatre boutons en haut. Journal = liste + détail. Alerter = contact / triage. Rapporter = FRAGO / photo. Poste = compte et appui.

## Statut
Corrigé (visible après rechargement du pack)
