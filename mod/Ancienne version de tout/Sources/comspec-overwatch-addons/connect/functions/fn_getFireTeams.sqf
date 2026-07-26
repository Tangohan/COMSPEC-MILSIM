/*
    Récupère les équipes de feu éphémères (mission) depuis la plateforme via l'extension
    native GetFireTeams, et les stocke dans missionNamespace ["COMSPEC_FireTeams"].

    Format stocké :
      [
        [id, label, color, mapId, kind, [[callsign, role, displayName], ...]],
        ...
      ]

    Variables mission optionnelles :
      comspec_overwatch_tenant_id — id communauté
      comspec_overwatch_map_id    — id carte ATAK

    Retourne : la liste (array). Échec / extension absente → [].
*/
if (!hasInterface) exitWith { [] };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { [] };

private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
private _mapId = missionNamespace getVariable ["comspec_overwatch_map_id", ""];
private _raw = ["COMSPECExtension" callExtension ["GetFireTeams", [_tenantId, _mapId]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };

if (_prefix != "OK") exitWith {
    missionNamespace setVariable ["COMSPEC_FireTeams", [], true];
    []
};

private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };
private _lines = _payload splitString (toString [10]);
private _teams = [];
private _memberRows = [];

{
    if (_x != "") then {
        private _cols = _x splitString "\t";
        if ((_cols select 0) isEqualTo "M") then {
            if (count _cols >= 5) then {
                _memberRows pushBack [
                    parseNumber (_cols select 1),
                    _cols select 2,
                    _cols select 3,
                    _cols select 4
                ];
            };
        } else {
            if (count _cols >= 6) then {
                _teams pushBack [
                    parseNumber (_cols select 0),
                    _cols select 1,
                    _cols select 2,
                    parseNumber (_cols select 3),
                    _cols select 4,
                    []
                ];
            };
        };
    };
} forEach _lines;

{
    private _team = _x;
    private _tid = _team select 0;
    private _mem = [];
    {
        if ((_x select 0) isEqualTo _tid) then {
            _mem pushBack [_x select 1, _x select 2, _x select 3];
        };
    } forEach _memberRows;
    _team set [5, _mem];
} forEach _teams;

missionNamespace setVariable ["COMSPEC_FireTeams", _teams, true];
_teams
