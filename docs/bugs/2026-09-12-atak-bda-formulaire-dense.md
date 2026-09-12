# Formulaire BDA trop dense sur le téléphone

## Contexte
12 septembre 2026. Application Comptes-rendus → Nouveau → Bilan des dégâts, et app BDA IceMan autonome.

## Symptôme
Environ quinze champs empilés : lecture difficile, libellés encore en anglais sur l’app BDA autonome.

## Cause
IceMan crée tous les champs BDA (DTG, unité, TRN, plateforme, destinataires, etc.). Le recalage COMSPEC les montrait tous. L’app BDA IceMan n’avait pas de couche de libellés français.

## Correctif
- Masquer les champs secondaires préremplis (DTG, unité, TRN, munition véhicule, plateforme, destinataires) sans les vider : le contenu envoyé reste complet.
- Listes Type / Résultat / Nouvelle frappe en français (valeurs techniques inchangées).
- App BDA autonome : titre et libellés FR après ouverture IceMan.

## Fichiers touchés
- `fn_athena_fixReportsLayout.sqf`
- `fn_athena_bdaOnOpened.sqf`

## Vérification
1. Nouveau → Bilan des dégâts : champs principaux lisibles, Envoyer accessible.
2. Envoi : le message reçu contient toujours DTG / unité / TRN / destinataires.
3. App BDA autonome : « Bilan des dégâts », « Envoyer », « Effacer ».

## Statut
corrigé (pack 1.5.47 · Athena 1.0.92)
