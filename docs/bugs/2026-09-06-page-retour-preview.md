# Questionnaire preview : page de retour de nouveau visible

## Contexte

La preview Athena avait un questionnaire d’avis. Il n’était plus proposé que pendant une session de démonstration, donc presque personne ne le trouvait.

## Symptôme

Aucun lien public pour donner son avis. L’adresse historique n’était plus mise en avant.

## Cause

Le questionnaire restait branché uniquement sur le minuteur de démonstration, pas sur le pied de page ni le tableau de bord.

## Correctif

Adresse publique `/retour-preview` (l’ancienne adresse reste valable). Lien « Donner votre avis » dans le pied de page et sur le tableau de bord. Questions fermées sur les trois niveaux d’accès.

## Fichiers touchés

- `app/Services/DemoNda/DemoNdaGateService.php`
- `app/Controllers/Web/DemoNdaController.php`
- `views/demo_nda/feedback.php`
- `views/partials/portal_footer.php`
- `views/partials/dashboard_command_center.php`

## Vérification

- Test `PreviewFeedbackPageAssetTest`
- Ouvrir `/retour-preview` sans code d’accès : le questionnaire s’affiche

## Statut

Corrigé.
