# ACE Medical — plaque d’identification sans identité SSE

## Contexte

Sur un blessé / mort SSE, **ACE → Dog Tag → Check** affichait le nom Arma (ou un SSN générique), pas le profil SSE. Aucun lien avec le dossier renseignement.

## Symptôme

Plaque ACE ≠ identité narrative SSE ; pas d’entrée journal / brouillard après lecture de plaque.

## Cause

ACE `getDogtagData` génère `[getName, ssn(name), bloodType(name)]` une fois et le cache. SSE ne synchronisait pas cette variable.

## Correctif

Addon `compat_ace` (0.7.5) : wrap `getDogtagData` / `checkDogtag`, sync depuis `identity` SSE, `revealFog` action `dogtag`.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/compat_ace/*`
- `fn_revealFog.sqf`, `fn_generatePerson.sqf`, `fn_generateData.sqf`, `fn_setIdentity.sqf`
- Docs `ACE-DOGTAGS.md`, CHANGELOG

## Vérification

1. Charger `@COMSPEC_SSE` (PBO `compat_ace`) + ACE Medical.
2. PNJ SSE KO → Check Dog Tag → nom SSE sur l’overlay.
3. Hint / journal : identité sur plaque.

## Statut

corrigé (sources — rebuild PBO)
