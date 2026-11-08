/*
    Exécute un code après un délai (secondes de mission, CBA_missionTime), hors environnement suspendable. Mêmes
    paramètres que CBA_fnc_waitAndExecute :
      [code, arguments, délai] call comspec_atak_native_fnc_waitAndExec;
    Passe par la file du mod (fn_frameQueue) : une erreur de script dans le rappel n'est jamais rejouée et ne bloque
    ni les autres rappels ni le gestionnaire de CBA. File triée à l'insertion, en partant de la fin (un nouveau délai
    est presque toujours le plus tardif). Recherche et insertion dans un seul bloc non suspendable (isNil) : appelée
    depuis un script spawn, elle ne peut pas être coupée par le gestionnaire d'image qui retire la tête de file.
*/
params [["_code", {}, [{}]], ["_args", []], ["_delay", 0, [0]]];
if (isNil "COMSPEC_ATAK_WaitQ") then { ["init"] call comspec_atak_native_fnc_frameQueue; };
private _due = (missionNamespace getVariable ["CBA_missionTime", time]) + (_delay max 0);
isNil {
    private _w = COMSPEC_ATAK_WaitQ;
    private _i = count _w;
    while { _i > 0 && { (_w select (_i - 1) select 2) > _due } } do { _i = _i - 1; };
    _w insert [_i, [[_code, _args, _due]]];
};
nil
