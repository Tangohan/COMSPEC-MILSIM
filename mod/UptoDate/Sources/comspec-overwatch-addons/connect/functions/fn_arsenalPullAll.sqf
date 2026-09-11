/*
    Tire la liste cloud + fusionne dans ace_arsenal_saved_loadouts (profile).
    params: [applyId optional, onlyIds optional] — onlyIds vide = toutes.
*/
params [["_applyId", "", [""]], ["_onlyIds", [], [[]]]];

if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    ["Liaison Athena requise pour récupérer les tenues.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

if (missionNamespace getVariable ["COMSPEC_ArsenalPullBusy", false]) exitWith {
    ["Récupération des tenues déjà en cours.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _lines = [] call comspec_overwatch_connect_fnc_arsenalListWardrobes;
if (_lines isEqualTo []) exitWith {
    private _probe = ["COMSPECExtension" callExtension ["ListWardrobes", ["0"]]] call comspec_overwatch_connect_fnc_extResult;
    if (!(_probe isEqualType "") || {_probe find "OK|" != 0}) then {
        private _err = if (_probe isEqualType "") then { _probe } else { str _probe };
        [format ["Impossible de lister les tenues (%1).", _err], "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    } else {
        [
            if (_onlyIds isEqualTo []) then { "Aucune tenue dans la communauté." } else { "Cette tenue n’est plus disponible." },
            "arsenal",
            "info",
            true
        ] call comspec_overwatch_connect_fnc_announce;
    };
    false
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

if (_onlyIds isNotEqualTo []) then {
    _meta = _meta select { (_x select 0) in _onlyIds };
};

if (_meta isEqualTo []) exitWith {
    [
        if (_onlyIds isEqualTo []) then { "Aucune tenue dans la communauté." } else { "Cette tenue n’est plus disponible." },
        "arsenal",
        "info",
        true
    ] call comspec_overwatch_connect_fnc_announce;
    false
};

missionNamespace setVariable ["COMSPEC_ArsenalCloudMeta", _meta, false];
missionNamespace setVariable ["COMSPEC_ArsenalPullBusy", true, false];

private _merged = [] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts;
private _names = _merged apply { toLower (_x select 0) };
private _pulled = 0;
private _skippedDense = 0;

{
    _x params ["_id", "_name"];
    private _loadout = [_id] call comspec_overwatch_connect_fnc_arsenalCloudLoadout;
    if (_loadout isEqualTo []) then {
        if ((missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadoutError", ""]) isEqualTo "too_large") then {
            _skippedDense = _skippedDense + 1;
        };
        continue;
    };

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
missionNamespace setVariable ["COMSPEC_ArsenalPullBusy", false, false];

private _msg = format ["%1 tenue(s) de la communauté ajoutée(s) à l’arsenal.", _pulled];
if (_skippedDense > 0) then {
    _msg = _msg + format [" %1 trop dense(s) pour l’import en jeu.", _skippedDense];
};
[_msg, "arsenal", if (_pulled > 0) then { "ok" } else { "warn" }, true] call comspec_overwatch_connect_fnc_announce;

if (_applyId != "") then {
    [_applyId] call comspec_overwatch_connect_fnc_arsenalApplyCloud;
};

_pulled > 0
