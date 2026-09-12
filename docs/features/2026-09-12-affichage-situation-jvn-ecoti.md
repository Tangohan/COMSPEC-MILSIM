# Affichage situation (JVN) — vague ECOTI Overwatch

Date : 2026-09-12  
Statut : livré (1.5.56 · Athena 1.0.101) — découpage d’étage opérationnel (pas d’ouverture de murs)

## Contexte

L’affichage situation sous jumelles de vision nocturne (style ECOTI) projetait déjà alliés, marqueurs et véhicules. Les badges restaient peu lisibles, la fonction était active par défaut, et plusieurs capacités (éclairage, contour, itinéraire, désignation) manquaient côté Overwatch (sans dépendre de F-PANO).

## Comportement livré

- Désactivé par défaut ; activation dans **ATAK → Paramètres → Affichage situation (JVN)** (mémorisé sur le profil Arma).
- Badges plus lisibles (plaque sombre + texte contouré, nom et distance).
- Éclairage local d’une zone sous le regard (menu ACE).
- Contour (cadre) de l’objet regardé.
- Trace d’itinéraire GPS du poste + points locaux (ACE).
- Désignation d’un bâtiment : marqueur carte + silhouette + badge.
- **Découpage d’étage** (Paramètres → Découpage d’étage, OFF par défaut) : sur un bâtiment désigné, la silhouette est coupée au plafond de l’étage choisi, la dalle est mise en évidence, les points intérieurs de cet étage apparaissent, et ACE propose **Changer d’étage**.
- Si F-PANO ECOTI est chargé, Overwatch laisse la place (pas de double affichage).

## Limites assumées

- **Ouverture réelle des murs / géométrie cutaway moteur** : impossible proprement sans assets dédiés. Le mode livré est une silhouette multi-étages avec sélection d’étage, pas un vrai découpage 3D des parois.
- Le nombre d’étages est estimé depuis la hauteur du bâtiment (~3 m par niveau), pas depuis un plan architectural.

## Fichiers principaux

- `connect/functions/fn_ecoti*.sqf`
- `atak_athena/ui/settings_page.hpp`
- `atak_athena/functions/fn_athena_ecotiHudSave.sqf`
- `atak_athena/functions/fn_athena_ecotiCutawaySave.sqf`
- `connect/XEH_preInit.sqf` / `XEH_postInit.sqf`

## Vérification

1. Quitter Arma, recharger le pack.
2. ATAK → Paramètres → **Affichage situation (JVN)** → **Activé sous JVN** → Enregistrer.
3. Optionnel : **Découpage d’étage** → **Activé** → Enregistrer.
4. En mission, activer les JVN : badges lisibles, contour sur l’objet regardé.
5. ACE → désigner un bâtiment ; si découpage actif, ACE → Changer d’étage.
6. Avec F-PANO ECOTI chargé : pas de double HUD Overwatch.
