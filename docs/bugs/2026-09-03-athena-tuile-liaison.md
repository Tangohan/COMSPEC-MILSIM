# Bandeau identité sous l’heure et tuile Athena

## Contexte

Sur la carte du téléphone, l’indicatif devait se lire juste sous l’heure. La tuile Athena servait encore de hub (journal, alertes, photos, poste).

## Symptôme

Le bandeau d’identité n’apparaissait pas sous l’horloge. La tuile Athena restait encombrée.

## Cause

Le bandeau s’alignait sur la boussole (en haut à gauche de la carte), pas sous l’horloge. La tuile ouvrait encore journal, alertes, photos et poste.

## Correctif

Bandeau noir calé sous le bandeau d’heure (bas du bandeau OSD, centré sur l’horloge). Tuile Athena : bouton Connexion / Liaison OK, petit journal Steam / compte, fiche si tout est prêt.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`

## Vérification

Carte : bandeau sous 13:19. Athena : Connexion, puis Liaison OK et la fiche.

## Statut

corrigé (Athena 1.0.80)
