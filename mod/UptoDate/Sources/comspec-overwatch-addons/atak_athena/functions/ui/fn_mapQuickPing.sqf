/*
    SHIFT+clic : Super ping visuel (poste + opérateurs).
*/
params [["_world", []], ["_kind", "", [""]]];
[_world] call comspec_overwatch_connect_fnc_superPingSend;
