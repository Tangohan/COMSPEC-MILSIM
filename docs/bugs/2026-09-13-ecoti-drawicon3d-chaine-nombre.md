# ECOTI — erreur drawIcon3D « Chaîne, Nombre attendu »

## Contexte

Affichage situation JVN (ECOTI) via jumelles / vision nocturne. Badges 3D (alliés, marqueurs, véhicules, itinéraires).

## Symptôme

Spam d’erreur script en jeu :

- Fichier : `fn_ecotiDrawBadge.sqf` ligne 35
- Message : `Error Type Chaîne, Nombre attendu`
- Le marqueur `| # |` pointe sur le second `drawIcon3D` (icône + libellé)

La première icône (plaque sombre, couleur en dur) s’exécute ; la seconde (couleur dynamique) échoue.

## Cause

Les couleurs de marqueurs vanilla (`ColorWEST`, `ColorEAST`, etc.) sont stockées dans `CfgMarkerColors` sous forme d’**expressions** (chaînes `profilenamespace getvariable …`), pas de nombres.

`getArray` renvoie donc un tableau du type `["(profilenamespace …)", …, 0.8]`.  
`drawIcon3D` exige des nombres RGBA → type String là où un Number est attendu.

Police secondaire : `PuristaSemiBold` (casse incorrecte) remplacée par `PuristaSemibold`.

## Correctif

1. Normaliser `_color` dans `fn_ecotiDrawBadge.sqf` : compiler les composants chaîne en nombres avant `drawIcon3D`.
2. Même normalisation à la lecture des couleurs de marqueurs dans `fn_ecotiDraw.sqf`.
3. Police `PuristaSemibold`.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiDrawBadge.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiDraw.sqf`

## Vérification

1. Rebuild / recharger le PBO `connect`.
2. Activer ECOTI (JVN / jumelles) sur une carte avec des marqueurs colorés (bleu / rouge / côté).
3. Confirmer : plus d’erreur script ; badges visibles avec couleurs cohérentes.

### Validation terrain — 13/09/2026

Confirmé en jeu sur Malden : les marqueurs s’affichent correctement sur la carte.

- Symbole NATO infanterie avec libellé (ex. **TA1**)
- Formes colorées (carré bleu, point jaune, cercle violet, ancre)
- Icônes / rectangles unitaires et zone (cercle rouge)
- Point d’attention (exclamation dans un cercle)

Plus de panne d’affichage liée aux couleurs de marqueurs côté ECOTI / carte.

## Statut

corrigé et validé en jeu (13/09/2026)
