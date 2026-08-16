# Athena — panneau illisible (DÉTAIL coupé)

## Contexte

Panneau Athena ATAK : zone DÉTAIL trop basse, boutons/onglets serrés, bord droit
parfois masqué.

## Symptôme

Texte du détail tronqué à une ligne (« Message TOC » illisible) ; onglets /
boutons de droite partiellement coupés.

## Cause

Hauteur `Detail` = `0.64` (≈ 1 ligne) alors que les messages multi-lignes
nécessitent ~2.5. Ajout SEEK / triage avait aussi allongé le panneau au-delà
du viewport ATAK.

## Correctif

- DÉTAIL → hauteur **2.55**
- Journal / notifs compactés
- SEEK sur la même rangée que photo / repère (`TX SEEK`)
- Actualiser aligné avec Compte / Adresse
- PanelFill ramené à ~11.2
- PBO sync vers `Arma 3\@COMSPECOverwatch` (pas seulement le repo)

## Fichiers

- `atak_athena/ui/athena_page.hpp`
- `atak_athena/config.cpp` (1.0.26)

## Vérification

Relancer Arma (mod local Steam). Ouvrir Athena → sélectionner un message :
le corps complet doit être lisible dans DÉTAIL.

## Statut

Corrigé — rebuild + sync Steam requis
