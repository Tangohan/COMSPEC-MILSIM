/*
    Panneau Zeus : liaison Overwatch / Athena.
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
    [{ [_this, 0] call comspec_overwatch_connect_fnc_zeusAttributesOverwatch }, _obj, _delay] call CBA_fnc_waitAndExecute;
    true
};

private _unit = [_obj] call comspec_overwatch_connect_fnc_zeusAttributesPerson;
private _isVeh = !(_obj isKindOf "CAManBase") && {
    (_obj isKindOf "LandVehicle") || {_obj isKindOf "Air"} || {_obj isKindOf "Ship"}
};

if (_isVeh && {isNull _unit}) exitWith {
    private _cs = _obj getVariable ["COMSPEC_GpsCallsign", ""];
    if (!(_cs isEqualType "")) then { _cs = "" };
    if (isNil "zen_dialog_fnc_create") exitWith {
        [_obj, true] call comspec_overwatch_connect_fnc_setGpsBeacon;
        ["Véhicule signalé sur Athena (balise).", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        true
    };
    private _opened = [
        format ["Overwatch — %1", getText (configOf _obj >> "displayName")],
        [
            ["EDIT", ["Nom sur Athena", "Laisser vide : le modèle du véhicule."], _cs],
            ["CHECKBOX", ["Envoyer la position maintenant", "Pousse un point vers le poste de commandement."], true]
        ],
        {
            params ["_values", "_args"];
            _values params ["_name", "_ping"];
            _args params ["_veh"];
            private _n = trim _name;
            if (_n isNotEqualTo "") then {
                _veh setVariable ["COMSPEC_GpsCallsign", _n, true];
            };
            if (_ping) then {
                [_veh, true] call comspec_overwatch_connect_fnc_setGpsBeacon;
                _veh setVariable ["COMSPEC_GpsBeaconLastAt", -1e9, false];
                [_veh] call comspec_overwatch_connect_fnc_reportGpsBeacon;
                ["Position du véhicule envoyée.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
            };
        },
        {},
        [_obj]
    ] call zen_dialog_fnc_create;
    if (_opened isEqualTo false && {!_retried} && {!isNil "CBA_fnc_waitAndExecute"}) then {
        [{ [_this, 0, true] call comspec_overwatch_connect_fnc_zeusAttributesOverwatch }, _obj, 0.45] call CBA_fnc_waitAndExecute;
    };
    if (_opened isEqualTo false && {_retried}) then {
        ["Fenêtre Overwatch indisponible — fermez l’édition puis réessayez.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        systemChat "[COMSPEC] OVERWATCH : la fenêtre ne s’est pas ouverte. Fermez l’édition puis recliquez.";
    };
    true
};

if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith {
    ["Overwatch : sélectionnez une personne ou un véhicule.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

private _name = name _unit;
private _steam = if (isPlayer _unit) then { getPlayerUID _unit } else { "—" };
private _terminal = _unit getVariable ["COMSPEC_TerminalUid", ""];
private _atakId = _unit getVariable ["COMSPEC_AtakId", ""];
private _callsign = _unit getVariable ["COMSPEC_CallsignPublic", ""];
private _link = _unit getVariable ["COMSPEC_LinkState", ""];
private _nl = toString [10];
private _dash = {
    params ["_v"];
    if (!(_v isEqualType "") || {_v isEqualTo ""}) then { "—" } else { _v }
};
private _info = format [
    "%1%2Indicatif : %3%2Liaison : %4%2Terminal : %5%2Identifiant ATAK : %6%2Compte : %7",
    _name,
    _nl,
    [_callsign] call _dash,
    [_link] call _dash,
    [_terminal] call _dash,
    [_atakId] call _dash,
    [_steam] call _dash
];

if (isNil "zen_dialog_fnc_create") exitWith {
    copyToClipboard _info;
    ["Aperçu copié. Zeus Enhanced permet la synchro depuis ce panneau.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    true
};

private _fields = [
    ["EDIT:MULTI", ["État de la liaison", "Indicatif, terminal, identifiants — lecture seule."], [_info, {_this}, 6]]
];
if (isPlayer _unit) then {
    _fields append [
        ["CHECKBOX", ["Renvoyer l’état maintenant", "Le poste de commandement reçoit la position et l’état actuels."], false],
        ["CHECKBOX", ["Rétablir le terminal", "Annule brouillage, écran cassé ou capture."], false]
    ];
};

private _opened = [
    format ["Overwatch — %1", _name],
    _fields,
    {
        params ["_values", "_args"];
        _args params ["_unit"];
        if !(isPlayer _unit) exitWith {};
        private _sync = false;
        private _repair = false;
        if ((count _values) > 1) then { _sync = _values select 1 };
        if ((count _values) > 2) then { _repair = _values select 2 };
        if (_sync) then {
            [] remoteExecCall ["comspec_overwatch_connect_fnc_forceSyncData", _unit];
            [format ["Synchro demandée pour %1.", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        };
        if (_repair) then {
            [_unit, "repair", 30] remoteExecCall ["comspec_overwatch_connect_fnc_relayZeusAtakEffect", 2];
            [format ["Terminal rétabli pour %1.", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        };
    },
    {},
    [_unit]
] call zen_dialog_fnc_create;

if (_opened isEqualTo false) exitWith {
    if (!_retried && {!isNil "CBA_fnc_waitAndExecute"}) then {
        [{ [_this, 0, true] call comspec_overwatch_connect_fnc_zeusAttributesOverwatch }, _obj, 0.45] call CBA_fnc_waitAndExecute;
    } else {
        ["Fenêtre Overwatch indisponible — fermez l’édition puis réessayez.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        systemChat "[COMSPEC] OVERWATCH : la fenêtre ne s’est pas ouverte. Fermez l’édition puis recliquez.";
    };
    true
};
true
