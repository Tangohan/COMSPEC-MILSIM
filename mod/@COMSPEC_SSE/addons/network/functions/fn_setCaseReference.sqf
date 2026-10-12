params [
    ["_ref", "", [""]]
];

comspec_sse_caseRef = _ref;
missionNamespace setVariable ["comspec_sse_caseRef", _ref, true];
missionNamespace setVariable ["comspec_sse_caseReference", _ref];
// Même dossier pour le terminal SEEK d'Overwatch (une seule référence active).
missionNamespace setVariable ["COMSPEC_SSE_ActiveCase", toUpper (trim _ref)];
[format ["caseReference=%1", _ref]] call comspec_sse_fnc_log;
true
