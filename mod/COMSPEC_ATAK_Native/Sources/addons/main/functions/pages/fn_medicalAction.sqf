/*
    Actions de l'app Médical. Params : [action, valeur, id d'alerte]
    MEDEVAC : "auto" (remplit les lignes 3 à 5 avec les blessés alliés à moins de 50 m), "pick"/"picked" (LZ sur la carte),
    "medevac" (envoi au camp en jeu et, avec Overwatch, au poste web), "recv" (demande reçue), "status" / "statusRecv" (suivi),
    "locateReq" (centre la carte sur la LZ d'une demande).
    Demandes suivies : missionNamespace COMSPEC_ATAK_MedevacReqs = HashMap id -> HashMap.
*/
params ["_action", ["_v", ""], ["_id", ""]];
private _steps = ["DEMANDÉE", "ACCEPTÉE", "EN VOL", "SUR ZONE", "TERMINÉE"];
// État d'un blessé : Overwatch si présent, sinon lecture ACE / vanilla.
private _medState = {
    params ["_u"];
    if (!alive _u) exitWith { "kia" };
    if (!isNil "comspec_overwatch_connect_fnc_getMedicalState") exitWith { (([_u] call comspec_overwatch_connect_fnc_getMedicalState) splitString "|") param [0, "stable"] };
    switch (true) do {
        case (_u getVariable ["ace_medical_inCardiacArrest", false]): { "cardiac_arrest" };
        case (_u getVariable ["ACE_isUnconscious", false] || {lifeState _u isEqualTo "INCAPACITATED"}): { "unconscious" };
        case ((damage _u) > 0.5 || {(_u getVariable ["ace_medical_woundBleeding", 0]) > 0.1}): { "critical" };
        case ((damage _u) > 0.1 || {(_u getVariable ["ace_medical_woundBleeding", 0]) > 0}): { "wounded" };
        default { "stable" };
    }
};
private _reqs = missionNamespace getVariable ["COMSPEC_ATAK_MedevacReqs", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_MedevacReqs", _reqs];
private _lzMarker = {
    params ["_r"];
    private _mk = format ["COMSPEC_MEDEVAC_%1", _r get "id"];
    deleteMarkerLocal _mk;
    if ((_r get "status") >= 4) exitWith {};
    createMarkerLocal [_mk, _r get "pos"];
    _mk setMarkerTypeLocal "mil_pickup";
    _mk setMarkerColorLocal "ColorRed";
    _mk setMarkerTextLocal format ["MEDEVAC %1 · %2", _r get "from", _steps select (_r get "status")];
};
private _save = {
    private _m = uiNamespace getVariable ["COMSPEC_ATAK_Medevac", createHashMap];
    private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
    { if (_x in _form) then { _m set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["mLz", "mRem"];
    uiNamespace setVariable ["COMSPEC_ATAK_Medevac", _m];
    _m
};
private _render = { [{ ["MEDICAL"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
switch (_action) do {
    case "set": { (call _save) set [_v, _id]; call _render; };
    case "auto": {
        private _m = call _save;
        private _t = [0, 0, 0]; private _litter = 0; private _amb = 0;
        {
            private _h = [_x] call _medState;
            if (_h in ["cardiac_arrest", "unconscious"]) then { _t set [0, (_t select 0) + 1]; _litter = _litter + 1; };
            if (_h isEqualTo "critical") then { _t set [1, (_t select 1) + 1]; _litter = _litter + 1; };
            if (_h isEqualTo "wounded") then { _t set [2, (_t select 2) + 1]; _amb = _amb + 1; };
        } forEach (allUnits select { side group _x isEqualTo side group player && {_x distance player < 50} });
        { _m set [_x, str ((_t select _forEachIndex) min 4)]; } forEach ["mT1", "mT2", "mT3"];
        _m set ["litter", _litter]; _m set ["amb", _amb];
        if ((_t select 0) > 0) then { _m set ["prio", "URGENT"]; } else { if ((_t select 1) > 0) then { _m set ["prio", "PRIORITY"]; } else { _m set ["prio", "ROUTINE"]; }; };
        ["INFO", format ["Blessés à 50 m : %1 urgent(s), %2 prioritaire(s), %3 différé(s)", _t select 0, _t select 1, _t select 2], 4, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "pick": {
        call _save;
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["medevacPick", "LZ"];
        ["MAP"] call comspec_atak_native_fnc_navigate;
        ["INFO", "Touchez la carte pour placer la LZ", 4, 20] call comspec_atak_native_fnc_notify;
    };
    case "picked": {
        (call _save) set ["mLz", [_v, 8] call comspec_atak_native_fnc_gridRef];
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["medTab", "MEDEVAC"];
        ["MEDICAL"] call comspec_atak_native_fnc_navigate;
    };
    case "recv": {
        _v params ["_r", "_side"];
        if (_side isNotEqualTo str side group player) exitWith {};
        if ((_r get "id") in _reqs) exitWith {};
        _reqs set [_r get "id", _r];
        [_r] call _lzMarker;
        if ((_r get "uid") isNotEqualTo getPlayerUID player && {[player] call comspec_atak_native_fnc_hasDevice}) then {
            ["WARNING", format ["MEDEVAC %1 · %2 · T1 %3 / T2 %4 / T3 %5 · LZ %6", _r get "prio", _r get "from", _r get "t1", _r get "t2", _r get "t3", _r get "grid"], 10, 85] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MEDICAL") then { call _render; };
    };
    case "status": {
        // _v : id, _id : nouvel état (index) ; -1 = annulée
        private _r = _reqs getOrDefault [_v, createHashMap];
        if ((count _r) isEqualTo 0) exitWith {};
        private _st = parseNumber _id;
        [{ params ["_rid", "_st", "_by", "_side"]; ["comspec_atak_native_medevacStatus", [_rid, _st, _by, _side]] call CBA_fnc_globalEvent; },
            [_v, _st, [player] call comspec_atak_native_fnc_unitCallsign, str side group player], "MEDEVAC", 1] call comspec_atak_native_fnc_netSend;
    };
    case "statusRecv": {
        _v params ["_rid", "_st", "_by", "_side"];
        if (_side isNotEqualTo str side group player) exitWith {};
        private _r = _reqs getOrDefault [_rid, createHashMap];
        if ((count _r) isEqualTo 0) exitWith {};
        _r set ["status", [_st, 4] select (_st < 0)];
        _r set ["cancelled", _st < 0];
        _r set ["by", _by];
        [_r] call _lzMarker;
        if ((_r get "uid") isEqualTo getPlayerUID player) then {
            ["INFO", format ["MEDEVAC : %1 (%2)", if (_st < 0) then { "ANNULÉE" } else { _steps select _st }, _by], 6, 60] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MEDICAL") then { call _render; };
    };
    case "locateReq": {
        private _r = _reqs getOrDefault [_v, createHashMap];
        if ((count _r) isEqualTo 0) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.03] call comspec_atak_native_fnc_mapCenter; }, _r get "pos"] call CBA_fnc_execNextFrame;
    };
    case "lzHere": { (call _save) set ["mLz", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef]; call _render; };
    case "medevac": {
        private _m = call _save;
        private _n = { params ["_k", "_d"]; (parseNumber (_m getOrDefault [_k, _d])) max 0 };
        private _grid = (_m getOrDefault ["mLz", ""]) splitString " " joinString "";
        private _pos = getPos player;
        if (_grid isNotEqualTo "") then { private _p = ([_grid] call BIS_fnc_gridToPos) param [0, []]; if ((count _p) >= 2) then { _pos = [_p select 0, _p select 1, 0]; }; };
        if ((["mT1", "1"] call _n) + (["mT2", "0"] call _n) + (["mT3", "0"] call _n) < 1) exitWith { ["WARNING", "MEDEVAC : indiquez au moins un blessé", 4, 30] call comspec_atak_native_fnc_notify; };
        // En jeu : diffusée au camp, avec suivi.
        private _r = createHashMapFromArray [
            ["id", format ["%1-%2", getPlayerUID player, round diag_tickTime]], ["uid", getPlayerUID player], ["from", [player] call comspec_atak_native_fnc_unitCallsign],
            ["prio", _m getOrDefault ["prio", "URGENT"]], ["t1", ["mT1", "1"] call _n], ["t2", ["mT2", "0"] call _n], ["t3", ["mT3", "0"] call _n],
            ["litter", _m getOrDefault ["litter", 0]], ["amb", _m getOrDefault ["amb", 0]], ["equip", _m getOrDefault ["equip", "NONE"]],
            ["sec", _m getOrDefault ["sec", "NO_ENEMY"]], ["mark", format ["%1 %2", _m getOrDefault ["mark", "SMOKE"], _m getOrDefault ["col", "GREEN"]]],
            ["pos", _pos], ["grid", [_pos, 8] call comspec_atak_native_fnc_gridRef], ["rem", _m getOrDefault ["mRem", ""]],
            ["status", 0], ["by", ""], ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString]
        ];
        [{ params ["_r", "_side"]; ["comspec_atak_native_medevac", [_r, _side]] call CBA_fnc_globalEvent; }, [_r, str side group player], "MEDEVAC", 2] call comspec_atak_native_fnc_netSend;
        ["SUCCESS", "MEDEVAC envoyé au camp · suivi dans l'onglet MEDEVAC", 5, 50] call comspec_atak_native_fnc_notify;
        // Avec Overwatch : aussi au poste web.
        if (isNil "comspec_overwatch_connect_fnc_requestMEDEVAC") exitWith {};
        private _args = [_m getOrDefault ["prio", "URGENT"], ["mT1", "1"] call _n, ["mT2", "0"] call _n, ["mT3", "0"] call _n,
            _m getOrDefault ["sec", "NO_ENEMY"], _m getOrDefault ["mark", "SMOKE"], _m getOrDefault ["col", "GREEN"], _pos, _m getOrDefault ["mRem", ""], _grid];
        _args spawn {
            private _ok = _this call comspec_overwatch_connect_fnc_requestMEDEVAC;
            if (_ok isEqualType true && {_ok}) then { ["SUCCESS", "MEDEVAC demandé · LZ marquée", 5, 50] call comspec_atak_native_fnc_notify; };
        };
    };
    case "locate": {
        if (_v isEqualTo "") exitWith { ["WARNING", "Pas de grille pour cette alerte", 3, 20] call comspec_atak_native_fnc_notify; };
        private _p = [_v] call BIS_fnc_gridToPos;
        private _pos = _p param [0, []];
        if ((count _pos) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.03] call comspec_atak_native_fnc_mapCenter; }, [_pos select 0, _pos select 1]] call CBA_fnc_execNextFrame;
    };
    case "triage": {
        [_v, _id] spawn {
            params ["_v", "_id"];
            [_v, _id] call comspec_overwatch_connect_fnc_medicalTriage;
            [] call comspec_overwatch_connect_fnc_pollMedicalAlerts;
            [{ ["MEDICAL"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
    };
};
true
