# Carte et app Zones : plus aucun clic après une action du menu du clic droit ; pages superposées

## Contexte
ATAK natif 2.9.38 / 2.9.39. App Carte, clic droit › ZONES › ZONE DEPUIS LE CLIC (plan OBJ BRAVO, cercle 300 m) ou
› NOMMER LES AXES. Lanceur APPLICATIONS ouvert depuis Athena ou Profil.

## Symptôme
- Après l'action, la carte n'accepte plus aucun clic (sélection, menu, outils) ; dans l'app Zones, choisir POLYGONE ou
  RECTANGLE puis cliquer n'ajoute aucun point ; ensuite « la carte ne s'ouvre plus » (elle ne répond plus).
- APPLICATIONS : la page Athena / Profil reste dessinée sous le lanceur.

## Cause
- `fn_mapCtxMenu` : chaque ligne du menu lançait `act` dans son propre ButtonClick, et `act` commence par `close`, qui
  supprimait (ctrlDelete) tous les contrôles du menu, dont le bouton cliqué, pendant son gestionnaire. Le moteur perd
  alors la capture de la souris et ne laisse plus passer aucun clic jusqu'à la réouverture du téléphone : même défaut
  que celui corrigé dans `fn_pageClear` le 25/10 (app Drone). Aucune donnée de plan n'est en cause (`fn_zoneUnpack`
  valide déjà chaque élément).
- `fn_pageRender` : nombreux rendus différés (`[{ ["ATHENA"] call ...pageRender }] call CBA_fnc_execNextFrame`,
  `waitAndExecute`) sans vérifier que la page est toujours active : une page quittée entre-temps se redessinait
  par-dessus la nouvelle.

## Correctif
- Ligne du menu exécutée à l'image suivante ; `close` masque les contrôles et ne les supprime qu'à l'image suivante.
- `fn_pageRender` abandonne (journal `[UI]`) tout rendu d'une page autre que la page active (posée par `fn_navigate`).
- `fn_navigate` : quitter l'app Zones remet l'outil et les points du tracé à zéro (journal `[MAP]`).

## Déblocage immédiat en 2.9.39
Fermer puis rouvrir le téléphone (ou se reconnecter) rend les clics. Aucune donnée à effacer. En dernier recours
seulement, console de débogage locale : `profileNamespace setVariable [format ["COMSPEC_ATAK_Zn_%1", worldName], nil]; saveProfileNamespace;`
(efface les plans de zones locaux de la carte en cours).

## Fichiers touchés
`functions/map/fn_mapCtxMenu.sqf`, `functions/ui/fn_pageRender.sqf`, `functions/ui/fn_navigate.sqf`

## Vérification
En jeu : clic droit › ZONES › NOMMER LES AXES puis clic sur la carte ; › ZONE DEPUIS LE CLIC puis POLYGONE, 4 clics,
Entrée ; Athena puis APPLICATIONS (aucune trace d'Athena) ; miniature Maj+U et plein écran.

## Suite 2.9.41
Outil POLYGONE relu : barre d'outils, panneau et menu de la carte de l'app passent tous par un clic différé d'une
image, la carte de l'app n'est jamais supprimée dans son propre gestionnaire (fin de tracé, clic droit, Entrée) : le
correctif 2.9.40 suffit. Rectangle et cercle acceptent en plus le clic-glisser (`fn_zoneMap` « up »), et l'app propose
DÉCOUPER ou TRACER dès la zone de travail posée.

## Statut
corrigé à vérifier en jeu
