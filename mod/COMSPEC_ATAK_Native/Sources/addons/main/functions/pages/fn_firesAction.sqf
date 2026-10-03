/*
    Actions de l'app Feux. Params : [action, argument]
    tab, mode, sheaf, pick, clear, prefill9, prefill5, send9, send5, gunHere, compute, shot, eom
*/
params [["_action", ""], ["_arg", ""]];
[] call comspec_atak_native_fnc_firesSave;
private _f = [] call comspec_atak_native_fnc_firesState;
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _render = { [{ ["FIRES"] call comspec_atak_native_fnc_pageRender; (call comspec_atak_native_fnc_firesState) set ["clearing", false]; }] call CBA_fnc_execNextFrame; };
private _say = { params ["_t", ["_warn", false]]; _f set ["hint", [_t, _warn]]; };
private _time = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
private _me = { private _c = if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then { [] call comspec_overwatch_connect_fnc_getCallsign } else { "" }; if (_c isEqualTo "") then { groupId group player } else { _c } };
// Grille saisie → position monde (centre de la case).
private _gridPos = {
    params ["_g"];
    _g = (_g splitString " ") joinString "";
    if ((count _g) < 6 || {((count _g) mod 2) isEqualTo 1}) exitWith { [] };
    private _r = [_g, true] call BIS_fnc_gridToPos;
    if !(_r isEqualType [] && {(count _r) >= 2}) exitWith { [] };
    _r params ["_p", "_s"];
    [(_p select 0) + (_s select 0) / 2, (_p select 1) + (_s select 1) / 2, 0]
};
// Envoi d'un ordre (vu sur le web et par les joueurs visés) quand Overwatch est là ; sinon chat local.
private _order = {
    params ["_type", "_target", "_body", ["_prio", "URGENT"], ["_targetType", ""]];
    if (_bridge) then { [_type, _target, _body, _prio, "", _targetType] call comspec_overwatch_connect_fnc_issueOrder; } else { [_body] call comspec_atak_native_fnc_chatSend; };
};

