# Overwatch Beta — vue Réseau trop sommaire sur les terminaux

**Statut :** corrigé (sources)

## Contexte

Espace Réseau du poste Overwatch Beta.

## Symptôme

Les terminaux ATAK n’affichaient qu’une ligne (type · certificat · activité). Manquaient certificat détaillé, fiabilité, transmission, débit, taux de transmission, ressources relais, adresse ATAK, versions pack / liaison / jeu.

## Cause

`networkTerminalRowHtml` ne montrait qu’un résumé ; les champs existent déjà côté terminal + télémétrie unité.

## Correctif

Fiche détaillée par terminal (et relais) : certificat / échéance, liaison, transmission, fiabilité (100 − perte), débit (télémétrie ou relais proche), paquets reçus/envoyés, places relais, adresse ATAK et réseau, versions Overwatch / Liaison Athena / jeu quand connues.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/css/atak-overwatch-beta.css`

## Vérification

1. Ctrl+F5 Overwatch Beta → Réseau.
2. YA1 / TA1 : lignes détaillées sous chaque terminal.
3. Relais (s’il y en a) : débit, fiabilité, places, identité.

## Statut

corrigé (sources) — déploiement portail requis
