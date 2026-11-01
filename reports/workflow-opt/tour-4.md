# Tour 4 - corrections appliquées

## Constats (confirmés) et état

| # | Gravité | Fichier | Constat | État |
|---|---------|---------|---------|------|
| 1 | moyen | connect/functions/fn_createRoleplayZoneFromZeus.sqf:41 | Zone Zeus accrochée à une ancre mobile : `publicVariable` de toute la liste `COMSPEC_RoleplayZones` + `setMarkerPos` global toutes les 2 s dès 1 m de déplacement | Appliqué : seuil `<= (((_zone getOrDefault ["radius", 100]) * 0.1) max 10)` |
| 2 | faible | connect/functions/fn_setAllyTrack.sqf:81 | Renvois des mêmes variables publiques à 0,4 / 2 / 6 s (jusqu'à 9 messages de plus par unité) | Appliqué : bloc `forEach [0.4, 2, 6]` supprimé, EH "Local" conservé |
| 3 | faible | @COMSPEC_SSE/addons/campaign/functions/fn_campaignOp.sqf:82 | Wipe complet : `setVariable ["COMSPEC_SSE_Org", nil, true]` sur chaque unité | Appliqué : envoi seulement si la variable existe |
| 4 | moyen | @COMSPEC_SSE/addons/core/functions/fn_initSettings.sqf:78 | Réglage CBA `comspec_sse_timePhoto` sans lecteur | Appliqué : bloc supprimé |
| 5 | faible | @COMSPEC_SSE/addons/core/functions/fn_setSeed.sqf | Fonction jamais appelée | Appliqué : fichier et `class setSeed {};` (core/config.cpp) supprimés ; ticket docs/bugs laissé tel quel |

## Ignorés

Aucun.

## Vérifications

- grep `tests/` : aucun test ne lit les textes modifiés (`setSeed`, `timePhoto`, `COMSPEC_SSE_Org`, bloc de renvois setAllyTrack).
- Fichiers restés en UTF-8 CRLF.
- `hemtt check` (ATAK natif) : seules les 2 erreurs connues (ctrlWebBrowserAction dans fn_screenCast / fn_slideWebShow). Aucune erreur nouvelle.
- connect / SSE : syntaxe des lignes modifiées relue (crochets, accolades, points-virgules).

## Limite connue

Constat 1 : au-dessus d'environ 36 km/h, une zone de 200 m (seuil 20 m) est encore rediffusée à chaque passage de 2 s ; le gain porte sur les porteurs à pied et les véhicules lents.
