# Fenêtre d’accès manquant sur la carte du poste

## Contexte

Un opérateur peut être **en liaison** Overwatch / Athena (position visible, statut en liaison) tout en se voyant refuser des vues ou des actions de la carte, parce que son **grade**, son **rôle** ou sa **fonction** n’ouvrent pas ces accès.

## Symptôme

Sur la carte du poste (`/atak`), la liaison est bonne, mais des parties restent inaccessibles (fiches opérateurs, renseignement, documents de mission). Rien n’expliquait pourquoi, ni comment demander l’ouverture.

## Cause

Les droits viennent du profil RH (rôle / grade / fonction / habilitation). La carte n’avait pas de parcours dédié pour le signaler quand la liaison, elle, fonctionnait.

## Correctif

- Détection : opérateur connecté **et** en liaison en jeu, **et** au moins une vue carte fermée que d’autres profils de la communauté ont.
- Fenêtre en langage métier, bouton **Demander les autorisations d’accès**.
- La demande réutilise le circuit d’élévation RH (courrier à l’encadrement, validation dans le bureau effectifs).

## Fichiers touchés

- `app/Services/Tactical/AtakMapAccessGapService.php`
- `app/Controllers/Web/AtakController.php`
- `routes/web.php`
- `views/atak.php`
- `public/assets/js/atak-access-gap.js`
- `public/assets/css/atak.css`

## Vérification

- Tests unitaires du catalogue d’écarts et des libellés.
- Test d’assets (fenêtre, bouton, route, circuit élévation).
- Pas de fenêtre si l’opérateur n’est pas en liaison, ni pour l’encadrement qui accorde déjà les accès, ni pour une session téléphone.

## Statut

corrigé
