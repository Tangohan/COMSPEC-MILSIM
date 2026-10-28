# SQF — profil de coût (lecture seule)

Agent 2, indépendant. Branche annoncée : `release/2.7.0`. Aucun code modifié, aucun commit, aucune production.

**Arma n’a pas été lancé.** Ce poste ne peut pas ouvrir le jeu. Il n’y a donc aucun FPS, aucune latence de script, aucun `diag_`, aucun benchmark. Les fréquences ci-dessous sont celles écrites dans le SQF (délai CBA, `Draw`, événement). L’impact « probable » est une lecture de ce que le code alloue et envoie, pas une mesure. Un gain chiffré serait une estimation : il est marqué comme tel.

## Périmètre

| Inclus | Exclu |
| --- | --- |
| `mod/COMSPEC_ATAK_Native/Sources` (addon natif, sources compilées dans le PBO du téléphone) | `mergewt/`, tout `_tmp`, `mod/Workshop`, tout `.pbo` |
| SQF de liaison encore compilé dans le pack : `mod/UptoDate/Sources/comspec-overwatch-addons/connect` (`connect.pbo`, `workshop-pack.ps1` exige ce PBO à côté de `main.pbo`) | addon `main` Overwatch hors liaison, SSE, Iceman, cTab, sources C# de la DLL |

`COMSPEC_ATAK_PfhSpread` (`XEH_preInit.sqf` lignes 28-33) décale le premier tir des boucles à délai > 0 (au plus 5 s) et **ne renvoie pas l’identifiant**. Un délai 0 passe tout de suite en `CBA_fnc_addPerFrameHandler` sans décalage. `XEH_postInitClient.sqf` enregistre **39** de ces boucles (comptage par recherche dans le fichier, pas en jeu), auxquelles s’ajoutent, une fois la liaison Athena prête, celles de `fn_startSyncLoops.sqf`.

## Profil de coût (fréquence lue dans le code)

Légende réseau : **Arma** = `setVariable` public / événement vers d’autres machines ; **Athena** = `callExtension` vers la DLL de liaison (le fil de jeu attend la fin de l’appel, sauf passage par `fn_extAwait`).

| Fonction | Fréquence lue | Ce qui est alloué à chaque passage utile | Réseau |
| --- | --- | --- | --- |
| `fn_mapOnDraw` | Chaque image tant que la carte du téléphone est dessinée (mini, porté, plein écran) | Listes de segments, défauts `createHashMap` évalués avant `getOrDefault` | Aucun |
| `fn_mapGameMarkers` | Même image | Relecture complète d’`allMapMarkers` au plus toutes les 30 s ; **chaque image** : `getMarkerPos` + `ctrlMapWorldToScreen` pour chaque icône | Aucun |
| `fn_meshDraw` | Même image ; calque Relais **affiché par défaut** | Dessin (ellipses, jusqu’à ~80 segments pointillés par répéteur). La liste relais est en cache 2 s | Aucun sur l’image ; le recalcul 2 s appelle le bilan radio |
| `fn_schedulerTick` | 5 Hz tant que le téléphone est ouvert (`fn_schedulerStart.sqf` ligne 4, délai 0,2 s) | Délègue | Non |
| `fn_localDataRefresh` | 4 Hz sur MAP/BFT interactif, 1 Hz téléphone porté, sinon 2 s (`fn_schedulerTick.sqf` lignes 7-10) | `allUnits` filtré toutes les 2 s ; un HashMap **par allié vivant** à chaque passage | Non (lit des variables déjà publiques) |
| `fn_linkQuality` + `fn_gpsFix` + `fn_radioLink` | Au plus toutes les 2 s téléphone ouvert, 6 s téléphone rangé | Rayons `lineIntersectsSurfaces`, hauteurs de terrain, `nearestTerrainObjects` en mode réaliste | Non |
| `fn_pli` | Garde 1 Hz ; envoi à `pliRate` (10 s Milsim, 5 s Standard), moitié si déplacement > 50 m | Tableau de 4 cases | **Arma** : `setVariable` public du joueur |
| `fn_aiAtak` `"tick"` | 1 Hz serveur | Tableau de toutes les IA ; si le point est dû, rayon + arbres + `setVariable` public | **Arma**, une diffusion par IA échue (plafond 250) |
| `fn_foreignHud` | Chaque image téléphone affiché ; scan 1 Hz ; `allVariables uiNamespace` toutes les 10 s | Tableau de noms au scan décennal | Non |
| Moniteur `[PERF]` | Chaque image après la première frame de jeu (`XEH_postInitClient.sqf` lignes 1106-1118, délai 0) | Presque rien si l’image < 50 ms ; journal si > 400 ms | `diag_log` local |
| `fn_ecotiDraw` / `fn_ecotiTickPostFX` | Draw3D chaque image ; PFH 0,12 s (~8 Hz), enregistrés pour toute la partie | Si JVN coupé : un test puis sortie. Si JVN actif : `allUnits`, `nearestObjects`, **`allMapMarkers`**, tris | Non |
| `fn_updatePosition` | PFH 1 Hz après handshake (`fn_startSyncLoops.sqf` lignes 109-114) | État médical et radio **à chaque seconde** ; gros texte JSON seulement si l’envoi part | **Athena** asynchrone (`fn_extAwait`) ; `COMSPEC_PliAt` public au plus toutes les 5 s |
| Sondages `fn_startSyncLoops` | 2 s à 300 s selon la file (ordres 8 s, chat 6 s, formes 10 s, marqueurs joueur 8 s, resync complet 90 s, profil 300 s) | Chaînes de réponse DLL | **Athena**, un appel par sondage, la plupart via `extAwait` |
| `fn_syncMapMarker` | Chaque `MarkerUpdated` / créé / supprimé qui passe les gardes | Corps du marqueur | **Athena synchrone** (`callExtension` direct, pas `extAwait`) |
| `fn_initGpsBeacons` | PFH 3 s ; balayage `vehicles` + `allUnits` toutes les 12 s **sur chaque client** | Tableaux de suivis | **Athena** seulement pour le client élu le plus proche |
| `fn_serverFlushQueue` | 0,4 s sur le serveur hub | Recopie du HashMap de file | Indirect (envois marqueurs) |
| `fn_jpcScreen` / `fn_tocScreen` | JPC : PFH délai 0 tant que l’écran de gilet est ouvert, `displayUpdate` plafonné (défaut 6 Hz). TOC : tick 1 Hz, `displayUpdate` au plus toutes les 2 s si l’écran est vu à < 80 m | Rendu d’un display entier vers une texture | Texture d’objet (JPC : `setObjectTexture` local ; ouverture : `setObjectTextureGlobal`) |
| Nacelle drone / FLIR | Délai 0 tant que le flux est ouvert | Pose de caméra chaque image ; liste LOCK relue à 4 Hz, pas à chaque image | Non sur l’image |
| `fn_dataAction` `"tick"` | 4 Hz (`fn_dataInit.sqf` lignes 85-93) | Rien si aucun câble : sortie ligne 142 | Non au repos |
| `fn_musicTick` | 0,5 s | Si une piste DLL joue : `callExtension` `MusicStatus` au plus toutes les 1,5 s | **Athena synchrone** pendant la lecture ; `setVariable` public du haut-parleur quand il change |

