/*
    Renvoie uniquement ce que la dernière vérification a trouvé manquant au poste.
*/
if (!hasInterface) exitWith {};

if (missionNamespace getVariable ["COMSPEC_TheaterSampling", false]) exitWith {
    ["Un relevé est déjà en cours. Attendez la fin, ou interrompez-le.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

if (missionNamespace getVariable ["COMSPEC_TheaterVerifyBusy", false]) exitWith {
    ["Attendez la fin de la vérification d’intégrité.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith {
    ["Reliez votre compte Athena, puis relancez l’envoi.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _mode = missionNamespace getVariable ["COMSPEC_TheaterResendMode", ""];
if (!(_mode isEqualType "") || {_mode isEqualTo ""}) exitWith {
    ["Rien à renvoyer. Lancez d’abord une vérification d’intégrité.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _forceGeo = missionNamespace getVariable ["COMSPEC_TheaterResendGeo", false];
missionNamespace setVariable ["COMSPEC_TheaterResendMode", "", false];
missionNamespace setVariable ["COMSPEC_TheaterResendGeo", false, false];
missionNamespace setVariable ["COMSPEC_TheaterForceGeo", _forceGeo, false];

if (_mode isEqualTo "geo") then {
    [0, true] call comspec_overwatch_connect_fnc_sampleGeoNetwork;
} else {
    [_mode] call comspec_overwatch_connect_fnc_sampleTheater;
};
