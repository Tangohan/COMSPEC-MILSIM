# Alerte vibrante — téléphones suivis à proximité

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Les opérateurs ATAK alliés devaient être prévenus (vibration) lorsqu’ils s’approchent d’un téléphone suivi par Zeus, avec un rayon choisi sur le terminal (200 m, 1 km, etc.).

## Symptôme

Aucun buzz ni bandeau quand on entre dans le rayon d’un téléphone géolocalisé. Le rayon n’était pas réglable sur l’ATAK.

## Cause

La vibration existait seulement comme appel manuel depuis le poste (ordre « faire vibrer »). Il n’y avait pas de veille de distance entre l’opérateur et les contacts `phone_geoloc` / `COMSPEC_PhoneTrack`.

## Correctif

- Paramètres ATAK : liste « Alerte téléphones suivis » (désactivée, 50 m, 100 m, 200 m, 500 m, 1 km, 2 km). Défaut 200 m, mémorisé dans le profil.
- Boucle locale ~1,5 s : entrée dans le rayon → triple vibration + bandeau. Une alerte par contact tant qu’on reste dedans ; ré-armement après sortie (bande à 1,15 × le rayon).
- Carte web : même liste (barre d’alertes + compte). Vibration de l’appareil si l’indicatif de session correspond à un ATAK allié qui s’approche d’un téléphone suivi. Mode « sans vibration » respecté.

## Fichiers touchés

- `app/Services/Tactical/AtakPhoneProximity.php`
- `tests/Unit/AtakPhoneProximityTest.php`
- `public/assets/js/atak-phone-proximity.js`
- `views/atak.php`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/` (Paramètres, tick, alerte, 1.0.45)
- `storage/app_version.json` (1.5.26)

## Vérification

Smoke PHP : entrée à 180 m / 200 m alerte ; 220 m encore dedans sans nouvelle alerte ; 250 m sortie puis ré-entrée. En jeu : régler le rayon sur l’ATAK, s’approcher d’un téléphone suivi, sentir la vibration. Web : même test avec indicatif de session.

## Statut

corrigé
