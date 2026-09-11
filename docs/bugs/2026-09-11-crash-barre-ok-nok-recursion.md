# Crash ouverture ATAK — bandeau OK/NOK (récursion)

## Contexte

11 septembre 2026. Pack 1.5.38–1.5.39. Ouverture téléphone → gel/crash. Barre sombre sous le statut cTab souvent vide (sans texte OK/NOK).

## Symptôme

- Hitch / crash dès l’ouverture ATAK
- Bandeau semi-transparent sous la barre d’état, texte absent

## Cause

`fn_athena_updateLinkStrip` appelait `refreshLinkState`, qui appelle `updateStatusBadges`, qui rappelle `updateLinkStrip` → **récursion infinie**.
Appelé aussi depuis le tick HUD carte → explosion immédiate à l’open.

## Correctif

- Bandeau en lecture seule (plus de `refreshLinkState`)
- Garde `COMSPEC_LinkStripUpdating`
- Retrait de l’appel depuis chaque tick `updateMapHud` (PFH 2 s + badges suffisent)

## Fichiers touchés

- `fn_athena_updateLinkStrip.sqf`
- `fn_athena_updateMapHud.sqf`

## Vérification

Quitter Arma, pack **1.5.40**. Ouvrir ATAK : pas de crash ; bandeau affiche OK/NOK · débit · perte.

## Statut

corrigé — Overwatch 1.5.40
