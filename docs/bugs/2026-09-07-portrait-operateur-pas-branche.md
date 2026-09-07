# Portrait opérateur : le lien n’était pas branché sur le dossier

## Contexte

Sur la fiche, le bandeau d’actions proposait « Photo de compte » et « Portrait opérateur ». Le second n’ouvrait pas le dépôt de photo de **cette** fiche.

## Symptôme

Le membre cliquait sur Portrait opérateur depuis le journal / le suivi et se retrouvait hors du dossier, ou sur une page de compte qui ne mettait pas à jour l’image visible sur la fiche.

## Cause

Le portrait se changeait uniquement dans Mon compte. Le dossier n’avait ni onglet ni envoi de fichier. Le lien de la fiche pointait vers cette page de compte, pas vers la fiche éditée.

## Correctif

Onglet **Portrait** dans l’édition du dossier, avec aperçu et dépôt. Le lien Portrait de la fiche ouvre cet onglet. L’image enregistrée est le portrait opérateur du dossier.

## Fichiers touchés

- `views/personnel/edit.php`
- `views/personnel/file.php`
- `app/Controllers/Web/PersonnelController.php`
- `routes/web.php`
- `views/personnel/tutorials.php`

## Vérification

- Contrôle syntaxe PHP
- Test `PersonnelDossierPortraitAssetTest`
- Test `DevDispatchCatalogTest` (UPDATE #472)

## Statut

Corrigé
