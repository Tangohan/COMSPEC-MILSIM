# Doublon « FRS / FRM » à la place des fiches de renseignement

## Contexte

Les PR #184 et #185 (déjà mergées dans `main`) livrent les **fiches de renseignement simplifiées** : une note libre datée et située, avec thèmes et pièces jointes. Le PC local était en retard Git et n’avait pas ces fichiers.

## Symptôme

Une seconde fiche « FRS / FRM » (recueil source vs matériel) a été ajoutée sur le PC, plus pauvre et différente de celle déjà en production sur le site. L’URL `/atak/sse/fiches/nouvelle` ne correspondait plus au rédacteur existant.

## Cause

Mauvaise lecture de « FRS » (prise pour fiche recueil source / matériel) alors que le produit parle de **fiches de renseignement simplifiées**. Branche locale en retard sur `origin/main`.

## Correctif

- Récupération des fichiers des PR #184/#185 depuis `origin/main`.
- Suppression du doublon (formulaire portail, app ATAK `AtakFrsFrm`, hook rapport FRM).
- Tiroir ATAK : menu **RENS** + rédacteur plein cadre existant.
- Vue ATAK web : onglet FRS du #185.
- Raccourci Maj+Ctrl+S : ouvre ce rédacteur.

## Fichiers touchés

- Contrôleurs / dépôt / catalogue `SseFieldNote*`
- `views/atak/sse/field_note_*.php`, `public/assets/css/sse_field_note.css`
- Addon `connect` (`fn_intelNote*`, `display_intel_note.hpp`)
- Addon `atak_athena` (menu `AtakNote`, `ui/note_page.hpp`)
- Suppression de `fiche_nouvelle.php` et de `AtakFrsFrm`

## Vérification

1. `/atak/sse/fiches/nouvelle` affiche le rédacteur plein écran (pas un formulaire source/matériel).
2. Tiroir ATAK : entrée **RENS**.
3. Vue ATAK web, domaine Renseignement : premier module **Fiches** — même rédacteur que le bureau.
4. Depuis Personnes : bouton **Rédiger une fiche**.

## Statut

corrigé en sources — rebuild PBO + DLL extension requis pour le terrain
