/*
    Fixe la référence du dossier Athena de la mission.
    [_ref] call comspec_sse_fnc_setCaseReference
    Seul le serveur la publie : le réglage CBA rejoué par un joueur arrivant en cours
    de partie ne peut plus écraser la référence posée par Zeus.
*/
params [
    ["_ref", "", [""]]
];

if (!isServer) exitWith { true };
// Appel distant : réservé à un Zeus (ou à l'administrateur connecté).
if (isRemoteExecuted && {remoteExecutedOwner != 2}) then {
    private _caller = remoteExecutedOwner;
    private _isZeus = (allCurators findIf { owner (getAssignedCuratorUnit _x) == _caller }) >= 0;
    private _isAdmin = (admin _caller) > 0;
    if (!_isZeus && {!_isAdmin}) then { _ref = nil; };
};
if (isNil "_ref") exitWith {
    ["setCaseReference refusé : appelant ni Zeus ni administrateur", "WARN"] call comspec_sse_fnc_log;
    false
};

comspec_sse_caseRef = _ref;
missionNamespace setVariable ["comspec_sse_caseRef", _ref, true];
missionNamespace setVariable ["comspec_sse_caseReference", _ref];
// Même dossier pour le terminal SEEK d'Overwatch (une seule référence active).
missionNamespace setVariable ["COMSPEC_SSE_ActiveCase", toUpper (trim _ref), true];
[format ["caseReference=%1", _ref]] call comspec_sse_fnc_log;
true
