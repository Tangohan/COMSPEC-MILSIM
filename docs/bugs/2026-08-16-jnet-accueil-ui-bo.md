# Extranet JNET — UI Accueil illisible / incohérente dans le BO

## Contexte

Tableau d’unité embarqué dans le chrome back-office ATHENA (fond clair).

## Symptôme

- Titre « Accueil » + boutons rapides redondants avec les onglets, grand vide horizontal.
- En-têtes de panneaux encore sombres, textes secondaires / bandeau bêta peu lisibles.
- Commandement : même libellé « NewPI » pour plusieurs cadres (pseudo compte au lieu de l’indicatif).

## Cause

1. `jnet_portal.css` (thème sombre) hardcode des fonds / couleurs non surchargés par `jnet_bo_embed.css`.
2. Affichage personnel : `display_name` prioritaire sur l’indicatif quand le personnage n’est pas renseigné.
3. Titre / actions rapides dupliquent la navigation à onglets.

## Correctif

- Surcharges claires complètes (panneaux, badge, bêta, feed, empty, contrastes).
- Nom affiché : personnage → indicatif → display name.
- Titre « Tableau d’unité », sous-titre court, plus d’actions rapides dupliquées.

## Fichiers touchés

- `public/assets/css/jnet_bo_embed.css`
- `app/Controllers/Web/JnetPortalController.php`
- `app/Services/Jnet/JnetDashboardService.php`
- `views/jnet/home.php`

## Vérification

Recharger `/jnet` : titres lisibles, commandement distinct (N-10 / N-01…), bandeau bêta lisible, panneaux clairs.

## Statut

corrigé
