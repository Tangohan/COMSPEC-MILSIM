# Fiche identité : biométrie inventée (48 %, SEEK / ATAK)

## Contexte

Sur une fiche personne SSE, le panneau Biométrie affichait une qualité de capture et un terminal même quand le jeu n’avait rien transmis.

## Symptôme

Photo absente, empreintes et iris non relevés, mais « Qualité capture : 48 % » et « Terminal : SEEK / ATAK ». L’opérateur YA1 / Bravo, lui, venait bien du jeu.

## Cause

La fiche inventait une note technique (48 % ou 72 %) et un terminal générique. Empreintes / iris pouvaient aussi être marqués « relevés (sim.) » sans échantillon transmis.

## Correctif

N’afficher que ce qui a été reçu : qualité seulement s’il y a un relevé, terminal seulement s’il est signé ou vraiment remonté du terrain, tiret sinon.

## Fichiers touchés

- `app/Controllers/Web/SsePortalController.php`
- `app/Services/Sse/SseTerrainService.php`
- `views/atak/sse/person_show.php`
- `public/assets/css/sse_workspace.css`

## Vérification

Test `SsePersonBiometryHonestyTest`. Recharger une fiche sans relevé : qualité et terminal en tiret, pas 48 % ni SEEK / ATAK.

## Statut

corrigé
