# Carte ATAK trop plate — relief, animation et réglages live

## Contexte

Sur `/public/atak`, la carte (tuiles Arma + marqueurs) paraissait plate : peu d’ombres, icônes petites, réglages de taille enterrés dans Compte → Affichage.

## Symptôme

Fond et positions sans profondeur ; pas de contrôle rapide taille / style depuis la carte.

## Cause

Marqueurs en `divIcon` avec ombre minimale ; pas d’option de relief/animation ; prefs déjà live mais uniquement dans le panneau Compte.

## Correctif

- Prefs `markerDepth` / `markerMotion` (défaut activés) + classes CSS sur `#atak-map`
- Filtre léger sur les tuiles + ombres portées / halo animé sur les contacts
- Bouton **Affichage** sur la barre d’outils carte (taille, style, relief, animation) branché sur `patchDisplayPrefs`

## Fichiers touchés

- `views/atak.php`
- `public/assets/js/atak-map.js`
- `public/assets/js/atak-map-tools.js`
- `public/assets/css/atak.css`
- `public/assets/css/atak-c2-shell.css`

## Vérification

Ouvrir ATAK → **Affichage** → bouger le curseur d’icônes : mise à jour immédiate. Basculer relief / animation. NVG reste cohérent avec le filtre tuiles.

## Statut

corrigé
