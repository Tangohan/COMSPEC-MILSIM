# Éditeur cartographique ATHENA

## Architecture retenue

L'éditeur complète le canal ATAK existant au lieu de le remplacer. Les objets préparés sont stockés dans
`athena_tactical_markers`; chaque mutation écrit, dans la même transaction, un événement ordonné dans
`athena_tactical_events`. Les anciens clients continuent d'appeler `GetMarkers`: les points publiés y sont
projetés au format historique. Les clients récents peuvent utiliser le flux cursorisé.

## Migration

1. Sauvegarder la base MySQL.
2. Exécuter `migrations/20260904190000_athena_tactical_editor.sql` avec l'outil de migration habituel.
3. Déployer PHP, les assets, l'extension NativeAOT puis reconstruire le PBO `connect`.
4. Ouvrir **ATHENA > Opérations > Carte tactique** à
   `/back-office/operations/carte-tactique` et vérifier le terrain.

La migration est additive: elle ne modifie ni `atak_markers`, ni les formes existantes, ni les passerelles P2P.

## API

* `GET /api/athena/tactical/sync?world_name=Altis&cursor=0` renvoie un snapshot publié.
* Le même appel avec un curseur non nul renvoie au plus 500 événements `MARKER_CREATE`,
  `MARKER_UPDATE` ou `MARKER_DELETE`, ainsi que le prochain curseur.
* `POST /api/athena/tactical/markers` crée un brouillon.
* `PATCH /api/athena/tactical/markers/{uuid}` exige `revision`; une révision périmée reçoit HTTP 409
  avec la version courante.
* `DELETE /api/athena/tactical/markers/{uuid}?revision=N` produit une tombstone synchronisable.

Les lectures jeu exigent la clé ATAK existante. Les mutations de préparation exigent une session back-office;
les ACL de route existantes imposent en plus le droit de gestion ATAK. Seuls les objets `PUBLISHED` sont servis
au terminal. Les portées de visibilité sont conservées côté serveur pour l'intégration des groupes/opérations.

## Map packs

`athena_map_packs` référence un répertoire hors PBO et son manifeste (terrain, version, format, zooms,
bounds, SHA-256 et taille). La résolution reste: cache local, `/map-data/{world}`, puis carte Arma native.
