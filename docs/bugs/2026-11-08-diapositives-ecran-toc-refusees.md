# Diapositives refusées sur l'écran TOC

## Contexte
ATAK natif 4.0.0, serveur, `COMSPEC_ATAK_Screen_Tripod`, menu ACE « Diffuser mes diapositives ».

## Symptôme
Rien ne s'affiche sur l'écran, et rien à l'écran du joueur. Journal du 08/11, 19:23:00 :
`[WARNING][SCREEN] Diffusion refusée : COMSPEC_ATAK_Screen_Tripod n'est pas désigné comme écran`.

## Cause
`fn_screenCast` « castOn » demande la désignation au serveur par un événement réseau, puis appelle « cast » tout de suite.
Hors serveur, `COMSPEC_ATAK_Screen` n'est pas encore revenu : refus, écrit au journal seulement. Un écran TOC n'est pas
un écran de diffusion tant que personne n'a diffusé : le premier essai échouait toujours.
Défaut voisin : avec le repli `data:` de `fn_slideWebShow`, `COMSPEC_ATAK_SlideWebOk` reste faux et l'image n'était jamais
recopiée sur la texture.

## Correctif
- « castOn » attend la désignation (`castWait`, 8 s au plus), avec message au joueur si elle n'arrive pas.
- Tout refus se voit en notification, et l'écran écrit la raison (Link absent, chargement, image illisible).
- Recopie de l'image 6 s après la demande, même sans le signal de la page.

## Vérification
`hemtt check` : seulement les 2 erreurs acceptées. `tests/Unit/AtakTocPostTest.php`. Non testé en jeu.
Non prouvé : le navigateur du jeu dans une texture posée sur un objet. Si l'écran reste noir une fois « Chargement » disparu,
regarder les traces `[SCREEN]` et `[BRIEFING]` du journal.

## Statut
Corrigé dans les sources, à vérifier en jeu.
