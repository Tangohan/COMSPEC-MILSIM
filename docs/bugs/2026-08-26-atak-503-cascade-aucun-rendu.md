# ATAK web — cascade de refus, carte vide, relevé Zeus

## Contexte

Production Athena 1.5.48. Après un relevé théâtre (26/08/2026 22h01, ~107 000 objets en jeu), le poste web se vide : lectures en refus, images `1.png`–`4.png` introuvables, bandeau carte vide, et Zeus affiche que le poste n’a pas encore la vérification. La table des volumes de théâtre existe et contient ~35 000 lignes (communauté 7, carte 1).

## Symptôme

- Toutes les lectures du poste (demandes médicales, statistiques, modèles d’ordres, aéronefs, marqueurs, codes laser, identification) échouent d’un coup.
- La carte reste presque vide (fond topo, presque aucun pictogramme) — journal console après l’échec des lectures : « Aucun rendu visuel ».
- Console : images `1.png`, `2.png`, `3.png`, `4.png` introuvables (chemin relatif, page carte).
- Zeus : « Le poste n’a pas encore cette vérification. Relancez un relevé complet, ou attendez la mise à jour du site. »
- Le relevé jeu annonce ~25 611 bâtiments + 81 870 forêts + 400 relief ; le poste n’en a qu’une partie.

## Cause

1. **Cascade.** Après un premier refus temporaire (base occupée, cadence), le client web mettait **toutes** les lectures en pause et renvoyait un faux refus. Les appels suivants (marqueurs, effectifs, etc.) n’allaient plus au poste. Une lecture ratée (codes laser, marqueurs) était traitée comme une carte vide, d’où l’impression « aucun rendu visuel », alors que les données n’avaient pas disparu.
2. **Reprise.** Même une fois la base revenue, la pause globale restait active tant qu’un succès d’effectifs n’arrivait pas — or les effectifs étaient eux-mêmes en faux refus.
3. **Vérification Zeus.** La comparaison jeu / poste n’existe pas encore sur le site 1.5.48 (mise à jour ouverte, pas déployée). Zeus interprète l’absence comme « le poste n’a pas encore cette vérification », même si des volumes sont déjà en base. Un refus temporaire pendant l’envoi explique aussi l’écart 107 k → 35 k.
4. **Images `1.png`–`4.png`.** Un identifiant numérique (volume ou pictogramme) était pris pour un nom d’image, d’où des demandes relatives `1.png`… depuis la page carte.
5. **Service worker.** Un échec réseau sur l’ouverture de la carte pouvait se résoudre en erreur, au lieu de laisser le navigateur charger la page.

## Correctif

- Ne plus interrompre les **lectures** (effectifs, marqueurs, relevé, statistiques…). La pause ne concerne que les **envois**. Le bandeau « Différé · mauvaise connexion » reste.
- Un succès d’effectifs **ou** de battement de liaison relâche la pause.
- En cas d’échec de lecture : conserver le dernier affichage (marqueurs, volumes, aéronefs, demandes médicales, identification). Ne pas vider la carte.
- Ne plus transformer un identifiant numérique en image `1.png`.
- Service worker : laisser passer la carte ; ne plus renvoyer une erreur réseau brute.

## Fichiers touchés

- `public/assets/js/atak-socket.js`
- `public/assets/js/atak-scene-3d.js`
- `public/assets/js/atak-laser-codes.js`
- `public/assets/js/atak-air-assets.js`
- `public/assets/js/atak-medevac.js`
- `public/assets/js/atak-iff.js`
- `public/assets/js/atak-terrain.js`
- `public/assets/js/arma-map-markers.js`
- `app/Support/helpers.php`
- `public/sw.js`
- `app/Support/DevDispatchCatalog.php` (UPDATE 236)
- tests unitaires associés

## Vérification

- Tests unitaires : pause limitée aux envois ; pas de message « Aucun rendu visuel » ; pas d’image d’identifiant ; catalogue 236.
- Recette : ouvrir la carte, provoquer une coupure courte : le bandeau Différé apparaît, les pastilles restent, les lectures reprennent toutes seules. Zeus : après déploiement du site, Vérifier et renvoyer doit comparer les volumes réellement reçus.

## Statut

corrigé (sources) — à déployer sur Athena ; le message Zeus « poste n’a pas encore cette vérification » disparaît avec cette mise à jour du site, pas avec un nouveau pack jeu
