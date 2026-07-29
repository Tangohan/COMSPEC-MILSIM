# Bug — rapports structurés avec détail vide

## Contexte

Comptes rendus tactiques consultés depuis l’ATAK web, via le journal d’activité et la fiche de détail.

## Symptôme

- certains comptes rendus structurés affichaient `Aucun détail textuel.`
- le cas visible était `SALUTE`
- le même risque existait pour les autres rapports structurés (`SPOTREP`, `SITREP`, `CONTACT`, `SALUTE`) quand l’activité ne gardait pas les champs détaillés

## Cause

- l’événement d’activité ne stockait pas systématiquement les métadonnées structurées utiles au front
- pour `SALUTE` web, seule l’information de type était journalisée au début
- pour `TACTICAL_REPORT`, le résumé était loggé mais les champs détaillés (`structured_data`, priorité, grille, remarques, etc.) n’étaient pas aplatis dans les métadonnées lisibles par la fiche web

## Correctif

- enrichissement de l’activité `SALUTE` avec `salute`, `summary`, `grid`, `pos_x`, `pos_y`, `chat_id`
- enrichissement de l’activité `TACTICAL_REPORT` avec :
  - type, numéro, priorité, classification
  - résumé, détails, remarques
  - grille, localisation, DTG, heure signalée
  - champs structurés aplatis avec préfixe `report_`
- ajout des libellés FR côté front pour que ces champs soient affichés proprement

## Fichiers touchés

- `app/Controllers/Api/AtakApiController.php`
- `public/assets/js/atak-activity.js`
- `public/assets/js/tacmap-tactical-alerts.js`
- `views/atak.php`

## Vérification

- lint JS/PHP sans erreur signalée par l’éditeur
- contrôle du flux logique :
  - création du rapport
  - journalisation enrichie
  - lecture des métadonnées côté fiche activité

## Statut

`corrigé à vérifier en jeu`
