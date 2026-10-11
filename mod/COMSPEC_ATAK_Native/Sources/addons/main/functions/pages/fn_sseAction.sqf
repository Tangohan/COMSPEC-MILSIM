/*
    Actions de l'app SSE.
    Params : [action, argument]
      (sans action) : renvoie le sujet visé (homme à moins de 6 m, vivant ou non) ou objNull
      "case" : pose le dossier saisi pour tout l'élément     "clear" : efface le dossier actif
      "open" n : ferme l'ATAK et ouvre le terminal SEEK à la page n (0 accueil … 7 terrain)
      "face" : range l'ATAK, photographie le visage du sujet visé, puis rouvre l'app SSE
*/
params [["_act", ""], ["_arg", 0]];
private _target = {
    private _c = cursorObject;
    if (isNull _c || {!(_c isKindOf "CAManBase")}) then { _c = cursorTarget; };
    if (isNull _c || {!(_c isKindOf "CAManBase")} || {_c isEqualTo player} || {(player distance _c) > 6}) exitWith { objNull };
    _c
};
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "SSE") then { ["SSE"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
if (_act isEqualTo "") exitWith { call _target };
switch (_act) do {
    case "case": {
        private _ref = trim (["sseCase", ""] call comspec_atak_native_fnc_formValue);
        if (_ref isEqualTo "") exitWith { ["WARNING", "Saisissez la référence du dossier", 3, 20] call comspec_atak_native_fnc_notify; };
        ["set", _ref, true] call comspec_overwatch_connect_fnc_sseActiveCase;
        ["SUCCESS", format ["Dossier %1 utilisé par toute l'équipe", toUpper _ref], 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "clear": { ["clear"] call comspec_overwatch_connect_fnc_sseActiveCase; call _render; };
    case "face": {
        private _t = call _target;
        if (isNull _t) exitWith { ["WARNING", "Regardez la personne à photographier", 3, 20] call comspec_atak_native_fnc_notify; };
        // Pas de téléphone porté (HUD) réaffiché pendant le cliché.
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["suppressHudRestore", true];
        [] call comspec_atak_native_fnc_close;
        _s set ["suppressHudRestore", false];
        [{
            params ["_t"];
            // Le cliché prend ~0,2 s : on rouvre l'ATAK sur l'app SSE une fois l'image enregistrée
            // (ou tout de suite si la capture a été refusée : sujet trop loin, capture déjà en cours).
            [_t] call comspec_overwatch_connect_fnc_sseCaptureFacePhoto;
            [{ !(missionNamespace getVariable ["COMSPEC_SsePerson_PhotoBusy", false]) }, {
                [] call comspec_atak_native_fnc_open;
                [{ ["SSE"] call comspec_atak_native_fnc_navigate; }, [], 0.1] call CBA_fnc_waitAndExecute;
            }, [], 5, {
                [] call comspec_atak_native_fnc_open;
            }] call CBA_fnc_waitUntilAndExecute;
        }, [_t], 0.15] call CBA_fnc_waitAndExecute;
    };
    case "open": {
        private _t = call _target;
        // Le terminal SEEK est un écran à part : on range l'ATAK pour lui laisser la souris.
        [] call comspec_atak_native_fnc_close;
        [{ _this call comspec_overwatch_connect_fnc_sseOpenTerminal; }, [_t, _arg], 0.1] call CBA_fnc_waitAndExecute;
    };
};
true
