/*
    Données de l'IA RP (toute machine, Eden compris), lues une fois puis gardées :
      ["bank"]     -> HashMap intention -> ton -> [[fr, en]…]          data\iarp_bank.sqf
      ["lexicon"]  -> [[intention, poids, [motifs…]]…]                data\iarp_lexicon.sqf
      ["presets"]  -> HashMap presets / pools                          data\iarp_presets.sqf
      ["dict"]     -> dictionnaire de l'interprète tolérant (fn_iarpIntent), tiré du lexique une fois :
                      words (mots connus, jamais corrigés), skel (mot sans lettres doublées -> mot du lexique),
                      idx (« deux premières lettres + longueur » -> mots du lexique de 5 lettres et plus)
      ["reset"]    oublie les données lues (fichier de mission modifié pendant les essais)
    Extension sans toucher au mod : fichier de même nom à la racine de la mission (iarp_bank.sqf, iarp_lexicon.sqf,
    iarp_presets.sqf), ou variable missionNamespace comspec_sse_iarpExtra_bank / _lexicon / _presets posée par un script
    avant la première phrase. Ajouts seulement : les phrases et motifs du mod restent.
*/
params [["_what", "bank", [""]]];
if (_what isEqualTo "reset") exitWith {
    { missionNamespace setVariable ["comspec_sse_iarpData_" + _x, nil]; } forEach ["bank", "lexicon", "presets", "dict"];
    true
};
private _key = "comspec_sse_iarpData_" + _what;
private _have = missionNamespace getVariable [_key, 0];
if !(_have isEqualType 0) exitWith { _have };
// Dictionnaire de l'interprète : mots des motifs du lexique (5 lettres et plus) ; « _keep » : mots courants proches d'un
// mot du lexique, gardés tels quels (« ferme » n'est pas « femme », « plage » n'est pas « place »).
if (_what isEqualTo "dict") exitWith {
    private _skelOf = {
        private _o = [];
        { if (_o isEqualTo [] || {(_o select -1) isNotEqualTo _x}) then { _o pushBack _x; }; } forEach (toArray _this);
        toString _o
    };
    private _words = createHashMap;
    private _skel = createHashMap;
    private _idx = createHashMap;
    private _keep = [];
    {
        _x params [["_i", ""], "", ["_pats", []]];
        if (_i isEqualTo "_keep") then { _keep append _pats; } else {
            if ((_i select [0, 1]) isNotEqualTo "_") then {
                {
                    {
                        if ((count _x) >= 5 && {((toArray _x) findIf { _x < 97 || {_x > 122} }) < 0} && {!(_x in _words)}) then {
                            _words set [_x, true];
                            private _s = _x call _skelOf;
                            if !(_s in _skel) then { _skel set [_s, _x]; };
                            private _k = format ["%1%2", _x select [0, 2], count _x];
                            private _l = _idx getOrDefault [_k, []];
                            _l pushBack _x;
                            _idx set [_k, _l];
                        };
                    } forEach (_x splitString " ");
                } forEach _pats;
            };
        };
    } forEach (["lexicon"] call comspec_sse_fnc_iarpData);
    { _words set [trim _x, true]; } forEach _keep;
    private _out = createHashMapFromArray [["words", _words], ["skel", _skel], ["idx", _idx]];
    missionNamespace setVariable [_key, _out];
    _out
};

private _read = {
    params ["_path"];
    if !(fileExists _path) exitWith { 0 };
    private _r = call compile preprocessFileLineNumbers _path;
    if (isNil "_r") then { 0 } else { _r }
};
private _base = [format ["\z\comspec_sse\addons\ia_rp\data\iarp_%1.sqf", _what]] call _read;
private _extra = [[format ["iarp_%1.sqf", _what]] call _read, missionNamespace getVariable ["comspec_sse_iarpExtra_" + _what, 0]];

private _out = switch (_what) do {
    case "lexicon": {
        private _l = [[], +_base] select (_base isEqualType []);
        {
            if (_x isEqualType []) then {
                {
                    _x params [["_i", "", [""]], ["_w", 1, [0]], ["_pats", [], [[]]]];
                    private _at = _l findIf { (_x select 0) isEqualTo _i };
                    if (_at < 0) then { _l pushBack [_i, _w, _pats]; } else { ((_l select _at) select 2) append _pats; };
                } forEach _x;
            };
        } forEach _extra;
        _l
    };
    case "bank": {
        private _b = [createHashMap, +_base] select (_base isEqualType createHashMap);
        {
            if (_x isEqualType createHashMap) then {
                {
                    private _intent = _x;
                    private _tones = _b getOrDefault [_intent, createHashMap];
                    _b set [_intent, _tones];
                    if (_y isEqualType createHashMap) then {
                        { (_tones getOrDefault [_x, []]) append _y; if !(_x in _tones) then { _tones set [_x, +_y]; }; } forEach _y;
                    };
                } forEach _x;
            };
        } forEach _extra;
        _b
    };
    default {
        private _p = [createHashMap, +_base] select (_base isEqualType createHashMap);
        {
            if (_x isEqualType createHashMap) then { (_p getOrDefault ["presets", []]) append (_x getOrDefault ["presets", []]); };
        } forEach _extra;
        _p
    };
};
missionNamespace setVariable [_key, _out];
_out