Sondages lents (5 s et plus) : coût confirmé faible **par appel**, coût Athena cumulé seulement si beaucoup de joueurs liés font le même GET (le hub coupe déjà relais, ordres IA et roleplay côté clients quand `COMSPEC_ServerHubActive` est vrai). Ce n’est pas un sujet d’image.

## Déjà borné dans le code (pas un défaut ouvert)

Ces choix sont visibles et tiennent : ne pas les « réparer ».

- Propriétés des marqueurs de mission relues seulement si le marqueur est sale, relecture complète plafonnée à 30 s (`fn_mapGameMarkers.sqf` lignes 21-24).
- Heatmap : hauteurs recalculées quand la vue change, cache coupé à 20 000 cases (`fn_mapOnDraw.sqf` lignes 119-125).
- Maillage Wave Relay : pas de bilan radio dans l’événement Draw ; recalcul en `spawn`, cache 3 s / 30 s (`fn_waveRelayAction.sqf` lignes 25-27).
- Positions Athena : `fn_extAwait` + un seul envoi en vol (`COMSPEC_PosTxBusyAt`, 30 s).
- Signature de marqueur identique : pas de renvoi avant 60 s, mémoire coupée à 1 500 (`fn_syncMapMarker.sqf` lignes 93-102).
- File d’ordres, historiques de chat, notifications : plusieurs tableaux ont un `while deleteAt` de plafond.
- JVN coupé : le Draw3D et le PFH sortent avant `allUnits` (`fn_ecotiDraw.sqf` lignes 14-18). Le coût 8 Hz résiduel est un appel à `fn_ecotiIsActive`.

---

## SQF-001 — Carte du téléphone redessinée à chaque image

- **Fichier** : `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/map/fn_mapOnDraw.sqf:10` et `:158` ; `fn_mapGameMarkers.sqf:136-144` ; `fn_meshDraw.sqf:68` et `:149`.
- **Symptôme** : tant que la carte est à l’écran (y compris le téléphone porté, toute la mission), chaque image paie boussole, symboles, et le calque Relais.
- **Cause** : l’événement Draw du contrôle carte n’a pas de cadence. Les icônes ne sont pas écartées avant l’appel moteur : la position monde puis écran est demandée pour **chaque** icône, même hors de la vue. Le calque Relais est vrai par défaut et redemande la liste à chaque image (le calcul lourd est en cache 2 s, le dessin ne l’est pas).
- **Preuve** :

```sqf
private _pos = getMarkerPos _m;
(_map ctrlMapWorldToScreen _pos) params ["_sx", "_sy"];
if (_sx < (_mx - _mg) || ...) then { continue };
_map drawIcon [_icon, _c, _pos, ...];
```

```sqf
if (profileNamespace getVariable ["COMSPEC_ATAK_RelaysLayer", true]) then {
    ...
} forEach (["list"] call comspec_atak_native_fnc_relayNet);
```

- **Gravité** : haute sur une mission riche en marqueurs ou en relais, téléphone porté en continu. Moyenne sur une carte presque vide.
- **Impact probable** : client, images. Pas de réseau Arma sur ce chemin. Le calque maillage (`COMSPEC_ATAK_MeshOnMap`, coupé par défaut) ajoute un rectangle par case de couverture visible.
- **Correction minimale** : pour les icônes, garder la dernière position dans l’entrée de cache et ne rappeler `getMarkerPos` que si le marqueur est dans `COMSPEC_ATAK_MkDirtyGm` ou si c’est un marqueur suivi ; écarter par la boîte avant `getMarkerPos` quand la position n’a pas bougé. Dessiner le pointillé des répéteurs au plus à 10 Hz, pas à chaque image. Ne pas changer le rendu des unités.
- **Test de non-régression** : marqueur déplacé par Zeus suit la carte ; marqueur suivi (piste) bouge ; téléphone porté, mini et plein écran ; calque Relais masqué dans CALQUES ; pointillé répéteur et trait vers le relais utilisé toujours présents ; heatmap et fond PHOTO inchangés.
- **Mesure avant / après (non faite)** : journal déjà prévu `[COMSPEC ATAK NATIVE][PERF] Image figée` (`XEH_postInitClient.sqf` lignes 1102-1117) avec les drapeaux téléphone / carte. Comparer, même mission, même vue, téléphone porté 2 minutes puis rangé. Outil : `diag_fps` et le moniteur de scripts CBA sur `fn_mapOnDraw` / `fn_mapGameMarkers`. Ne pas comparer deux cartes différentes.
- **Statut** : confirmé (le chemin et la fréquence). Le gain en images est estimé : il dépend du nombre de marqueurs, non compté ici.

## SQF-002 — Rafraîchissement des unités : tableau de tout le camp, HashMap par allié

- **Fichier** : `fn_schedulerTick.sqf:7-10` ; `fn_localDataRefresh.sqf:61-67` et `:160-166`.
- **Symptôme** : carte ou BFT ouverts, le client reconstruit la piste de chaque allié plusieurs fois par seconde.
- **Cause** : `allUnits select { … }` alloue un nouveau tableau (ennemis compris dans le parcours) toutes les 2 s. Chaque passage utile fait `createHashMapFromArray` par allié, puis `keys` + filtre. Le commentaire du fichier dit que la signature texte a déjà été retirée ; l’allocation des HashMaps, non.
- **Preuve** :

```sqf
_fl = [allUnits select { alive _x && {side group _x isEqualTo _side} }, diag_tickTime, _side, player];
...
_units set [_id, createHashMapFromArray [
    ["id", _id], ["object", _obj], ["self", _obj isEqualTo player],
    ["callsign", _callsign], ["position", _pos], ...
]];
```

Fréquence carte interactive : `_fastEvery = 0.25` (`fn_schedulerTick.sqf` ligne 8).

- **Gravité** : moyenne, haute si le camp a plusieurs centaines d’unités (joueurs + IA équipées).
- **Impact probable** : client, à-coups réguliers téléphone ouvert. Pas d’envoi réseau dans cette fonction.
- **Correction minimale** : réutiliser le HashMap de piste déjà stocké et n’y écrire que position, cap, fraîcheur, précision. Garder le relevé `allUnits` à 2 s (déjà là).
- **Test de non-régression** : filtre ALL / groupe / ORBAT ; IA masquées ; fraîcheur LIVE / STALE / LOST / OFFLINE ; point PLI (position GPS, pas la position exacte) ; un allié mort disparaît au plus tard au relevé suivant ; BFT et carte montrent la même piste.
- **Mesure avant / après (non faite)** : la fonction appelle déjà `fn_perfAcc` avec le libellé « unités et marqueurs (carte) ». Lire ce cumul dans le bilan `[PERF]` toutes les 30 s, carte ouverte, même effectif. Second regard : moniteur CBA, temps passé dans `fn_localDataRefresh`.
- **Statut** : confirmé pour l’allocation et la fréquence. Gain estimé.

