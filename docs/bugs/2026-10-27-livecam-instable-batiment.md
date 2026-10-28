# Vidéo Live — instable en bâtiment

## Contexte

App Vidéo live, « Ma caméra », opérateur à l’intérieur. Capture du 27/10/2026 : image sombre, bandeau « SD · 2,0 Mbit/s · perte 28 % », voile « SIGNAL INSTABLE ».

## Symptôme

Le flux de sa propre caméra clignote ou affiche « SIGNAL INSTABLE » dès qu’on est sous un toit, alors que l’image est locale sur le téléphone.

## Cause

`fn_feedLink` traitait « Ma caméra » comme un flux réseau : les pertes radio d’intérieur (murs, toiture) remontaient dans `drop`. Toutes les 0,5 s, un tirage aléatoire affichait un voile noir. En plus, la formule `loss/150` rendait les décrochages trop fréquents pour les flux distants.

## Correctif

- Flux local (`player`) : HD, 0 % de perte, aucun décrochage.
- Flux distant / Wi-Fi proche : décrochage plafonné et moins sensible à la perte ; voile espacé (image stable ≥ 1,2 s entre deux coupures).

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_feedLink.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_pageLivecam.sqf`
- `app/Support/DevDispatchCatalog.php` (UPDATE #1180)

## Vérification

Quitter Arma, republier le pack. Entrer dans un bâtiment, ouvrir Vidéo live → Ma caméra : image continue, pas de voile « SIGNAL INSTABLE », bandeau sans pertes fictives. Un flux d’équipier peut encore baisser de palier, sans clignoter en permanence.

## Statut

corrigé
