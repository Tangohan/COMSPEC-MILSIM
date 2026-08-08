# Documents SSE — corps pauvres et rendu sans UI papier

## Contexte

Atelier de rédaction SSE (`/atak/sse/documents/nouveau` et fiche document).

## Symptôme

- Corps de document trop sommaires (quelques tirets).
- Lecture en `<pre>` sombre, sans rendu type document officiel du module Documents / Courrier.

## Cause

- Templates `documentTemplate()` minimalistes.
- Aucune couche de présentation papier (bandeau classification, filigrane, en-tête, signature).

## Correctif

- Templates enrichis par type (flash, CR, note, synthèse, diffusion) dans `SseDocumentRepository::bodyTemplate()`.
- Rendu papier inspiré Courrier/CERBERE (`partials/document_paper.php`).
- Formulaire en atelier double panneau (édition + aperçu live).
- Page lecture en feuille A4 avec signature / tampon de validation.

## Fichiers touchés

- `app/Repositories/SseDocumentRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `views/atak/sse/document_form.php`
- `views/atak/sse/document_show.php`
- `views/atak/sse/partials/document_paper.php`
- `public/assets/css/sse_portal.css`
- `views/atak/sse/_layout.php`

## Vérification

1. Nouveau document → type Flash : corps guidé long, aperçu papier à droite.
2. Changer de type sans éditer : modèle rechargé.
3. Créer / ouvrir : feuille blanche, classification, signature.
4. Valider : tampon « Document validé ».

## Statut

corrigé
