# Wave Relay devient Relais AT

## Contexte

19 septembre 2026. Tiroir du téléphone ATAK. La tuile Wave Relay (icône radio) n’affichait pas les mâts COMSPEC posés en mission. Le poste ne voyait qu’un calque « Relais ATAK » sans débit, fiabilité, identité, adresse, passerelle, certificat, places ni puissance. L’éditeur ne proposait que nom et portée.

## Symptôme

Ouvrir Wave Relay ne donne pas la fiche du mât le plus proche. Détruire le mât ne change rien de visible sur le téléphone. Le poste n’a pas le détail du relais.

## Cause

Wave Relay appartenait à un autre pack radio. Les mâts COMSPEC n’envoyaient que position, portée et état intact / détruit.

## Correctif

- La tuile s’appelle Relais AT et ouvre la fiche du mât le plus proche (intact d’abord).
- Fiche : position (grille et distance), débit, fiabilité, identité, adresse réseau, passerelle, certificat, places occupées, puissance.
- Un mât détruit passe hors service (débit et puissance à zéro) sur le téléphone et au poste.
- Éditeur (Modules COMSPEC) et Zeus : tous les champs, avec notice de pose dans l’éditeur.

Pack : Overwatch **1.5.96** · Athena **1.0.153**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/relay_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateRelay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getNearestAtakRelay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_placeAtakRelay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_atak_relay.hpp`
- `app/Repositories/AtakRelayRepository.php`
- `public/assets/js/atak-overwatch-ops.js`
- `views/admin/atak/server_control.php`

## Vérification

1. Quitter Arma. Recharger Overwatch 1.5.96 et Athena 1.0.153.
2. Poser un mât Relais ATAK dans l’éditeur, renseigner identité, débit, places.
3. En jeu, Relais AT affiche ce mât et sa fiche.
4. Détruire le mât : l’état passe à hors service, y compris sur la carte du poste.

## Statut

corrigé
