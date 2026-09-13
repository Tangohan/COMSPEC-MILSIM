/*
    Module Zeus/Eden : présentation des documents / fiches SSE.

    Impose le modèle (papier, bandeau, titres, pied de page) pour toute la mission.
    Les champs libres remplacent le modèle lorsqu’ils sont renseignés.
*/
private _logic = objNull;
private _activated = true;

if (_this isEqualType objNull) then {
    _logic = _this;
} else {
    if (!(_this isEqualType [])) exitWith { false };
    private _a0 = _this param [0, objNull];
    if (_a0 isEqualType objNull) then {
        _logic = _a0;
        _activated = _this param [2, true];
    } else {
        if (_a0 isEqualType "" && { (_this param [1, objNull]) isEqualType objNull }) then {
            _logic = _this param [1, objNull];
            _activated = _this param [3, true];
        };
    };
};

if (isNull _logic) exitWith { false };
if (!(_activated isEqualType true)) then { _activated = true; };
if (!_activated) exitWith { false };

if (!isServer && { isMultiplayer }) exitWith {
    deleteVehicle _logic;
    true
};

if (isNil "comspec_sse_fnc_getDocumentChrome") exitWith {
    ["WARN", "SSE", "Présentation documents : pack SSE requis"] call comspec_overwatch_connect_fnc_log;
    deleteVehicle _logic;
    false
};

private _prefab = _logic getVariable ["Prefab", "standard_restreint"];
if (!(_prefab isEqualType "")) then { _prefab = "standard_restreint"; };
_prefab = toLower (trim _prefab);

private _chrome = ["apply_prefab", _prefab] call comspec_sse_fnc_getDocumentChrome;

private _overrides = createHashMap;
{
    _x params ["_key", "_var"];
    private _v = _logic getVariable [_var, ""];
    if (_v isEqualType "" && {(trim _v) isNotEqualTo ""}) then {
        _overrides set [_key, trim _v];
    };
} forEach [
    ["paper_style", "PaperStyle"],
    ["banner", "Banner"],
    ["title_person", "TitlePerson"],
    ["title_docs", "TitleDocs"],
    ["subtitle_dossier", "SubtitleDossier"],
    ["footer", "Footer"],
    ["btn_consult", "BtnConsult"],
    ["btn_transmit", "BtnTransmit"],
    ["btn_close", "BtnClose"]
];

if ((count _overrides) > 0) then {
    _chrome = ["set", _overrides] call comspec_sse_fnc_getDocumentChrome;
};

["INFO", "SSE", format [
    "Présentation documents SSE : modèle %1, papier %2",
    _chrome getOrDefault ["prefab", _prefab],
    _chrome getOrDefault ["paper_style", "clean"]
]] call comspec_overwatch_connect_fnc_log;

deleteVehicle _logic;
true
