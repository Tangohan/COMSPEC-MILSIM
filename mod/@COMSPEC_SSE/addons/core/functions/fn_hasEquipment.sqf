/*
    Vérifie si l’unité possède le matériel requis (item SSE natif OU substitut d’un autre mod).
    [_unit, "camera"] call comspec_sse_fnc_hasEquipment
    [_unit, "COMSPEC_SSE_Camera"] call comspec_sse_fnc_hasEquipment
    [_unit, ["fingerprint", "seek"]] call comspec_sse_fnc_hasEquipment

    Reconnaît notamment le S7 Android cTab (ItemAndroid / ItemAndroidMisc), y compris
    sous-classes et détection terminal Overwatch (même logique ATAK).
*/
params [
    ["_unit", objNull, [objNull]],
    ["_itemOrRole", "", ["", []]]
];

if (isNull _unit) exitWith { false };

if !(missionNamespace getVariable ["comspec_sse_requireEquipment", true]) exitWith { true };

private _roles = if (_itemOrRole isEqualType []) then { _itemOrRole } else { [_itemOrRole] };
if (_roles isEqualTo [] || {(_roles select 0) isEqualTo ""}) exitWith { true };

// Inventaire élargi : items, assignés, chargeurs, armes (slot GPS/Android), conteneurs.
private _gear = (items _unit) + (assignedItems _unit) + (magazines _unit) + (weapons _unit);
{
    if !(isNull _x) then {
        _gear append (itemCargo _x);
        _gear append (magazineCargo _x);
        _gear append (weaponCargo _x);
    };
} forEach [uniformContainer _unit, vestContainer _unit, backpackContainer _unit];

{
    _x params ["", "_cont"];
    if (!isNull _cont) then {
        _gear append (itemCargo _cont);
        _gear append (magazineCargo _cont);
    };
} forEach (everyContainer _unit);

_gear = _gear select { _x isEqualType "" && {_x isNotEqualTo ""} };
private _gearLower = _gear apply { toLower _x };

// Pont ATAK Overwatch : S7 reconnu pour la liaison ⇒ photo / SEEK / terminal OK.
private _owRoles = ["camera", "face", "seek", "terminal", "fingerprint", "sse_terminal", "seekii"];
private _needOwBridge = false;
{
    private _r = toLower (str _x);
    // str on string keeps the string; also map classname→role via aliases later
    if (_x isEqualType "") then { _r = toLower _x; };
    if (
        _r in _owRoles
        || {(_r find "camera") >= 0}
        || {(_r find "seek") >= 0}
        || {(_r find "terminal") >= 0}
        || {(_r find "face") >= 0}
        || {(_r find "fingerprint") >= 0}
    ) then {
        _needOwBridge = true;
    };
} forEach _roles;

if (
    _needOwBridge
    && {!isNil "comspec_overwatch_connect_fnc_hasTerminal"}
    && {[_unit] call comspec_overwatch_connect_fnc_hasTerminal}
) exitWith { true };

private _fnc_gearMatches = {
    params ["_aliases", "_gearExact", "_gearRaw"];
    if (_aliases isEqualTo []) exitWith { false };
    private _aliasLower = _aliases apply { toLower _x };
    if (({_x in _gearExact} count _aliasLower) > 0) exitWith { true };

    private _ok = false;
    {
        private _cls = _x;
        private _cfg = configFile >> "CfgWeapons" >> _cls;
        if (!isClass _cfg) then { _cfg = configFile >> "CfgMagazines" >> _cls; };
        if (!isClass _cfg) then { _cfg = configFile >> "CfgVehicles" >> _cls; };
        if (isClass _cfg) then {
            private _tree = ([_cfg, true] call BIS_fnc_returnParents) apply { toLower _x };
            _tree pushBackUnique (toLower _cls);
            if (({_x in _aliasLower} count _tree) > 0) then { _ok = true; };
        };
        if (_ok) exitWith {};
    } forEach _gearRaw;
    _ok
};

private _matched = false;
{
    private _aliases = [_x] call comspec_sse_fnc_getEquipmentAliases;
    if ([_aliases, _gearLower, _gear] call _fnc_gearMatches) then { _matched = true; };
    if (_matched) exitWith {};
} forEach _roles;

_matched
