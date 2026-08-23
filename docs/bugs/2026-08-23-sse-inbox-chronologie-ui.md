# Bureau SSE — inbox et chronologie : codes techniques et icônes génériques

## Contexte

Intelligence Workspace : colonnes Inbox, Chronologie et Contexte.

## Symptôme

- Pictogramme générique (cible) pour les fiches personnes et documents.
- Badges en anglais ou avec des tirets bas : `DOCUMENTARY`, `REQUIREMENT_CREATED`, `PERSON`.
- La même fiche personne apparaît deux fois dans l’inbox et dans la frise.

## Cause

Les types d’événement du cycle de renseignement n’étaient pas dans le catalogue de libellés : le code brut s’affichait. Les listes n’avaient pas de pictogrammes dédiés. Chaque transmission créait un événement distinct, recopié tel quel dans l’inbox.

## Correctif

- Catalogue français des natures d’événement, sources, types d’entité et niveaux d’identité.
- Cartes inbox / chronologie / entités récentes avec pictogrammes personne, document, dossier, biométrie, etc.
- Fusion des doublons visuels (même résumé, même nature) ; un badge « 2 fois » si besoin.

## Fichiers touchés

- `app/Support/SseWorkspaceUi.php`
- `app/Repositories/SseIntelEventRepository.php`
- `app/Repositories/SseEntityIndexRepository.php`
- `app/Services/Sse/SseIntelligenceWorkspaceService.php`
- `app/Services/Sse/SseIntelFoundationService.php`
- `views/atak/sse/workspace/_inbox.php`
- `views/atak/sse/workspace/_timeline.php`
- `views/atak/sse/workspace/_context.php`
- `views/atak/sse/workspace/_case_folder.php`
- `public/assets/css/sse_workspace.css`
- `public/assets/js/sse-intelligence-workspace.js`

## Vérification

Libellés unitaires (exigence créée, identité documentaire, fusion des doublons). Recharger le bureau SSE : cartes avec icônes, badges en français, une seule ligne PrenomUlu si les événements sont identiques.

## Statut

corrigé
