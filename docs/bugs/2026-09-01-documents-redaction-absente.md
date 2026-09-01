# Documents — rédaction absente

## Contexte

À la création d’un document (`/documents/gestion/ajout`), seuls le dépôt de fichier et la fiche sans fichier étaient proposés.

## Symptôme

Impossible de rédiger un manuel dans Athena. Pas de page de garde, ni de page de signatures.

## Cause

Le formulaire et l’enregistrement exigeaient un fichier (sauf case « sans fichier »). Aucun corps rédigé n’était stocké ni affiché.

## Correctif

Choix **Joindre un fichier** / **Rédiger le document**. La rédaction enregistre la page de garde, l’avant-propos, les signatures et le texte. À l’ouverture, le manuel s’affiche comme un document imprimé.

## Fichiers touchés

- `app/Support/DocumentManuscript.php`
- `app/Controllers/Admin/AdminDocumentsController.php`
- `app/Controllers/Web/DocumentsController.php`
- `views/admin/documents/upload.php`
- `views/partials/document_fm_paper.php`
- `public/assets/css/document-fm.css`

## Vérification

Tests unitaires `DocumentManuscriptTest` et `DocumentAuthoredAssetTest`. Formulaire : bascule Rédiger, aperçu page de garde + signatures.

## Statut

corrigé
