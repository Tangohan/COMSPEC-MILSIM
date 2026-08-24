/*
    Panneau Zeus : suivi carte ATAK (téléphone, IA alliée, balise GPS).
    Params: [_obj, _delay]
*/
params [
    ["_obj", objNull, [objNull]],
    ["_delay", 0],
    ["_retried", false]
];
if (!hasInterface) exitWith { false };
if (isNull _obj) exitWith {
    ["Sélectionnez une personne ou un véhicule.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};
if (_delay > 0 && {!isNil "CBA_fnc_waitAndExecute"}) exitWith {
    [{ [_this, 0] call comspec_overwatch_connect_fnc_zeusAttributesAtak }, _obj, _delay] call CBA_fnc_waitAndExecute;
    true
};

private _isMan = _obj isKindOf "CAManBase";
private _isVeh = !_isMan && {
    (_obj isKindOf "LandVehicle") || {_obj isKindOf "Air"} || {_obj isKindOf "Ship"}
};

if (!_isMan && {!_isVeh}) exitWith {
    ["ATAK : sélectionnez une personne ou un véhicule.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

if (isNil "zen_dialog_fnc_create") exitWith {
    if (_isMan) then {
        [_obj, 0] call comspec_overwatch_connect_fnc_phoneTrackConfigure;
    } else {
        private _on = !([_obj, "COMSPEC_GpsBeacon"] call comspec_overwatch_connect_fnc_isObjectFlag);
        [_obj, _on] call comspec_overwatch_connect_fnc_setGpsBeacon;
        if (_on) then {
            ["Balise GPS activée — le véhicule apparaît sur la carte.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        } else {
            ["Balise GPS coupée.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        };
    };
    true
};

if (_isVeh) exitWith {
    private _gpsOn = [_obj, "COMSPEC_GpsBeacon"] call comspec_overwatch_connect_fnc_isObjectFlag;
    private _cs = _obj getVariable ["COMSPEC_GpsCallsign", ""];
    if (!(_cs isEqualType "")) then { _cs = "" };
    private _opened = [
        format ["ATAK — %1", getText (configOf _obj >> "displayName")],
        [
            ["CHECKBOX", ["Balise GPS", "Le véhicule apparaît sur la carte de commandement, même sans joueur à bord."], _gpsOn],
            ["EDIT", ["Nom sur la carte", "Laisser vide : le modèle du véhicule est utilisé."], _cs],
            ["CHECKBOX", ["Équipage IA sur la carte", "Les IA à bord apparaissent comme unités alliées."], false]
        ],
        {
            params ["_values", "_args"];
            _values params ["_gps", "_name", "_crewAlly"];
            _args params ["_veh"];
            [_veh, _gps] call comspec_overwatch_connect_fnc_setGpsBeacon;
            private _n = trim _name;
            if (_n isNotEqualTo "") then {
                _veh setVariable ["COMSPEC_GpsCallsign", _n, true];
            };
            if (_crewAlly) then {
                {
                    if (!isPlayer _x && {alive _x} && {_x isKindOf "CAManBase"}) then {
                        [_x, true] call comspec_overwatch_connect_fnc_setAllyTrack;
                    };
                } forEach (crew _veh);
            };
            if (_gps) then {
                _veh setVariable ["COMSPEC_GpsBeaconLastAt", -1e9, false];
                [_veh] call comspec_overwatch_connect_fnc_reportGpsBeacon;
                ["Suivi ATAK du véhicule enregistré.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
            } else {
                ["Balise GPS coupée.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
            };
        },
        {},
        [_obj]
    ] call zen_dialog_fnc_create;
    if (_opened isEqualTo false && {!_retried}) then {
        [{ [_this, 0, true] call comspec_overwatch_connect_fnc_zeusAttributesAtak }, _obj, 0.2] call CBA_fnc_waitAndExecute;
    };
    true
};

private _phoneOn = [_obj, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag;
private _allyOn = [_obj, "COMSPEC_AllyTrack"] call comspec_overwatch_connect_fnc_isObjectFlag;
private _isPlayer = isPlayer _obj;
private _fields = [
    ["CHECKBOX", ["Téléphone sur la carte", "Le contact apparaît. Décochez pour couper."], _phoneOn],
    ["CHECKBOX", ["Nom", "Le nom s’affiche sur la carte et dans la fiche."], [_obj, "identity"] call comspec_overwatch_connect_fnc_phoneRevealHas],
    ["CHECKBOX", ["Position (grille)", "Les coordonnées chiffrées. Le point reste visible."], [_obj, "grid"] call comspec_overwatch_connect_fnc_phoneRevealHas],
    ["CHECKBOX", ["Altitude", "Altitude au-dessus du sol."], [_obj, "altitude"] call comspec_overwatch_connect_fnc_phoneRevealHas],
    ["CHECKBOX", ["Cap", "Direction vers laquelle la personne est tournée."], [_obj, "heading"] call comspec_overwatch_connect_fnc_phoneRevealHas],
    ["CHECKBOX", ["Heure du dernier signal", "Depuis quand le dernier point a été reçu."], [_obj, "updated"] call comspec_overwatch_connect_fnc_phoneRevealHas],
    ["CHECKBOX", ["Camp", "Allié, hostile, neutre — change aussi la couleur du symbole."], [_obj, "affiliation"] call comspec_overwatch_connect_fnc_phoneRevealHas],
    ["CHECKBOX", ["Dans un véhicule", "Indique si la personne est montée."], [_obj, "vehicle"] call comspec_overwatch_connect_fnc_phoneRevealHas]
];
if (!_isPlayer) then {
    _fields pushBack ["CHECKBOX", ["IA alliée (unité de terrain)", "Apparaît comme une unité, pas comme un téléphone."], _allyOn];
};

private _opened = [
    format ["ATAK — %1", name _obj],
    _fields,
    {
        params ["_values", "_args"];
        _args params ["_unit", "_isPlayer"];
        private _phone = _values select 0;
        private _keys = [];
        if (_values select 1) then { _keys pushBack "identity" };
        if (_values select 2) then { _keys pushBack "grid" };
        if (_values select 3) then { _keys pushBack "altitude" };
        if (_values select 4) then { _keys pushBack "heading" };
        if (_values select 5) then { _keys pushBack "updated" };
        if (_values select 6) then { _keys pushBack "affiliation" };
        if (_values select 7) then { _keys pushBack "vehicle" };
        [_unit, _phone, _keys] call comspec_overwatch_connect_fnc_setPhoneTrack;
        if (_phone) then {
            _unit setVariable ["COMSPEC_PhoneTrackLastAt", -1e9, false];
            [_unit] call comspec_overwatch_connect_fnc_reportPhonePosition;
        };
        if (!_isPlayer && {(count _values) > 8}) then {
            [_unit, _values select 8] call comspec_overwatch_connect_fnc_setAllyTrack;
        };
        [format ["Suivi ATAK réglé pour %1.", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    },
    {},
    [_obj, _isPlayer]
] call zen_dialog_fnc_create;

if (_opened isEqualTo false) exitWith {
    if (!_retried && {!isNil "CBA_fnc_waitAndExecute"}) then {
        [{ [_this, 0, true] call comspec_overwatch_connect_fnc_zeusAttributesAtak }, _obj, 0.2] call CBA_fnc_waitAndExecute;
    };
    true
};
true
