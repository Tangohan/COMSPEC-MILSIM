# COMSPEC ATAK Native — audit et migration

L'audit a couvert les 2 987 fichiers de `mod/`, les sources dépaquetées sous `mod/UptoDate/Sources`, les PBO livrés, les anciens bridges et les recherches `CfgPatches`, `CfgFunctions`, XEH, `callExtension`, callbacks, contrôles dynamiques, displays, carte et routes API. Les binaires/PBO ont été inventoriés mais la source dépaquetée est utilisée pour établir les contrats.

## Générations trouvées

| Génération | Source active/packaging | Dépendances UI | Décision |
|---|---|---|---|
| Connecteur/classic tablet | `connect`, compilé obligatoirement avant les autres PBO | dialogs natifs et ancien navigateur selon fonction | conservé comme réseau et compatibilité ; pas utilisé pour rendre ATAK Native |
| Bridge Athena ATAK Enhanced | `atak_athena`, PBO optionnel explicitement packagé | cTab, BCE | conservé sur disque, bootstrap supprimé lorsque `COMSPEC_ATAK_Native` existe |
| SSE indépendant | `mod/@COMSPEC_SSE/addons/*` | CBA, ACE uniquement dans addon compat | données réutilisables, aucune dépendance UI dure ajoutée |
| ATAK Native | `mod/COMSPEC_ATAK_Native`, mod/PBO/DLL autonomes | Arma UI + CBA | client principal |

## Matrice fonctionnelle

| Ancienne fonction/source | Données / API / dépendances | Nouvelle fonction | État |
|---|---|---|---|
| `connect/fn_connect`, `fn_restoreAtakSession`, GameAuth extension | auth, Steam, OTP, tenant, profil via DLL | connecteur inchangé + état réseau/callback natif | conservé/intégré |
| `connect/fn_extensionCallback` | Connected, backoff, BftIdentity, slides, uploads | `network/extensionCallback` (UI/store) en parallèle du dispatcher global | intégré |
| `connect/fn_updatePosition` | `UpdatePosition`, callsign, radio, médical, plateforme | scheduler réutilise les boucles connect existantes ; rendu lit le store | conservé |
| `connect/fn_pollAthenaMarkers` | `GetMarkers`, format lignes TSV `M,id,...`, marqueurs locaux | `remoteSync` puis `localDataRefresh`/`mapOnDraw` | intégré |
| `connect/fn_syncMapMarker`, `fn_queueMapMarker` | `SendMarker` | marqueurs Arma locaux immédiatement, pipeline connect existant inchangé | partiel, fiable offline |
| `connect/fn_pollOrders` | `GetOrders`, `COMSPEC_Orders` HashMaps | `importLegacyData`, page TASK, `taskAction`/`UpdateOrderStatus` | intégré |
| `connect/fn_pollChatMessages` | `GetChatMessages`, inbox | store messages, page CHAT, `SendChat` | intégré |
| `connect/fn_sendIntel` | chat/ping/image et `COMSPEC_IntelStore` | page INTEL/SSE + import store | intégré, lecture native |
| `atak_athena/fn_athena_updateMapHud` et `functions/ui/mapDrawOverlay` | données cTab/BCE et overlays | `map/mapOnDraw`, `symbology`, cache moteur | remplacé |
| `atak_athena/functions/ui/mapUIInit/Destroy` | controls dynamiques et EH cTab | `displayLoad/Unload`, scheduler unique | remplacé |
| `athena_task*` | `COMSPEC_Orders`, cTab page | page TASK native | remplacé |
| `athena_sendHqMessage`, `tabletChat*` | `SendChat` | page CHAT native | remplacé |
| `getBriefingSlides`, callbacks Google | PNG local téléchargé par DLL | store briefing, page BRIEFING, boutons précédent/suivant | intégré |
| photo watcher/capture/upload | callbacks et chemins locaux | store/page PHOTOS avec fallback texte | structure prête, producteur conservé |
| `athena_bridgeWeather`, drone contacts, BDA/Iceman | variables mission/CBA | domaines events/intel/zones et pages dédiées | adaptation progressive |
| `getRadioState`, `getMedicalState`, `initACE*` | TFAR/ACRE/ACE détectés runtime | inspecteur peut être enrichi sans requiredAddon | compatibilité préservée |
| `webBrowser*`, `JSDialog`, pages Athena web | HTML/CEF/JS | aucun remplacement de rendu ; RscDisplay/RscMap natifs | interdit/supprimé du nouveau chemin |

## Contrats extension observés

Les commandes réutilisées comprennent notamment Connect, Ping, UpdatePosition, GetMarkers/SendMarker, GetOrders/UpdateOrderStatus, GetChatMessages/SendChat, images/uploads, slides, profils et BFT. Les endpoints `/api/atak/*` et `/api/sse/*` restent encapsulés par la DLL dédiée `COMSPECATAKNativeExtension`; aucun HTTP n'est réimplémenté en SQF. Le site reçoit explicitement `client_product=comspec_atak_native`, `client_name=COMSPEC ATAK Native` et `ui_generation=native-rsc-v1`.

Les fonctions historiques ne sont pas supprimées. Leur chargement simultané est empêché par la présence de `CfgPatches/comspec_atak_native_main`, avec canary RPT explicite. Cela permet un rollback en retirant seulement le PBO natif.

