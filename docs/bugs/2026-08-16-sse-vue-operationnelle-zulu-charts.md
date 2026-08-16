# Vue opérationnelle — Zulu, graphiques, alertes, animations

## Contexte
Écran Pilotage → Situation (`/atak/sse/operations`), Control Tower SSE.

## Symptôme
Horaires sans fuseau explicite, peu d’alertes actionnables, pas de graphiques ni d’animations d’entrée.

## Cause
La vue affichait des KPI et listes texte ; les timestamps n’étaient pas normalisés en Zulu ; pas de séries pour graphiques.

## Correctif
- Horloge Zulu live (`HH:MM:SSZ`) + date UTC ; activité / objets horodatés en Zulu.
- Graphiques : activité 24 h (8 créneaux 3 h) + charge de travail ; file avec barres de proportion.
- Alertes enrichies (niveaux, action, pulse critique/élevée) + état nominal si vide.
- Animations : fade-in panels, count-up KPI, montée des barres.

## Fichiers touchés
- `app/Services/Sse/SseWorkspaceService.php` (`controlTower`)
- `views/atak/sse/operations.php`
- `public/assets/css/sse_workspace.css`
- `views/atak/sse/_layout.php` (cache-bust CSS)

## Vérification
Recharger Pilotage → Vue opérationnelle : horloge `…Z`, graphiques, alertes cliquables, animations à l’entrée.

## Statut
corrigé
