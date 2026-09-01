/*
    Nom du groupe Arma à afficher (Paramètres / HUD).
    Vide si le groupe a été renommé avec le titre de communauté.
*/
params [["_unit", objNull, [objNull]]];

if (isNull _unit) then { _unit = player; };
if (isNull _unit) exitWith { "" };

private _gid = trim (groupId (group _unit));
if (!(_gid isEqualType "")) then { _gid = str _gid; };
_gid = trim _gid;
if (_gid isEqualTo "" || {(toLower _gid) in ["error", "grpnull"]}) exitWith { "" };

if ((count _gid) > 24 && {!([_gid] call comspec_overwatch_connect_fnc_isUsableCallsign)}) exitWith { "" };

private _tenant = toLower (trim (str (missionNamespace getVariable ["comspec_tenant_name", ""])));
if (_tenant isNotEqualTo "" && {(toLower _gid) isEqualTo _tenant}) exitWith { "" };

_gid
