# Fenêtre de mise à jour du portail — hors-cadre et peu lisible

## Contexte

Lorsque le portail a été mis à jour pendant qu’une page reste ouverte, un message invite à actualiser. Il s’affiche sur le chrome du portail (tableau de bord, back-office, pages membres).

## Symptôme

L’invitation ressemblait à un bandeau sombre coincé en bas de l’écran, décentré, avec un bouton ambre peu cohérent avec le reste du site. Elle n’évoquait pas une fenêtre de dialogue et passait facilement inaperçue, ou au contraire gênait le pied de page.

## Cause

Le script construisait un toast en `position: fixed; bottom: 1rem` avec des styles inline (fond ardoise, bouton ambre), sans overlay ni centrage.

## Correctif

Fenêtre centrée (overlay plein écran, dialogue au milieu), inspirée du système de design de l’État : titre « Mise à jour », croix Fermer, texte lisible avec l’ancienne et la nouvelle version, bouton principal « Actualiser » et secondaire « Plus tard ». Échap, clic hors de la fenêtre et « Plus tard » ferment la fenêtre. Aperçu : `?preview_update_modal=1`.

## Fichiers touchés

- `public/assets/js/app-version-check.js`
- `public/assets/css/app-update-modal.css`
- `views/layout/main.php`
- `views/atak.php`
- `views/atak/sse/_layout.php`

## Vérification

Aperçu visuel (fenêtre centrée, overlay, boutons « Actualiser » / « Plus tard », croix Fermer). Mesure : centre de la fenêtre = centre de l’écran. « Plus tard » et Échap ferment la fenêtre. Sur une page du portail : ajouter `?preview_update_modal=1` à l’adresse.

## Statut

corrigé
