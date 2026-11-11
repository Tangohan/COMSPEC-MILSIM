# Audit de l'app « Poste TOC » du PC (console du poste) — novembre 2026

Demande de TTRD : « l'APP POSTE TOC est totalement à refaire », puis « il faut que tout le TOC soit utilisable sans
téléphone, c'est le principe ». Périmètre : la console ouverte par « Ouvrir le poste » sur un PC de TOC
(`fn_tocLaptop.sqf`, `fn_tocDraw.sqf`, `fn_tocHallUi.sqf`, dialogue `COMSPEC_RscTocLaptop`). L'app du même nom dans le
téléphone (`fn_pageToc.sqf`, 145 lignes : télécommande des écrans à côté d'un poste) n'est pas refaite ici ; elle reste
un raccourci facultatif, la console n'en dépend pas.

## Inventaire (4.7.1)

| Élément | État |
|---|---|
| Onglets | 14 sur deux rangées pleine largeur : GROUPES, ORBAT, FLUX, ORDRES, CAMP, RÉSEAU, RADIO / SAT, SALLE, CHRONOS, MAIN COURANTE, EXCHECK, PERTES, DEMANDES |
| Mise en page | moteur sain : grille `_grid`, table `_layout`, table `_btnDefs`, pose unique `_place`, contrôle « audit » |
| Carte | vraie carte d'Arma ; 9 boutons au-dessus, 5 dessous, 2 lignes de texte dessous ; aucun bouton de zoom, ni échelle, ni nord |
| Données | `COMSPEC_ATAK_TocFeed_<camp>` (serveur, 3 s) : seulement les porteurs d'un ATAK ; `COMSPEC_ATAK_TocRoom_<camp>` (salle) ; plan du camp ; ORBAT du site |
| Salle | plan du mur, 13 rangées de réglages dans un seul panneau |
| Rafraîchissement | 1 Hz (PFH CBA retiré à la fermeture), listes reconstruites sur signature, HUD SAT à 4 Hz seulement onglet SAT ouvert |

## Défauts trouvés

1. **Unités invisibles sur la carte** : `_sideRgb` est lu dans « draw » (symbole du poste, nœuds en direct et anciens)
   sans jamais être déclaré dans `fn_tocLaptop.sqf` (resté dans `fn_tocHallUi.sqf` à la refonte du 11/11). Chaque image
   lève « variable indéfinie » : seuls les nœuds perdus ou hors ligne (gris) étaient dessinés (corrigé entre-temps par la
   4.7.2, avec « mk » qui rendait `true` et arrêtait l'ouverture du PC). Ni le contrôle de casse ni
   les tests statiques ne le voyaient.
2. **Dépendances au téléphone** (contraires au principe) :
   - carte, GROUPES, recherche, suivi SAT, centrage : uniquement les nœuds ATAK ; un groupe sans téléphone est
     « position inconnue du poste », une unité sans téléphone n'existe pas ;
   - états « perdu » / « hors ligne » appliqués à des unités que le jeu connaît parfaitement ;
   - demandes (MEDEVAC, appui, ravitaillement) et comptes rendus : seulement reçus des téléphones, impossibles à créer
     au poste ;
   - « VISER UN ÉCRAN » et le refus d'ouverture passent par le centre de notifications du téléphone
     (`fn_notify`) : rien ne s'affiche sans téléphone ;
   - onglet RÉSEAU : renvoie aux « apps Réseau et Météo du téléphone ».
   L'ouverture elle-même (ACE ou molette, `fn_tocPost`) ne demande aucun objet : conservé.
   Autre défaut de la carte : chercher une grille ne faisait rien (`fn_gridParse` rend `[[x, y, 0], taille, grille]`,
   la console lisait `[x, y]`) ; le tri des nœuds était refait à chaque image.
3. **Navigation** : 14 onglets à plat, sans regroupement ; la console web `/atak/toc` a quatre rubriques, le PC non.
4. **Zoom** : molette seule, sans retour visible (pas d'échelle) ; aucun bouton, aucun raccourci.
5. **Boutons mal placés** : outils de dessin sous la carte dans tous les onglets, MARQUEUR en haut et les autres outils en
   bas, « contenu des écrans » dans RÉSEAU, « caméra sur les écrans » dans CAMP, scènes au fond de SALLE.
6. **Écrans** : les actions « sur les écrans » visent « les écrans à moins de 30 m » au lieu des écrans de la salle.
7. **Calques** : deux boutons (IA, ALERTES) dont l'état ne se lit qu'à la couleur.
8. Aucun raccourci clavier.

## Gardé

Moteur de mise en page et son test, thème (`fn_tocTheme`), ligne d'aide au survol (pas d'infobulle), ligne d'état
(pas de fenêtre du jeu), brouillons, console SAT, registres de la salle (`fn_tocRoom`), outils de dessin et plan du
camp (`fn_tocDraw`, mêmes objets que l'ATAK), plan du mur (`fn_tocHallUi`), PIP du poste (`COMSPEC_ATAK_TocView`),
correctif 4.5 de la casse (`_h` / `_H`).

## Abandonné

Coque du PC portable autour de l'écran (4,4 % de la largeur perdus), deux rangées d'onglets, boutons de calque IA /
ALERTES, renvois au téléphone, ciblage « 30 m » quand le poste a une salle.

## Refonte (4.9.0)

- Coque : en-tête (poste, salle, opérateur, alertes, ZULU / LOCAL, liaisons), rail des quatre rubriques de la console
  web (SITUATION, CONDUITE, PLANS, SALLE ; F1 à F4), onglets de la rubrique, carte, panneau, aide, état.
- Source directe : `fn_tocAppData.sqf` lit le jeu (unités, véhicules, aéronefs, groupes, santé, munitions) et le fusionne
  avec le flux des téléphones ; état neutre « sans téléphone · source directe », jamais « perdu » faute de téléphone
  (fraîcheur lue par `fn_tocStatus` de la 4.8.0 quand elle est là). Réglage de mission
  `comspec_atak_native_toc_direct` (vrai par défaut).
- Demandes, comptes rendus, ordres, messages, main courante, plan : créés au poste.
- Salle : la console pilote le modèle de la 4.8.0 (mises en page `fn_tocLayout`, diffusion et mire `fn_tocBroadcast`,
  tableau des écrans `fn_tocManager`, luminosité) ; rien n'est dupliqué, chaque appel est gardé par `isNil` pour un
  serveur ou un PBO plus ancien.
- Performance : rien ne tourne console fermée ; données directes recalculées toutes les 2 s au plus ; dessin de la carte
  sur les tableaux en cache ; trace d'une seule unité (120 points).
