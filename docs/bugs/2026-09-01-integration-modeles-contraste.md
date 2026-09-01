# Modèles d’intégration — contraste trop faible

## Contexte

Back-office Athena, pages `/back-office/integration-membres/modeles` (liste) et création / modification d’un modèle de parcours d’arrivée.

## Symptôme

Sur la liste, les noms, versions et durées étaient gris pâle sur fond blanc : presque illisibles. Le formulaire s’affichait comme une carte bleu-gris, avec des champs sombres et des libellés trop discrets. Les durées en jours prenaient toute la largeur ; les cases à cocher et la hiérarchie entre « Étapes » et « Étape 1 » étaient difficiles à suivre. Beaucoup de vide sous le tableau.

## Cause

La feuille `member-integration.css` était écrite pour un thème sombre (texte clair, fonds ardoise) alors que la coque back-office est claire. Les vues ajoutaient un second titre et une carte sombre au-dessus d’une zone déjà blanche.

## Correctif

La feuille reprend les couleurs du bureau (encre sombre, surface blanche). La liste utilise le tableau Athena, un état vide, et un bouton « Nouveau modèle ». Le formulaire reprend les cartes de saisie du bureau : bloc parcours, blocs d’étapes bordés, durées en case courte, cases à cocher lisibles.

## Fichiers touchés

- `public/assets/css/member-integration.css`
- `views/admin/member_integration/templates.php`
- `views/admin/member_integration/template_form.php`
- `config/back_office_pages.php`
- `app/Controllers/Admin/MemberIntegrationAdminController.php`
- `tests/Unit/MemberIntegrationAssetTest.php`

## Vérification

Test `MemberIntegrationAssetTest::testTemplateAdminScreensUseLightBackOfficeChrome`. Contrôle navigateur de la liste et du formulaire si une session back-office est disponible.

## Statut

corrigé
