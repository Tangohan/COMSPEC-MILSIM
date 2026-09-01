# Documents RH — dépôt de fichier invisible

## Contexte

Page Bureau effectifs → Documents RH (`/back-office/ressources/effectifs/documents-rh`). Le coffre enregistrait déjà les pièces côté serveur, mais l’écran de production ne proposait qu’un champ « Emplacement » (lien ou dossier partagé).

## Symptôme

Impossible de joindre un PDF, une image ou un document Word depuis « Ajouter une pièce ». Seul un texte d’emplacement était proposé. En local, un champ fichier existait mais était stylé comme une zone de saisie : hauteur fixe, fond sombre, bouton de choix coupé — on ne voyait pas qu’on pouvait déposer un fichier.

## Cause

Le formulaire production n’exposait pas le contrôle de dépôt. En local, `.eff-rh-field input` s’appliquait aussi aux champs fichier, ce qui les faisait ressembler à un emplacement vide.

## Correctif

Zone de dépôt visible (« Pièce jointe » / « Déposer le fichier »), emplacement conservé en option, ouverture de la pièce depuis le registre (et depuis Mon espace RH si la visibilité le permet).

## Fichiers touchés

- `views/admin/effectifs_workspace/rh_documents.php`
- `public/assets/css/effectifs_lms.css`
- `views/personnel/rh_workspace.php`
- `bootstrap/rh_dossier_individuel_migration.php`
- `tests/Unit/RhDossierIndividuelAssetTest.php`

## Vérification

Aperçu local du formulaire : zone en pointillés lisible, choix de fichier, nom affiché. Test d’assets `RhDossierIndividuelAssetTest`. Le serveur d’enregistrement (`storeFromUpload`) et l’ouverture (`downloadDocument`) étaient déjà en place.

## Statut

Corrigé (à déployer).
