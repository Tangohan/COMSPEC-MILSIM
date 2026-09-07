# Mon intégration — page nue sans utilité

## Contexte

La page membre `/mon-integration` doit servir d’espace d’arrivée : étapes visibles, rendez-vous à confirmer, messages de l’encadrement, lien vers la fiche.

## Symptôme

La page affichait un titre brut, des états techniques (`pending`), une barre de progression sans variables CSS, et un message vide quand aucun parcours n’était ouvert. Aucune action claire pour le membre.

## Cause

Prototype hors charte : CSS scoppé surtout au back-office (`.mi-admin` / `.mi-show`), pas de coque membre, libellés d’étape non traduits, invitations confirmées mélangées aux réponses en attente.

## Correctif

- Coque `.mi-member` avec hero, progression, puces d’état et grille utile.
- États d’étape en français, prochaine action mise en avant, état vide avec raccourcis fiche / compte / démarches.
- Invitations en attente séparées des rendez-vous déjà confirmés.

## Fichiers touchés

- `views/member_integration/index.php`
- `public/assets/css/member-integration.css`
- `app/Controllers/Web/MemberIntegrationController.php`
- `tests/Unit/MemberIntegrationAssetTest.php`

## Vérification

- Test `MemberIntegrationAssetTest::testMemberSelfServicePageHasChromeAndFrenchStepLabels`
- Contrôle visuel : ouvrir `/mon-integration` avec et sans parcours.

## Statut

corrigé
