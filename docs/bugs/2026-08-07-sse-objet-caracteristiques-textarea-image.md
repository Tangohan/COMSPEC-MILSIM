# Formulaire objet SSE — caractéristiques pauvres, sans zones de texte ni image

## Contexte

Création d’objet portail Athena SSE (`/atak/sse/objets/nouveau`), notamment pour les comptes rendus et documents.

## Symptôme

- Peu de champs utiles selon le type (compte rendu surtout).
- Aucune zone de texte longue : tout était en saisie d’une ligne.
- Pas d’upload d’image (scan, photo, capture).
- Les textes un peu longs étaient tronqués à 200 caractères en base (`meta_json`).

## Cause

- `SseMeshRepository::metaSchema()` trop minimal (report / document sans corps).
- Vue `object_create.php` ne rendait que `text` / `select`.
- `objectStore` sans `multipart` ni traitement d’image.
- `encodeMetaJson()` limitait chaque valeur à 200 caractères.

## Correctif

- Schémas enrichis avec `textarea` (situation, corps, suites, résumé, transcription, etc.).
- Formulaire : textareas, `enctype="multipart/form-data"`, upload d’image avec aperçu.
- Stockage image sous `public/uploads/sse/objects/`, chemin dans `meta.image_path`.
- Limite meta portée à 12 000 caractères (512 pour les chemins).
- Affichage de l’image et des lignes meta dans la fiche sélectionnée de la toile.

## Fichiers touchés

- `app/Repositories/SseMeshRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `views/atak/sse/object_create.php`
- `views/atak/sse/mesh_show.php`
- `views/atak/sse/_layout.php`
- `public/assets/css/sse_portal.css`
- `public/assets/js/sse-mesh.js`

## Vérification

1. Ouvrir « Créer un objet », type **Compte rendu** : zones Situation / Corps / Suites visibles.
2. Joindre une image JPEG/PNG : aperçu puis création → image visible sur la toile.
3. Type **Document** : résumé + transcription en zones de texte.

## Statut

corrigé
