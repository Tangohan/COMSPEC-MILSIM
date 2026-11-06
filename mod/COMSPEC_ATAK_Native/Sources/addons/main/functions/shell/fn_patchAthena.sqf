/* Source ATHENA additive. Téléchargement/aperçu != montage d'une texture 3D.
   Ligne distante : [id, variante, révision, hash PAA, hash PNG, classe exacte, largeur, hauteur, nom]. */
params [["_act", "ensure"], ["_arg", ""]];
private _fallback = "\z\comspec_atak_native\addons\main\data\patch_comspec.paa";
private _rows = missionNamespace getVariable ["COMSPEC_ATAK_AthenaPatches", []];
private _key = { params ["_r"]; "athena:" + (_r select 0) + ":" + (_r select 1) };
private _row = {
    params ["_value"];
    private _i = _rows findIf { ([_x] call _key) isEqualTo _value };
    if (_i >= 0) exitWith { _rows select _i };
    private _saved = profileNamespace getVariable ["COMSPEC_ATAK_AthenaSelected", []];
    if ((count _saved) >= 9 && {([_saved] call _key) isEqualTo _value}) then { _saved } else { [] }
};
if (_act isEqualTo "row") exitWith { [_arg] call _row };
if (_act isEqualTo "texture") exitWith {
    private _r = [_arg] call _row;
    if (_r isEqualTo []) exitWith { [_fallback, false] };
    private _cls = _r select 5;
    private _cfg = missionConfigFile >> "CfgAthenaPatches" >> _cls;
    if !(isClass _cfg) then { _cfg = configFile >> "CfgAthenaPatches" >> _cls; };
    private _tex = getText (_cfg >> "texture");
    private _valid = isClass _cfg && {getText (_cfg >> "hash") isEqualTo (_r select 3)} && {getText (_cfg >> "projectId") isEqualTo (_r select 0)};
    // Seulement un chemin déclaré dans une config montée, jamais le cache téléchargé.
    if (!_valid || {_tex isEqualTo ""} || {((getTextureInfo _tex) param [0, -1]) <= 0}) exitWith { [_fallback, false] };
    [_tex, (parseNumber (_r select 6)) isEqualTo (parseNumber (_r select 7))]
};
if (_act isEqualTo "preview") exitWith {
    private _r = [_arg] call _row;
    if (_r isEqualTo []) exitWith { "" };
    private _cached = (uiNamespace getVariable ["COMSPEC_ATAK_AthenaPreviewPaths", createHashMap]) getOrDefault [_r select 4, ""];
    if (_cached isNotEqualTo "") exitWith { _cached };
    ""
};
if (_act isEqualTo "previews") exitWith {
    [_arg] spawn {
        params ["_items"];
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_AthenaPreviewData", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_AthenaPreviewData", _cache];
        {
            _x params ["_r", "_ctrl"];
            if (!isNull _ctrl && {count _r >= 9}) then {
                private _hash = _r select 4;
                private _hit = _cache getOrDefault [_hash, []];
                if (_hit isEqualTo []) then {
                    private _res = [(["COMSPECExtension", "AthenaPatches", ["download", _r select 0, _r select 2, _r select 1, "png", _hash]] call comspec_atak_native_fnc_extAwait)] call comspec_overwatch_connect_fnc_extResult;
                    if (_res isEqualType "" && {(_res select [0,3]) isEqualTo "OK|"}) then {
                        private _data = ["local:" + (_res select [3]), "patch_" + (_hash select [0,24])] call comspec_atak_native_fnc_slideWebData;
                        if ((_data select 2) isEqualTo "") then {
                            _hit = _data select [0,2];
                            if (count _cache >= 16) then { _cache deleteAt ((keys _cache) select 0); };
                            _cache set [_hash, _hit];
                        };
                    };
                };
                if (!isNull _ctrl && {count _hit >= 2}) then { [_ctrl, _hit select 0, _hit select 1] call comspec_atak_native_fnc_slideWebShow; };
            };
        } forEach _items;
    };
    true
};
if (_act isEqualTo "download") exitWith {
    private _r = [_arg] call _row;
    if (_r isEqualTo []) exitWith { false };
    [_r] spawn {
        params ["_r"];
        private _res = [(["COMSPECExtension", "AthenaPatches", ["download", _r select 0, _r select 2, _r select 1, "paa", _r select 3]] call comspec_atak_native_fnc_extAwait)] call comspec_overwatch_connect_fnc_extResult;
        private _ok = (_res select [0, 3]) isEqualTo "OK|";
        [["Synchronisation impossible. Réessayez depuis le catalogue.", "PAA vérifié en cache. Application 3D : addon/mission chargé requis."] select _ok, 5] call comspec_atak_native_fnc_toast;
        ["INFO", "PATCH", ["Échec du téléchargement ATHENA", "PAA ATHENA téléchargé et vérifié"] select _ok] call comspec_atak_native_fnc_log;
    };
    true
};
if (_act isEqualTo "load" || {_act isEqualTo "ensure"}) exitWith {
    if (missionNamespace getVariable ["COMSPEC_ATAK_AthenaPatchBusy", false]) exitWith {};
    if (_act isEqualTo "ensure" && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_AthenaPatchAt", -1e9]) < 60}) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_AthenaPatchBusy", true];
    missionNamespace setVariable ["COMSPEC_ATAK_AthenaPatchAt", diag_tickTime];
    [] spawn {
        private _all = []; private _offset = 0; private _err = "";
        for "_page" from 0 to 49 do {
            private _raw = [(["COMSPECExtension", "AthenaPatches", ["list", _offset toFixed 0]] call comspec_atak_native_fnc_extAwait)] call comspec_overwatch_connect_fnc_extResult;
            if !(_raw isEqualType "") then { _raw = ""; };
            if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { _err = "Catalogue ATHENA indisponible. Session liée et COMSPEC Link mis à jour requis."; };
            private _next = -1;
            {
                private _f = _x splitString toString [9];
                if ((_f param [0, ""]) isEqualTo "M") then { _next = parseNumber (_f param [1, "-1"]); };
                if ((_f param [0, ""]) isEqualTo "P" && {count _f >= 10}) then { _all pushBack (_f select [1,9]); };
            } forEach ((_raw select [3]) splitString toString [10]);
            if (_next < 0) exitWith {};
            _offset = _next;
        };
        if (_err isEqualTo "") then { missionNamespace setVariable ["COMSPEC_ATAK_AthenaPatches", _all]; } else {
            missionNamespace setVariable ["COMSPEC_ATAK_AthenaPatches", []];
            [_err, 4] call comspec_atak_native_fnc_toast;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_AthenaPatchBusy", false];
        [{ ["grid"] call comspec_atak_native_fnc_pagePatches; }] call CBA_fnc_execNextFrame;
    };
};
false
