# Brevet de qualification — « Membre # » à la place du pseudo

## Contexte

Page opérateur Mes qualifications (`/back-office/ma-situation/qualifications`) et génération du brevet.

## Symptôme

Le brevet affiche « Membre #123 » (ou un numéro de compte) à la place du pseudo de communauté.

## Cause

La génération lisait `first_name`, `last_name` et `username` sur `users`. Ces colonnes n’existent plus (identité civile et fiche communauté séparées). La requête échouait, et le repli écrivait « Membre # » + identifiant. Même quand la requête passait, le pseudo réel est désormais dans `user_community_profiles`.

## Correctif

- Titulaire = pseudo de communauté (`display_name`), sinon indicatif (`callsign`).
- Plus de repli « Membre # ».
- Bandeau Version d’essai + bouton Mettre à jour le brevet pour recalculer un document déjà établi.

## Fichiers touchés

- `app/Services/Personnel/QualificationCertificatePdfService.php`
- `app/Controllers/Admin/Organization/MemberSituationController.php`
- `app/Core/Container.php`
- `views/admin/member_situation/qualifications.php`
- `public/assets/css/back-office-member-situation.css`
- `tests/Unit/QualificationReferentielTest.php`
- `tests/Unit/MemberSituationBackOfficeAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE #748)

## Vérification

- `pickHolderName` : pseudo prioritaire, indicatif ensuite, jamais le prénom/nom civil ni un numéro de compte.
- Assertions de page : bandeau Version d’essai, « Brevet au nom de », « Mettre à jour le brevet ».
- La génération n’interroge plus l’identité civile absente ; le pseudo vient de la fiche communauté.

## Statut

Corrigé.
