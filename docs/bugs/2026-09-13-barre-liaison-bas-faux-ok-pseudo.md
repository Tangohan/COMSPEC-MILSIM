# Barre de liaison — bas d’écran, faux OK avec pseudo

## Contexte

13 septembre 2026. Téléphone ATAK. La barre OK/NOK était sous la barre d’état (haut), avec un indicatif/pseudo trop gros. Affichage « OK » alors que le bandeau montrait le pseudo Arma (ex. NewPI) au lieu du prénom/nom du compte Athena.

## Symptôme

- Barre de liaison trop haute, gêne la lecture de la carte / boussole.
- Texte « TA1 · NewPI » trop visible.
- Statut OK alors que l’identité compte n’est pas chargée (pseudo jeu à la place).

## Cause

- Ancrage calculé sous la barre d’état cTab (`_hy + _hh`).
- Ligne identité en `size='0.90'` trop grande.
- Repli `name player` si `comspec_profile_name` vide, tout en autorisant OK via `AthenaReady` **ou** santé fraîche seule.

## Correctif

- Ancrage en bas du rectangle carte.
- Indicatif / nom en taille très réduite (`size='0.55'`).
- Nom = fiche Athena uniquement ; si égal au pseudo jeu → traité comme absent (« compte… »).
- OK strict : liaison `linked` + Athena prêt + identité compte réelle ; sinon OK* / NOK.

## Fichiers touchés

- `fn_athena_updateLinkStrip.sqf`
- `atak_athena/config.cpp` (1.0.103)
- `app/Support/DevDispatchCatalog.php` (UPDATE #525)
- `docs/bugs/2026-09-13-barre-liaison-bas-faux-ok-pseudo.md`

## Vérification

1. Pack Athena 1.0.103, quitter Arma.
2. Ouvrir le téléphone : barre en bas de la carte, texte identité tout petit.
3. Compte lié avec prénom/nom : OK + nom Athena (pas le pseudo).
4. Sans identité compte : pas de faux OK ; « compte… » visible.

## Statut

corrigé — Athena 1.0.103
