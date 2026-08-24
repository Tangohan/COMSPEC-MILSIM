# ATAK web — décalage de 2 h et terminaux en double

## Contexte

Onglet Terminaux de la carte ATAK. Les fiches affichent la dernière activité et un appareil par opérateur.

## Symptôme

- « Dernière activité : il y a 2 h » alors que le poste vient d’émettre (heure d’été en France).
- Le même indicatif (ex. N-10, même identifiant militaire) apparaît deux fois, avec d’anciennes versions du mod (1.4.x et 1.3.0).

## Cause

- `last_seen_at` est enregistré en UTC (`UTC_TIMESTAMP`) sans fuseau. Le navigateur lisait `2026-08-24 15:10:00` comme heure de Paris → 2 h de trop.
- L’identifiant local du terminal était régénéré (hasard + horloge) dès que le profil Arma était vide, donc une nouvelle fiche Athena pour le même opérateur.

## Correctif

- L’API envoie la dernière activité en heure universelle (`…Z`) ; l’onglet Terminaux la lit comme UTC.
- La liste web ne garde qu’une fiche jeu (et une fiche téléphone) par opérateur, la plus récente.
- Au prochain enregistrement, les anciennes fiches jumelles sont fusionnées / retirées.
- Génération d’identifiant local stable (Steam + profil), sans hasard.

## Fichiers touchés

- `app/Repositories/AtakRealismRepository.php`
- `public/assets/js/atak-terminals.js`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getTerminalUid.sqf`
- `tests/Unit/AtakWebSessionTerminalTest.php`

## Vérification

- Assertions PHP : `collapsePhysicalDuplicates` (N-10 ×2 → 1 jeu + 1 téléphone) et `mysqlUtcToIso`.
- Contrôle syntaxe PHP du dépôt.

## Statut

corrigé
