/*
    Log d’exception obligatoire (ne jamais catcher silencieusement).

    Usage: ["comspec_sse_fnc_initACE", _exception, "postInit"] call comspec_debug_fnc_exception;
*/
params [
    ["_fn", "", [""]],
    ["_exception", "", ["", []]],
    ["_context", "", [""]]
];

["ERROR", "EXCEPTION", "CATCH", format [
    "function=%1 exception=%2 context=%3",
    _fn, _exception, _context
]] call comspec_debug_fnc_log;

true
