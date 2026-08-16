# ATAK — mauvaise image « écran endommagé »

## Contexte
Écran cassé roleplay : texte « ÉCRAN ENDOMMAGÉ » visible, mais pas les fissures (carte nue / voile sombre).

## Cause
Overlay pointait vers `overlays/comspec_overlay_screen_cracked_ca.png` (cadre sombre) et un fond trop opaque masquait les cracks. L’asset prévu pour un overlay transparent est `img/atak-fx/broken-screen.png` (déjà utilisé côté web ATAK).

## Correctif
- `fn_updateDeviceOverlay` + `fn_updateAtakEnhancedRoleplay` → `broken-screen.png`
- Priorité « écran cassé » avant hors-service
- Alpha de fond abaissé (~0.16–0.18)

## Statut
corrigé
