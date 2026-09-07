# Compte : photo de compte morte et page fourre-tout

## Contexte

La page `/account/` mélangeait connexion, formations, fiche, santé des services, deux photos et deux liens vers les mêmes appareils ATAK. La « photo de compte » n’était plus affichée sur le portail : l’adresse redirigeait déjà vers le portrait.

## Symptôme

Un membre ne savait pas s’il devait poser une photo de compte ou un portrait. Le menu répétait les mêmes destinations que le centre de page. L’unité et le grade apparaissaient comme des réglages du compte.

## Cause

Ancien modèle à deux images (avatar civil / portrait). Le portail n’utilise plus que le portrait, mais les libellés et le hub n’avaient pas suivi. La vue d’ensemble recopiait tout le menu, plus l’intégration et l’état des services.

## Correctif

Un seul nom : **Portrait**. La vue d’ensemble montre connexion, nom affiché et portrait, puis quatre destinations. Le menu perd la photo de compte, le doublon ATAK, la charte et le départ déjà présents ailleurs. La pastille « Compte » collée sur la fiche disparaît.

## Fichiers touchés

- `views/account/index.php`
- `views/partials/account/shell_open.php`
- `views/account/portrait.php`
- `views/account/banner.php`
- `app/Controllers/Web/AccountController.php`
- `views/personnel/file.php`
- `app/Services/Alerts/AccountProfileAlertsBuilder.php`

## Vérification

- Contrôle syntaxe PHP
- Tests `AccountHubClarityAssetTest`, `PersonnelPublicFileHeroAssetTest`, `DevDispatchCatalogTest`

## Statut

Corrigé
