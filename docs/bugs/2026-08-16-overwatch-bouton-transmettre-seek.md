# Bouton Overwatch — transmettre les données SEEK

## Contexte

Sur le panneau Athena (Terminal & certificat), il manquait une action unique pour pousser vers Athena toutes les fiches SEEK déjà collectées en session, sans repasser par chaque cible.

## Symptôme

L’opérateur devait transmettre fiche par fiche (ou via la file SSE) ; aucune action « tout envoyer » depuis Overwatch.

## Cause

Fonctionnalité absente côté UI / CfgFunctions `atak_athena`.

## Correctif

- Bouton vert **Transmettre données SEEK** (`BtnSeekTx`, idc 9753) sous « Renvoyer photo / Repère web ».
- Fonction `comspec_overwatch_atak_athena_fnc_athena_sendSeekData` : collecte des cibles locales avec données SEEK, soumission biometrie / personne / numérique, puis `flushQueue`.
- Sections triage / liaison décalées pour éviter le chevauchement.
- Addon `atak_athena` en 1.0.20 ; PBO reconstruit.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_sendSeekData.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`

## Vérification

Rebuild AddonBuilder → `@COMSPECOverwatch\addons\comspec_overwatch_atak_athena.pbo`. En jeu : ouvrir Athena → bouton visible → message de feedback après envoi.

## Statut

Corrigé / livré (PBO reconstruit).
