# Menu Zeus « ATAK Info » sans sous-menu / sans effet

## Contexte

En Zeus (ZEN, clic droit sur un joueur), l’entrée **ATAK — Infos / dégâts / brouillage** apparaît dans le menu contextuel.

## Symptôme

La ligne n’a pas de flèche de sous-menu (contrairement à LAMBS). Un clic ne ouvre ni panneau ni liste d’actions.

## Cause

1. L’entrée ZEN était une **feuille** (aucune action enfant) : ZEN n’affiche une flèche que s’il y a un sous-menu.
2. Le clic relisait seulement `curatorSelected` / `zen_context_menu_selected` **après** fermeture du menu, souvent déjà vide, au lieu d’utiliser l’unité passée par ZEN (`[_position, _objects]`).
3. Le panneau ZEN était ouvert **pendant** que le menu clic droit tenait encore l’affichage : `zen_dialog_fnc_create` échouait, et le script sortait quand même sans repli visible.

## Correctif

- Dossier ZEN avec enfants : ouvrir le panneau, brouiller, casser l’écran, éteindre, crash, détruire, capturer, réparer.
- Sélection du joueur depuis le contexte ZEN (y compris équipage d’un véhicule) + délai avant d’ouvrir le panneau.
- Si la fenêtre ZEN ne s’ouvre pas, repli hint / confirmation.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenAtakPlayerActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusShowPlayerAtak.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.55)

## Vérification

Rebuild Overwatch **connect 1.4.55**, relancer Arma. Zeus → clic droit sur un joueur : flèche à droite de **ATAK — Infos / dégâts / brouillage**, puis **Ouvrir le panneau** ou une action directe.

## Statut

Corrigé (sources) — rebuild mod requis
