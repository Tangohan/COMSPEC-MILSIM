# ATAK invisible dans un cockpit cliquable ; téléphone bloqué après la descente, revenu avec un cadre bleu plein écran

## Contexte
ATAK natif 2.9.40. A-10C (mod avion à cockpit cliquable : mains d'interaction sur le tableau de bord, HUD / MFD),
place pilote. Raccourcis Alt+U (prendre en main), Maj+U (interagir avec la miniature, 2.9.38).

## Symptôme
1. Dans le cockpit, Alt+U passe bien en mode interactif (curseur, commandes de vol suspendues) mais le téléphone
   n'apparaît pas.
2. Après la descente de l'appareil, plus aucune touche du téléphone ne marche. Quelques minutes plus tard, le
   téléphone « revient tout seul » avec un fin rectangle bleu faisant le tour de presque tout l'écran.

## Cause
- `fn_open` créait toujours le téléphone en main sur le display de mission (46). L'écran de cockpit cliquable de
  l'autre mod est une fenêtre déjà empilée au-dessus de 46 : le téléphone passait dessous, invisible, alors que la
  souris lui était donnée.
- Le relais des touches posé sur les fenêtres de bord (`fn_vehicleKeys`, 2.9.39) s'effaçait dès que le téléphone était
  « en main », en supposant qu'il recevait lui-même le clavier ; caché sous la fenêtre de cockpit, il ne le recevait
  pas : Alt+U, Maj+U et Ctrl+U ne faisaient plus rien, et après la descente (relais limité aux véhicules) non plus.
- Le cadre bleu de Maj+U était dessiné une fois à l'ouverture autour de la boîte du téléphone dès que
  `COMSPEC_ATAK_MiniPrevMode` était noté. Une interaction miniature ouverte sous le cockpit n'était jamais terminée
  proprement ; `fn_interactToggle` repassait ensuite le mode en FULL sans l'effacer : la réouverture suivante en grand
  dessinait le cadre à la taille de l'écran (et un clic à côté refermait le téléphone).
- Retour « tout seul » : la fenêtre de l'autre mod se fermait (le téléphone caché dessous redevenait visible), ou une
  ouverture automatique en main (CR de fin d'opération `fn_debrief`, liaison Athena) le rouvrait avec l'état resté noté.

## Correctif
- `fn_vehicleKeys` « parent » : à bord, téléphone en main créé sur la fenêtre la plus haute de la pile (hors téléphone,
  menu pause, carte cachée) ; « covered » : à bord, une fenêtre étrangère au-dessus du téléphone ; la touche Prendre en
  main / Maj+U le rouvre alors au premier plan (l'interaction miniature continue). Relais de touches actif tant que le
  téléphone est recouvert.
- « reset » sur montée / descente / changement de véhicule, changement de place, nouvelle unité (évènements joueur CBA)
  et mort / inconscience : mode du joueur remis, drapeaux, displays orphelins fermés, téléphone en main rendu au jeu,
  vol stationnaire, alternat, relais retirés, menu d'actions rendu. « heal » avant chaque ouverture par une touche.
  Action ACE sur soi « ATAK : réparer l'affichage » (« repair »).
- Cadre de Maj+U posé par `fn_layoutApply` (suit le téléphone, retiré hors interaction miniature) ; interaction notée
  terminée à l'ouverture en grand (`fn_displayLoad`), avant le grand (`fn_interactToggle`) et si l'ouverture échoue
  (`fn_miniInteract`).
- Journal `[ATAK]` (aussi dans le journal de session COMSPEC) : transitions, fenêtre de bord relayée (IDD, contrôles,
  véhicule), téléphone ouvert par-dessus / recouvert, fenêtre restée ouverte après la descente, réparations.

## Fichiers touchés
`functions/ui/fn_vehicleKeys.sqf`, `fn_open.sqf`, `fn_interactToggle.sqf`, `fn_miniInteract.sqf`, `fn_displayLoad.sqf`,
`fn_layoutApply.sqf`, `functions/shell/fn_aceMenu.sqf`, `functions/core/fn_log.sqf`, `XEH_postInitClient.sqf`

## Vérification
`hemtt check` (2 erreurs connues seulement). En jeu (non testé avant main) : A-10C cockpit cliquable (Alt+U, Maj+U,
Échap), hélicoptère vanilla, véhicule terrestre ; descente téléphone en main ; action ACE ; RPT `[ATAK]`.

## Statut
corrigé à vérifier en jeu
