/*
    Détermine si une liaison COMSPEC / Overwatch est disponible.
*/
if (!isNil "comspec_overwatch_connect_fnc_sendIntel") exitWith { true };
if (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { true };

// Extension présente ? (appel version soft)
private _ver = "COMSPECExtension" callExtension ["version", []];
if (_ver isEqualType "" && {_ver != ""} && {((toLower _ver) find "error") < 0}) exitWith { true };

false
