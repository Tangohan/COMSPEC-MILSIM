# Menu d’applications : icônes empilées, fond gris clair

**Date :** 2026-09-19  
**Statut :** corrigé (Athena 1.0.145)

## Contexte

Téléphone ATAK, chevron du menu d’applications. Premier signal : tuiles empilées, fond gris clair. Ensuite : fond sombre, mais grille cassée (Video / Photo / Tâches décalées, cases vides, noms illisibles). Les tuiles Athena n’apparaissaient pas. Un clic ouvrait Task et Video Feeds, quelle que soit la case.

Le rendu correct est celui des packs Workshop du 6 et du 15 septembre : grille IceMan 3 colonnes, icône au-dessus du nom, sans superposition.

## Symptôme

- Tuiles superposées, libellés collés (`QPhotoLibrarys`).
- Apps Athena (Athena, Messagerie, Ordres, Paramètres…) absentes du tiroir.
- N’importe quelle case ouvre Task et Video Feeds.

## Cause

1. Recalage sur la **largeur animée** du tiroir (`largeur / 3`) alors qu’IceMan pose les tuiles sur leur **taille native**. Maille trop étroite → superposition → deux boutons reçoivent le même clic.
2. IceMan range la liste d’apps dans `localNamespace`. On écrivait encore le profil : les apps Athena n’entraient pas dans la liste utilisée à la création des tuiles.
3. Redéfinir `message` comme bouton nu (sans l’icône IceMan) et écraser `Group` cassait le modèle des tuiles IceMan.

## Correctif

Le 1.0.143 recréait les tuiles et en masquait. Le 1.0.144 ne déplace plus la grille. Le 1.0.145 ne redéfinit plus la tuile Message IceMan : les apps Athena héritent de nouveau de l’icône + nom.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_layoutAppDrawer.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_syncAtakApps.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.145)
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`

## Vérification

Quitter Arma complètement. Recharger Athena 1.0.145. Chevron : grille IceMan, icônes Athena visibles.

## Statut

corrigé (Athena 1.0.145)