switch (_action) do {
    case "tab": { _f set ["tab", _arg]; _f set ["hint", ["", false]]; };
    case "mode": { _f set ["mode", _arg]; };
    case "sheaf": { _f set ["sheaf", !(_f getOrDefault ["sheaf", false])]; };
    case "clear": {
        { _f deleteAt _x; } forEach ([["n1","n2","n3","n4","n5","n6","n7","n8","n9","nr"], ["f1","f2","f3","f4","f5"]] select (_arg isEqualTo "five"));
        _f set ["clearing", true];
        ["Formulaire vidé."] call _say;
    };
    case "pick": {
        // Le prochain clic sur la carte devient la cible, puis retour à l'app Feux.
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["firePick", _arg];
        ["Cliquez la cible sur la carte."] call _say;
        [{ ["MAP"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        ["INFO", "Cliquez la cible sur la carte", 3, 40] call comspec_atak_native_fnc_notify;
    };
    case "picked": {
        // Appelé par la carte avec la position cliquée.
        _f set ["target", _arg];
        private _g = [_arg, 8] call comspec_atak_native_fnc_gridRef;
        { _f set [_x, _g]; } forEach ["n6", "f3", "tgtGrid"];
        _f set ["mode", "GRID"];
        _f deleteAt "solution";
        [format ["Cible : %1", _g]] call _say;
        [{ ["FIRES"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    };
    case "prefill9": {
        private _t = _f getOrDefault ["target", []];
        if ((count _t) < 2) exitWith { ["Pointez d'abord la cible (CIBLE SUR LA CARTE).", true] call _say; };
        _f set ["n4", str round (getTerrainHeightASL _t)];
        _f set ["n6", [_t, 8] call comspec_atak_native_fnc_gridRef];
        private _code = player getVariable ["ace_laser_code", -1];
        if (_code isEqualType 0 && {_code > 0}) then { _f set ["n7", format ["Laser %1", _code]]; };
        private _dirs = ["N", "NE", "E", "SE", "S", "SO", "O", "NO"];
        _f set ["n8", format ["%1 %2 m (%3)", _dirs select ((round ((_t getDir player) / 45)) mod 8), round (player distance2D _t), [] call _me]];
        ["Lignes 4, 6, 7 et 8 remplies."] call _say;
    };
    case "prefill5": {
        private _t = _f getOrDefault ["target", []];
        _f set ["f1", [] call _me];
        _f set ["f2", format ["%1 · %2", [player, 8] call comspec_atak_native_fnc_gridRef, "IR strobe"]];
        if ((count _t) >= 2) then { _f set ["f3", [_t, 8] call comspec_atak_native_fnc_gridRef]; };
        ["Lignes 1 à 3 remplies."] call _say;
    };
    case "send9": {
        private _lines = ["n1","n2","n3","n4","n5","n6","n7","n8","n9"] apply { trim (_f getOrDefault [_x, ""]) };
        if ((_lines select 5) isEqualTo "" || {(_lines select 4) isEqualTo ""}) exitWith { ["Lignes 5 (description) et 6 (position) obligatoires.", true] call _say; };
        private _rem = trim (_f getOrDefault ["nr", ""]);
        private _esc = { params ["_s"]; [[[_s, "\", "\\"] call CBA_fnc_replace, """", "\"""] call CBA_fnc_replace, toString [10], " "] call CBA_fnc_replace };
        private _mapId = missionNamespace getVariable ["COMSPEC_MapId", missionNamespace getVariable ["comspec_overwatch_map_id", 1]];
        private _json = format ['{"mapId":%1,"author":"%2","jtac":"%2","mission_kind":"CAS","remarks":"%3","lines":{%4}}', _mapId, [[] call _me] call _esc, [_rem] call _esc,
            (_lines apply { format ['"line%1":"%2"', _forEachIndex + 1, [_x] call _esc] }) joinString ","];
        // DLL à jour : vrai 9-line sur Athena (pilotes et web). Sinon : ordre CAS lisible.
        private _ok = false;
        if (_bridge) then {
            private _r = "COMSPECExtension" callExtension ["SubmitNineLine", [_json]];
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            _ok = (toUpper _r) find "OK" >= 0 && {(toUpper _r) find "ERR" < 0};
        };
        private _text = format ["9-LINE %1 | 1 %2 | 2 %3 | 3 %4 | 4 %5 | 5 %6 | 6 %7 | 7 %8 | 8 %9 | 9 %10%11", [] call _me,
            _lines select 0, _lines select 1, _lines select 2, _lines select 3, _lines select 4, _lines select 5, _lines select 6, _lines select 7, _lines select 8,
            ["", format [" | REM %1", _rem]] select (_rem isNotEqualTo "")];
        if (!_ok) then { ["CAS", "", _text, "URGENT", "all"] call _order; };
        private _log = _f getOrDefault ["log", []];
        _log pushBack format ["<t color='#5cc76b'>%1</t> 9-LINE · %2 · %3", [] call _time, _lines select 5, ["ordre CAS", "Athena"] select _ok];
        _f set ["log", _log];
        [["9-line transmis en ordre CAS (la DLL ne connaît pas encore SubmitNineLine).", "9-line transmis à Athena : les pilotes le reçoivent."] select _ok] call _say;
        ["SUCCESS", "9-LINE transmis", 3, 50] call comspec_atak_native_fnc_notify;
    };
    case "send5": {
        private _lines = ["f1","f2","f3","f4","f5"] apply { trim (_f getOrDefault [_x, ""]) };
        if ((_lines select 2) isEqualTo "") exitWith { ["Ligne 3 (position de la cible) obligatoire.", true] call _say; };
        ["CAS", "", format ["5-LINE | 1 %1 | 2 %2 | 3 %3 | 4 %4 | 5 %5", _lines select 0, _lines select 1, _lines select 2, _lines select 3, _lines select 4], "URGENT", "all"] call _order;
        private _log = _f getOrDefault ["log", []];
        _log pushBack format ["<t color='#5cc76b'>%1</t> 5-LINE · %2", [] call _time, _lines select 2];
        _f set ["log", _log];
        ["5-line transmis."] call _say;
        ["SUCCESS", "5-LINE transmis", 3, 50] call comspec_atak_native_fnc_notify;
    };
    case "gunHere": {
        _f set ["gun", "MAN"];
        _f set ["gunGrid", [player, 10] call comspec_atak_native_fnc_gridRef];
        ["Pièce placée à votre position."] call _say;
    };
    case "compute";
    case "shot": {
        // Pièce
        private _gunId = _f getOrDefault ["gun", "MAN"];
        private _gun = if (_gunId isEqualTo "MAN") then { objNull } else { objectFromNetId _gunId };
        private _gunPos = if (isNull _gun) then { [_f getOrDefault ["gunGrid", ""]] call _gridPos } else { getPosATL _gun };
        if ((count _gunPos) < 2) exitWith { ["Choisissez une pièce ou saisissez sa grille.", true] call _say; };
        // Cible
        private _tgt = if ((_f getOrDefault ["mode", "GRID"]) isEqualTo "POLAR") then {
            private _az = (parseNumber (_f getOrDefault ["az", "0"])) * 360 / 6400;
            player getPos [parseNumber (_f getOrDefault ["dist", "0"]), _az]
        } else { [_f getOrDefault ["tgtGrid", ""]] call _gridPos };
        if ((count _tgt) < 2) exitWith { ["Cible invalide : grille de 8 ou 10 chiffres, ou pointez-la sur la carte.", true] call _say; };
        _tgt = [_tgt select 0, _tgt select 1, 0];
        _f set ["target", _tgt];
        private _ammo = _f getOrDefault ["ammo", "HE"];
        private _mag = "";
        private _eta = -1;
        private _inRange = false;
        if (!isNull _gun) then {
            private _mags = getArtilleryAmmo [_gun];
            private _want = switch (_ammo) do { case "ILLUM": { ["flare", "illum"] }; case "SMOKE": { ["smoke", "wp"] }; default { ["he", "_he", "shells", "rnd"] }; };
            private _i = _mags findIf { private _m = toLower _x; (_want findIf { (_m find _x) >= 0 }) >= 0 };
            _mag = _mags param [[_i, 0] select (_i < 0), ""];
            if (_mag isNotEqualTo "") then {
                _inRange = _tgt inRangeOfArtillery [[_gun], _mag];
                _eta = _gun getArtilleryETA [_tgt, _mag];
            };
        };
        private _sol = createHashMapFromArray [
            ["gunName", if (isNull _gun) then { "Pièce manuelle" } else { _gun getVariable ["COMSPEC_ATAK_GunName", getText (configOf _gun >> "displayName")] }],
            ["dist", _gunPos distance2D _tgt], ["mils", (_gunPos getDir _tgt) * 6400 / 360], ["eta", _eta],
            ["inRange", if (isNull _gun) then { true } else { _inRange }], ["grid", [_tgt, 8] call comspec_atak_native_fnc_gridRef], ["mag", _mag]
        ];
        _f set ["solution", _sol];
        if (_action isEqualTo "compute") exitWith { ["Solution calculée."] call _say; };
        // TIR
        private _rounds = (round parseNumber (_f getOrDefault ["rounds", "3"])) max 1 min 12;
        private _sheaf = _f getOrDefault ["sheaf", false];
        private _summary = format ["APPEL DE FEU %1 | pièce %2 | cible %3 | %4 x%5%6 | az %7 mil | %8 m", [] call _me, _sol get "gunName", _sol get "grid", _ammo, _rounds, ["", " gerbe"] select _sheaf, round (_sol get "mils"), round (_sol get "dist")];
        private _how = "ordre";
        if (!isNull _gun && {!isNull gunner _gun}) then {
            if (isPlayer gunner _gun) then {
                // Pièce tenue par un joueur : la mission arrive sur son téléphone.
                ["comspec_atak_native_fireMission", [_summary, _tgt, [] call _me], gunner _gun] call CBA_fnc_targetEvent;
                _how = "téléphone du servant";
            } else {
                if (!(_sol get "inRange") || {_mag isEqualTo ""}) exitWith { _how = "hors portée"; };
                // Gerbe ouverte : un point d'impact par coup autour de la cible.
                private _shots = [_tgt];
                if (_sheaf) then { _shots = []; for "_i" from 1 to _rounds do { _shots pushBack (_tgt getPos [random 40, random 360]); }; };
                if (_sheaf) then {
                    [_gun, _shots, _mag] spawn { params ["_g", "_pts", "_m"]; { [_g, [_x, _m, 1]] remoteExec ["commandArtilleryFire", _g]; sleep 4; } forEach _pts; };
                } else {
                    [_gun, [_tgt, _mag, _rounds]] remoteExec ["doArtilleryFire", gunner _gun];
                };
                _how = "tir IA";
            };
        };
        if (_how isEqualTo "hors portée") exitWith { ["Cible hors de portée de la pièce (ou munition absente).", true] call _say; };
        ["CUSTOM_CFF", "", _summary, "URGENT", "all"] call _order;
        _f set ["active", true];
        private _log = _f getOrDefault ["log", []];
        _log pushBack format ["<t color='#e5483a'>%1</t> TIR · %2 · %3 x%4 · %5", [] call _time, _sol get "grid", _ammo, _rounds, _how];
        _f set ["log", _log];
        [format ["Tir demandé (%1).%2", _how, ["", format [" Impact dans %1 s.", round _eta]] select (_eta > 0)]] call _say;
        playSound "ClickSoft";
    };
    case "eom": {
        private _sol = _f getOrDefault ["solution", createHashMap];
        if !(_f getOrDefault ["active", false]) exitWith { ["Aucune mission en cours."] call _say; };
        ["CUSTOM_CFF", "", format ["FIN DE MISSION %1 | cible %2", [] call _me, _sol getOrDefault ["grid", ""]], "IMPORTANT", "all"] call _order;
        _f set ["active", false];
        private _log = _f getOrDefault ["log", []];
        _log pushBack format ["<t color='#8a9a93'>%1</t> FIN DE MISSION · %2", [] call _time, _sol getOrDefault ["grid", ""]];
        _f set ["log", _log];
        ["Fin de mission transmise."] call _say;
    };
};
if (_action isNotEqualTo "pick" && {_action isNotEqualTo "picked"}) then { [] call _render; };
true
