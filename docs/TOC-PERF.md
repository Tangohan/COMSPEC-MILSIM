# Écrans TOC : ce qui tourne, et quand (ATAK natif 4.8.0)

Demande de TTRD : « optimise aussi le mod et les écrans, pas besoin d'être chargé à distance ». Tableau de ce que
chaque machine fait pour un écran TOC selon l'endroit où se trouve le joueur. Rien ici n'a été mesuré en jeu : c'est
la lecture du code (`fn_tocScreen` « tick », `fn_tocCam`, `fn_tocSat`, `fn_tocMosaic`, `fn_tocFeed`).

## Poste du joueur, par écran

| État de l'écran | Condition | Travail |
|---|---|---|
| Veille | plus loin que la distance d'affichage + 50 % (10 m au moins), éteint, autre camp | aucun : texture d'origine, pas de display, pas de caméra |
| Figé | entre la distance d'affichage et la veille, ou proche mais dos tourné ou derrière un mur | aucun : la dernière image reste |
| Allumé | à moins de la distance d'affichage (réglage « Distance d'affichage des écrans TOC », 25 m ; propre à l'écran dans le tableau des écrans), devant soi, pas derrière un mur | une image redessinée toutes les 2 s (1 s : horloge, satellite, bandeau, mosaïque, texte, mire), 8 écrans redessinés par seconde au plus |
| Caméra, satellite, mosaïque | écran allumé, à moins de « Portée des caméras d'écran » (40 m), image à l'écran | une caméra de rendu par source, « Caméras d'écran en même temps » (2) au plus, les plus proches d'abord |

Boucle commune : une passe par seconde sur les seuls écrans des cases de 100 m voisines (index refait quand le
registre change), un contrôle de visibilité par écran toutes les 3 s au plus.

## Retenues ajoutées en 4.8.0 (plus d'allumage et d'extinction en boucle)

| Bascule | Avant | Maintenant |
|---|---|---|
| Écran caché derrière un obstacle | éteint au premier contrôle raté, rallumé 3 s après | « caché » après deux contrôles ratés de suite (6 s) |
| Caméra du satellite | détruite 3 s après le dernier regard, recréée au suivant (journal « rendu sur ce poste » / « rendu arrêté » en boucle) | gardée 12 s après le dernier regard |
| Caméra en direct d'un écran | 3 s | 10 s |
| Vignette de mosaïque | 3 s | 8 s |
| Image jamais chargée | nouvel essai toutes les 10 s, sans fin | essais espacés : 4, 8, 16, 32, 60 s |
| Contenu ↔ carte AUCUN SIGNAL | (pas de carte) | contenu après 2 images avec flux, carte après 3 sans |
| Source d'une diffusion | (nouveau) | prise après 2 images, rendue après 3 |

## Vue satellite (4.8.0, réglage « Qualité de la vue satellite »)

| Qualité | Image du PC | Écran à 8 m au plus | Écran au-delà de 10 m | Texture d'un écran du mur | Écran regardé de près |
|---|---|---|---|---|---|
| Désactivée | aucune | aucune | aucune | — | — |
| Basse (défaut) | 1024 x 512 | 512 x 256 | 512 x 256 | 2 par seconde | 5 par seconde |
| Moyenne | 2048 x 1024 | 1024 x 512 | 512 x 256 | 3 par seconde | 5 par seconde |
| Haute | 2048 x 1024 | 1024 x 512 | 1024 x 512 | 5 par seconde | 6 à 7 par seconde |

Une caméra par satellite et par camp, quel que soit le nombre d'écrans. Visée relue 5 fois par seconde, cibles gardées
0,3 s. Une image de plus de 4 ms double l'intervalle (4 fois au plus) pendant 30 s. Le moteur rend la caméra à chaque
image du jeu : seule la résolution en réduit le coût.

## Serveur

| Travail | Rythme | Condition |
|---|---|---|
| Flux d'un camp (`fn_tocFeed`) | calcul toutes les 3 s, envoi seulement si le contenu change (sinon toutes les 9 s) | un joueur du camp au PC du poste ou à moins de 200 m d'un écran du camp ; sinon ni calcul ni envoi |
| Réglage d'un écran | à chaque changement seulement (variable publique de l'objet) | — |
| Sauvegarde | 3 s après un changement, positions relevées toutes les 15 s | réglage « Garder les écrans TOC » |
| Réglage automatique (`fn_tocAuto`) | à la création de l'objet ; passe de rattrapage toutes les 30 s | — |

## Non fait dans cette version

- Pas de résolution ni de cadence dégressive par paliers de distance pour les textures d'interface (seul le satellite
  change de résolution selon la distance, la mosaïque et la caméra selon la liaison).
- Pas de rendu partagé entre deux écrans qui montrent la même caméra en direct (le satellite, lui, est déjà partagé :
  une caméra par satellite et par camp, quel que soit le nombre d'écrans).
- Pas de démarrage échelonné à l'entrée dans une salle au-delà du plafond de 8 images par seconde.
- Pas de nouveaux réglages CBA : les trois réglages existants ci-dessus couvrent distance, portée et nombre de rendus.
- Pas d'audit du reste du mod (relais, marqueurs, conditions ACE) dans cette PR.
