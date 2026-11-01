/*
    Bridge ACE dogtags → SSE (sans wrap de fonctions ACE).

    IMPORTANT: ne plus remplacer getDogtagData / checkDogtag.
    Les wraps entraient dans la pile ACE Medical (InitPost / Check) et
    provoquaient C00000FD STACK_OVERFLOW avec generateData.

    Mode sûr: on ÉCRIT seulement ace_dogtags_dogtagData (aceDogtagSync)
    après génération / setIdentity. ACE lit alors son cache natif.
*/
if (missionNamespace getVariable ["comspec_sse_aceDogtagHooksInstalled", false]) exitWith { true };
if !([] call comspec_sse_fnc_aceDogtagIsPresent) exitWith { false };

missionNamespace setVariable ["comspec_sse_aceDogtagHooksInstalled", true];

["Hooks ACE dogtags: mode sync only (pas de wrap getDogtagData/checkDogtag)."] call comspec_sse_fnc_log;
true
