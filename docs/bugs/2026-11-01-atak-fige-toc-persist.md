# ATAK figé après un moment (« Error foreach: Typ Bool » dans fn_tocPersist)

## Contexte

Rapport joueur (ATAK natif 2.9.20.AG, CBA 3.19.0, ACE 3.21.2, mission VR vide, Overwatch désactivé ou non).

## Symptôme

Après un moment d'utilisation, plus aucun bouton du téléphone ne répond. Le RPT contient des milliers de lignes
`Error foreach: Typ Bool, erwartet Array,HashMap` — `fn_tocPersist.sqf`, ligne 31.

## Cause

`fn_tocFeed` se termine toujours par `true` : l'appel `["events"] call fn_tocFeed` renvoyait `true` au lieu de la
table des flux. `fn_tocPersist` (« write », sauvegarde des écrans TOC) faisait `forEach` sur ce booléen à chaque
événement de camp. En solo / hébergé, le joueur est aussi le serveur : l'erreur tournait sur sa machine.

## Correctif

`fn_tocPersist` lit directement `COMSPEC_ATAK_TocEvents` (table vide si absente).

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/fn_tocPersist.sqf`

## Vérification

En jeu : mission VR, CBA + ACE + ATAK seuls, naviguer 15 min dans les apps et envoyer des comptes rendus ;
le RPT ne doit plus contenir d'erreur `fn_tocPersist`.

## Statut

corrigé à vérifier en jeu (2.9.21)
