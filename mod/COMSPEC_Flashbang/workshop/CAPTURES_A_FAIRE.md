# Captures en jeu à faire — COMSPEC Flashbang

La galerie actuelle (`gallery/`) ne contient **aucune capture du jeu** : rendus 3D des grenades (Blender) et schémas
dessinés, signalés comme tels en pied d'image. Le mod n'a pas encore été lancé en jeu. Ces captures remplaceront ou
compléteront les schémas une fois les tests faits.

Mission : `missions/COMSPEC_FB_Range.Stratis` (Eden, CBA_A3 + mod). Les actions de la caisse au départ téléportent
vers chaque zone (« Aller : A … E »), règlent l'heure (« Jour (12 h) », « Nuit (23 h, sans lune) ») et rechargent
les grenades.

Réglages conseillés pendant les prises : interface masquée (touche « Masquer l'interface » ou Échap > Options
> Interface), 1920 x 1080, qualité vidéo maximale, champ de vision par défaut. Pas de débogage à l'écran.

Déposer les fichiers dans `workshop/source/` (à créer) avec les noms ci-dessous.

| N° | Fichier | Où et comment | Remplace |
|---|---|---|---|
| 1 | `flash-exterieur.jpg` | Zone A, jour. Joueur à ~15 m du cercle, de côté, 5-BANG BF lancée au centre : capturer au 2e ou 3e flash (lumière, étincelles, fumée, IA qui se détournent). | complète 02 |
| 2 | `ecran-blanc.jpg` | Zone A, joueur à 3 m face au cercle : capture 0,2 s après le 1er flash (voile blanc). | schéma 04, vignette 1 |
| 3 | `retour-vue.jpg` | Même prise, ~3 s après : vue qui revient, couleurs ternes. | schéma 04, vignette 2 |
| 4 | `tache-remanente.jpg` | Même prise, ~10 s après, en tournant la tête : la tache sombre suit le regard. | schéma 04, vignette 3 |
| 5 | `jvn.jpg` | Zone A, nuit (action « Nuit »), JVN allumées (N), joueur à 8 m : tube saturé et grain. | — |
| 6 | `lunette-halo.jpg` | Zone A, jour, MX + HAMR visée sur le cercle à 20 m : halo dans l'optique. | — |
| 7 | `piece-fermee.jpg` | Zone B, porte fermée, joueur dedans dos à la grenade : la pièce entière s'illumine. | schéma 05, droite |
| 8 | `derriere-mur.jpg` | Zone B, joueur dehors contre le mur : aucune lumière, seulement la fumée par la porte (faire aussi une vidéo pour le bang sourd). | schéma 05, droite |
| 9 | `fenetre.jpg` | Zone C, joueur dehors face à la fenêtre pendant un flash dans la pièce. | schéma 05 |
| 10 | `couloir.jpg` | Zone D, grenade au fond du couloir, IA dans les chambres. | — |
| 11 | `hangar.jpg` | Zone E, grand volume, IA à 5, 12 et 20 m. | — |
| 12 | `drill.jpg` | N'importe quelle zone, Drill : petit pop et bouffée de fumée, aucune lumière. | complète 02 |
| 13 | `arsenal.jpg` | Arsenal virtuel, onglet des grenades, liste des 1-BANG BF … 9-BANG BF, GANG BANG BF et DRILL BF (après validation des noms courts). | complète 02 |
| 14 | `eden-module.jpg` | Eden : module « Flashbang (COMSPEC) » ouvert, ses trois modes visibles. | complète 07 |
| 15 | `zen-modules.jpg` | Zeus avec ZEN : catégorie « COMSPEC Flashbang », fenêtre « Lancer un flashbang ». | complète 07 |

Vidéos courtes (GIF ou MP4 pour la galerie Steam, 5 à 10 s, sans musique) :

- `rafale-9.mp4` : Zone A, 9-BANG BF vue de 15 m, son activé (on compte 9 bangs).
- `effet-joueur.mp4` : Zone A, joueur à 3 m de face, du flash au retour de la vue (son étouffé et sifflement audibles).
- `mur.mp4` : Zone B, joueur dehors derrière le mur : bang sourd, pas de lumière.

Après les prises : vérifier qu'aucun nom de joueur, chat ou adresse de serveur n'apparaît. Ensuite, ajouter une
mise en page « capture » à `tools/make_workshop_gallery.py` (comme `hero` dans la galerie SSE) et regénérer.
