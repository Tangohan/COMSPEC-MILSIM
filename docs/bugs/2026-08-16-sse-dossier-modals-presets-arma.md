# Dossier SSE — création via modal + presets + remontées Arma

## Contexte

Sur la fiche dossier, les actions « rattacher une identité » et « verser une pièce » étaient des formulaires inline (`details`), sans création rapide ni lien avec les données terrain.

## Demande

Créer directement via modal, disposer de presets, et intégrer les données remontées par Arma 3.

## Correctif

- Modales : synthèse, identité (Créer / Rattacher / Remontées Arma), pièces (Nouvelle / Saisies terrain).
- Presets : `config/sse_case_presets.php` (modèles d’identité + types de pièces).
- Backend : `caseCreatePerson`, `caseImportSeizure`, inbox `listArmaInbox`, badge `from_arma`.
- Routes : `…/personnes/creer`, `…/preuves/saisie`.

## Fichiers

- `views/atak/sse/case_show.php`, `partials/case_modals.php`
- `public/assets/js/sse-case-modals.js`, `sse_portal.css`
- `SsePortalController.php`, `SsePersonRepository.php`, `routes/web.php`
- `config/sse_case_presets.php`

## Vérification

1. Ouvrir un dossier (gestionnaire) → **Ajouter une identité** → créer avec un modèle rapide.
2. Onglet **Remontées Arma** si des fiches SEEK existent.
3. **Verser une pièce** → preset « Téléphone saisi » ; onglet saisies si sites liés.

## Statut

corrigé
