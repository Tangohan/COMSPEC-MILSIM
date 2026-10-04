/*
    Mode drone, côté téléphone du pilote. Le drone doit être posé à ses pieds pour s'appairer (rien à distance) ;
    ensuite la liaison radio porte comspec_atak_native_drone_range mètres, réduite par le relief.
    Liaison perdue : le drone rentre seul à son point de décollage, comme un DJI.
    Drones du mod Mavic (Mavic_drone_base_F) : appairage identique, signal repris de Mavic_fnc_getSignal.
      ["pair", drone] / ["unpair"]
      ["mode", "hover" | "me" | "player" | "home" | "land" | "hunt"]
      ["player", uid]             joueur à suivre ;
      ["alt", m] / ["speed", km/h]
      ["arm"]                     fixe une charge prise dans l'inventaire (drone à moins de 5 m) ;
      ["strike", "LASER" | "GRID"]  tir et oublie, à confirmer d'un second appui ;
      ["cam"]                     caméra du drone dans l'app ;
      ["tick"]                    surveillance (toutes les secondes tant qu'un drone est appairé) ;
      ["link"]                    renvoie [barres 0-4, distance, en liaison].
    État : missionNamespace COMSPEC_ATAK_Drone (drone appairé), COMSPEC_ATAK_DroneHome, COMSPEC_ATAK_DroneCam.
*/
params [["_act", "tick"], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DRONE") then { ["DRONE"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _send = { params ["_cmd", ["_args", []]]; ["comspec_atak_native_droneCmd", [_d, _cmd, _args], _d] call CBA_fnc_targetEvent; };
private _say = { params ["_lvl", "_txt"]; [_lvl, _txt, 4, 40] call comspec_atak_native_fnc_notify; };
// Charges acceptées : chargeur de l'inventaire → munition déclenchée à l'impact.
private _payloads = [
    ["RPG7_F", "R_PG7_F", "roquette RPG-7"], ["RPG32_F", "R_PG32V_F", "roquette RPG-42"],
    ["DemoCharge_Remote_Mag", "DemoCharge_Remote_Ammo_Scripted", "charge de démolition"],
    ["HandGrenade", "GrenadeHand", "grenade RGO"], ["MiniGrenade", "mini_Grenade", "grenade RGN"]
];
private _link = {
    if (isNull _d || {!alive _d}) exitWith { [0, 0, false] };
    private _range = missionNamespace getVariable ["comspec_atak_native_drone_range", 2500];
    if (terrainIntersectASL [eyePos player, getPosASL _d]) then { _range = _range * 0.3; };
    if !([player] call comspec_atak_native_fnc_hasDevice) then { _range = 0; };
    if ((missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0) then { _range = 0; };
    private _dist = player distance _d;
    // Drone du mod Mavic (DJI) : on reprend son propre calcul de signal (0-1, perdu sous 0,05).
    if (_range > 0 && {_d isKindOf "Mavic_drone_base_F"} && {!isNil "Mavic_fnc_getSignal"}) exitWith {
        private _sig = [player, _d] call Mavic_fnc_getSignal;
        if !(_sig isEqualType 0) then { _sig = 1; };
        private _okM = _sig >= 0.05;
        [[0, (ceil (_sig * 4)) max 1 min 4] select _okM, round _dist, _okM]
    };
    private _ok = _dist < _range;
    [[0, ceil (4 * (1 - _dist / (_range max 1))) max 1] select _ok, round _dist, _ok]
};
private _needLink = {
    if ((call _link) select 2) exitWith { true };
    ["WARNING", "Drone hors liaison : ordre non transmis"] call _say;
    false
};
switch (_act) do {
    case "link": { call _link };
    case "pair": {
        private _new = _arg;
        if (isNull _new || {!alive _new} || {(player distance _new) > 15}) exitWith { ["WARNING", "Posez le drone à vos pieds pour l'appairer"] call _say; };
        private _owner = _new getVariable ["COMSPEC_DroneOwner", ""];
        if (_owner isNotEqualTo "" && {_owner isNotEqualTo getPlayerUID player}) exitWith { ["WARNING", "Ce drone est déjà appairé à un autre téléphone"] call _say; };
        if (!isNull _d && {_d isNotEqualTo _new}) then { ["unpair"] call comspec_atak_native_fnc_droneAction; };
        _new setVariable ["COMSPEC_DroneOwner", getPlayerUID player, true];
        _new setVariable ["COMSPEC_DroneSide", side group player, true];
        _new setVariable ["COMSPEC_DroneAlt", _s getOrDefault ["droneAlt", 40], true];
        _new setVariable ["COMSPEC_DroneSpd", _s getOrDefault ["droneSpd", 40], true];
        missionNamespace setVariable ["COMSPEC_ATAK_Drone", _new];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneHome", getPosASL _new];
        ["SUCCESS", format ["Drone appairé : %1", getText (configOf _new >> "displayName")]] call _say;
        if ((missionNamespace getVariable ["COMSPEC_ATAK_DronePfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DronePfh", [{ ["tick"] call comspec_atak_native_fnc_droneAction; }, 1] call CBA_fnc_addPerFrameHandler];
        };
        call _render;
    };
    case "unpair": {
        if (!isNull _d) then { _d setVariable ["COMSPEC_DroneOwner", "", true]; };
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back", "comspec_dronecam"]; camDestroy _cam; };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCam", objNull];
        missionNamespace setVariable ["COMSPEC_ATAK_Drone", objNull];
        [missionNamespace getVariable ["COMSPEC_ATAK_DronePfh", -1]] call CBA_fnc_removePerFrameHandler;
        missionNamespace setVariable ["COMSPEC_ATAK_DronePfh", -1];
        call _render;
    };
    case "mode": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        switch (_arg) do {
            case "hover": { ["hover"] call _send; };
            case "me": { ["follow", [player]] call _send; };
            case "player": {
                private _uid = _s getOrDefault ["droneFollowUid", ""];
                private _t = allPlayers param [(allPlayers findIf { getPlayerUID _x isEqualTo _uid }), objNull];
                if (isNull _t || {!alive _t}) exitWith { ["WARNING", "Choisissez d'abord le joueur à suivre"] call _say; };
                ["follow", [_t]] call _send;
            };
            case "home": { ["home", [player]] call _send; };
            case "land": { ["land"] call _send; };
            case "hunt": { ["hunt", [_s getOrDefault ["droneHuntRad", 250], player]] call _send; };
        };
        call _render;
    };
    case "player": { _s set ["droneFollowUid", _arg]; call _render; };
    case "alt": { _s set ["droneAlt", _arg]; if (!isNull _d && {call _needLink}) then { ["alt", [_arg]] call _send; }; call _render; };
    case "speed": { _s set ["droneSpd", _arg]; if (!isNull _d && {call _needLink}) then { ["speed", [_arg]] call _send; }; call _render; };
    case "radius": { _s set ["droneHuntRad", _arg]; call _render; };
    case "arm": {
        if (isNull _d) exitWith {};
        if ((player distance _d) > 5) exitWith { ["WARNING", "Le drone doit être à moins de 5 m pour fixer une charge"] call _say; };
        if !(missionNamespace getVariable ["comspec_atak_native_drone_strike", true]) exitWith { ["WARNING", "Drones armés interdits sur ce serveur"] call _say; };
        private _mags = magazines player;
        private _i = _payloads findIf { ((_x select 0) in _mags) && {isClass (configFile >> "CfgAmmo" >> (_x select 1))} };
        if (_i < 0) exitWith { ["WARNING", "Aucune charge dans l'inventaire (roquette RPG, charge de démolition ou grenade)"] call _say; };
        (_payloads select _i) params ["_mag", "_ammo", "_label"];
        player removeMagazine _mag;
        _d setVariable ["COMSPEC_DroneArmed", _ammo, true];
        _d setVariable ["COMSPEC_DroneArmedLabel", _label, true];
        ["SUCCESS", format ["Drone armé : %1", _label]] call _say;
        call _render;
    };
    case "strike": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        if ((_d getVariable ["COMSPEC_DroneArmed", ""]) isEqualTo "") exitWith { ["WARNING", "Drone non armé"] call _say; };
        private _tgt = switch (_arg) do {
            case "LASER": {
                private _l = ((units group player) apply { laserTarget _x }) select { !isNull _x };
                if ((count _l) > 0) then { _l select 0 } else { objNull }
            };
            default {
                private _g = (["droneGrid", ""] call comspec_atak_native_fnc_formValue) regexReplace ["[^0-9]", ""];
                if ((count _g) < 6 || {((count _g) mod 2) isEqualTo 1}) then { [] } else {
                    private _r = [_g] call BIS_fnc_gridToPos;
                    _r params [["_p", []], ["_sz", [0, 0]]];
                    if ((count _p) < 2) then { [] } else {
                        private _c = [(_p select 0) + (_sz select 0) / 2, (_p select 1) + (_sz select 1) / 2];
                        [_c select 0, _c select 1, (getTerrainHeightASL _c) + 1]
                    }
                };
            };
        };
        if ((_tgt isEqualType objNull && {isNull _tgt}) || {_tgt isEqualTo []}) exitWith {
            ["WARNING", ["Grille invalide (6, 8 ou 10 chiffres)", "Aucun laser allumé dans votre groupe"] select (_arg isEqualTo "LASER")] call _say;
        };
        // Second appui dans les 5 s pour confirmer : pas de frappe partie par erreur.
        private _c = _s getOrDefault ["droneConfirm", ["", 0]];
        if ((_c select 0) isNotEqualTo _arg || {diag_tickTime - (_c select 1) > 5}) exitWith {
            _s set ["droneConfirm", [_arg, diag_tickTime]];
            ["WARNING", "Appuyez encore pour confirmer la frappe"] call _say;
            call _render;
        };
        _s set ["droneConfirm", ["", 0]];
        ["strike", [_tgt, player]] call _send;
        ["WARNING", format ["Frappe lancée sur %1", [[_tgt] call comspec_atak_native_fnc_gridRef, "le point laser"] select (_tgt isEqualType objNull)]] call _say;
        call _render;
    };
    case "cam": {
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        if (!isNull _cam) exitWith {
            _cam cameraEffect ["Terminate", "Back", "comspec_dronecam"]; camDestroy _cam;
            missionNamespace setVariable ["COMSPEC_ATAK_DroneCam", objNull];
            call _render;
        };
        if (isNull _d) exitWith {};
        _cam = "camera" camCreate (getPosATL _d);
        _cam cameraEffect ["Internal", "Back", "comspec_dronecam"];
        _cam camSetFov 0.7;
        _cam attachTo [_d, [0, 0.3, -0.3]];
        _cam setVectorDirAndUp [[0, 0.8, -0.6], [0, 0.6, 0.8]];
        "comspec_dronecam" setPiPEffect [[0, 1] select (sunOrMoon < 0.3)];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCam", _cam];
        call _render;
    };
    case "tick": {
        if (isNull _d) exitWith { ["unpair"] call comspec_atak_native_fnc_droneAction; };
        if (!alive _d) exitWith {
            ["WARNING", ["Drone perdu", "Drone : impact"] select ((_d getVariable ["COMSPEC_DroneMode", ""]) isEqualTo "STRIKE")] call _say;
            ["unpair"] call comspec_atak_native_fnc_droneAction;
        };
        private _l = call _link;
        private _was = missionNamespace getVariable ["COMSPEC_ATAK_DroneLinked", true];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneLinked", _l select 2];
        // Liaison perdue : retour automatique au point de décollage (sauf frappe déjà lancée).
        if (_was && {!(_l select 2)} && {!((_d getVariable ["COMSPEC_DroneMode", ""]) in ["STRIKE", "RTH", "LAND"])}) then {
            ["WARNING", "Drone : liaison perdue, retour au point de décollage"] call _say;
            [] call comspec_atak_native_fnc_vibrate;
            ["rth", [missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", getPosASL player]]] call _send;
        };
        if (!_was && {_l select 2}) then { ["SUCCESS", "Drone : liaison rétablie"] call _say; };
        private _osd = uiNamespace getVariable ["COMSPEC_ATAK_DroneOsd", controlNull];
        if (!isNull _osd) then { _osd ctrlSetStructuredText parseText ([_d, _l] call comspec_atak_native_fnc_droneOsd); };
    };
};
