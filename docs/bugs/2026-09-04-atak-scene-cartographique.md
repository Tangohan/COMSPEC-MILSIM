# Scène cartographique ATAK (dessin, journal, modes)

- Date : 2026-09-04
- Statut : livré (pack 1.8.15) — fondations

## Contexte

La carte du téléphone était un fond satellite avec des points. Il fallait un moteur de scène : dessins structurés, zones, relecture, persistance locale.

## Symptôme

Pas de polygone / flèche / zone d’intérêt. Pas d’historique. Pas de lecture relief ou tactique.

## Cause

Le moteur ne conservait que la position et quelques marqueurs de session.

## Correctif

Moteur de scène dans l’écran : objets (polygone, flèche, cercle, danger, AO…), journal d’événements, relecture, modèles de fiche, trois lectures (plane, relief, tactique). Stockage d’abord sur l’appareil ; le journal est aussi noté dans le profil du joueur. File d’attente disque Athena et tuiles hors-ligne : étape suivante (l’extension a déjà une file mémoire).

## Fichiers touchés

- `web/map-engine.js`, `web/map-symbols.js`, `web/live-map.js`, `web/phone.html`
- `fn_scenePersist.sqf`, `fn_webJSDialog.sqf`, `config.cpp`

## Vérification

- Vérification : preview `phone.html?preview=map` — ZONE ROUGE, lecture Relief / Vue tactique, relecture après suppression.

## Statut

Fondations livrées. Hors-ligne tuiles + file disque : à enchaîner.
