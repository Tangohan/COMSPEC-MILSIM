/*
    Vérifie si un module pont est activé (réglage admin Athena).
    Par défaut : activé si le catalogue n’a pas encore été reçu.
    Params: [_moduleId]
*/
params [["_moduleId", "", [""]]];
if (_moduleId isEqualTo "") exitWith { false };

private _mods = missionNamespace getVariable ["COMSPEC_AthenaModules", createHashMap];
if (!(_mods isEqualType createHashMap)) exitWith { true };
if ((count _mods) == 0) exitWith { true };

_mods getOrDefault [_moduleId, true]
