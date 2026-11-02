/*
    Carte de l'app Routes (vue EDIT, fn_pageRoute) : nord en haut, elle ne suit pas le joueur.
    Params : [événement, arguments de l'événement de la carte]
      "draw" [carte]               routes (fn_routeDraw), route modifiée au premier plan, aperçu sous le curseur
      "move" [carte, x, y]         curseur
      "down" [carte, bouton, x, y] clic gauche : sur une étape, la choisir ; sinon selon le mode de l'app : ajouter en fin,
                                   insérer après l'étape choisie, déplacer l'étape choisie. Clic droit : plus d'étape choisie.
    Vue : une carte neuve montre d'abord le coin du monde avant son animation ; la vue (COMSPEC_ATAK_RteView) n'est
    enregistrée qu'une fois la carte calée sur le but posé par fn_pageRoute (même calage que l'app Plan).
    Après un clic, seule la fiche est refaite (fn_pageRoute "#refresh") : la carte garde sa vue.
*/
params [["_ev", ""], ["_a", []]];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_RteUi", createHashMap];
switch (_ev) do {
    case "draw": {
        _a params ["_map"];
        private _cp = ctrlPosition _map;
        private _mid = [(_cp select 0) + (_cp select 2) / 2, (_cp select 1) + (_cp select 3) / 2];
        if (_map getVariable ["comspec_rteReady", false]) then {
            uiNamespace setVariable ["COMSPEC_ATAK_RteView", [ctrlMapScale _map, _map ctrlMapScreenToWorld _mid]];
        } else {
            private _aim = _map getVariable ["comspec_rteAim", []];
            if ((count _aim) < 2) exitWith { _map setVariable ["comspec_rteReady", true]; };
            if ((diag_tickTime - (_map getVariable ["comspec_rteBorn", 0])) < 0.15 || {!ctrlMapAnimDone _map}) exitWith {};
            private _tries = _map getVariable ["comspec_rteFix", 0];
            private _sp = _map ctrlMapWorldToScreen [(_aim select 1) select 0, (_aim select 1) select 1];
            if ((_sp distance2D _mid) < 0.004 || {_tries >= 3}) exitWith { _map setVariable ["comspec_rteReady", true]; };
            _map setVariable ["comspec_rteFix", _tries + 1];
            private _last = _map getVariable ["comspec_rteLast", _aim select 1];
            private _st = _map ctrlMapWorldToScreen [_last select 0, _last select 1];
            private _fix = _map ctrlMapScreenToWorld [(_st select 0) + (_sp select 0) - (_mid select 0), (_st select 1) + (_sp select 1) - (_mid select 1)];
            _map setVariable ["comspec_rteLast", _fix];
            _map ctrlMapAnimAdd [0, _aim select 0, [_fix select 0, _fix select 1, 0]];
            ctrlMapAnimCommit _map;
        };
        // Moi : point et cap.
        private _me = getPosATL vehicle player;
        _map drawIcon ["\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa", [0.36, 0.85, 0.42, 1], _me, 22, 22, getDirVisual (vehicle player), "", 0];
        private _id = _ui getOrDefault ["id", ""];
        private _mine = ["isMine", _id] call comspec_atak_native_fnc_routeAction;
        private _k = ((((_cp select 3) / (safeZoneH * 0.7)) max 0.6) min 1.2);
        private _mode = _ui getOrDefault ["mode", "SEL"];
        // Aperçu sous le curseur seulement dans un mode de saisie (rien en mode CHOISIR).
        [_map, _k, _k max 0.95, _id, _ui getOrDefault ["sel", -1], [[], _ui getOrDefault ["cursor", []]] select (_mine && {_mode isNotEqualTo "SEL"}), _mode] call comspec_atak_native_fnc_routeDraw;
    };
    case "move": {
        _a params ["_map", "_mx", "_my"];
        _ui set ["cursor", _map ctrlMapScreenToWorld [_mx, _my]];
    };
    case "down": {
        _a params ["_map", "_btn", "_mx", "_my"];
        private _id = _ui getOrDefault ["id", ""];
        private _r = ["get", _id] call comspec_atak_native_fnc_routeAction;
        if ((count _r) isEqualTo 0) exitWith {};
        // Clic droit : sort du mode de saisie (ce qui est posé reste), sinon plus d'étape choisie.
        if (_btn isEqualTo 1) exitWith {
            if ((_ui getOrDefault ["mode", "SEL"]) isNotEqualTo "SEL") exitWith { ["#ui", ["modeEnd"]] call comspec_atak_native_fnc_pageRoute; };
            _ui set ["sel", -1];
            ["#refresh"] call comspec_atak_native_fnc_pageRoute;
        };
        if (_btn isNotEqualTo 0) exitWith {};
        private _st = _r get "steps";
        // Étape sous le clic (14 pixels) : choisie.
        private _hit = -1;
        private _best = pixelW * 14;
        {
            private _d = (_map ctrlMapWorldToScreen ((_x select 0) select [0, 2])) distance2D [_mx, _my];
            if (_d < _best) then { _best = _d; _hit = _forEachIndex; };
        } forEach _st;
        private _mode = _ui getOrDefault ["mode", "SEL"];
        private _sel = _ui getOrDefault ["sel", -1];
        if (_hit >= 0 && {!(_mode isEqualTo "MOVE" && {_sel >= 0})}) exitWith {
            _ui set ["sel", [_hit, -1] select (_hit isEqualTo _sel)];
            playSound "ClickSoft";
            ["#refresh"] call comspec_atak_native_fnc_pageRoute;
        };
        if !(["isMine", _id] call comspec_atak_native_fnc_routeAction) exitWith {
            ["INFO", "Route reçue : DUPLIQUER pour la modifier", 3, 10] call comspec_atak_native_fnc_notify;
        };
        // Mode CHOISIR : un clic hors d'une étape ne pose rien (plus d'étape choisie).
        if (_mode isEqualTo "SEL") exitWith {
            if (_sel >= 0) then { _ui set ["sel", -1]; ["#refresh"] call comspec_atak_native_fnc_pageRoute; };
        };
        private _w = _map ctrlMapScreenToWorld [_mx, _my];
        private _p = [_w select 0, _w select 1, 0];
        switch (true) do {
            case (_mode isEqualTo "MOVE" && {_sel >= 0}): {
                ["stepMove", [_id, _sel, _p]] call comspec_atak_native_fnc_routeAction;
                _ui set ["mode", "SEL"];
            };
            case (_mode isEqualTo "INSERT" && {_sel >= 0}): {
                private _i = ["stepAdd", [_id, _p, _sel + 1]] call comspec_atak_native_fnc_routeAction;
                if (_i >= 0) then { _ui set ["sel", _i]; };
            };
            default {
                private _i = ["stepAdd", [_id, _p]] call comspec_atak_native_fnc_routeAction;
                if (_i >= 0) then { _ui set ["sel", _i]; };
            };
        };
        playSound "ClickSoft";
    };
};
