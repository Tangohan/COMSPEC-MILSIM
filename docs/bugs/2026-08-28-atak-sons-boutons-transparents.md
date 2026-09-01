# Écran Sons ATAK : boutons sans fond

## Contexte

Sur le téléphone ATAK, l’application Sons (volumes, style d’alerte, Tester) affichait les commandes comme du texte flottant, sans tuile.

## Symptôme

Les « − / + », Mode discret, Tester et État ATAK n’avaient plus de fond. Seul le libellé restait lisible sur le gris du panneau.

## Cause

Les boutons COMSPEC hérités du menu ATAK utilisaient une texture d’animation totalement transparente (pour cacher le dégradé du jeu). Or ce type de bouton dessine son fond avec cette texture : alpha 0 = bouton invisible.

## Correctif

Texture blanche opaque, teintée par la couleur de tuile. Les touches de volume sont un peu plus claires pour se détacher du panneau.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/atak_theme.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/sound_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`

## Vérification

Tests `AtakSoundButtonsAssetTest`. Pack Athena 1.0.56, relancer Arma, ouvrir Sons : les boutons ont un fond gris, − / + et Tester se voient d’un coup d’œil.

## Statut

corrigé (pack Overwatch 1.4.95)
