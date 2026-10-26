/*
    App Extraction (câble DL-7) : actions côté joueur.
    État de la liaison : uiNamespace COMSPEC_ATAK_DataLink (HashMap) : obj, phase, t0, dur, sel, queue, got, msg.
    Phases : PLUG (câble branché) → HANDSHAKE (négociation) → AUTH (mot de passe) | LOCKED (verrouillé) | WIPED (vide)
             → BROWSE (fichiers) → DL (transfert) → DONE ; CRACK (forçage) depuis AUTH ou LOCKED.
    Bibliothèque : missionNamespace COMSPEC_ATAK_DataLib, entrées [support, nom, type, contenu, taille Ko, heure, carroyage, de].
    Params : [action, argument]
      "can" objet        : l'action « Brancher l'ATAK » est-elle possible sur cet objet ?
      "connect" objet    "disconnect"    "login"    "crack"    "toggle" n    "all"    "download"    "tick"
      "view" n (-1 ferme)    "mark" n    "play" n    "share" (n ou -1 pour tout)    "delete" n    "clear"
      "nodes"            : supports à moins de 25 m, [[objet, distance]...] du plus proche au plus loin
*/
params [["_act", ""], ["_arg", objNull]];
private _st = uiNamespace getVariable ["COMSPEC_ATAK_DataLink", createHashMap];
private _obj = _st getOrDefault ["obj", objNull];
private _phase = _st getOrDefault ["phase", ""];
private _cfgOf = { createHashMapFromArray ((_this select 0) getVariable ["COMSPEC_ATAK_Data", []]) };
private _len = missionNamespace getVariable ["comspec_atak_native_data_length", 3];
private _speed = (missionNamespace getVariable ["comspec_atak_native_data_speed", 1]) max 0.1;
private _render = {
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DATALINK" && {!isNull ([] call comspec_atak_native_fnc_display)}) then {
        [{ ["DATALINK"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
};
private _end = {
    params ["_msg", ["_type", "WARNING"]];
    uiNamespace setVariable ["COMSPEC_ATAK_DataLink", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_Secret_dlPass", ""];
    [] call comspec_atak_native_fnc_dataOverlay;
    if (_msg isNotEqualTo "") then { [_type, _msg, 4, 30] call comspec_atak_native_fnc_notify; };
    call _render;
};
private _lib = missionNamespace getVariable ["COMSPEC_ATAK_DataLib", []];
private _libSet = { missionNamespace setVariable ["COMSPEC_ATAK_DataLib", _lib]; };

switch (_act) do {
    case "can": {
        private _o = _arg;
        !isNull _o && {!isNil { _o getVariable "COMSPEC_ATAK_Data" }} && {(player distance _o) <= _len + 0.5} && {_phase isEqualTo ""} && {alive player} && {isNull objectParent player}
    };
    case "nodes": {
        private _out = [];
        { if (!isNull _x && {!isNil { _x getVariable "COMSPEC_ATAK_Data" }}) then { private _dd = player distance _x; if (_dd < 25) then { _out pushBack [_dd, _x]; }; }; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_DataNodes", []]);
        _out sort true;
        _out apply { [_x select 1, _x select 0] }
    };
    case "connect": {
        private _o = _arg;
        if (isNull _o || {isNil { _o getVariable "COMSPEC_ATAK_Data" }}) exitWith { false };
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { ["ERROR", "Il faut un téléphone ATAK pour extraire les données", 4, 30] call comspec_atak_native_fnc_notify; false };
        if ((missionNamespace getVariable ["comspec_atak_native_data_cable", false]) && {!("COMSPEC_ATAK_DataCable" in (items player))}) exitWith {
            ["ERROR", "Câble DL-7 absent de votre équipement", 4, 30] call comspec_atak_native_fnc_notify;
            false
        };
        if ((player distance _o) > _len + 0.5) exitWith { ["WARNING", format ["Trop loin : le câble fait %1 m", _len], 3, 20] call comspec_atak_native_fnc_notify; false };
        private _cfg = [_o] call _cfgOf;
        uiNamespace setVariable ["COMSPEC_ATAK_Secret_dlPass", ""];
        uiNamespace setVariable ["COMSPEC_ATAK_DataView", -1];
        uiNamespace setVariable ["COMSPEC_ATAK_DataLink", createHashMapFromArray [
            ["obj", _o], ["phase", "PLUG"], ["t0", diag_tickTime], ["dur", 1.2], ["port", _cfg getOrDefault ["port", "usb"]],
            ["sel", (_cfg getOrDefault ["files", []]) apply { true }], ["queue", []], ["got", 0], ["msg", ""], ["fresh", true]
        ]];
        playSound "ClickSoft";
        ["connect", _o, player, []] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2];
        // Téléphone en main sur l'app Extraction.
        if (isNull ([] call comspec_atak_native_fnc_display) || {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then {
            [true] call comspec_atak_native_fnc_open;
        };
        [{ ["DATALINK"] call comspec_atak_native_fnc_navigate; [] call comspec_atak_native_fnc_dataOverlay; }] call CBA_fnc_execNextFrame;
        true
    };
    case "disconnect": {
        if (_phase isEqualTo "DL" && {!isNull _obj}) then { ["rip", _obj, player, []] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2]; };
        playSound "ClickSoft";
        [["", "Câble débranché : transfert interrompu"] select (_phase isEqualTo "DL"), "INFO"] call _end;
    };
    case "login": {
        if (_phase isNotEqualTo "AUTH") exitWith {};
        private _pw = uiNamespace getVariable ["COMSPEC_ATAK_Secret_dlPass", ""];
        uiNamespace setVariable ["COMSPEC_ATAK_Secret_dlPass", ""];
        if (_pw isEqualTo "") exitWith { _st set ["msg", "Saisissez le mot de passe"]; call _render; };
        private _cfg = [_obj] call _cfgOf;
        if ((hashValue _pw) isEqualTo (_cfg getOrDefault ["pwHash", ""])) then {
            ["unlock", _obj, player, []] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2];
            _st set ["phase", "BROWSE"];
            _st set ["msg", ""];
            playSound "ClickSoft";
        } else {
            ["fail", _obj, player, []] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2];
            _st set ["msg", "Mot de passe incorrect"];
            _st set ["failAt", diag_tickTime];
            playSound "addItemFailed";
        };
        [] call comspec_atak_native_fnc_dataOverlay;
        call _render;
    };
    case "crack": {
        if !(_phase in ["AUTH", "LOCKED"]) exitWith {};
        private _cfg = [_obj] call _cfgOf;
        if !(_cfg getOrDefault ["crack", true]) exitWith {};
        _st set ["phase", "CRACK"];
        _st set ["t0", diag_tickTime];
        _st set ["dur", (_cfg getOrDefault ["crackTime", 90]) / _speed];
        _st set ["msg", ""];
        call _render;
    };
    case "toggle": {
        private _sel = _st getOrDefault ["sel", []];
        if (_arg isEqualType 0 && {_arg >= 0} && {_arg < count _sel}) then { _sel set [_arg, !(_sel select _arg)]; };
        call _render;
    };
    case "all": {
        private _sel = _st getOrDefault ["sel", []];
        private _on = (_sel findIf { !_x }) >= 0;
        _st set ["sel", _sel apply { _on }];
        call _render;
    };
    case "download": {
        if (_phase isNotEqualTo "BROWSE") exitWith {};
        private _cfg = [_obj] call _cfgOf;
        private _files = _cfg getOrDefault ["files", []];
        private _sel = _st getOrDefault ["sel", []];
        private _queue = [];
        { if (_sel param [_forEachIndex, true]) then { _queue pushBack _forEachIndex; }; } forEach _files;
        if (_queue isEqualTo []) exitWith { _st set ["msg", "Aucun fichier sélectionné"]; call _render; };
        private _total = 0;
        { _total = _total + ((_x select 3) max 1); } forEach _files;
        private _part = 0;
        { _part = _part + (((_files select _x) select 3) max 1); } forEach _queue;
        private _portK = switch (_st getOrDefault ["port", "usb"]) do { case "rj45": { 0.8 }; case "serie": { 2.5 }; default { 1 }; };
        _st set ["phase", "DL"];
        _st set ["queue", _queue];
        _st set ["got", 0];
        _st set ["bytes", _part];
        _st set ["full", (count _queue) isEqualTo (count _files)];
        _st set ["t0", diag_tickTime];
        _st set ["dur", (((_cfg getOrDefault ["duration", 45]) * _part / (_total max 1)) * _portK / _speed) max 2];
        _st set ["msg", ""];
        call _render;
    };
    case "tick": {
        if (_phase isEqualTo "") exitWith {};
        private _why = "";
        if (isNull _obj || {!alive _obj && {!(_obj isKindOf "CAManBase")}}) then { _why = "Support détruit : liaison perdue"; };
        if (_why isEqualTo "" && {(player distance _obj) > _len + 0.5}) then { _why = "Câble arraché : vous vous êtes éloigné du support"; };
        if (_why isEqualTo "" && {!alive player || {!isNull objectParent player}}) then { _why = "Câble débranché"; };
        if (_why isEqualTo "" && {!([player] call comspec_atak_native_fnc_hasDevice)}) then { _why = "ATAK retiré : liaison perdue"; };
        if (_why isNotEqualTo "") exitWith {
            if (_phase isEqualTo "DL") then { ["rip", _obj, player, []] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2]; };
            [_why, "WARNING"] call _end;
            playSound "addItemFailed";
        };
        private _cfg = [_obj] call _cfgOf;
        private _now = diag_tickTime;
        private _p = (_now - (_st getOrDefault ["t0", _now])) / ((_st getOrDefault ["dur", 1]) max 0.1);
        private _wiped = _obj getVariable ["COMSPEC_ATAK_DataWiped", false];
        private _locked = _obj getVariable ["COMSPEC_ATAK_DataLocked", false];
        private _next = "";
        switch (_phase) do {
            case "PLUG": {
                if (_p >= 1) then {
                    _st set ["phase", "HANDSHAKE"];
                    _st set ["t0", _now];
                    _st set ["dur", switch (_st getOrDefault ["port", "usb"]) do { case "rj45": { 3.5 }; case "serie": { 6 }; default { 2.5 }; }];
                };
            };
            case "HANDSHAKE": {
                if (_p >= 1) then {
                    _next = switch (true) do {
                        case _wiped: { "WIPED" };
                        case _locked: { "LOCKED" };
                        case ((_cfg getOrDefault ["pwHash", ""]) isNotEqualTo ""): { "AUTH" };
                        default { "BROWSE" };
                    };
                };
            };
            case "AUTH": { if (_wiped) then { _next = "WIPED"; } else { if (_locked) then { _next = "LOCKED"; }; }; };
            case "BROWSE": { if (_wiped) then { _next = "WIPED"; }; };
            case "CRACK": {
                if (_wiped) exitWith { _next = "WIPED"; };
                if (_p >= 1) then {
                    ["cracked", _obj, player, []] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2];
                    _next = "BROWSE";
                    ["SUCCESS", format ["%1 : accès forcé", _cfg getOrDefault ["name", "Support"]], 3, 20] call comspec_atak_native_fnc_notify;
                };
            };
            case "DL": {
                if (_wiped) exitWith { _next = "WIPED"; };
                // Fichiers enregistrés au fil du transfert : un câble arraché garde ceux déjà reçus.
                private _files = _cfg getOrDefault ["files", []];
                private _queue = _st getOrDefault ["queue", []];
                private _got = _st getOrDefault ["got", 0];
                private _done = (_p min 1) * (_st getOrDefault ["bytes", 1]);
                private _cum = 0;
                { _cum = _cum + (((_files param [_x, ["", "", "", 1]]) select 3) max 1); if (_forEachIndex >= _got && {_cum <= _done + 0.001}) then {
                    private _f = _files param [_x, []];
                    if ((count _f) >= 4) then {
                        private _src = _cfg getOrDefault ["name", "Support"];
                        private _i = _lib findIf { (_x select 0) isEqualTo _src && {(_x select 1) isEqualTo (_f select 0)} };
                        private _e = [_src, _f select 0, _f select 1, _f select 2, _f select 3, [dayTime, "HH:MM"] call BIS_fnc_timeToString, mapGridPosition _obj, ""];
                        if (_i >= 0) then { _lib set [_i, _e]; } else { _lib pushBack _e; };
                    };
                    _got = _forEachIndex + 1;
                }; } forEach _queue;
                call _libSet;
                _st set ["got", _got];
                if (_p >= 1) then {
                    ["done", _obj, player, [_queue apply { (_files param [_x, ["?"]]) select 0 }, _st getOrDefault ["full", false]]] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2];
                    _next = "DONE";
                    playSound "ClickSoft";
                    ["SUCCESS", format ["%1 fichier(s) récupéré(s) de %2", count _queue, _cfg getOrDefault ["name", "Support"]], 4, 30] call comspec_atak_native_fnc_notify;
                };
            };
            default {};
        };
        if (_next isNotEqualTo "") then {
            _st set ["phase", _next];
            _st set ["t0", _now];
            if (_next isEqualTo "BROWSE") then { _st set ["sel", (_cfg getOrDefault ["files", []]) apply { true }]; };
            call _render;
        } else {
            // Écrans animés (barres, journal) redessinés deux fois par seconde.
            if (_phase in ["PLUG", "HANDSHAKE", "CRACK", "DL"] && {_now - (_st getOrDefault ["drawn", 0]) >= 0.5}) then {
                _st set ["drawn", _now];
                call _render;
            };
        };
        [] call comspec_atak_native_fnc_dataOverlay;
    };
    case "view": { uiNamespace setVariable ["COMSPEC_ATAK_DataView", _arg]; call _render; };
    case "mark": {
        private _e = _lib param [_arg, []];
        if ((count _e) < 4) exitWith {};
        // « x, y » en mètres, sinon carroyage (045123, espaces ignorés).
        private _txt = trim (((_e select 3) splitString "[]") joinString "");
        private _pos = if ((_txt find ",") >= 0) then {
            private _xy = (_txt splitString ", ") apply { parseNumber _x };
            [_xy param [0, 0], _xy param [1, 0]]
        } else {
            // Carroyage en tête (« 045123 libellé » des marqueurs d'un téléphone capturé) : seuls les blocs de chiffres
            // du début, sinon le libellé fausse BIS_fnc_gridToPos. Centre de la case calculé à la main (vecteurs 2D).
            private _grid = "";
            {
                if ((_x regexReplace ["[0-9]", ""]) isNotEqualTo "") exitWith {};
                _grid = _grid + _x;
            } forEach (_txt splitString " ");
            if (_grid isEqualTo "") then { [] } else {
                ([_grid] call BIS_fnc_gridToPos) params [["_gp", [0, 0]], ["_gs", [0, 0]]];
                [(_gp select 0) + (_gs param [0, 0]) / 2, (_gp select 1) + (_gs param [1, 0]) / 2]
            }
        };
        if ((count _pos) < 2) exitWith { ["WARNING", "Coordonnées illisibles", 3, 20] call comspec_atak_native_fnc_notify; };
        private _n = format ["COMSPEC_DATA_%1_%2", _arg, floor diag_tickTime];
        private _m = createMarkerLocal [_n, _pos];
        _m setMarkerTypeLocal "mil_objective";
        _m setMarkerColorLocal "ColorOrange";
        _m setMarkerTextLocal (_e select 1); // local : setMarkerText rendait le marqueur visible de tous
        ["SUCCESS", format ["%1 marqué sur la carte (%2)", _e select 1, mapGridPosition _pos], 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "play": {
        private _e = _lib param [_arg, []];
        if ((count _e) < 4) exitWith {};
        private _s = trim (_e select 3);
        switch (true) do {
            case (isClass (missionConfigFile >> "CfgSounds" >> _s) || {isClass (configFile >> "CfgSounds" >> _s)}): { playSound _s; };
            case (fileExists (getMissionPath _s)): { playSoundUI [getMissionPath _s]; };
            case (fileExists _s): { playSoundUI [_s]; };
            default { ["WARNING", "Fichier audio illisible", 3, 20] call comspec_atak_native_fnc_notify; };
        };
    };
    case "share": {
        private _send = if (_arg isEqualType 0 && {_arg >= 0}) then { [_lib param [_arg, []]] select { (count _x) >= 4 } } else { +_lib };
        if (_send isEqualTo []) exitWith {};
        private _to = (units group player) select { isPlayer _x && {_x isNotEqualTo player} };
        if (_to isEqualTo []) exitWith { ["WARNING", "Personne d'autre dans le groupe", 3, 20] call comspec_atak_native_fnc_notify; };
        { ["comspec_atak_native_dataShare", [name player, _send], _x] call CBA_fnc_targetEvent; } forEach _to;
        ["SUCCESS", format ["%1 fichier(s) partagé(s) avec le groupe (%2)", count _send, count _to], 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "delete": {
        if (_arg isEqualType 0 && {_arg >= 0} && {_arg < count _lib}) then { _lib deleteAt _arg; call _libSet; };
        uiNamespace setVariable ["COMSPEC_ATAK_DataView", -1];
        call _render;
    };
    case "clear": { _lib = []; call _libSet; uiNamespace setVariable ["COMSPEC_ATAK_DataView", -1]; call _render; };
};
