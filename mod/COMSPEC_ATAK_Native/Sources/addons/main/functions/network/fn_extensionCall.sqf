/*
    Appel à la DLL native. callExtension [cmd, args] renvoie [résultat, code, erreur] : on garde le résultat.
    _maxAge (4.2.1, GetAuthState seulement) : réponse gardée et partagée entre les lecteurs (barre d'état, app Athena,
    fn_athenaStatus, rappel de connexion) ; un lecteur qui accepte une réponse de moins de _maxAge secondes ne rappelle
    pas la DLL. -1 (défaut) : appel réel, comme avant (fn_remoteSync relit toujours la DLL avant ses sondages).
*/
params ["_command", ["_args", []], ["_maxAge", -1]];
if (!(_command isEqualType "") || {_command isEqualTo ""}) exitWith { "" };
private _authQ = _command isEqualTo "GetAuthState" && {_args isEqualTo []};
private _memo = missionNamespace getVariable ["COMSPEC_ATAK_AuthStateMemo", [-100, ""]];
// Réponse gardée encore fraîche : rendue sans appel.
if (_authQ && {_maxAge >= 0} && {diag_tickTime - (_memo select 0) <= _maxAge}) exitWith { _memo select 1 };
private _pt = diag_tickTime;
private _ret = "COMSPECATAKNativeExtension" callExtension [_command, _args];
if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) then { ["DLL native " + _command, _pt] call comspec_atak_native_fnc_perfAcc; } else {
    if (_authQ) then { ["DLL native GetAuthState", _pt] call comspec_atak_native_fnc_perfAcc; };
};
private _raw = if (_ret isEqualType []) then { _ret param [0, ""] } else { _ret };
if !(_raw isEqualType "") then { _raw = str _raw; };
if (_authQ) then { missionNamespace setVariable ["COMSPEC_ATAK_AuthStateMemo", [diag_tickTime, _raw]]; };
// Journal de débogage : au plus une ligne par seconde et par commande (avant : une par appel, des dizaines par seconde).
if (profileNamespace getVariable ["COMSPEC_ATAK_Debug", false]) then {
    if (isNil "COMSPEC_ATAK_ExtLogAt") then { COMSPEC_ATAK_ExtLogAt = createHashMap; };
    if (diag_tickTime < (COMSPEC_ATAK_ExtLogAt getOrDefault [_command, 0])) exitWith {};
    COMSPEC_ATAK_ExtLogAt set [_command, diag_tickTime + 1];
    ["DEBUG", "EXT", format ["%1 completed (%2 chars)", _command, count _raw]] call comspec_atak_native_fnc_log;
};
_raw
