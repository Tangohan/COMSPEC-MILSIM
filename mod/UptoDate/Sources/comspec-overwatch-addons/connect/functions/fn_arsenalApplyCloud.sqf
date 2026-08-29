/*
    Applique une wardrobe cloud (par id Athena) sur le joueur.
*/
params [["_id", "", [""]]];

if (!hasInterface) exitWith { false };
if (_id isEqualTo "") exitWith { false };
if (isNull player || {!alive player}) exitWith { false };

private _raw = ["COMSPECExtension" callExtension ["GetWardrobe", [_id]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw find "OK|" != 0}) exitWith {
    ["Wardrobe Athena introuvable ou trop volumineuse.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _parts = (_raw select [3]) splitString toString [9];
if (count _parts < 3) exitWith { false };
private _name = _parts select 1;
private _loadout = [_parts select 2] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
if (_loadout isEqualTo []) exitWith {
    ["Loadout Athena invalide.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

player setUnitLoadout [_loadout, true];
[format ["Loadout « %1 » appliqué.", _name], "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;
true
