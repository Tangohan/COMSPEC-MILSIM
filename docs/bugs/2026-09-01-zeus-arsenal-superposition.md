# 2026-09-01 — Zeus et arsenal : boutons COMSPEC en superposition

## Contexte

En Zeus et à l’arsenal ACE, les raccourcis COMSPEC (SSE / ATAK / OVERWATCH, tenues Athena) recouvraient l’interface native.

## Symptôme

1. Fenêtre Zeus « Update Editable Objects » : les boutons SSE / ATAK / OVERWATCH masquent les filtres (Unités, Véhicules) et le bouton OK.
2. Fiche d’édition d’une personne : les mêmes boutons passent devant le titre, les champs ou OK ; OVERWATCH est parfois coupé.
3. Arsenal ACE « Mes équipements » : le panneau Athena recouvre la liste des tenues locales et la barre de défilement.

## Cause

- Un balayage de toutes les fenêtres ouvertes injectait les boutons dès qu’un bouton OK était en bas d’écran, y compris les modules Zeus (classe générique d’attributs).
- Les boutons Zeus étaient calés au-dessus de OK, puis collés sur le titre.
- Le panneau arsenal était une colonne haute collée au bord droit, pile sur « Mes équipements ».

## Correctif

- Injection limitée aux fiches personne / véhicule / groupe, seulement si une cible éditable est reconnue. La classe générique n’est plus branchée.
- Barre dédiée **au-dessus** du titre ; s’il n’y a pas la place, calage à droite du titre ; sinon rien n’est injecté.
- Bandeau Athena compact **au centre haut** de l’arsenal (pas sur « Mes équipements »), liste des tenues **de toute la communauté**.
- Injection Zeus limitée aux fiches personne / véhicule / groupe. Plus de balayage d’autres fenêtres.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesInject.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZeusAttributeButtons.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/CfgEventHandlers.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayShow.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayRefresh.sqf`

## Vérification

- Tests d’assets : pas de balayage `allDisplays`, pas de classe générique, ancre titre `30001`, bandeau arsenal `safeZoneY + 0.012`.
- Contrôle en session : pack Overwatch 1.4.96, relancer Arma. Double-clic une personne : barre au-dessus du titre. Fenêtre objets éditables : plus de boutons Comspec.

## Statut

corrigé (pack Overwatch 1.4.96)
