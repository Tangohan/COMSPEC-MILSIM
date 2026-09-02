# Charge « Uniquement depuis ATAK » : le poste dit explosé, le jeu non

## Contexte

Pose d’une Stick Charge avec le déclencheur ACE **Uniquement depuis ATAK**
(entrée en double dans la liste). Déclenchement depuis le poste / la
tablette. Journal Overwatch : deux envois « Charge explosive — queued »
(deux poses). La carte du poste passe à **A explosé** ; rien ne saute
en jeu.

## Symptôme

- Dans ACE, **Uniquement depuis ATAK** apparaît deux fois.
- En jeu, la charge reste posée.
- Sur le poste : badge **A explosé**, délai « Aucun — tablette et poste uniquement ».

## Cause

1. Le même choix ACE était enregistré deux fois : une fois dans le
   sélecteur de déclencheur, une fois dans le menu principal de la
   charge (classe), plus une copie sur le placeholder au moment de la
   pose.
2. Dès l’ordre de déclenchement, le jeu prévenait le poste que la
   charge avait explosé, **avant** que ACE ne fasse réellement sauter
   l’objet. Si ACE bloquait le mode ATAK (déclencheur local retiré,
   feu non autorisé) ou si l’objet n’était pas trouvé, le poste
   affichait quand même **A explosé**. Après plusieurs recherches
   infructueuses, le jeu mentait aussi au poste.

## Correctif

- Une seule entrée **Uniquement depuis ATAK** dans le sélecteur ACE.
- Le poste n’est prévenu qu’une fois la charge disparue en jeu, ou
  après un repli d’explosion local si ACE n’a rien fait.
- Si la charge est introuvable en jeu, le poste reste en attente
  (plus de fausse explosion).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initChargeAceActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initExplosiveTimers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_detonateChargeById.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_detonateChargeLocal.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_findChargeObject.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

Pack jeu Overwatch 1.5.12. En session : poser une charge Uniquement
depuis ATAK (une seule ligne dans ACE), déclencher depuis le poste,
constater l’explosion en jeu puis le badge A explosé sur le poste.

## Statut

corrigé
