/*
    Démarre un nouveau fichier journal pour cette session Arma.
    Purge automatique des anciens fichiers (12 derniers conservés).
    Returns: chemin absolu du journal ou "" si indisponible.
*/
if (!hasInterface) exitWith { "" };

private _keep = missionNamespace getVariable ["comspec_overwatch_log_keep_count", 12];
if (!(_keep isEqualType 0)) then { _keep = 12; };
_keep = (_keep max 3) min 50;

private _raw = ["COMSPECExtension" callExtension ["LogSessionStart", [str _keep]]] call comspec_overwatch_connect_fnc_extResult;
private _path = "";
if (_raw isEqualType "" && {(_raw select [0, 3]) isEqualTo "OK|"}) then {
    _path = _raw select [3, count _raw - 3];
    missionNamespace setVariable ["COMSPEC_LogFilePath", _path, false];
};

_path
