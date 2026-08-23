# Marqueurs SSE illisibles sur la carte in-game

## Contexte

Après exploitation d’un site, le mod SSE posait des marqueurs locaux sur la
carte Arma (pas Athena) : icône orange « ? », libellé commençant par « SSE ».

## Symptôme

Gros tampon orange « SSE » avec plusieurs lignes de texte empilées, illisible.
Les joueurs les trouvaient laids et inutilisables.

## Cause

`fn_createMapMarkers.sqf` créait un marqueur `mil_unknown` par point d’intérêt
extrait, souvent au **même** `getPosATL` que l’entité. Les libellés se
superposaient. Réglage CBA `comspec_sse_autoMarkers` activé par défaut.

## Correctif

Les marqueurs SSE ne sont plus créés. La fonction ne fait que supprimer ceux
déjà posés (`_comspec_sse_*`) au chargement et à chaque exploitation.
Le réglage CBA est désactivé et décrit comme retiré.

Les dossiers restent dans le terminal SEEK et sur Athena.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/intel/functions/fn_createMapMarkers.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_initIntelSettings.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_advanceExploitation.sqf`
- `mod/@COMSPEC_SSE/addons/intel/XEH_postInit.sqf`
- `mod/@COMSPEC_SSE/addons/main/script_mod.hpp` (0.7.10)

## Vérification

Relancer une mission avec le PBO `comspec_sse_intel` 0.7.10 : plus de marqueur
orange « SSE » à l’exploitation ; ceux déjà présents disparaissent au chargement.

## Statut

Corrigé.
