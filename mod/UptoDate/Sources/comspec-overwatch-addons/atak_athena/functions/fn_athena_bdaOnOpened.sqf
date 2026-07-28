/*
    Ouverture BDA Report (stub BCE sans Opened).
    - Si ATAK_BDA (Iceman) est chargé → délègue à Iceman_fnc_bda_onOpened
    - Sinon → onglet Athena BDA / envoi rapide
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

if (!isNil "Iceman_fnc_bda_onOpened") exitWith {
    _this call Iceman_fnc_bda_onOpened;
};

// Repli COMSPEC : pas de module Iceman BDA
private _ph = _group controlsGroupCtrl 9860;
if (!isNull _ph) then {
    _ph ctrlSetStructuredText parseText (
        "<t align='center'>Module BDA ATAK indisponible.</t><br/>" +
        "<t align='center' color='#8aa0b4'>Ouverture d’Athena (onglet BDA)…</t>"
    );
};

[{
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openFeature") then {
        ["bda"] call comspec_overwatch_atak_athena_fnc_athena_openFeature;
    };
}, [], 0.35] call CBA_fnc_waitAndExecute;
