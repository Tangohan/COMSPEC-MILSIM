/*
    Retourne le tampon journal SSE (copie).
    [] call comspec_sse_fnc_getLog
*/
private _buf = missionNamespace getVariable ["comspec_sse_logBuffer", []];
if (!(_buf isEqualType [])) exitWith { [] };
+_buf
