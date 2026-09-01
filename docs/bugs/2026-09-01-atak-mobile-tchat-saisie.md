# Tchat téléphone — saisie vidée et messages illisibles

## Contexte

Tchat ATAK sur téléphone (et le même écran ouvert sur ordinateur) : `/atak/mobile/chat`.

## Symptôme

- La zone d’écriture se vide toutes les quelques secondes, le curseur sort du champ : impossible d’écrire un message long.
- Les messages de groupe et les alertes médicales s’affichent en une seule ligne technique, trop petite.

## Cause

Le suivi des messages reconstruisait tout l’écran tchat, y compris le champ de saisie. Le texte du message était collé brut, avec les séparateurs internes.

## Correctif

Le champ de saisie reste en place. Seule la liste des messages est mise à jour, et seulement s’il y a du nouveau. Les messages de groupe et les alertes médicales s’affichent en clair, plus grands.

## Fichiers touchés

- `views/atak/mobile.php`
- `public/assets/js/atak-mobile/atak-mobile.js`
- `public/assets/css/atak-mobile.css`

## Vérification

Ouvrir le tchat, écrire plusieurs phrases : le texte reste, le curseur aussi. Un message de groupe montre l’indicatif et le texte. Recharger la page (cache).

## Statut

corrigé
