# Athena Data Inspector

## Accès et objectif

La page `/athena/data-inspector` montre ce qu'Athena a réellement persisté, pas un miroir direct du jeu. Elle est réservée aux administrateurs de communauté/système. Elle réutilise le layout, la session, le RBAC, les clés ATAK, la base SQL et les conventions visuelles Athena.

## Vues

* **LIVE** : flux par polling adaptatif léger (3 s), limité à 500 lignes. Colonnes heure, source, type, entité, monde, taille, statut et latence. Pause bloque les requêtes ; « Effacer la vue » ne touche qu'à l'affichage local.
* **MAP** : projection des `athena_live_state` et `athena_map_objects` en coordonnées monde Arma. « Show raw server state » inclut les tombstones. La grille ne prétend pas restituer une donnée absente.
* **PAYLOAD** : métadonnées et JSON formaté via `textContent`, donc sans injection HTML ; copie, brut et recherche de l'historique d'entité.
* **HISTORY** : événements persistants recherchables. Les positions/BFT coalescés ne polluent pas cet historique ; la structure permet un futur Mission Replay.
* **HEALTH** : compteurs d'ingestion, états API/BDD/polling/stockage/auth et couverture terrain. Une valeur indisponible est affichée `N/A`.

## Panneaux permanents

**Data Sources** calcule ONLINE (<45 s), DEGRADED (45 s–5 min) et OFFLINE (>5 min) depuis `last_seen_at`. **Sync Center** affiche l'état des sources ; la taille de file reste `N/A` tant qu'un terminal n'envoie pas cette information. **Pipeline Debug** affiche les timestamps ARMA, extension, HTTP, Athena, publication et web présents ; aucun timestamp manquant n'est inventé.

## Filtres et temps réel

Recherche plein texte locale, type, monde et statut se combinent. Le serveur renvoie uniquement les données du tenant de la session. Le polling a été retenu car le portail PHP existant documente explicitement le mode sans Socket.IO ; aucune dépendance temps réel lourde n'a été ajoutée.

## Terrain et synchronisation

La couverture agrège les chunks par monde, couche et état `UNKNOWN/PARTIAL/COMPLETE/ERROR`. Le schéma conserve bounds et hash pour une future heatmap précise. Les sessions/items de sync permettent les résumés de batch et conflits futurs ; le socle d'ingestion gère déjà idempotence, coalescence d'état, tombstones et versions.

## Mode DEV

Hors production uniquement, le bouton orange crée cinq sources `DEV-*`, positions, BFT, marqueur, dessin, route, chunks terrain et heartbeat. Les données portent `source_type=dev_debug`, mission `DEV_INSPECTOR` et serveur `LOCAL-DEBUG`. Le bouton est absent et l'endpoint renvoie 404 en production. Les payloads invalides se testent directement contre l'endpoint afin de vérifier les rejets sans insérer de fausse donnée.
