# Toile SSE — images / photos 3D absentes sur le canevas

## Contexte

Investigations (`/atak/sse/toiles/{id}`) : objets avec image jointe, ou identités
liées à une fiche personne ayant une photo terrain (SEEK / Arma).

## Symptôme

Les images « ne remontent pas » sur la toile : nœuds sans vignette ; photo visible
seulement (parfois) dans le panneau de sélection si `meta.image_path` était déjà
renseigné.

## Cause

1. Le canevas SVG n’affichait que l’initiale du type — jamais `image_url`.
2. Les nœuds importés depuis un dossier (`seedFromCase`) ne copiaient pas la photo
   primaire de la fiche identité.
3. Les nœuds `person` avec `ref_id` sans `meta.image_path` n’étaient pas enrichis
   à la lecture.

## Correctif

- `sse-mesh.js` : vignette circulaire sur le nœud si `image_url`.
- `SseMeshRepository::attachLinkedPersonPhotos` : reprend la photo primaire via
  `user_media_public_url` (préfixe `/public` inclus).
- `SseMeshService::seedFromCase` : pose `image_path` dans le meta à l’import.

## Fichiers touchés

- `public/assets/js/sse-mesh.js`
- `views/atak/sse/mesh_show.php`
- `app/Repositories/SseMeshRepository.php`
- `app/Services/Sse/SseMeshService.php`

## Vérification

1. Objet créé avec image → toile : pastille photo sur le nœud + panneau.
2. Identité avec mugshot liée (`ref_type=person`) → vignette même sans meta local.
3. Si la fiche identité n’a **pas** de photo (SEEK non rebuild / `file_not_found`),
   rien à afficher — autre bug captures Arma.

## Statut

corrigé côté web — photos terrain absentes restent un sujet Overwatch / SEEK
