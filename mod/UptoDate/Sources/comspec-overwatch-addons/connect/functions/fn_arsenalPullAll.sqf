/*
    Tire la liste cloud + fusionne dans ace_arsenal_saved_loadouts (profile).
    params: [applyId optional]
*/
params [["_applyId", "", [""]]];

if (!hasInterface) exitWith { false };

private _key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
if (_key isEqualTo "") exitWith {
    ["Liaison Athena requise pour récupérer les tenues.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _raw = ["COMSPECExtension" callExtension ["ListWardrobes", []]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw find "OK|" != 0}) exitWith {
    private _err = if (_raw isEqualType "") then { _raw } else { str _raw };
    [format ["Impossible de lister les tenues (%1).", _err], "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _body = _raw select [3];
private _lines = _body splitString endl;
if (_lines isEqualTo [] && {_body != ""}) then {
    _lines = [_body];
};
private _meta = [];
{
    if (_x isEqualTo "") then { continue };
    private _parts = _x splitString toString [9];
    if (count _parts < 2) then { continue };
    _meta pushBack [
        _parts select 0,
        _parts select 1,
        if (count _parts > 3) then { _parts select 3 } else { "" }
    ];
} forEach _lines;

missionNamespace setVariable ["COMSPEC_ArsenalCloudMeta", _meta, false];

private _merged = [] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts;
private _names = _merged apply { toLower (_x select 0) };
private _pulled = 0;

{
    _x params ["_id", "_name"];
    private _detailRaw = ["COMSPECExtension" callExtension ["GetWardrobe", [_id]]] call comspec_overwatch_connect_fnc_extResult;
    if (!(_detailRaw isEqualType "") || {_detailRaw find "OK|" != 0}) then { continue };
    private _dBody = _detailRaw select [3];
    private _dParts = _dBody splitString toString [9];
    if (count _dParts < 3) then { continue };
    private _payload = _dParts select 2;
    private _loadout = [_payload] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
    if (_loadout isEqualTo []) then { continue };

    private _idx = _names find toLower _name;
    if (_idx >= 0) then {
        _merged set [_idx, [_name, _loadout]];
    } else {
        _merged pushBack [_name, _loadout];
        _names pushBack toLower _name;
    };
    _pulled = _pulled + 1;
} forEach _meta;

profileNamespace setVariable ["ace_arsenal_saved_loadouts", _merged];
saveProfileNamespace;
missionNamespace setVariable ["COMSPEC_ArsenalLastPullAt", diag_tickTime, false];

[format ["%1 tenue(s) de la communauté ajoutée(s) à l’arsenal.", _pulled], "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;

if (_applyId != "") then {
    [_applyId] call comspec_overwatch_connect_fnc_arsenalApplyCloud;
};

_pulled > 0
