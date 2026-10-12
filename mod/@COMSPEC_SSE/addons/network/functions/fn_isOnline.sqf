/*
    Détermine si une liaison COMSPEC / Overwatch est disponible.
*/
if (!isNil "comspec_overwatch_connect_fnc_sendIntel") exitWith { true };
if (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { true };

// Extension présente ? (appel version soft)
// Résultat gardé pour la partie (la présence de la DLL ne change pas en cours de mission).
if (isNil "comspec_sse_extPresent") then {
    private _ver = "COMSPECExtension" callExtension ["version", []];
    if (_ver isEqualType []) then { _ver = _ver param [0, ""]; };
    comspec_sse_extPresent = _ver isEqualType "" && {_ver != ""} && {((toLower _ver) find "error") < 0};
};
comspec_sse_extPresent
