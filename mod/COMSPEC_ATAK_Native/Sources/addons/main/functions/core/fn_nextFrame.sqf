/*
    Exécute un code à l'image suivante, hors environnement suspendable. Mêmes paramètres que CBA_fnc_execNextFrame :
      [code, arguments] call comspec_atak_native_fnc_nextFrame;
    Passe par la file du mod (fn_frameQueue) : une erreur de script dans le rappel n'est jamais rejouée et ne bloque
    ni les autres rappels ni le gestionnaire de CBA.
*/
params [["_code", {}, [{}]], ["_args", []]];
if (isNil "COMSPEC_ATAK_NextFrameQ") then { ["init"] call comspec_atak_native_fnc_frameQueue; };
COMSPEC_ATAK_NextFrameQ pushBack [_code, _args, diag_frameNo];
nil
