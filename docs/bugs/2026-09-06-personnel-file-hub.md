# Fiche personnel publique — affichage illisible

## Contexte

L’écran `https://athena.ttrd.fr/public/personnel/{slug}` (exemple : jake-gylenhall) mélangeait un bandeau d’identité, un récapitulatif, une colonne de photos, neuf onglets, puis d’autres onglets opérateur. Les mêmes informations revenaient plusieurs fois.

## Symptôme

- Lecture peu intuitive : trop d’onglets, libellés d’atelier, photos recopiées.
- Le bandeau sous le nom répétait grade, unité et habilitation déjà présents en haut.
- La colonne de gauche recopiait portrait, matricule et identifiant.
- Après un changement d’écran, l’onglet ouvert n’était pas conservé.

## Cause

Une grille à deux colonnes avec une sidebar permanente, neuf panneaux Alpine, et un second bandeau de synthèse. Les anciens liens `?tab=` n’étaient pas regroupés.

## Correctif

Cinq rubriques (Portrait, Unité, Parcours, Suivi, Dossier) en pleine largeur. Le bandeau d’identité porte le nom, le grade, l’unité et les photos. Les anciens onglets (ancienneté, dotation, bilans, vue regroupée) sont fusionnés. La rubrique ouverte est conservée dans l’adresse et après un rechargement. Les anciens liens `?tab=` restent compris.

## Fichiers touchés

- `views/personnel/file.php`
- `views/partials/personnel/file_bilans_tab.php`
- `views/partials/personnel/file_tableau_admin_tab.php`
- `public/assets/js/personnel-file-hub.js`
- `public/assets/css/personnel-file.css`
- `views/layout/main.php`

## Vérification

- Ouvrir une fiche publique : cinq rubriques sous le nom, pas de colonne photos.
- Ouvrir Unité, recharger : Unité reste ouverte.
- Un ancien lien `?tab=bilans` ouvre Suivi, avec les bilans si l’on y est habilité.
- La vue RH (`?view=rh`) n’est pas modifiée.

## Statut

corrigé
