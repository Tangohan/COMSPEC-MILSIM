/*
    App Musique : boucle du son (toutes les 0,5 s, et après chaque action).
    1. Ma piste : fin, erreur, recalage de l'heure de départ sur la position réelle (téléchargement d'une URL).
    2. Haut-parleur : ma piste publiée sur mon unité (COMSPEC_ATAK_Spk = [kind, ref, titre, départ, volume, jeton]).
    3. Ce que j'entends : ma propre musique, sinon le haut-parleur le plus fort autour de moi (distance, véhicule),
       calé sur l'heure de départ du propriétaire pour que tout le monde entende le même passage.
    Moteurs : DLL (fichiers, URL : lecteur Windows) ou Arma (pistes CfgMusic : playMusic + fadeMusic).
*/
if ((missionNamespace getVariable ["COMSPEC_ATAK_MusicTickFrame", -1]) isEqualTo diag_frameNo) exitWith {};
missionNamespace setVariable ["COMSPEC_ATAK_MusicTickFrame", diag_frameNo];
private _m = [] call comspec_atak_native_fnc_musicState;
private _now = [time, serverTime] select isMultiplayer;
private _enabled = missionNamespace getVariable ["comspec_atak_native_music_enabled", true];
private _range = (missionNamespace getVariable ["comspec_atak_native_music_range", 30]) max 2;
private _cur = missionNamespace getVariable ["COMSPEC_ATAK_MusicNow", ["", "", -1]];
private _fail = missionNamespace getVariable ["COMSPEC_ATAK_MusicFail", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_MusicFail", _fail];
private _ownKey = {
    if ((_m get "kind") isEqualTo "" || {_m get "paused"}) exitWith { "" };
    format ["me|%1|%2|%3", _m get "kind", _m get "ref", _m getOrDefault ["tok", 0]]
};

// 1. Ma piste
if ((_m get "kind") isNotEqualTo "" && {!_enabled || {!alive player} || {([] call comspec_atak_native_fnc_canUse) isNotEqualTo ""}}) then {
    _m set ["kind", ""]; _m set ["status", ["idle", 0, 0, "", ""]];
};
private _key = call _ownKey;
if (_key isNotEqualTo "" && {(_cur select 0) isEqualTo _key}) then {
    if ((_cur select 1) isEqualTo "dll") then {
        private _st = [["MusicStatus"] call comspec_atak_native_fnc_extensionCall, "|"] call CBA_fnc_split;
        if ((_st param [0, ""]) isNotEqualTo "OK") exitWith {};
        _st params ["", ["_state", ""], ["_pos", "0"], ["_len", "0"], ["_title", ""], ["_err", ""]];
        _pos = parseNumber _pos; _len = parseNumber _len;
        _m set ["status", [_state, _pos, _len, _title, _err]];
        switch (_state) do {
            case "playing": {
                if (_len > 0) then { _m set ["len", _len / 1000]; };
                if (abs ((_now - _pos / 1000) - (_m get "start")) > 2) then { _m set ["start", _now - _pos / 1000]; };
                _m set ["errors", 0];
            };
            case "ended": { ["ended"] call comspec_atak_native_fnc_musicAction; };
            case "error": {
                ["WARNING", format ["Lecture impossible : %1", [_err, "format ou adresse non lisible"] select (_err isEqualTo "")], 5, 30] call comspec_atak_native_fnc_notify;
                _m set ["errors", (_m getOrDefault ["errors", 0]) + 1];
                if ((_m get "errors") >= 3) then { ["stop"] call comspec_atak_native_fnc_musicAction; } else { ["ended"] call comspec_atak_native_fnc_musicAction; };
            };
        };
    } else {
        private _len = _m get "len";
        _m set ["status", ["playing", (_now - (_m get "start")) * 1000, _len * 1000, _m get "title", ""]];
        if (_len > 0 && {(_now - (_m get "start")) >= _len + 0.5}) then { ["ended"] call comspec_atak_native_fnc_musicAction; };
    };
};
_key = call _ownKey;

// 2. Haut-parleur
private _pub = [];
if (_key isNotEqualTo "" && {_m get "speaker"} && {missionNamespace getVariable ["comspec_atak_native_music_speaker", true]}) then {
    _pub = [_m get "kind", _m get "ref", _m get "title", (round ((_m get "start") * 10)) / 10, _m get "vol", _m getOrDefault ["tok", 0]];
};
if (_pub isNotEqualTo (player getVariable ["COMSPEC_ATAK_Spk", []])) then { player setVariable ["COMSPEC_ATAK_Spk", _pub, true]; };

// 3. Ce que j'entends
private _tgt = [];
if (_key isNotEqualTo "") then {
    _tgt = [_key, _m get "kind", _m get "ref", _m get "start", _m get "vol"];
} else {
    if (_enabled && {alive player} && {_m get "hear"}) then {
        private _best = 0;
        private _veh = vehicle player;
        {
            private _s = _x getVariable ["COMSPEC_ATAK_Spk", []];
            if ((count _s) >= 6 && {alive _x}) then {
                private _d = _x distance player;
                private _g = if (_veh isNotEqualTo player && {vehicle _x isEqualTo _veh}) then { 1 } else {
                    private _k = (1 - _d / _range) max 0;
                    _k = _k * _k;
                    if ((_veh isNotEqualTo player) || {vehicle _x isNotEqualTo _x}) then { _k = _k * 0.35; };
                    _k
                };
                private _k2 = format ["%1|%2|%3|%4", getPlayerUID _x, _s select 0, _s select 1, _s select 5];
                if (_g > _best && {!(_k2 in _fail)}) then {
                    _best = _g;
                    _tgt = [_k2, _s select 0, _s select 1, _s select 3, (_s select 4) * _g * ((_m get "vol") / 100), name _x, _s select 2];
                };
            };
        } forEach (allPlayers - [player]);
        if (_best < 0.02) then { _tgt = []; };
    };
};
missionNamespace setVariable ["COMSPEC_ATAK_MusicHeard", [[], [_tgt select 5, _tgt select 6]] select ((count _tgt) > 5)];

// 4. Appliquer
private _stopCur = {
    switch (_cur select 1) do {
        case "dll": { ["MusicStop"] call comspec_atak_native_fnc_extensionCall; };
        case "arma": {
            playMusic "";
            0 fadeMusic (missionNamespace getVariable ["COMSPEC_ATAK_MusicPrevVol", 1]);
        };
    };
};
if ((_tgt param [0, ""]) isNotEqualTo (_cur select 0)) then {
    call _stopCur;
    _cur = ["", "", -1];
    if ((count _tgt) > 0) then {
        _tgt params ["_k", "_kind", "_ref", "_start", "_vol"];
        private _off = (_now - _start) max 0;
        if (_kind isEqualTo "srv") then {
            if ((missionNamespace getVariable ["COMSPEC_ATAK_MusicNowEngine", ""]) isNotEqualTo "arma") then { missionNamespace setVariable ["COMSPEC_ATAK_MusicPrevVol", musicVolume]; };
            playMusic [_ref, _off];
            0 fadeMusic (_vol / 100);
            _cur = [_k, "arma", _vol];
        } else {
            private _res = ["MusicPlay", [_kind, _ref, str (round (_off * 1000)), str (round _vol)]] call comspec_atak_native_fnc_extensionCall;
            if ((_res find "OK|") isEqualTo 0) then {
                _cur = [_k, "dll", _vol];
            } else {
                _fail set [_k, true];
                if (_k isEqualTo _key) then {
                    private _why = if (_res isEqualTo "") then { "lecteur audio indisponible (DLL du mod à mettre à jour)" } else { (_res splitString "|") param [1, _res] };
                    ["WARNING", format ["Lecture impossible : %1", _why], 5, 30] call comspec_atak_native_fnc_notify;
                    _m set ["errors", (_m getOrDefault ["errors", 0]) + 1];
                    _m set ["status", ["error", 0, 0, _m get "title", _why]];
                    if (_res isEqualTo "" || {(_m get "errors") >= 3}) then { _m set ["kind", ""]; } else { _m set ["skip", true]; };
                };
            };
        };
    };
    missionNamespace setVariable ["COMSPEC_ATAK_MusicNowEngine", _cur select 1];
} else {
    if ((count _tgt) > 0 && {abs ((_tgt select 4) - (_cur select 2)) >= 2}) then {
        private _vol = _tgt select 4;
        if ((_cur select 1) isEqualTo "arma") then { 0.5 fadeMusic (_vol / 100); } else { ["MusicVolume", [str (round _vol)]] call comspec_atak_native_fnc_extensionCall; };
        _cur set [2, _vol];
    };
};
missionNamespace setVariable ["COMSPEC_ATAK_MusicNow", _cur];
// Piste illisible : on passe à la suivante au prochain passage (pas de récursion).
if (_m getOrDefault ["skip", false]) then { _m set ["skip", false]; [{ ["ended"] call comspec_atak_native_fnc_musicAction; }] call CBA_fnc_execNextFrame; };
