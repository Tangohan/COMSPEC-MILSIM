# Courrier — impossible d’enregistrer sa signature hors document

## Contexte

Bureau Courrier (`/courrier`). L’opérateur veut créer sa signature avant de signer un courrier.

## Symptôme

La signature ne pouvait s’enregistrer que pendant la signature d’un document déjà validé. Depuis le tableau de bord, aucun moyen de la dessiner et de la conserver.

## Cause

Le pad n’existait que dans la fenêtre « Signer le document ». Il n’y avait pas de page dédiée.

## Correctif

Page « Ma signature » : dessin, nom, signature principale, retrait. Lien depuis le tableau de bord et depuis l’éditeur.

## Fichiers touchés

- `app/Controllers/Courrier/CourrierSignatureController.php`
- `app/Services/Courrier/DocumentSignatureService.php`
- `views/courrier/signature.php`
- `views/courrier/dashboard.php`
- `routes/web.php`

## Vérification

Ouvrir le bureau Courrier → Ma signature. Dessiner, enregistrer. La signature apparaît dans la liste. En signant un courrier, elle est proposée sous « Ma signature enregistrée ».

## Statut

corrigé
