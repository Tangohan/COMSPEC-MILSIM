# Fiche d’intégration : parcours difficile à suivre

## Contexte

Page back-office `Intégration des recrues` → fiche d’un parcours (`/back-office/integration-membres/{id}`).

## Symptôme

La fiche empilait les étapes sans mettre en avant la prochaine action. Les formulaires de rendez-vous et d’annulation occupaient la colonne latérale en permanence. On ne voyait pas d’un coup d’œil le référent, le reste à faire, ni l’état du dossier.

## Cause

L’écran listait les blocs métier sans synthèse opérationnelle ni chronologie visuelle.

## Correctif

- Bandeau de synthèse : statut, barre d’avancement, référent, reste à faire, prochaine étape.
- Étapes en chronologie numérotée, étape courante mise en avant, bouton « Valider l’étape en cours ».
- Rendez-vous et annulation repliés derrière un détail. Colonne latérale collante.

## Fichiers touchés

- `views/admin/member_integration/show.php`
- `public/assets/css/member-integration.css`
- `public/assets/css/back-office-dark.generated.css`
- `tests/Unit/MemberIntegrationAssetTest.php`

## Vérification

Rendu local avec une étape terminée et une étape à faire : « À faire maintenant », chronologie, labels français, pas de codes internes.

## Statut

Corrigé
