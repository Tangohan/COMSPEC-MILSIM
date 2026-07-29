# Panneau ATAK détachable — contenu noir

## Contexte

Ouverture d’un panneau latéral dans une fenêtre séparée (`?popout=left&tab=photos`).

## Symptôme

La barre d’onglets s’affiche mais la zone de contenu reste noire / vide.

## Cause

1. La sélection d’onglet en popout appelait `btn.click()` avant l’installation des listeners dans `startAtakApplication()` → aucun panneau `.atak-tabs-content.active`.
2. `applyGating()` utilisait aussi un clic simulé trop tôt.
3. Risque de `visibility:hidden` si le body restait verrouillé par le hub de session.

## Correctif

- `ATAKPanelChrome.activateTab()` : bascule DOM directe + événement `atak:tab-activated`.
- Onglet popout appliqué à la fin de `startAtakApplication()` (après boot).
- CSS popout : force l’affichage de `.atak-left-body` ; annule `is-popped-out` en fenêtre détachée.
- Déverrouillage immédiat du body en mode popout.

## Fichiers touchés

- `public/assets/js/atak-panel-chrome.js`
- `public/assets/js/atak-session-profile.js`
- `views/atak.php`
- `public/assets/css/atak.css`

## Vérification

1. Ouvrir ATAK → « Ouvrir dans une autre fenêtre » sur le panneau gauche (onglet Photos).
2. La fenêtre popout affiche le contenu Photos (ou l’onglet demandé), pas un fond noir.
3. Changer d’onglet dans la fenêtre détachée fonctionne.

## Statut

Corrigé
