# Tableur — portrait opérateur perdu à l’enrichissement

## Contexte

Le tableur des effectifs (`/back-office/ressources/effectifs`) doit afficher le portrait opérateur de chaque membre, pas la photo de compte.

## Symptôme

Le portrait restait vide ou tombait sur le visuel de repli, même lorsque le dossier avait une image opérateur.

## Cause

`listForTenant` ne porte pas le chemin du portrait (il vient du dossier personnel).
`enrichRosterRows` chargeait bien ce chemin via `listEffectifsRosterByIds`, puis le perdait : le fusionnement ne recopiait pas `character_portrait_path`.
La fonction de portrait renvoyait aussi le visuel de repli à la place d’un vide, ce qui empêchait d’afficher les initiales.

## Correctif

- Recopier `character_portrait_path` lors de l’enrichissement des lignes.
- Le portrait du tableur n’utilise que l’image opérateur ; sinon, les initiales.
- Repli visuel `portrait` si le fichier est introuvable.

## Fichiers touchés

- `app/Controllers/Admin/EffectifsWorkspaceController.php`
- `app/Support/helpers.php`
- `views/admin/effectifs_workspace/roster.php`

## Vérification

- Tests unitaires du portrait et des assets du tableur.
- Contrôle visuel : une ligne avec portrait opérateur affiche cette image ; une ligne sans portrait affiche les initiales.

## Statut

corrigé
