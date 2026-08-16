# Aperçu PDF — UI indépendante de la classification

## Contexte

Création / édition d’un document SSE (`/atak/sse/documents/...`) : l’aperçu papier à droite doit refléter le niveau de classification choisi dans le formulaire.

## Symptôme

1. Quel que soit le niveau (Diffusion interne, Encadrement, Confidentiel, Diffusion très restreinte), le bandeau, le tampon et les mentions de canal restaient en rouge « très restreint ».
2. Le corps du document (surtout « Version de diffusion ») restait plat : titres peu hiérarchisés, avertissement non mis en avant, champs vides peu lisibles ; l’éditeur gauche était un bloc sombre type console.

## Cause

- Styles CSS figés (`#b91c1c`) sur bandeau / tampon / caveats ; `syncMeta()` ne basculait pas le thème.
- Parseur `bodyToHtml` trop minimal (sections = texte brut, pas de bloc avertissement ni lignes de champ).
- Éditeur `.sse-desk-paper` en thème sombre monospace, déconnecté de l’aperçu officiel.

## Correctif

- Modificateurs `.sse-doc-paper--*` + variables CSS ; sync live du thème et du canal.
- Parseur enrichi (PHP + JS) : bloc avertissement, numéros de section, champs libellé/valeur, puces et lignes à remplir.
- Styles corps papier + éditeur crème aligné sur la feuille officielle.

## Fichiers touchés

- `public/assets/css/sse_portal.css`
- `views/atak/sse/partials/document_paper.php`
- `views/atak/sse/partials/case_cover.php`
- `views/atak/sse/document_form.php`
- `app/Support/SseDocumentMarkings.php`
- `app/Repositories/SseDocumentRepository.php`

## Vérification

1. Changer la classification : couleurs bandeau / tampon / canal.
2. Type « Version de diffusion » : avertissement encadré, sections numérotées, champs soulignés dans l’aperçu ; éditeur gauche en feuille claire.

## Statut

Corrigé
