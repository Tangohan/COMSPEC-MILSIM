# Documents — fichier annoncé introuvable après modification

## Contexte

Fiche de modification d’un document (bibliothèque / doctrine). L’opérateur enregistre la fiche, puis ouvre le fichier depuis le référentiel (`Ouvrir le fichier`).

## Symptôme

La fiche affiche « Document mis à jour ». L’ouverture du fichier montre « Fichier non consultable » : le fichier n’est pas encore déposé, ou il n’est plus disponible.

## Cause

- Le bouton **Enregistrer** du haut de fiche ne prenait pas le fichier : le dépôt passait seulement par le formulaire de version plus bas.
- La page publique proposait **Ouvrir le fichier** dès qu’une version existait, même si le fichier n’était plus sur le serveur.
- Un pointeur officiel (texte livré avec la doctrine) pouvait rester affiché alors que le fichier n’était plus à cet emplacement.

## Correctif

Choix du fichier dans Contenu, pris en compte par Enregistrer. Avertissement si le fichier n’est plus disponible. L’ouverture cherche une version encore présente. Recopie du texte livré uniquement lorsque le pointeur officiel est vide.

## Fichiers touchés

- `views/admin/documents/edit.php`
- `app/Controllers/Admin/AdminDocumentsController.php`
- `app/Controllers/Web/DocumentsController.php`
- `app/Controllers/Web/DoctrineDocumentsController.php`
- `app/Support/DocumentAttachedFile.php`
- `app/Repositories/DocumentVersionRepository.php`
- `bootstrap/doctrine_atak_employment_seed.php`
- `views/documents/doctrine_show.php`

## Vérification

Tests `DocumentAttachedFileTest`, `DocumentEditFileActionsTest`, `DocumentFileMissingAssetTest`, `DoctrineAtakEmploymentAssetTest`, `DevDispatchCatalogTest`. Sur la fiche concernée : choisir le fichier dans Contenu, Enregistrer, puis Ouvrir depuis le référentiel.

## Statut

corrigé
