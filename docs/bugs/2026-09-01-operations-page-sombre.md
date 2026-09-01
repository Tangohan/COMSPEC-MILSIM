# Espaces opérationnels : page sombre et vide

## Contexte

Page https://athena.ttrd.fr/public/operations (espaces opérationnels), dans le portail Athena déjà clair (tableau de bord, personnel, effectifs).

## Symptôme

Fond presque noir, une carte sombre « Ouvrir une opération », puis une phrase isolée « Aucune opération n’est ouverte pour le moment » dans un grand vide. Le bandeau d’info Ancienneté (annonces sous le menu) paraît trop lourd, parce que tout le reste est une cave.

## Cause

La feuille `ops-workspace.css` peignait toute la page en charbon, indépendamment du chrome clair du portail. L’état vide n’était qu’une ligne. Le bandeau Ancienneté est une annonce globale, pas un élément de cette page.

## Correctif

La page reprend le fond clair, les cartes et le vert du tableau de bord. Sans opération, un encadré d’accueil. Le formulaire explique que l’indicatif est un nom court d’opération (AEGIS), pas celui de la communauté. La vue terrain plein écran reste sombre.

## Fichiers touchés

- `views/operations/workspace/index.php`
- `public/assets/css/ops-workspace.css`
- `app/Controllers/Web/OperationWorkspaceController.php`
- `app/Services/Operations/OperationWorkspaceService.php`
- `app/Support/OperationLabels.php`
- `tests/Unit/OperationsWorkspaceAssetTest.php`

## Vérification

Ouvrir Opérations : fond clair, encadré d’accueil et formulaire lisibles. Avec des opérations : cartes blanches, indicatif, statut, classification. Le bandeau d’annonces sous le menu n’est pas dupliqué.

## Statut

Corrigé
