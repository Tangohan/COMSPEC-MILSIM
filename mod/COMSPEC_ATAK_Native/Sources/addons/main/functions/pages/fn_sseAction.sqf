/*
    Actions de l'app SSE.
    Params : [action, argument]
      (sans action) : renvoie le sujet visé (homme à moins de 6 m, vivant ou non) ou objNull
      "case" : pose le dossier saisi pour tout l'élément     "clear" : efface le dossier actif
      "open" n : ferme l'ATAK et ouvre le terminal SEEK à la page n (0 accueil … 7 terrain)
      "face" : range l'ATAK, photographie le visage du sujet visé, puis rouvre l'app SSE
      "frs" type : fiche rapide FRS (FRM, FRC, FRO, FRT) pré-remplie (dossier, sujet visé), ouverte dans l'app FRS
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
// Dossier partagé : tenu par COMSPEC Link (addon connect). ATAK seul : message au lieu d'une erreur de script.
if (_act in ["case", "clear"] && {isNil "comspec_overwatch_connect_fnc_sseActiveCase"}) exitWith {
    ["WARNING", "Dossier partagé : COMSPEC Link doit être chargé", 3, 20] call comspec_atak_native_fnc_notify;
};
switch (_act) do {
    case "case": {
        private _ref = trim (["sseCase", ""] call comspec_atak_native_fnc_formValue);
        if (_ref isEqualTo "") exitWith { ["WARNING", "Saisissez la référence du dossier", 3, 20] call comspec_atak_native_fnc_notify; };
        ["set", _ref, true] call comspec_overwatch_connect_fnc_sseActiveCase;
        ["SUCCESS", format ["Dossier %1 utilisé par toute l'équipe", toUpper _ref], 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "clear": { ["clear"] call comspec_overwatch_connect_fnc_sseActiveCase; call _render; };
    case "frs": {
        // Fiche rapide : nouvelle fiche FRS du type demandé (FRM par défaut), rattachée au dossier actif.
        // Un brouillon déjà rédigé est gardé (seuls type et dossier changent) pour ne rien perdre.
        if !([] call comspec_atak_native_fnc_bridge) exitWith { ["WARNING", "Fiches : COMSPEC Overwatch doit être chargé", 3, 20] call comspec_atak_native_fnc_notify; };
        private _kind = [_arg, "FRM"] select !(_arg isEqualType "" && {_arg isNotEqualTo ""});
        private _draft = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
        private _case = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase;
        private _keep = (trim (_draft getOrDefault ["body", ""])) isNotEqualTo "";
        if (!_keep) then {
            _draft = createHashMap;
            private _t = call _target;
            private _lines = [];
            if (!isNull _t) then {
                private _who = trim format ["%1 %2", _t getVariable ["COMSPEC_SSE_FirstName", ""], _t getVariable ["COMSPEC_SSE_LastName", ""]];
                if (_who isEqualTo "") then { _who = name _t; };
                _lines pushBack format ["Sujet : %1 (%2), contrôlé à %3 au %4.", _who, ["décédé", "vivant"] select (alive _t), [dayTime, "HH:MM"] call BIS_fnc_timeToString, [_t, 8] call comspec_atak_native_fnc_gridRef];
                _draft set ["themes", ["PERSON"]];
            };
            if ((count _lines) > 0) then { _lines pushBack ""; };
            _draft set ["body", _lines joinString toString [10]];
        };
        _draft set ["kind", _kind];
        if (_case isNotEqualTo "") then { _draft set ["case", _case]; };
        uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", _draft];
        // Volets refermés : la fiche s'ouvre sur le texte.
        private _ui = uiNamespace getVariable ["COMSPEC_ATAK_FrsUi", createHashMap];
        { _ui set [_x, false]; } forEach ["head", "pj", "fab"];
        _ui set ["view", ""];
        uiNamespace setVariable ["COMSPEC_ATAK_FrsUi", _ui];
        ["INFO", format ["Fiche %1%2%3", _kind, ["", format [" · dossier %1", _case]] select (_case isNotEqualTo ""), ["", " (brouillon en cours gardé)"] select _keep], 3, 20] call comspec_atak_native_fnc_notify;
        [{ ["FRS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    };
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
