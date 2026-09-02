# Documents — fichier joint invisible et non retirable

## Contexte

Page de modification d’une fiche (`/documents/gestion/{id}/modifier`). Un document peut avoir une pièce jointe (version courante).

## Symptôme

Impossible d’ouvrir ou de prévisualiser le fichier actuellement joint. Impossible de le retirer : la fiche ne proposait que l’ajout d’une nouvelle version.

## Cause

La page d’édition listait les versions sans lien vers le fichier, ni action de retrait. Les adresses d’ouverture et de téléchargement existantes n’acceptaient que les fiches publiées, donc un brouillon restait inaccessible depuis la modification.

## Correctif

Bloc **Fichier joint** : Ouvrir le fichier, Télécharger, Retirer le fichier (case de confirmation). Le retrait conserve la fiche et vide le pointeur de la version courante ; le fichier existant est rangé à part, jamais inventé. Un gestionnaire de la fiche peut ouvrir le fichier même si elle n’est pas encore publiée. Une fiche sans fichier (NULL) reste un état normal.

## Fichiers touchés

- `views/admin/documents/edit.php`
- `app/Controllers/Admin/AdminDocumentsController.php`
- `app/Controllers/Web/DocumentsController.php`
- `app/Services/Documents/DocumentUploadService.php`
- `app/Support/DocumentAttachedFile.php`
- `app/Repositories/DocumentVersionRepository.php`
- `routes/web.php`

## Vérification

Tests `DocumentAttachedFileTest`, `DocumentEditFileActionsTest`, `DevDispatchCatalogTest`. Sur une fiche avec pièce jointe : ouvrir, télécharger, retirer (la fiche reste, plus de fichier affiché).

## Statut

corrigé
