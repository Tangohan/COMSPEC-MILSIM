# Paramètres communauté — page trop longue, bouton perdu

## Contexte

L’écran `https://athena.ttrd.fr/public/back-office/community` (alias organisation / paramètres) mélangeait identité, vitrine, navigation, cycle, type de communauté et un simple lien vers l’inscription. Les photos d’accueil étaient hors du formulaire principal. Le bouton Enregistrer était en bas de page, sans suivi au défilement. Après enregistrement, l’écran revenait toujours en haut, sans mémoriser la rubrique.

## Symptôme

- Page trop longue, mal découpée, avec une notice « Nouveaux réglages » qui ajoutait du bruit.
- Bouton Enregistrer invisible dès que l’on descend.
- Après enregistrement, perte de l’endroit où l’on travaillait.
- Inscription, photos d’accueil et type de communauté vécus comme des pages à part.

## Cause

Une seule vue verticale sans rubriques, deux formulaires distincts, des actions d’accueil hors formulaire, et des redirections sans `onglet`. L’inscription avait sa propre URL.

## Correctif

Hub à six rubriques (Identité, Vitrine, Inscription, Accueil, Portail, Profil) sur le même écran. Barre Enregistrer collante. Mémoire de rubrique via l’adresse (`?onglet=`), le stockage local et les anciens liens `#…`. L’ancienne page d’inscription redirige vers la rubrique Inscription. Le bouton Enregistrer soumet le bon formulaire.

## Fichiers touchés

- `views/admin/organization/settings.php`
- `views/admin/organization/inscription_settings.php`
- `app/Controllers/Admin/Organization/OrganizationSettingsController.php`
- `public/assets/js/community-settings-hub.js`
- `public/assets/css/back-office-shell.css`
- Navigation latérale, recherche du back-office, raccourcis d’en-tête

## Vérification

- Ouvrir Paramètres de la communauté : six rubriques en haut, une seule carte visible à la fois.
- Enregistrer depuis Inscription : l’écran reste sur Inscription, le bouton Enregistrer reste visible en bas.
- Ajouter une photo d’accueil : la rubrique Accueil reste ouverte.
- L’ancien lien `/back-office/community/inscription` ouvre la rubrique Inscription.

## Statut

corrigé
