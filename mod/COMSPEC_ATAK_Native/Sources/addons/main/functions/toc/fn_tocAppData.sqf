/*
    Source directe de la console du poste TOC (demande de TTRD, 4.9.0 : « il faut que tout le TOC soit utilisable sans
    téléphone, c'est le principe »). La console (fn_tocLaptop, fn_tocApp) ne dépend d'aucun téléphone : les unités du
    camp sont lues dans le jeu (position, cap, véhicule, groupe, santé, munitions) et fusionnées avec le flux des
    téléphones publié par le serveur (fn_tocFeed) quand il existe.
      - une unité qui porte un ATAK garde son nœud du réseau (dernier point PLI, en direct / ancien / perdu / hors ligne :
        le brouillage et la portée gardent leur effet sur elle) ;
      - une unité sans téléphone devient un nœud « source directe » : position du jeu, jamais « perdue » ni « hors ligne » ;
      - sans flux du serveur (serveur sans le mod, aucun écran), la console garde toutes les unités par la source directe.
    Réglage de mission : missionNamespace comspec_atak_native_toc_direct (vrai par défaut ; faux : seuls les téléphones).
    Calcul côté client, console ouverte seulement, gardé 2 s (les appels entre-temps rendent le même tableau).
    Modes :
      ["feed", camp]      [serverTime du flux (-1 : aucun), nœuds, flux d'événements, état]
                          nœud : les 9 champs de fn_tocFeed + source "P" (téléphone) | "D" (source directe)
      ["unit", clé]       fiche d'un nœud : table "obj", "name", "cs", "grp", "type", "veh", "crew", "grid", "pos", "speed",
                          "alt", "health" [libellé, jeton], "ammo", "player", "phone", "uid" ; vide si l'unité a disparu
      ["label", [fraîcheur, source]]   [libellé, jeton du thème] : jamais « perdu » pour une unité sans téléphone
*/
params [["_mode", "feed"], ["_a", ""]];
switch (_mode) do {
    case "feed": {
        private _side = _a;
        // Gardé 2 s : les appels entre-temps (dessin de la carte à chaque image) rendent le même tableau, sans rien relire.
        private _key = [_side, floor (diag_tickTime / 2)];
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_TocAppFeed", [[], []]];
        if ((_c select 0) isEqualTo _key) exitWith { _c select 1 };
        // Flux des téléphones : celui de fn_tocStatus quand la 4.8.0 est là (nœud du joueur local corrigé), sinon la variable.
        private _raw = if (isNil "comspec_atak_native_fnc_tocStatus") then { [] } else { ["feed", _side] call comspec_atak_native_fnc_tocStatus };
        if (isNil "_raw" || {!(_raw isEqualType [])} || {(count _raw) < 2}) then { _raw = missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _side], []]; };
        _raw params [["_ft", -1], ["_nodes", []], ["_events", []], ["_stats", [0, 0, 0, 0]]];
        // 4.10.0 : le serveur publie aussi les pistes du jeu (fn_tocTracks, source « J ») ; elles restent « source directe » ici.
        private _out = _nodes apply { private _n = +_x; _n set [9, ["P", "D"] select ((_n param [9, "A"]) isEqualTo "J")]; _n };
        if (missionNamespace getVariable ["comspec_atak_native_toc_direct", true]) then {
            // Véhicules et piétons déjà couverts par un nœud de téléphone.
            private _cov = createHashMap;
            { private _o = objectFromNetId (_x select 0); if (!isNull _o) then { _cov set [netId vehicle _o, true]; }; } forEach _nodes;
            // Indicatifs gardés 30 s par unité.
            private _names = uiNamespace getVariable ["COMSPEC_ATAK_TocAppNames", createHashMap];
            if ((count _names) > 800) then { _names = createHashMap; };
            uiNamespace setVariable ["COMSPEC_ATAK_TocAppNames", _names];
            private _byVeh = createHashMap;
            {
                private _u = _x;
                if (!alive _u || {(str side group _u) isNotEqualTo _side}) then { continue };
                private _v = vehicle _u;
                private _vk = netId _v;
                if (_vk in _cov) then { continue };
                private _isAi = !isPlayer _u;
                private _uk = netId _u;
                private _nm = _names getOrDefault [_uk, ["", -1]];
                if (diag_tickTime > (_nm select 1)) then {
                    private _cs = [_u, true] call comspec_atak_native_fnc_unitCallsign;
                    if (_cs isEqualTo "") then { _cs = [name _u, groupId group _u] select _isAi; };
                    _nm = [_cs select [0, 28], diag_tickTime + 25 + random 10];
                    _names set [_uk, _nm];
                };
                // Équipage d'un véhicule : un seul nœud, occupants comptés, indicatif du chef de bord.
                if (_v isNotEqualTo _u) then {
                    private _i = _byVeh getOrDefault [_vk, -1];
                    if (_i >= 0) then {
                        private _n = _out select _i;
                        _n set [8, (_n select 8) + 1];
                        if (_u isEqualTo effectiveCommander _v) then { _n set [1, _nm select 0]; _n set [6, _isAi]; };
                        continue
                    };
                    _byVeh set [_vk, count _out];
                };
                if ((count _out) >= 250) then { continue };
                private _kind = switch (true) do {
                    case (_v isEqualTo _u): { "inf" };
                    case (_v isKindOf "Air"): { "air" };
                    case (_v isKindOf "Ship"): { "boat" };
                    case (_v isKindOf "Tank" || {_v isKindOf "Wheeled_APC_F"}): { "armor" };
                    default { "veh" };
                };
                private _pos = getPosASL _v;
                _out pushBack [_uk, _nm select 0, [round (_pos select 0), round (_pos select 1)], round (getDir _v), 0, _kind, _isAi, groupId group _u, 1, "D"];
            } forEach allUnits;
        };
        private _feed = [_ft, _out, _events, _stats];
        uiNamespace setVariable ["COMSPEC_ATAK_TocAppFeed", [_key, _feed]];
        _feed
    };
    case "unit": {
        private _u = objectFromNetId _a;
        if (isNull _u || {!alive _u}) exitWith { createHashMap };
        private _v = vehicle _u;
        private _pos = getPosASL _v;
        // Santé : état du jeu, inconscience d'ACE si elle est là.
        private _health = switch (true) do {
            case (_u getVariable ["ACE_isUnconscious", false] || {(lifeState _u) isEqualTo "INCAPACITATED"}): { ["inconscient", "red"] };
            case ((damage _u) > 0.25 || {(lifeState _u) isEqualTo "INJURED"}): { ["blessé", "amber"] };
            default { ["indemne", "accent"] };
        };
        // Munitions : chargeurs de l'arme principale portés (hors celui qui est engagé).
        private _pw = primaryWeapon _u;
        private _mags = if (_pw isEqualTo "") then { -1 } else { private _ok = (compatibleMagazines _pw) apply { toLower _x }; { (toLower _x) in _ok } count (magazines _u) };
        createHashMapFromArray [
            ["obj", _u], ["name", name _u], ["cs", [_u, true] call comspec_atak_native_fnc_unitCallsign], ["grp", groupId group _u],
            ["type", getText (configOf _u >> "displayName")], ["veh", ["", getText (configOf _v >> "displayName")] select (_v isNotEqualTo _u)],
            ["crew", [0, count (crew _v)] select (_v isNotEqualTo _u)], ["grid", [_pos, 8] call comspec_atak_native_fnc_gridRef], ["pos", [_pos select 0, _pos select 1]],
            ["speed", round (abs (speed _v))], ["alt", round ((getPosATL _v) select 2)], ["health", _health], ["ammo", _mags], ["player", isPlayer _u],
            ["phone", [_u] call comspec_atak_native_fnc_hasDevice], ["uid", getPlayerUID _u]
        ]
    };
    case "label": {
        _a params [["_fr", 0], ["_src", "P"]];
        if (_src isEqualTo "D") exitWith { ["sans téléphone · source directe", "text2"] };
        // Libellés de la 4.8.0 (fn_tocStatus) quand elle est là : « perdu » seulement au-delà de la cadence adaptative.
        private _l = if (isNil "comspec_atak_native_fnc_tocStatus") then { [] } else { ["label", _fr] call comspec_atak_native_fnc_tocStatus };
        if (!isNil "_l" && {_l isEqualType []} && {(count _l) >= 2}) exitWith { _l };
        [["en direct", "accent"], ["point ancien", "amber"], ["liaison perdue", "lost"], ["téléphone éteint", "muted"]] select ((_fr max 0) min 3)
    };
    default { [] };
};
