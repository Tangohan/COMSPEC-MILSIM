# 2026-09-01 — Titres du cartouche carte ≠ paramètres

## Contexte
Sur le téléphone ATAK, Paramètres nomme Indicatif, Rôle, Équipe de feu, Groupe en jeu. Le cartouche de l’unité sur la carte affichait autre chose.

## Symptôme
Le cartouche disait GROUP et CALLSIGN. La grille n’avait pas de titre. CALLSIGN montrait le nom du personnage (NewPI) au lieu de l’indicatif enregistré (admin). L’unité de vitesse passait à la ligne.

## Cause
Libellés anglais copiés d’un OSD TAK. L’indicatif était lu via le nom Arma, pas via l’indicatif Overwatch.

## Correctif
Mêmes intitulés qu’au terminal : Indicatif, Rôle, Groupe, Grille, altitude et vitesse. L’indicatif vient de celui enregistré dans Paramètres.

## Fichiers touchés
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`

## Vérification
Carte ATAK : cartouche de droite = INDICATIF / RÔLE / GROUPE / GRILLE. Comparer avec Paramètres.

## Statut
Corrigé (visible après rechargement du pack)
