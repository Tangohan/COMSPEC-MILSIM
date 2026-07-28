/*
    Helper commun modules Zeus/Eden → création de zone roleplay.
    Entrée souple : BI peut passer [logic, units, activated], logic seul,
    ou (Eden) un libellé d’événement en tête — d’où l’ancien crash
    « Type Chaîne, Objet attendu ».
*/
if (!isServer && {isMultiplayer}) exitWith {
    // Clients : ne pas créer la zone (isGlobal=1) ; nettoyer la logique locale si présente.
    private _maybeLogic = if (_this isEqualType []) then { _this param [0, objNull] } else { _this };
    if (_maybeLogic isEqualType objNull && {!isNull _maybeLogic}) then {
        deleteVehicle _maybeLogic;
    };
    true
};

private _logic = objNull;
private _units = [];
private _activated = true;
private _type = "degraded";
private _defaultRadius = 200;
private _defaultIntensity = 50;

if (_this isEqualType objNull) then {
    _logic = _this;
} else {
    if (!(_this isEqualType [])) exitWith { false };
    private _a0 = _this param [0, objNull];
    private _a1 = _this param [1, []];
    private _a2 = _this param [2, true];
    private _a3 = _this param [3, "degraded"];
    private _a4 = _this param [4, 200];
    private _a5 = _this param [5, 50];

    // Cas BI classique : [logic, units, activated, type?, radius?, intensity?]
    if (_a0 isEqualType objNull) then {
        _logic = _a0;
        if (_a1 isEqualType []) then { _units = _a1; };
        if (_a2 isEqualType true) then { _activated = _a2; };
        if (_a3 isEqualType "") then { _type = _a3; };
        if (_a4 isEqualType 0) then { _defaultRadius = _a4; };
        if (_a5 isEqualType 0) then { _defaultIntensity = _a5; };
    } else {
        // Cas Eden / bruit : premier élément = chaîne (événement) → logic en 2e
        if (_a0 isEqualType "" && {_a1 isEqualType objNull}) then {
            _logic = _a1;
            if (_a2 isEqualType []) then { _units = _a2; };
            if ((_this param [3, true]) isEqualType true) then { _activated = _this param [3, true]; };
            if ((_this param [4, ""]) isEqualType "") then { _type = _this param [4, _type]; };
            if ((_this param [5, 0]) isEqualType 0) then { _defaultRadius = _this param [5, _defaultRadius]; };
            if ((_this param [6, 0]) isEqualType 0) then { _defaultIntensity = _this param [6, _defaultIntensity]; };
        } else {
            // Appel invalide — ignorer sans spam RPT
            false
        };
    };
};

if (isNull _logic) exitWith { false };
if (!_activated) exitWith { false };

private _anchor = objNull;
private _attached = attachedTo _logic;
if (!isNull _attached) then {
    _anchor = _attached;
} else {
    {
        if (!isNull _x && {(_x isKindOf "Man") || {_x isKindOf "AllVehicles"} || {_x isKindOf "Thing"}}) exitWith {
            _anchor = _x;
        };
    } forEach _units;
};

private _pos = if (!isNull _anchor) then {
    getPosATL _anchor
} else {
    getPosATL _logic
};

// Rayon : zone Eden/Zeus (canSetArea) puis Argument/Attribute legacy
private _radius = _defaultRadius;
private _area = _logic getVariable ["objectarea", []];
if (!(_area isEqualType []) || {count _area < 1}) then {
    _area = _logic getVariable ["objectArea", []];
};
if ((_area isEqualType []) && {count _area >= 1}) then {
    private _a = _area select 0;
    if (_a isEqualType 0 && {_a > 0}) then { _radius = _a; };
};

private _radiusVar = _logic getVariable ["Radius", -1];
if (_radiusVar isEqualType 0 && {_radiusVar > 0}) then { _radius = _radiusVar; };

private _intensity = _logic getVariable ["Intensity", _defaultIntensity];
if (!(_intensity isEqualType 0)) then { _intensity = _defaultIntensity; };
_intensity = (_intensity max 0) min 100;
_radius = (_radius max 5) min 5000;

[_pos, _radius, _type, _intensity, _anchor] call comspec_overwatch_connect_fnc_createRoleplayZoneFromZeus;

deleteVehicle _logic;
true
