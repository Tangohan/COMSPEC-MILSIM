# Chevauchement de l’écran Comptes-rendus (téléphone ATAK)

## Contexte

Sur le téléphone ATAK, l’application Comptes-rendus (Reports) affichait Inbox / New, un grand rectangle noir vide, Localiser et Effacer coupés en deux, puis « No reports received. » par-dessus, avec Retour tout en bas.

## Symptôme

Les boutons Reçus / Nouveau, la liste, Localiser / Effacer et le texte vide se marchaient dessus. Une large zone noire occupait le milieu de l’écran. Les actions du bas n’étaient plus cliquables proprement.

## Cause

Deux effets se cumulaient.

1. La page est d’abord dessinée à la taille pleine, puis le menu du téléphone se rétrécit à la zone utile. La liste et le détail gardaient leurs anciennes positions et recouvraient Localiser / Effacer.
2. L’écran Athena prenait cette page pour la sienne (mêmes numéros de contrôles) et y déplaçait sa liste, ce qui ajoutait le rectangle noir au milieu.

## Correctif

- Athena ne s’applique plus que sur sa propre page.
- À l’ouverture de Comptes-rendus, et après le calage du menu, les blocs sont replacés : titre, Reçus / Nouveau, liste, Localiser / Effacer, détail, sans recouvrement.
- Sur l’onglet Nouveau, un seul formulaire est visible (TIC, Eagle Down, bilan, FRAGO ou SALUTE). Les autres ne restent plus empilés.
- Athena est masquée tant que Comptes-rendus est ouvert.
- Libellés en français. La barre du bas montre Retour, Localiser et Effacer côte à côte.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_resolveAthenaGroup.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_fixReportsLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installReportsLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`

## Vérification

Tests unitaires `AtakReportsLayoutAssetTest`. Contrôle visuel en jeu après reconstruction du pack : titre, onglets, liste, boutons et message vide sur des lignes distinctes. Non vérifié ici dans Arma (rebuild du pack nécessaire).

## Statut

Corrigé (pack à reconstruire).