## SQF-003 — Bilan radio et GPS : rayons et objets de terrain toutes les 2 secondes

- **Fichier** : `fn_linkQuality.sqf:24-27` et `:52` ; `fn_gpsFix.sqf:61-68` ; `fn_radioLink.sqf:36-84` ; `fn_relayNet.sqf:141-149` ; `fn_waveRelayAction.sqf:71-80`.
- **Symptôme** : avec le réalisme réseau par défaut (1), chaque recalcul de liaison interroge le terrain, parfois les arbres et les bâtiments. Le téléphone ouvert le demande au plus toutes les 2 s, rangé toutes les 6 s. Le maillage, lui, le demande pour chaque paire de nœuds.
- **Cause** : `fn_radioLink` en mode autre que 0 pose jusqu’à 24 `getTerrainHeightASL`, puis en `"full"` jusqu’à 12 `nearestTerrainObjects` et un `lineIntersectsSurfaces`. `fn_gpsFix` ajoute un rayon vertical et huit rayons horizontaux dès qu’un toit est touché. `fn_relayNet` `"list"` (cache 2 s, rappelé par le calque carte et par `"best"`) refait un bilan par bond de répéteur. Le maillage est en O(n²) paires, plafonné à 64 téléphones (`select [0, 63]`) plus les relais ; le `spawn` ne contient pas de `sleep` dans la double boucle, donc il ne cède pas avant la fin du passage.
- **Preuve** :

```sqf
if ((count _cache) isEqualTo 2 && {diag_tickTime - (_cache select 0) < ([6, 2] select !isNull ([] call comspec_atak_native_fnc_display))}) exitWith { _cache select 1 };
...
private _best = ["best"] call comspec_atak_native_fnc_relayNet;
```

```sqf
private _roofs = count (lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 30], player, objNull, true, 4, "GEOM", "NONE"]);
for "_a" from 0 to 315 step 45 do {
    if ((count (lineIntersectsSurfaces [_eye, _eye vectorAdd [8 * sin _a, 8 * cos _a, 0], ...])) > 0) then { _walls = _walls + 1; };
};
```

- **Gravité** : haute en ville ou en forêt, réalisme 1 ou 2, plusieurs relais, calque maillage ou couverture. Faible si le réalisme réseau est à 0 (un seul `terrainIntersectASL` par lien).
- **Impact probable** : client (et le `spawn` du maillage partage le même budget de script). Pas de message Arma. Le serveur refait `"list"` seulement s’il appelle `fn_relayNet` (sweep batteries 30 s, conditions 2 s : `XEH_postInitServer.sqf` lignes 48-50) — le sweep 2 s est `fn_relayCond` `"srv"`, pas le bilan complet, à ne pas confondre.
- **Correction minimale** : mémoriser le profil de terrain d’une paire d’antennes (positions arrondies à 10 m) pendant 5 s ; en réalisme 1, ne pas appeler `nearestTerrainObjects` sur le lien « best » du téléphone au repos ; dans le `spawn` du maillage, `sleep` 0,01 toutes les N paires. Ne pas changer les décibels affichés pour un lien déjà en cache.
- **Test de non-régression** : barres de réseau, mode « exclusivement par relais », répéteur derrière une crête, bâtiment, pluie, brouilleur, MPU5. Couverture Wave Relay et triangulation toujours cohérentes avec l’ancienne formule à cache froid. Téléphone rangé : les alertes et la file d’envoi voient encore une qualité (cache 6 s).
- **Mesure avant / après (non faite)** : deux sessions, même position, réalisme 1. Compter les entrées « Image figée » du journal `[PERF]` sur 3 minutes téléphone ouvert immobile, puis 3 minutes en marchant. Le moniteur CBA sur `fn_radioLink` et `fn_gpsFix` donne le temps script ; ce rapport ne le fournit pas.
- **Statut** : confirmé pour les appels et les cadences. L’ampleur de l’à-coup est une hypothèse tant qu’Arma n’a pas exécuté le script (le nombre de rayons dépend du toit, du mode et du nombre de relais).

## SQF-004 — Serveur : toutes les IA parcourues chaque seconde, point public par IA équipée

- **Fichier** : `fn_aiAtak.sqf:24`, `:56-70`, `:71-115`.
- **Symptôme** : sur le serveur, chaque seconde, la liste de toutes les unités non joueurs est reconstruite. Si le réalisme IA et le PLI sont actifs, chaque IA est visitée même quand le réglage « IA équipées » est « aucune ».
- **Cause** : le `exitWith` ne teste que `_real` et `pli`, pas le mode d’équipement. Le balayage d’équipement (10 s) refait un second `allUnits select`. Une IA qui porte un ATAK et dont le point est dû fait `lineIntersects`, `nearestTerrainObjects` rayon 6 m, puis `setVariable [..., true]` (diffusion vers toutes les machines, file JIP comprise). Plafond d’envoi : 250 IA par passage, pas un plafond du `forEach`.
- **Preuve** :

```sqf
[{ ["tick"] call comspec_atak_native_fnc_aiAtak; }, 1] call COMSPEC_ATAK_PfhSpread;
...
if (!_real || {!(["pli"] call comspec_atak_native_fnc_netCond)}) exitWith {};
...
} forEach (allUnits select { !isPlayer _x });
...
_u setVariable ["COMSPEC_ATAK_Pli", [...], true];
```

Défauts lus dans `fn_netCond.sqf` lignes 52-53 : PLI vrai, réalisme réseau 1. `fn_aiAtak.sqf` ligne 58 : réalisme IA vrai par défaut (`comspec_atak_native_ai_realism`). Mode d’équipement ligne 57 : `"off"` si la variable n’est pas posée. Le scan 1 Hz reste.

