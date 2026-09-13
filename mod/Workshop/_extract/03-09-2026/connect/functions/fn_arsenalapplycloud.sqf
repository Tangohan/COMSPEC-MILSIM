/*
    Applique une tenue de la communauté (par identifiant) sur le mannequin de l’arsenal.
*/
params [["_id", "", [""]]];

if (!hasInterface) exitWith { false };
if (_id isEqualTo "") exitWith { false };

private _loadout = [_id] call comspec_overwatch_connect_fnc_arsenalCloudLoadout;
if (_loadout isEqualTo []) exitWith {
    ["Cette tenue est introuvable ou trop volumineuse.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _names = missionNamespace getVariable ["COMSPEC_ArsenalCloudNames", createHashMap];
private _name = if (_id in _names) then { _names get _id } else { "" };

[_loadout, _name] call comspec_overwatch_connect_fnc_arsenalApplyLoadout
