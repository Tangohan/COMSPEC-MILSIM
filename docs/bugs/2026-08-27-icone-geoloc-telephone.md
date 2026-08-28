# Icône combiné pour la géolocalisation téléphone

## Contexte

Contact « Tél. … » sur l’ATAK web et sur l’ATAK en jeu (téléphone IceMan).

## Symptôme

Carré bleu avec un combiné vintage sur la carte web ; pastille verte générique en jeu. On ne lit pas « téléphone suivi ».

## Cause

Le rôle « Téléphone » passait par le symbole OTAN (combiné). En jeu, la carte ATAK dessinait un bonhomme / pastille GPS.

## Correctif

Pin cyan avec un smartphone. Même logique sur le poste et overlay sur la carte ATAK en jeu.

## Fichiers touchés

- `public/assets/js/atak-phone-icon.js`
- `public/assets/js/atak-map.js`, `atak-units.js`, `views/atak.php`
- `app/Services/Tactical/AtakMarkerIconsService.php` (catégorie téléphone)
- `mod/.../fn_webBrowserMapOnDraw.sqf`
- `mod/.../fn_athena_installPhoneGeolocMap.sqf`, `fn_athena_hookPhoneGeolocMap.sqf`
- `mod/.../atak_athena/config.cpp` (1.0.53)

## Vérification

Recharger la carte : Tél. Pyotr = pin smartphone, plus le combiné. En jeu : pack 1.0.53, ouvrir ATAK, un téléphone suivi doit apparaître en cyan (icône radio/téléphone), pas en pastille verte.

## Statut

corrigé (pack Athena 1.0.53 pour le jeu)
