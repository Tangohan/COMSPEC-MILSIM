# Diapositive visible sur le téléphone, absente de l'écran

## Contexte

Retour d'un opérateur le 27/10 : la diapositive est dans l'application Briefing, l'écran du terrain garde une image déjà en place (affiche « MARINE OPERATION »).

## Symptôme

Diffuser la diapositive ne change pas l'écran. Le téléphone, lui, l'affiche.

## Cause

L'image est posée sur une sélection qui n'est pas toujours la dalle (souvent le châssis, sélection 0, alors que la dalle s'appelle « screen »). Le dessin était aussi recopié avant que l'image soit prête, donc la dalle ne recevait rien, et un autre chemin reposait ensuite un fichier que le jeu refuse, ce qui laissait l'image d'origine.

## Correctif

La dalle nommée écran est utilisée. L'image n'est recopiée qu'une fois prête, puis figée. Un fichier illisible n'écrase plus la diffusion. Les écrans de briefing déjà posés dans la mission suivent la même diffusion.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_screenCast.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/ui/displays.hpp`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/data/web/slide.html`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyGoogleBriefingSlide.sqf`

## Vérification

À confirmer en jeu après le pack 2.8.2 : diffuser la diapositive, l'écran du terrain montre la même image que le téléphone, y compris après un changement de diapositive.

## Statut

Corrigé dans les sources. Visible après reconstruction du pack.
