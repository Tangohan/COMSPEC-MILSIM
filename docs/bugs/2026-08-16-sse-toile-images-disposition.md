# Toile SSE — images cassées + disposition non enregistrée

**Date :** 2026-08-16  
**Statut :** corrigé

## Contexte

Investigation `/atak/sse/toiles/{id}` (ex. MESH-2026-0004) : image jointe
affichée en « cassée » dans le panneau Sélection ; bouton
« Enregistrer la disposition » sans effet durable.

## Symptôme

1. Vignette panneau : icône image brisée / alt « Image jointe à l’objet ».
2. Clic sur enregistrer la disposition : pas de confirmation fiable, positions
   perdues au rechargement.

## Cause

1. **Images** — URLs médias parfois sans préfixe `/public` devant `/uploads/…`
   (docroot Hostinger), ou chemin non normalisé ; le panneau n’avait pas de
   repli en cas de 404.
2. **Disposition** — sauvegarde en `FormData` fragile ; `UPDATE` avec
   `rowCount === 0` (coords déjà identiques) compté comme échec ; pas de lecture
   du corps JSON ; erreurs JSON/HTML avalées sans message.

## Correctif

- `user_media_public_url` / `normalize_public_uploads_url` : forcer `/public/uploads`.
- `SseMeshRepository::resolveNodeImage` + résolution côté JS (`mediaBase` / `basePath`).
- Message clair si le fichier est introuvable.
- `meshSaveLayout` : POST JSON + CSRF, contrôle `mesh_id`, succès même si coords
  inchangées, messages d’erreur explicites sur le bouton.

## Fichiers touchés

- `app/Support/helpers.php`
- `app/Repositories/SseMeshRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `public/assets/js/sse-mesh.js`
- `views/atak/sse/mesh_show.php`

## Vérification

1. Recharger l’investigation (cache JS `?v=202608162330`).
2. Sélectionner l’entité avec image → vignette OK, ou message « Image introuvable ».
3. Déplacer des nœuds → « Enregistrer la disposition » → libellé de succès → F5 → positions conservées.

## Statut

Livré — à déployer sur Athena.
