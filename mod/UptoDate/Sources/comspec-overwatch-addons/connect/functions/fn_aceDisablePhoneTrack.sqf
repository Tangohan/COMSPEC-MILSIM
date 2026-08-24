/*
    Action ACE : coupe la géolocalisation téléphone d’une personne (signal GPS).
    Params: [_target]
*/
params [["_target", objNull, [objNull]]];

if (!hasInterface) exitWith { false };
if (isNull _target || {!(_target isKindOf "CAManBase")}) exitWith { false };

if !([_target, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag) exitWith {
    ["Aucun téléphone GPS actif sur cette personne.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

if ((player distance _target) > 4) exitWith {
    ["Trop loin pour couper le téléphone.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

private _apply = {
    params ["_unit"];
    if (isNull _unit) exitWith {};
    if !([_unit, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag) exitWith {
        ["Le téléphone GPS n’est plus actif.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    [_unit, false] remoteExecCall ["comspec_overwatch_connect_fnc_setPhoneTrack", 0];
    private _who = if (_unit isEqualTo player) then { "vous" } else { name _unit };
    [format ["Téléphone GPS coupé — plus de signal pour %1.", _who], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
};

missionNamespace setVariable ["COMSPEC_AcePhoneGpsTarget", _target, false];

if (!isNil "ace_common_fnc_progressBar") exitWith {
    [
        2.5,
        [_target, _apply],
        {
            (_this select 0) params ["_unit", "_fn"];
            [_unit] call _fn;
            missionNamespace setVariable ["COMSPEC_AcePhoneGpsTarget", objNull, false];
        },
        {
            missionNamespace setVariable ["COMSPEC_AcePhoneGpsTarget", objNull, false];
            ["Mise hors service interrompue.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        },
        "Mise hors service du GPS du téléphone…",
        {
            private _unit = missionNamespace getVariable ["COMSPEC_AcePhoneGpsTarget", objNull];
            !isNull _unit
            && { (player distance _unit) < 5 }
            && { [_unit, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag }
        },
        ["isNotInside"]
    ] call ace_common_fnc_progressBar;
    true
};

[_target] call _apply;
true
