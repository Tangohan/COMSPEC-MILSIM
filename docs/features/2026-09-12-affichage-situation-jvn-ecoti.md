# Affichage situation (JVN) — vague ECOTI Overwatch

Date : 2026-09-12  
Statut : livré (1.5.54 · Athena 1.0.99) — découpage 3D reporté

## Contexte

L’affichage situation sous jumelles de vision nocturne (style ECOTI) projetait déjà alliés, marqueurs et véhicules. Les badges restaient peu lisibles, la fonction était active par défaut, et plusieurs capacités (éclairage, contour, itinéraire, désignation) manquaient côté Overwatch (sans dépendre de F-PANO).

## Comportement livré

- Désactivé par défaut ; activation dans **ATAK → Paramètres → Affichage situation (JVN)** (mémorisé sur le profil Arma).
- Badges plus lisibles (plaque sombre + texte contouré, nom et distance).
- Éclairage local d’une zone sous le regard (menu ACE).
- Contour (cadre) de l’objet regardé.
- Trace d’itinéraire GPS du poste + points locaux (ACE).
- Désignation d’un bâtiment : marqueur carte + silhouette + badge.
- Si F-PANO ECOTI est chargé, Overwatch laisse la place (pas de double affichage).

## Reporté

- **Découpage 3D réel des bâtiments** (ouverture des murs / géométrie) : non supporté proprement dans le moteur sans assets dédiés. Réglage « à venir » présent ; la silhouette (cadre + étages) reste la solution actuelle.

## Fichiers principaux

- `connect/functions/fn_ecoti*.sqf`
- `atak_athena/ui/settings_page.hpp`
- `atak_athena/functions/fn_athena_ecotiHudSave.sqf`
- `connect/XEH_preInit.sqf` / `XEH_postInit.sqf`

## Vérification

1. Quitter Arma, recharger le pack.
2. ATAK → Paramètres → **Affichage situation (JVN)** → **Activé sous JVN** → Enregistrer.
3. En mission, activer les JVN : badges lisibles, contour sur l’objet regardé.
4. ACE → désigner un bâtiment, éclairer une zone, ajouter des points d’itinéraire.
5. Avec F-PANO ECOTI chargé : pas de double HUD Overwatch.
