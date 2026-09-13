# Menu ACE COMSPEC Athena — liste à plat illisible

## Contexte

Self-interact ACE : ouverture de **COMSPEC Athena**.

## Symptôme

Une colonne d’environ 25 actions (situation, tenues, ping, cartographie, ordres, etc.) s’étale d’un coup, illisible en JVN.

## Cause

Toutes les actions étaient des enfants directs de `COMSPEC_Main`, sans sous-rubriques. ACE affiche alors toute la liste verticale.

## Correctif

Arbre v3 sous `COMSPEC Athena` :
- Connexion / Téléphone en tête
- Rubriques : Applications, Affichage situation, Transmission, Cartographie, Appui & mission, Tenues, Compte & liaison
- Purge des anciennes actions de classe avant réinstallation (`COMSPEC_ACEMenuStructureVer`)

## Fichiers touchés

- `fn_initACE.sqf`

## Vérification

Pack Overwatch **1.5.65**. Quitter Arma. ACE self-interact → COMSPEC Athena → quelques rubriques seulement.

## Statut

corrigé
