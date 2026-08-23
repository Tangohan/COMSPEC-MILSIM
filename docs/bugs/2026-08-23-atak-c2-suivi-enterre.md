# ATAK web — C2 sans vue directe sur les ordres

## Contexte

Panneau gauche ATAK, domaine Commandement. L’opérateur ouvre C2 pour suivre les ordres, mais le tableau de suivi n’est pas visible d’emblée.

## Symptôme

Dans le même défilement : liste des modules, volume des alertes, chrome « ouvrir dans une autre fenêtre », formulaire d’émission, puis seulement les cartes d’ordres (ex. « EN RETARD ») tout en bas. La carte à droite reste visible, pas le suivi C2.

## Cause

1. Le formulaire d’émission était au-dessus de la liste des ordres.
2. Les réglages d’alertes occupaient le flux du panneau, toutes sections confondues.
3. La liste de modules prenait jusqu’à 440 px, ce qui ne laissait presque plus de place au contenu.
4. Une apostrophe PHP (`'<button`) cassait le marquage de l’onglet Charges.

## Correctif

- Onglets de travail **Suivi** (défaut) / **Émettre** dans le module Ordres.
- Réglages d’alertes déplacés dans un aside « Réglages du poste » (bouton ⚙, fermé par défaut).
- Liste de modules C2 compacte ; le suivi occupe le reste du panneau.
- Apostrophe de l’onglet Charges corrigée.

## Fichiers touchés

- `views/atak.php`
- `public/assets/css/atak-c2-shell.css`
- `public/assets/css/atak.css`
- `public/assets/js/atak-c2-workspace.js`
- `public/assets/js/atak-section-nav.js`
- `public/assets/js/atak-orders.js`

## Vérification

Ouvrir l’ATAK web, domaine C2 : la liste d’ordres s’affiche sans scroller le formulaire. « Émettre » et « Nouvel ordre » n’apparaissent que pour un profil commandement. ⚙ ouvre les alertes sonores.

## Statut

corrigé
