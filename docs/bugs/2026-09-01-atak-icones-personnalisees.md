# Bug — icônes personnalisées absentes de Réglages du poste

## Contexte

Carte ATAK web (`/atak`), panneau **Réglages du poste**. La bibliothèque d’icônes de la communauté existe déjà dans Cartographie & ATAK (`/admin/atak-config`, section Apparence des symboles). Le journal produit (mise à jour 249) indiquait que le gestionnaire choisit les icônes de la carte depuis le poste.

## Symptôme

Le panneau montrait l’apparence locale (positions, taille, libellés, relief, calques) mais aucun moyen de voir, choisir ou ajouter les icônes personnalisées de la communauté. Un opérateur ou un gestionnaire ouvert sur la carte ne trouvait pas le paramétrage annoncé.

## Cause

La bibliothèque et l’affectation par type (opérateurs, IA, véhicules, aéronefs, téléphones) n’étaient branchées que sur l’écran d’administration. Le panneau Réglages du poste n’y renvoyait pas.

## Correctif

Ajouter dans Réglages du poste une section **Icônes de la communauté** : aperçu des symboles en vigueur, extraits de la bibliothèque, et pour le gestionnaire un lien vers l’écran existant (envoi d’une image ou choix parmi celles déjà présentes). Pas de second système d’administration. Les libellés villes / routes et la vue en relief du même panneau passent en langage de poste.

## Fichiers touchés

- `views/atak.php`
- `app/Controllers/Web/AtakController.php`
- `public/assets/css/atak-c2-shell.css`
- `public/assets/js/atak-geo-live.js`
- `public/assets/js/atak-terrain3d-premium.js`
- `tests/Unit/AtakMarkerIconsAssetTest.php`
- `app/Support/DevDispatchCatalog.php`

## Vérification

Tests d’assets : la section `atak-settings-icons` et le lien `admin/atak-config#marker-icons` sont présents ; les textes `geo_places`, `geo_roads` et Three.js ne sont plus dans les libellés du panneau.

## Statut

corrigé