- **Gravité** : haute sur une mission à gros effectif IA et réglage « toutes » ou « chefs ». Moyenne avec « aucune » : le scan reste, les rayons et les diffusions ne partent que si `hasDevice` est vrai.
- **Impact probable** : serveur (script) et réseau Arma (une variable publique par point). Les clients reçoivent ces points et les rebalayent dans SQF-002. JIP : chaque variable publique d’IA est rejouée à l’arrivée.
- **Correction minimale** : si le mode est `"off"` et qu’aucune unité n’a `COMSPEC_ATAK_AiAtak`, sortir avant `allUnits`. Mémoriser la liste des IA équipées au balayage 10 s au lieu de filtrer `allUnits` chaque seconde. Grouper les points dans une seule variable de camp si le nombre d’IA équipées dépasse quelques dizaines (changement de contrat : à traiter à part).
- **Test de non-régression** : mode aucune / chefs / toutes ; module Zeus « ATAK · IA équipées » ; point identique sur deux clients ; brouilleur qui fait perdre un point ; IA tuée, piste qui passe LOST ; pas de téléphone ajouté à une IA qui n’a plus de place (comportement déjà décrit : le drapeau suffit).
- **Mesure avant / après (non faite)** : serveur dédié, `diag_fps` serveur et moniteur de scripts sur `fn_aiAtak`, mission avec un effectif IA connu, 2 minutes en « aucune » puis 2 minutes en « toutes ». Côté client, nombre de reçus `COMSPEC_ATAK_Pli` (journal réseau ou #monitor). Aucun de ces chiffres n’a été produit ici.
- **Statut** : confirmé.

## SQF-005 — Point BFT du joueur diffusé à tout le monde

- **Fichier** : `fn_pli.sqf:20-33` ; cadence d’appel `XEH_postInitClient.sqf:195`.
- **Symptôme** : chaque téléphone envoie sa position GPS à l’intervalle du profil, et la moitié de cet intervalle dès que le joueur a bougé de plus de 50 m.
- **Cause** : `player setVariable ["COMSPEC_ATAK_Pli", …, true]`. C’est le contrat du BFT (même point pour tous, y compris un client qui arrive en cours de partie). Le coût est proportionnel aux joueurs liés × (1 / intervalle). Profil Standard : 5 s, Milsim : 10 s (`fn_netCond.sqf` lignes 37 et 52-53), avec un facteur 0,85 à 1,15 et un tirage de perte. Juste avant l’envoi, `fn_linkQuality` et `fn_ewEffects` / `fn_gpsFix` peuvent tomber hors cache (SQF-003).
- **Preuve** : `player setVariable ["COMSPEC_ATAK_Pli", [(getPosASL player) vectorAdd _off, getDir vehicle player, _now, _err], true];`
- **Gravité** : moyenne à 40 joueurs en profil Standard et en mouvement (ordre de grandeur lu : un envoi toutes les ~2,5 s par joueur, pas une mesure). Faible en Milsim immobile.
- **Impact probable** : réseau Arma, toutes machines. Pas la DLL.
- **Correction minimale** : ne rien changer à l’intervalle par défaut. Si une session dépasse l’effectif visé, `CBA_fnc_targetEvent` vers le camp au lieu d’une variable publique monde (perte du rejeu JIP : il faudrait un instantané à la connexion, déjà le rôle du hub). Ce n’est pas un correctif gratuit.
- **Test de non-régression** : deux clients, le point affiché est le même ; pas de point téléphone éteint, sans balise, en réception seule, ou sans fix ; perte simulée (le point suivant respecte l’intervalle plein, `fn_pli.sqf` lignes 29-30).
- **Mesure avant / après (non faite)** : capture du trafic `setVariable` sur une machine dédiée, 10 minutes, effectif fixe, profil Standard puis Milsim. Comparer le nombre de messages, pas les FPS d’un client isolé.
- **Statut** : confirmé comme coût de fonction, pas comme bug. Le gain d’un changement de transport est estimé.

## SQF-006 — Liaison : chaque seconde de travail avant de décider d’envoyer

- **Fichier** : `fn_startSyncLoops.sqf:109-114` ; `fn_updatePosition.sqf:77-78`, `:97`, `:287-323`, `:348-360`, `:671-688` ; `fn_checkMedicalAlerts.sqf:28` ; `fn_getMedicalState.sqf:17-32`.
- **Symptôme** : un client lié, avec téléphone, exécute la préparation de position chaque seconde, même immobile et même si aucun POST ne part.
- **Cause** : le PFH est à 1 s. Avant le `exitWith` de la ligne 360, le script appelle `fn_checkMedicalAlerts` (qui appelle `fn_getMedicalState`) puis rappelle `fn_getMedicalState`, interroge la radio, le groupe, le véhicule. `fn_getMedicalState` avec ACE appelle volume sanguin et fréquence cardiaque. L’envoi lui-même est bien filtré (mouvement, cap, battement 20 à 30 s). En aéronef l’intervalle adaptatif est forcé à 0,4 s (ligne 290) : comme le PFH ne tourne qu’à 1 Hz, chaque passage envoie, donc **1 POST/s** tant que le vol dure. Le commentaire des lignes 671-672 parle d’« environ 2/s » : aucun second cadencement de `fn_updatePosition` n’existe dans ce périmètre (les autres appels sont des envois forcés, pas une boucle). Le POST passe par `spawn` + `fn_extAwait` (non bloquant pour l’image, contrairement à l’ancien appel direct). Le texte JSON est long (véhicule, mods, certificats, combat, médical) et n’est construit qu’au moment de l’envoi.
- **Preuve** :

```sqf
[_unit] call comspec_overwatch_connect_fnc_checkMedicalAlerts;
...
private _medicalState = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
...
if (_airborne) then { _adaptive = 0.4; };
...
if (!_shouldSend) exitWith {};
...
] spawn {
    ["COMSPECExtension", "UpdatePosition", _this] call comspec_overwatch_connect_fnc_extAwait;
};
```

- **Gravité** : moyenne pour le travail 1 Hz (ACE présent). Haute pour le débit Athena si beaucoup de joueurs restent en aéronef : 1 POST/s chacun, plus `COMSPEC_PliAt` public toutes les 5 s.
- **Impact probable** : CPU client chaque seconde ; réseau vers Athena à l’envoi ; un peu de réseau Arma via `COMSPEC_PliAt`. Le fil de jeu n’attend pas la DLL sur ce chemin.
- **Correction minimale** : une seule lecture `fn_getMedicalState` par tick, réutilisée par l’alerte. En aéronef, plancher d’envoi à 1 s déjà imposé par le PFH : écrire 1 s dans `_adaptive` pour que le commentaire et le code disent la même chose, ou baisser le PFH seulement en vol si le 0,4 s est vraiment voulu. Ne pas regrouper ça avec le PLI Arma (SQF-005) : ce sont deux canaux.
- **Test de non-régression** : immobile au sol, le POST ne part pas avant le battement ; un pas de 3 m (infanterie) ou un changement de cap > 15° envoie ; PTT radio envoie tout de suite ; KO ACE confirmé sur 3 ticks ; aéronef en vol et posé ; backoff 429 ; pas d’envoi à (0,0) ni pendant la grâce de réapparition ; Resynch manuel (`_force`) envoie encore.
- **Mesure avant / après (non faite)** : `fn_profileWrap` entoure déjà cet appel (libellé `"updatePosition"`). Lire ses traces dans le journal de liaison sur 5 minutes à pied immobile, puis 5 minutes en hélicoptère, même serveur. Côté Athena, compter les `UpdatePosition` reçus par joueur. Pas de FPS inventé : ce chemin est surtout du temps script et du débit DLL.
- **Statut** : confirmé. Le commentaire « ~2/s » est en écart avec le PFH à 1 Hz : hypothèse qu’il décrit un ancien cadencement.

## SQF-007 — Marqueur modifié : appel DLL bloquant, et souvent deux fois

- **Fichier** : `fn_syncMapMarker.sqf:95-106` ; `fn_startSyncLoops.sqf:192-216` ; `fn_initServerHub.sqf:64-88` ; `fn_extAwait.sqf:2-13`.
- **Symptôme** : déplacer un marqueur (Zeus, Marker Dropper, script de piste) peut figer l’image le temps de la DLL, sur la machine qui traite l’événement.
- **Cause** : `fn_extAwait` documente que `callExtension` bloque le fil du jeu. `SendMarker` ne passe pas par `extAwait` : il est appelé dans l’événement `MarkerUpdated`, qui n’est pas suspendable. Le client envoie tout de suite si le changement est local (lignes 201-203 : la sortie anticipée ne vaut que pour `_local` faux quand le hub est actif). Le hub serveur enfile le même événement et le renvoie après 0,75 s (`fn_serverFlushQueue.sqf` toutes les 0,4 s). La signature anti-doublon est **par machine** (`COMSPEC_MarkerSentSig` non public) : client et serveur dédié peuvent donc chacun poster le même marqueur. Un marqueur qui bouge change de corps, donc le délai de 60 s ne s’applique pas.
- **Preuve** :

```sqf
"COMSPECExtension" callExtension ["SendMarker", [_name, _body, "1", _flag]];
```

```sqf
if (_local isEqualTo false && {(((toLower _marker) find "_defined") >= 0) || {missionNamespace getVariable ["COMSPEC_ServerHubActive", false]}}) exitWith {};
[_marker, false, _force] call comspec_overwatch_connect_fnc_syncMapMarker;
```

- **Gravité** : haute pendant une rafale de marqueurs (glisser sous Zeus, nombreuses pistes). Faible pour une pose isolée.
- **Impact probable** : à-coup client (appel synchrone) ; débit Athena doublé client+serveur ; le réseau Arma des marqueurs, lui, est celui du jeu (création de marqueur), pas un `publicVariable` de ce script.
- **Correction minimale** : le client ne poste plus un marqueur local si `COMSPEC_ServerHubActive` (le serveur le voit aussi). Remplacer l’appel direct par la file déjà utilisée côté hub (`fn_serverEnqueue`, 0,75 s) et, dans le flush, `spawn` + `fn_extAwait`. Garder la signature 60 s.
- **Test de non-régression** : marqueur Zeus visible sur Athena une fois ; suppression ; marqueur joueur `_USER_DEFINED` encore envoyé par l’auteur quand le hub est coupé ; pas de boucle (marqueur `comspec_webmk_` / `comspec_shape_` ignoré, `fn_initServerHub.sqf` lignes 22-30) ; fermeture de la carte (`XEH_postInit.sqf` lignes 160-172) envoie encore les repères joueur ; JIP voit les marqueurs déjà posés via le resync 90 s.
- **Mesure avant / après (non faite)** : sur un serveur de test, déplacer 20 marqueurs en 10 s. Compter les `SendMarker` dans le journal de la DLL (client et serveur) et les lignes `[PERF] Image figée` côté Zeus. Même scénario après file unique. Interdire de conclure sans ces deux journaux.
- **Statut** : confirmé pour l’appel synchrone et pour les deux chemins dans le code. La durée du blocage dépend de la DLL : non mesurée, donc l’amplitude de l’à-coup reste une hypothèse.

## SQF-008 — Balayage de toutes les unités, sur chaque client, toutes les 12 secondes

- **Fichier** : `fn_initGpsBeacons.sqf:30-66`, `:95-100`, `:185`.
- **Symptôme** : même un joueur qui n’est pas le relais élu parcourt véhicules et unités.
- **Cause** : le commentaire dit que le balayage est passé de 3 s à 12 s, mais il n’est pas réservé à l’élu (`COMSPEC_GpsBeaconIsBridge`). Chaque objet subit `isObjectFlag`. Les envois Athena (`updateVehicleTracking`, `reportPhonePosition`, aéronefs, et contacts ennemis si `COMSPEC_AtakShowEnemyAi`) sont, eux, filtrés par `isNearestAtakReporter`. `reportEnemyAiPositions` (lignes 176-179) ne part que si le drapeau est vrai ; il fait alors `allGroups` côté est, plafond 24, toutes les 3 s chez l’élu.
- **Preuve** :

```sqf
} forEach vehicles;
...
} forEach allUnits;
...
if ((diag_tickTime - (missionNamespace getVariable ["COMSPEC_GpsBeaconScanAt", -1e9])) >= 12) then {
    [] call (missionNamespace getVariable ["COMSPEC_GpsBeaconScan", {}]);
};
```

Le PFH qui porte ce bloc est à 3 s (ligne 185).

- **Gravité** : moyenne dès que `allUnits` est lourd (mission avec beaucoup d’IA). Le PFH 3 s hors balayage (élection, listes déjà construites) est faible.
- **Impact probable** : CPU client, pic toutes les 12 s, synchronisé à peu près entre joueurs (pas de `PfhSpread` ici : délai fixe 3 s depuis le handshake de chacun). Réseau Athena seulement pour l’élu.
- **Correction minimale** : le `forEach allUnits` / `vehicles` uniquement si `[] call COMSPEC_GpsBeaconIsBridge`, ou au plus sur deux clients. Les autres gardent les listes déjà poussées par `setGpsBeacon` / `setAllyTrack` (le commentaire lignes 96-97 dit que ces poses alimentent déjà les listes).
- **Test de non-régression** : une balise, un téléphone Zeus et une IA `COMSPEC_AllyTrack` remontent encore, y compris après que l’élu meurt (le suivant prend la main, lignes 11-12). Un client non élu ne poste pas de doublon. JIP : le nouveau client ne renvoie pas tout l’historique (signature d’époque `COMSPEC_TrackSigEpoch`).
- **Mesure avant / après (non faite)** : moniteur CBA, `fn_initGpsBeacons`, 2 minutes, mission IA connue, en comparant un client élu et un client non élu. Le temps doit chuter chez le non-élu et rester du même ordre chez l’élu.
- **Statut** : confirmé.

## SQF-009 — JVN : Draw3D permanent et parcours de tous les marqueurs à 8 Hz

- **Fichier** : `fn_ecotiInit.sqf:13-26` ; `fn_ecotiDraw.sqf:143-211` ; `fn_ecotiTickPostFX.sqf:43-57`.
- **Symptôme** : JVN allumé, le tube et les pastilles coûtent chaque image, et le cache refait un scan lourd environ 8 fois par seconde. JVN éteint (défaut), il reste un Draw3D et un PFH qui sortent tout de suite.
- **Cause** : les deux handlers sont posés pour la partie, pas à l’allumage. Le cache actif fait `allUnits` (plafond distance 800 m), `nearestObjects` véhicules jusqu’à `comspec_overwatch_ecoti_max_dist` (défaut 1 200 m), puis **tout** `allMapMarkers` avec des lectures `configFile`. Le dessin chaque image retrie (`BIS_fnc_sortBy`) et appelle `worldToScreen` / `drawIcon3D`. Tube + autogating : `nearestObjects` véhicules/aéronefs dans 110 m à chaque PFH.
- **Preuve** :

```sqf
COMSPEC_EcotiDrawEH = addMissionEventHandler ["Draw3D", {
    [false] call comspec_overwatch_connect_fnc_ecotiDraw;
}];
...
[true] call comspec_overwatch_connect_fnc_ecotiDraw;
}, 0.12] call CBA_fnc_addPerFrameHandler;
```

```sqf
} forEach allMapMarkers;
```

(dans la branche `_buildCache`, après le filtre distance).

- **Gravité** : haute uniquement JVN actif sur une carte chargée de marqueurs. Faible JVN coupé (un test de variable par image + un appel `ecotiIsActive` à 8 Hz).
- **Impact probable** : client, images. Pas de réseau.
- **Correction minimale** : ne poser le Draw3D et le PFH qu’au passage inactif → actif, et les retirer au coupage. Dans le cache, ne parcourir que les marqueurs déjà filtrés par distance via la liste sale du natif (`COMSPEC_ATAK_MkDirtyGm`) plus un plafond, pas `allMapMarkers` entier à 8 Hz. `BIS_fnc_sortBy` sur 40 pastilles peut rester.
- **Test de non-régression** : JVN off par défaut, aucun changement d’image perceptible à l’œil (le test de non-régression fonctionnel : allumer, voir alliés / véhicules / marqueurs / itinéraire / bâtiment marqué, éteindre, le HUD JVN disparaît, F-PANO exclusif toujours respecté). Réallumage en cours de partie recreé les handlers.
- **Mesure avant / après (non faite)** : même scène, 60 s JVN off puis 60 s JVN on, journal `[PERF] Image figée` et moniteur CBA sur `fn_ecotiDraw`. Le delta off→on est le coût de la fonction ; le delta on avant/après correctif est le gain. Non mesuré ici.
- **Statut** : confirmé. Le coût JVN coupé est confirmé négligeable dans le code (sortie avant les scans) ; le gain du retrait des handlers à l’arrêt est estimé faible.

## SQF-010 — Téléphone ouvert : lecture de tout `uiNamespace` toutes les 10 secondes, et une boucle chaque image

- **Fichier** : `fn_foreignHud.sqf:25-33` et `:53-64`.
- **Symptôme** : à chaque ouverture du téléphone, une boucle délai 0 tourne jusqu’à la fermeture. Toutes les 10 s elle demande la liste complète des variables de `uiNamespace`.
- **Cause** : le commentaire du fichier dit que cette liste « compte des milliers de noms (caches de fonctions BIS/CBA/ACE) ». Le filtre ne garde que les displays, mais `allVariables` + `getVariable` + `isEqualType` s’exécutent quand même. Entre deux scans, la branche `"frame"` ne parcourt que les contrôles déjà masqués (bon marché). Le PFH est retiré à la fermeture (lignes 36-38) : pas de fuite.
- **Preuve** :

```sqf
}, 0] call CBA_fnc_addPerFrameHandler;
...
_names = (allVariables uiNamespace) select {
    (_x select [0, 12]) isNotEqualTo "comspec_atak" && {
        private _v = uiNamespace getVariable _x;
        !isNil "_v" && {_v isEqualType displayNull}
    }
};
```

- **Gravité** : moyenne (pic toutes les 10 s, téléphone en main). Le délai 0 au repos est faible.
- **Impact probable** : client. Pas de réseau.
- **Correction minimale** : construire la liste des noms une fois à l’ouverture, puis l’incrémenter seulement si un display connu manque, au lieu de relire `allVariables` entier. La branche frame peut rester.
- **Test de non-régression** : HUD ACE / autre calque qui chevauche la coque masqué téléphone en main, rétabli téléphone rangé, rétabli si le réglage CBA est décoché, pas de masquage sur l’écran 3D du JPC (ligne 50, `texture`).
- **Mesure avant / après (non faite)** : moniteur CBA sur `fn_foreignHud` pendant 30 s téléphone ouvert. Le pic décennal doit disparaître ; le temps par image de la branche frame doit rester le même.
- **Statut** : confirmé.

## SQF-011 — Écrans rendus dans une texture (`displayUpdate`)

- **Fichier** : `fn_jpcScreen.sqf:123-144` ; `fn_tocScreen.sqf:187-196` ; tick TOC `XEH_postInitClient.sqf:545` (1 Hz).
- **Symptôme** : un écran de gilet ouvert tient une boucle à chaque image et redessine le display jusqu’à 6 fois par seconde. Chaque PC de TOC visible à moins de 80 m redessine son display au plus toutes les 2 s.
- **Cause** : `displayUpdate` reconstruit le rendu UI vers la texture `ui()`. Le JPC ne le fait que si l’objet est à l’écran et à moins de 6 m (après les premières images). Plusieurs TOC dans le même bâtiment additionnent ces rendus. Le PFH JPC est retiré si le display tombe (lignes 128-130).
- **Preuve** :

```sqf
private _rate = (missionNamespace getVariable ["COMSPEC_ATAK_JpcRate", 6]) max 1 min 30;
...
["update", _disp] call comspec_atak_native_fnc_jpcMap;
displayUpdate _disp;
}, 0] call CBA_fnc_addPerFrameHandler];
```

```sqf
if (_due && {_draws < 3 || {_seen}}) then {
    ...
    displayUpdate _disp;
};
```

- **Gravité** : haute si le port sur gilet est utilisé en continu ou si plusieurs écrans TOC sont dans le champ. Nulle si personne n’approche un TOC et que le gilet n’est pas armé (le bascule indique « en chantier » tant que ce n’est pas autorisé, `fn_jpcScreen.sqf` lignes 165-167).
- **Impact probable** : client qui regarde l’écran. `setObjectTextureGlobal` à l’ouverture JPC : un message Arma, pas une boucle.
- **Correction minimale** : JPC à 2 Hz tant que le contenu (page, carte) n’a pas changé ; TOC : un seul `displayUpdate` par écran et par seconde, et pas plus de deux écrans rendus par client et par seconde (le plus proche). Ne pas baisser la cadence quand l’opérateur du TOC bouge la carte (là, 2 s est déjà le plafond utile).
- **Test de non-régression** : écran de gilet lisible en le regardant, noir évité au premier affichage (les premières images forcées, ligne 139) ; écran éteint quand le téléphone est rangé ; TOC à 150 m ne rend plus ; TOC à 10 m, vu de face, se met à jour ; deux clients ne se volent pas la texture (nom de texture séquentiel, ligne 174 de `fn_tocScreen.sqf`).
- **Mesure avant / après (non faite)** : un client face à un JPC, un client face à trois TOC. Journal `[PERF]` et moniteur sur `fn_jpcScreen` / `fn_tocScreen`. Scène vide de référence : mêmes clients, écrans dans le dos (`worldToScreen` vide) — le code doit alors sortir avant `displayUpdate`.
- **Statut** : confirmé pour le chemin. Fréquence réelle du JPC en partie : hypothèse, le portage est marqué en chantier dans l’action.

## SQF-012 — Musique : appel DLL bloquant pendant la lecture

- **Fichier** : `fn_musicTick.sqf:31-39` ; `fn_extensionCall.sqf:4` ; boucle `XEH_postInitClient.sqf:548` (0,5 s).
- **Symptôme** : tant qu’une piste lue par la DLL est en cours, le jeu appelle la DLL au plus toutes les 1,5 s, sur le fil de script, sans `extAwait`.
- **Cause** : `fn_extensionCall` est un `callExtension` direct. Le fichier le dit « appel bloquant ». La barre de lecture entre deux relevés est locale (pas d’appel). Le haut-parleur ne republie `COMSPEC_ATAK_Spk` que si le tableau change (ligne 71). L’écoute des voisins parcourt les joueurs à portée (défaut 30 m) seulement si je n’ai pas ma propre piste (lignes 79-105) : coût faible.
- **Preuve** :

```sqf
// Lecteur DLL interrogé au plus toutes les 1,5 s (appel bloquant pour le jeu ; la boucle tourne toutes les 0,5 s).
private _st = [["MusicStatus"] call comspec_atak_native_fnc_extensionCall, "|"] call CBA_fnc_split;
```

- **Gravité** : faible à moyenne, seulement pendant une lecture DLL. Nulle si l’app Musique est idle (pas d’appel).
- **Impact probable** : micro-à-coups client. Réseau Arma : un `setVariable` public quand le haut-parleur démarre, change de volume ou s’arrête, pas à 2 Hz.
- **Correction minimale** : `MusicStatus` via le même `Async` que `fn_extAwait` (connect), ou un rappel `ExtensionCallback` déjà posé côté natif (`XEH_postInitClient.sqf` ligne 225). Garder l’intervalle 1,5 s.
- **Test de non-régression** : lecture, pause, fin de piste, erreur DLL, haut-parleur entendu par un joueur à 10 m et plus à 40 m, piste Arma (`CfgMusic`) sans appel `MusicStatus`, Zeus mute.
- **Mesure avant / après (non faite)** : une piste de 3 minutes, compter les `[PERF] Image figée` pendant la lecture et pendant le silence. Le delta est le seul chiffre utile ; il n’existe pas encore.
- **Statut** : confirmé pour l’appel bloquant. Son poids en millisecondes est une hypothèse (la commande peut être un simple statut local dans la DLL).

## SQF-013 — Moniteur d’images figées à chaque image, sur chaque client

- **Fichier** : `XEH_postInitClient.sqf:1104-1120`.
- **Symptôme** : après l’arrivée en jeu, une boucle délai 0 tourne jusqu’à la fin de la partie, chez tout le monde, y compris sans téléphone.
- **Cause** : `PfhSpread` avec délai 0 n’est pas décalé et ne peut pas être retiré (l’identifiant n’est pas gardé). Sous 50 ms d’image, le corps est un `diag_deltaTime` et un test. Entre 50 ms et 400 ms, lecture/écriture d’un tableau de deux compteurs, **chaque image lente**. Au-delà de 400 ms, `findDisplay`, `createHashMap` en argument par défaut, `format`, `diag_log`.
- **Preuve** : `}, 0] call COMSPEC_ATAK_PfhSpread;` immédiatement après le test `diag_deltaTime`.
- **Gravité** : faible. Utile pour les mesures proposées dans ce rapport : ne pas le retirer sans garder une sonde.
- **Impact probable** : client, bruit de fond. Le `diag_log` d’une image > 400 ms est local.
- **Correction minimale** : délai 0,1 s suffit pour compter les images > 50 ms (on rate des images, on garde les pics). Garder l’identifiant si on veut le couper.
- **Test de non-régression** : une image volontairement lourde (arsenal ACE, déjà journalisé ligne 1144) produit encore une ligne `[PERF] Image figée` avec les drapeaux téléphone, carte, drone, page.
- **Mesure avant / après (non faite)** : comparer le temps CBA de ce PFH à celui de `fn_mapOnDraw`. S’il est du même ordre, le passer à 0,1 s ; sinon le laisser. Non mesuré.
- **Statut** : confirmé, gravité faible. Correctif facultatif.

## SQF-014 — Mort amie : télémétrie DLL synchrone dans l’événement

- **Fichier** : `XEH_postInitClient.sqf:641-666`.
- **Symptôme** : à la mort d’un fantassin ami, le client responsable appelle `EmitTelemetry` sur le fil de l’événement.
- **Cause** : `callExtension` direct, pas `extAwait`. L’événement est rare (une mort), mais une frappe qui tue plusieurs IA amies dans la même image empile les appels. Le tableau local est borné à 300 (`deleteAt 0`, coûteux en décalant le tableau : correctif facultatif `deleteRange`).
- **Preuve** : `"COMSPECExtension" callExtension ["EmitTelemetry", ["1", "combat", format ["kia:%1:%2", name _unit, round time], [_pay] call comspec_atak_native_fnc_json]];`
- **Gravité** : faible au coup par coup. Moyenne sur une seule image avec beaucoup de morts amies.
- **Impact probable** : à-coup client au moment du combat. Réseau Athena, un POST par mort (dédoublonné côté site, dit le commentaire).
- **Correction minimale** : pousser le HashMap dans une file et l’envoyer au tick 10 s déjà présent (`fn_aarRecord`), ou `spawn` + file async. Garder la règle « un seul téléphone signale ».
- **Test de non-régression** : mort du joueur, mort d’une IA du groupe, mort hors 300 m non signalée par un simple témoin, flux `combat` coupé dans les données remontées (`COMSPEC_DataFlows`) : aucun appel.
- **Mesure avant / après (non faite)** : en éditeur, 10 IA amies tuées la même seconde (grenade), présence ou absence d’une ligne `[PERF] Image figée` dans la seconde. Une seule répétition ne suffit pas (la grenade elle-même coûte) : comparer avec le flux `combat` coupé, même grenade.
- **Statut** : confirmé pour l’appel synchrone. Impact image : hypothèse.

## SQF-015 — Bibliothèque d’extraction sans plafond

- **Fichier** : `fn_dataInit.sqf:22-33` (`COMSPEC_ATAK_DataLib` `pushBack` sans `deleteAt`).
- **Symptôme** : chaque fichier reçu d’un équipier reste pour la partie. Un partage répété du même couple support+nom remplace l’entrée ; un nom nouveau s’ajoute sans limite.
- **Cause** : pas de plafond, contrairement aux historiques du téléphone (souvent 30 à 200).
- **Gravité** : faible. Le tick 4 Hz sort immédiatement sans câble (SQF profil, pas un défaut).
- **Impact probable** : mémoire client si l’app sert de fourre-tout. Pas une boucle image.
- **Correction minimale** : au-delà de 100 entrées, retirer les plus anciennes (`deleteRange`).
- **Test de non-régression** : recevoir un fichier, le voir dans l’app, recevoir une mise à jour du même nom (remplacement, pas doublon), redémarrage de mission (la variable mission repart vide).
- **Mesure avant / après (non faite)** : inutile en FPS. Vérifier `count` de la variable après 120 partages de noms distincts : elle doit rester ≤ 100.
- **Statut** : confirmé, facultatif.

---

## Ordre de travail suggéré (aucun correctif appliqué)

1. SQF-007 (marqueur bloquant, double envoi) et SQF-001 (icônes de carte hors vue) : seuls chemins où une action fréquente tient le fil de jeu ou chaque image.
2. SQF-003 et SQF-004 : rayons et `allUnits` sur les grosses missions.
3. SQF-006 : une lecture médicale en double, et le débit aéronef vers Athena.
4. SQF-002, SQF-008, SQF-009, SQF-010 : allocations et scans déjà en partie commentés dans le code.
5. SQF-005, SQF-011 à SQF-015 : selon l’effectif, le port du gilet, la musique, le JVN.

## Comment mesurer plus tard, sans réutiliser ce texte comme un résultat

Arma n’a pas tourné pour ce rapport. Quand une session sera possible, le dépôt a déjà les sondes suivantes ; les lire avant et après **un seul** correctif à la fois, même mission, même place, 3 minutes :

- Journal client : `[COMSPEC ATAK NATIVE][PERF] Arrivée en jeu` et `Image figée` (seuil écrit : 400 ms), drapeaux arsenal / téléphone / carte / drone / page.
- Bilan 30 s : `fn_perfAcc` (`"report"`), libellés déjà posés (« unités et marqueurs (carte) », « nacelle (image) », « positions BFT », « drone suivi »).
- Journal de liaison : `fn_profileWrap` autour de `updatePosition` et des sondages.
- Journal DLL : nombre de `UpdatePosition`, `SendMarker`, `EmitTelemetry` par minute, client et serveur séparés.
- Moniteur de scripts CBA (ou `diag_activeSQFScripts`) filtré sur les fonctions citées. C’est la seule façon d’obtenir une latence de script ; elle n’est pas dans ce fichier.

Ne pas additionner les gains estimés. Plusieurs correctifs touchent la même image (carte + bilan radio + médical) et un gain isolé ne se somme pas.

---

## Append — profil combat (27/10/2026 soir, reprise audit)

Branche de travail : `fix/tenues-athena-ui`. **Arma n'a pas ete lance** pour cet append. Frequences = lecture du code. Gains = **estimes** sauf mention contraire.

### Inventaire hot paths pendant un engagement

| Id | Chemin | Frequence lue | Lie au tir ? | Statut |
| --- | --- | --- | --- | --- |
| SQF-C01 | `fn_mapOnDraw` + `mapPlaceText` | Chaque image si carte ATAK dessinee (HUD porte inclus) | Oui si telephone porte | **Confirme** ; correctif applique (toponymes skips si !interactive) |
| SQF-C02 | `fn_meshDraw` pointilles relais | Jusqu'a ~80 `drawLine` / relais / image | Oui si calque Relais + HUD | **Confirme** ; correctif : trait plein en combat / HUD |
| SQF-C03 | `fn_localDataRefresh` HashMap/allie | 4 Hz carte interactive | Indirect (CPU) | **Confirme** ; reutilisation piste |
| SQF-C04 | `fn_syncMapMarker` `SendMarker` sync | Chaque MarkerUpdated | Peut coincider avec rafales marqueurs | **Confirme** ; spawn + `extAwait` |
| SQF-C05 | `fn_foreignHud` PFH delai 0 | Chaque image telephone ouvert | Oui si telephone ouvert en tirant | **Confirme** ; PFH 0.1 s |
| SQF-C06 | `deviceImpact` / ACE wound / Explosion | Par coup (file next frame) | Oui si touche | Deja borne ; pas de Fired EH pack |
| SQF-C07 | `fn_garminTick` stress | 2 s | `getSuppression` + ennemi proche | Cout faible ; pas de Fired |
| SQF-C08 | Moniteur PERF PFH 0 | Chaque image | Journal si hitch > 400 ms | Instrumentation enrichie (combat/suppression) |

Aucun `addEventHandler ["Fired"` dans le pack natif : le freeze au tir n'est **pas** un handler Fired COMSPEC. Hypothese principale (non mesuree) : Draw carte HUD + pointilles Relais + callExtension sync marqueurs sous charge moteur (particules/ballistique).

### Correctifs LOT-COMBAT (cette session)

Appliques dans les sources ; **gain FPS non mesure** (Arma non execute).

1. Toponymes ignores en HUD porte.
2. Pointilles relais → trait plein si HUD / COMBAT / suppression > 8 %.
3. Pistes unites : HashMap reutilise.
4. SendMarker hors thread image via `extAwait`.
5. foreignHud a 10 Hz.
6. Log hitch : champs `combat` + `suppression`.

### Non-regression a verifier en jeu

- Carte interactive : toponymes et pointilles relais inchanges hors combat.
- Marqueur pose pendant un engagement : arrive encore sur Athena (file Async).
- Telephone ouvert : HUD tiers toujours masques (delai max ~0.1 s).
- Journal `[PERF] Image figee` : comparer avant/apres meme scenario tir 60 s.


---

## Append — consolidation agents A1/A2/A3 (27/10 soir, follow-up)

### A2 ([SQF combat](2ca6d411-e939-465c-9166-cb94baa31206))
Priorite restante documentee : polls sync 4-10 s (SQF-C01 A2), carte Draw (partiellement traite), ECOTI, FiredMan journal.
Correctifs follow-up : `fn_emitTelemetryEvent` spawn+extAwait ; EntityKilled KIA async ; `fn_checkAtakDamage` early-exit natif avant getAllHitPointsDamage.

### A3 ([DLL](c07a70b5-0e90-409b-9f4f-eb97a0d4406f))
DLL-C01 ouvert : TryGetSyncResponse peut encore bloquer 8 s sur commandes reseau (hors Async). DLL-C02 MapPack.Count enum FS sur thread jeu. Non patches dans ce follow-up (lot large).

### A1 ([Architecture](977ec1dc-28c9-4807-91ab-4954eec5641e))
ARCH-006 : doublons routes relays/roleplay retires de `routes/web.php`. ARCH-004/005/007/008 ouverts ou SUSPECT.
