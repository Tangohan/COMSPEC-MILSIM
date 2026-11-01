# ATHENA Academy : salle de cours étroite et en-têtes empilés

## Contexte
Pages ATHENA Academy (`/academy/...`), en particulier la salle de cours ouverte sur un cursus (`/academy/classroom?course=...`).

## Symptôme
- Salle de cours : colonne de navigation sombre à gauche, texte du cours limité à environ 800 px dans une carte pleine largeur, grande marge vide à droite.
- Trois bandeaux empilés en haut de chaque page (bandeau « ATHENA ACADEMY / Retour à ATHENA », barre accueil + organisation + langue, note de préversion en pleine largeur).
- Accueil Academy : texte du grand visuel peu lisible sur la photo (dégradé trop léger, photo floutée et mal cadrée), titre géant qui pouvait déborder sur petit écran.

## Cause
- `views/academy/space.php` affichait toujours le menu Academy et le titre de rubrique, même en lecture de cours ; `.lesson-text` était plafonné à 50rem dans une carte pleine largeur.
- `views/academy/header.php` empilait le bandeau générique des modules LMS, la barre Academy et le bloc `.release`.
- La carte « Votre prochain niveau commence ici » a été remplacée par le grand visuel d’accueil (`academy-full-hero`) ; ses réglages de cadrage et de dégradé étaient trop faibles.

## Correctif
- Mode lecture en salle de cours : plus de menu latéral ni de titre de rubrique ; bandeau compact (retour au tableau de bord, nom du cours, chapitre, progression, étapes 1 à 7), sommaire des chapitres collant à côté du texte, contenu centré sur 78rem et texte sur toute la largeur de la carte.
- Un seul en-tête Academy : marque, mention « Préversion » cliquable (ouvre l’avertissement complet), organisation, langue, retour à ATHENA.
- Grand visuel : dégradé renforcé derrière le texte (vertical sur mobile), photo moins floutée et recadrée sur le groupe, titre dimensionné pour ne jamais déborder.
- Styles obsolètes supprimés (`.release`, `.academy-module-toolbar`, `.academy-hero-media`).

## Fichiers touchés
- `views/academy/header.php`, `views/academy/space.php`, `views/academy/course_player.php`
- `public/assets/css/academy.css`
- `lang/fr/academy.php`, `lang/en/academy.php`

## Vérification
- Rendu des pages salle de cours, tableau de bord et accueil en 1600 px et 375 px (capture navigateur sans interface) : pas de débordement horizontal.
- `phpunit` : mêmes 15 échecs qu’avant la modification (tous hors Academy), tests Academy verts ; `phpstan` sans erreur.

## Statut
Corrigé.
