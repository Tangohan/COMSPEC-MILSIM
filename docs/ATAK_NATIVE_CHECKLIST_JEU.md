# ATAK natif : checklist de test en jeu

Ordre conseillé : d'abord seul en local (éditeur, solo), puis à deux joueurs en serveur dédié. Garder l'app **Debug** ouverte en cas de doute (onglet JOURNAL filtré sur ERREURS) et noter toute erreur script (`.rpt`) avec le nom du fichier `fn_*.sqf`.

Préparation : relancer `build_mod.bat` (DLL + connect Overwatch), charger `@COMSPEC_ATAK_Native` + Overwatch + CBA (+ ACE / ACRE2 si dispo), avoir un téléphone dans l'inventaire.

Coche chaque ligne : OK / KO + capture ou message d'erreur.

## 1. Base (solo, 5 min)

- [ ] Ctrl+U ouvre le téléphone, mini et plein écran, portrait et paysage.
- [ ] Boutons RETOUR / ACCUEIL / APPS du cadre : chacun fait ce qu'il dit, RÉCENTS liste les apps ouvertes.
- [ ] Lanceur : toutes les apps ont une icône et s'ouvrent sans erreur (tour complet, une par une).
- [ ] Réglages : chaque catégorie s'ouvre, « < RÉGLAGES » revient ; changer la couleur d'accent, le fond et le dock, puis relancer la mission : les choix sont gardés.
- [ ] Réglages > Applications : masquer une app, elle disparaît du lanceur et du dock.

## 2. Carte, GPS, points de passage (solo)

- [ ] Outil ROUTE : clic sur une destination à 1-2 km, un tracé bleu suit les routes, le bandeau affiche manœuvre, distance, arrivée.
- [ ] Rouler hors itinéraire > 60 m : « Hors itinéraire : recalcul » puis nouveau tracé.
- [ ] Arrivée à < 30 m : notification + vibration, le tracé disparaît ; bouton ARRÊTER fonctionne.
- [ ] Outil WP : poser 3 points, la flèche de cap pointe vers l'étape active en mode mini ; SUIVANT / PRÉCÉDENT.
- [ ] Calques Relief et Wave Relay : activer/désactiver depuis leur app, la carte reste fluide (noter les FPS avec et sans).

## 3. Téléphone réaliste (solo)

- [ ] Prendre une balle au torse : écran fêlé (texture), parfois écran noir puis redémarrage.
- [ ] Plonger sous l'eau quelques secondes : le téléphone s'éteint.
- [ ] Action ACE « Réparer » (trousse à outils, 15 s) et « Changer de téléphone ».
- [ ] Réseau : TEST DE DÉBIT dans un bâtiment, en forêt, dans un véhicule ; les barres de signal changent.
- [ ] Envoyer un message sans signal : il reste « en file », part quand le signal revient (app Debug > TRANSFERTS).

## 4. Apps d'info (solo)

- [ ] Météo : les 3 onglets s'affichent, valeurs plausibles (température, vent, dérive).
- [ ] Relief : CALCULER à 1000 m, la progression avance, le résultat s'affiche et CARTE centre sur le point haut.
- [ ] Photos : prendre une photo, BIBLIOTHÈQUE la liste, RETRANSMETTRE l'envoie (vérifier sur le web).
- [ ] Debug : JOURNAL, TRANSFERTS, ÉTAT, OUTILS s'affichent ; « Copier » met le journal dans le presse-papier.
- [ ] Ration Express : commander, la caisse arrive en parachute avec fumigène.

## 5. À deux joueurs (serveur dédié, même camp)

- [ ] Messagerie : SMS dans les deux sens, commandes /urgent /contact, vibration à la réception.
- [ ] BFT : l'autre apparaît avec distance, gisement, trajet ; il bouge, « Se rapproche / S'éloigne » change.
- [ ] BFT hors ligne : l'autre éteint son téléphone (ou passe sous l'eau) : en moins de 10 s sa piste passe HORS LIGNE et reste figée ; alerte de groupe + vibration ; « de nouveau en ligne » quand il revient.
- [ ] Bouton VIBRER : l'autre reçoit 3 vibrations et le bandeau « vous appelle » (téléphone ouvert puis rangé).
- [ ] Groupe : renommer, changer le type, quitter, rejoindre.
- [ ] Points de passage : PARTAGER au groupe, l'autre les reçoit.
- [ ] Logistique : demande de munitions, l'autre VALIDE puis LANCE LE LARGAGE ; la caisse tombe au bon endroit avec le bon contenu.
- [ ] Guerre électronique : l'un pose un brouilleur 600 m ; dans la zone, l'autre voit son débit chuter et sa position GPS dériver ; à la fin de la durée, tout revient.
- [ ] Goniométrie : un joueur ennemi (autre camp) avec téléphone ; GONIO trace un azimut avec cône d'erreur vers lui.
- [ ] Live cam : partager, l'image apparaît sur le web (vue cams Overwatch beta).
- [ ] Rencard / OSINT : un like réciproque fait un match ; un post OSINT apparaît chez l'autre.

## 6. Web (navigateur)

- [ ] Demande logistique créée en jeu visible sur le site.
- [ ] Photos et live cam visibles, âge correct.

## 7. Robustesse

- [ ] Ouvrir/fermer le téléphone 20 fois : pas de ralentissement (Debug > OUTILS > dump : un seul PFH).
- [ ] Joueur qui rejoint en cours de partie : voit BFT, groupe, et (après la synchro serveur) les demandes et brouilleurs existants.
- [ ] Respawn : téléphone réparé, pas d'erreur.
- [ ] Sans ACE ni ACRE2 : aucune erreur, Médical/Wave Relay en mode dégradé.
