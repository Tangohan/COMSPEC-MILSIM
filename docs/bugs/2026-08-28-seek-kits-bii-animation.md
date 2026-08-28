# Bug — SEEK : prélèvements sans kit BII et sans animation

## Contexte

Terminal SEEK, page **Biométrie** (empreintes, iris, ADN). Le pack BII fournit des
kits d’exploitation dans l’inventaire (kit ADN, scanner oculaire, kit / scanner
d’empreintes).

## Symptôme

Les boutons du SEEK lançaient le relevé sans vérifier ces objets. L’écran restait
ouvert : pas l’agenouillement ni la fermeture / réouverture de l’appareil comme
avec les actions du pack.

## Cause

Le relevé du terminal n’exigeait pas le kit correspondant. Une tablette ATAK
pouvait même compter comme kit d’empreintes. Aucune animation d’agenouillement,
l’écran ne se fermait pas.

## Correctif

ADN, iris et empreintes demandent le kit du pack (ou le terminal SEEK / BII-10).
Sans kit, le relevé est refusé. Avec le kit, le SEEK se ferme, l’opérateur
s’agenouille le temps du prélèvement, puis l’écran se rouvre sur la biométrie.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseBiometricSample.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogShow.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogOnLoad.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp`
- `mod/@COMSPEC_SSE/addons/core/functions/fn_getEquipmentAliases.sqf`
- `mod/@COMSPEC_SSE/addons/core/functions/fn_hasEquipment.sqf`
- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_captureIris.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiRegisterEquipment.sqf`

## Vérification

Tests `SseBiiKitCollectAssetTest`. Pack Overwatch 1.4.93 + SSE 0.7.18, relancer
Arma. Avoir le kit dans les poches, ouvrir SEEK → Biométrie, lancer un relevé.

## Statut

corrigé
