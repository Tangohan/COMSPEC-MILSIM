/*
    Surveille l'état médical local et déclenche les alertes KO / FC=0.
    Appelé depuis le PFH position et depuis les événements ACE.
*/
params [["_unit", player, [objNull]]];
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (isNull _unit || {!local _unit}) exitWith {};
// Déconnexion en cours (mission Ended / retour menu) : l'état santé peut lire faux pendant le
// nettoyage du personnage (FC/ACE réinitialisés) — ne pas déclencher de fausse alerte critique.
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};

private _state = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
private _parts = _state splitString "|";
private _health = if (count _parts >= 1) then { _parts select 0 } else { "stable" };
private _hr = if (count _parts >= 4) then { parseNumber (_parts select 3) } else { 80 };
private _cardiac = (count _parts >= 5) && {(_parts select 4) == "1"};

private _lastKind = missionNamespace getVariable ["COMSPEC_lastMedicalAlertKind", ""];

// Remise à zéro du verrou quand le combattant est de nouveau stable / blessé conscient
if (_health in ["stable", "wounded"] && {_hr > 0} && {!_cardiac}) then {
    if (_lastKind != "") then {
        missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", "", false];
    };
} else {
    if (_cardiac || {_hr <= 0}) then {
        [_unit, "cardiac_arrest"] call comspec_overwatch_connect_fnc_reportMedicalAlert;
    } else {
        if (_health == "unconscious") then {
            [_unit, "unconscious"] call comspec_overwatch_connect_fnc_reportMedicalAlert;
        };
    };
};
