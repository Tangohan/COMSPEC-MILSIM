# Traduction : les textes sans anglais se noyaient dans la relecture

## Contexte

Espace de traduction du site (`/traduire-site`) et atelier du téléphone (`/traduire-atak`).

## Symptôme

Un traducteur anglais ne pouvait pas isoler les textes qui n’ont encore que le français. Le filtre « À traduire » mélangeait la première passe (aucun anglais) et la relecture de l’anglais déjà livré. Dans le jeu, une application entière sans anglais (le bureau SSE, par exemple) n’était pas distinguée des applications déjà traduites.

## Cause

Le catalogue livré contient déjà un anglais pour une partie des textes. L’espace ne regardait que « une traduction communautaire existe ou non », pas « l’anglais livré est vide ou identique au français ».

## Correctif

Filtre « Seulement en français » : pas de traduction communautaire, et l’anglais livré est vide ou identique au français. Sur English, ce filtre est ouvert par défaut. Dans « À traduire » et « Tous les textes », ces lignes passent devant. Dans l’atelier du téléphone, le même filtre existe, et les applications sans aucun anglais sont signalées « Sans anglais » et remontent en tête.

## Fichiers touchés

- `app/Services/I18n/CommunityLanguages.php`
- `app/Services/I18n/SiteTranslationService.php`
- `public/assets/js/site-translate.js`
- `public/assets/js/atak-translate.js`
- `views/site/site_translate.php`
- `views/site/site_translate_workspace.php`

## Vérification

`php -l` sur les fichiers PHP touchés, et le test unitaire de la traduction communautaire. L’atelier du site déjà présent sur la branche principale a été conservé : la file « Seulement en français » s’y ajoute, elle ne remplace pas la liste et l’éditeur. Les pages en ligne demandent une session : le filtre n’a pas été rejoué sur le portail.

## Statut

Corrigé
