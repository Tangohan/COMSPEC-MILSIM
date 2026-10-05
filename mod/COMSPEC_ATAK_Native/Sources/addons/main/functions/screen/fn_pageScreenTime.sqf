/*
    App Temps d'écran (comme le bien-être numérique d'Android) : temps du téléphone allumé, en main ou porté,
    temps par app, temps de jeu par rôle (pilote, chef d'équipe…), aujourd'hui sur ce PC, et envoi à Athena.
    Données de fn_screenTime. Params (module, fn_pageRender) : [page, [x, y, largeur, hauteur]]
*/
params [["_page", "SCREENTIME"], ["_rect", [0, 0, 1, 1]]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["stTab", "MISSION"];
private _sess = missionNamespace getVariable ["COMSPEC_ATAK_ScreenSess", createHashMap];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _dur = {
    params ["_sec"];
    private _m = floor (_sec / 60);
    switch (true) do { case (_sec < 60): { format ["%1 s", round _sec] }; case (_m < 60): { format ["%1 min", _m] }; default { format ["%1 h %2", floor (_m / 60), [str (_m mod 60), "0" + str (_m mod 60)] select ((_m mod 60) < 10)] }; }
};
private _of = { params ["_kind"]; private _l = []; { (_x splitString "|") params ["_k", "_c"]; if (_k isEqualTo _kind) then { _l pushBack [_y select 1, _y select 0, _c]; }; } forEach _sess; _l sort false; _l };
// Barre proportionnelle en texte structuré (blocs pleins / vides).
private _bar = {
    params ["_v", "_max", ["_hex", "#5cc76b"]];
    private _n = 0 max (round (14 * _v / (_max max 1))) min 14;
    private _on = ""; private _off = "";
    for "_i" from 1 to 14 do { if (_i <= _n) then { _on = _on + "|"; } else { _off = _off + "|"; }; };
    format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t><t font='EtelkaMonospaceProBold' color='#2a3430'>%3</t>", _hex, _on, _off]
};
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['stTab', '%1']; [{ ['SCREENTIME'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _rows = [["segment", "", [["CETTE MISSION", "MISSION"] call _tabBtn, ["AUJOURD'HUI", "TODAY"] call _tabBtn, ["RÔLES", "ROLES"] call _tabBtn]]];
if !(missionNamespace getVariable ["comspec_atak_native_screen_time", true]) then {
    _rows pushBack ["text", "<t color='#f2ab33'>Le serveur a coupé l'enregistrement du temps d'écran et des temps par rôle.</t>"];
};
switch (_tab) do {
    case "TODAY": {
        (profileNamespace getVariable ["COMSPEC_ATAK_ScreenDay", ["", []]]) params ["_day", "_list"];
        private _date = (systemTime select [0, 3]) joinString "-";
        if (_day isNotEqualTo _date) then { _list = []; };
        private _tot = ((_list select { (_x select 0) isEqualTo "screen|total" }) param [0, ["", "", 0]]) select 2;
        _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_screentime.paa", format ["<t size='1.6' font='RobotoCondensedBold'>%1</t><br/><t color='#8a9a93'>d'écran aujourd'hui sur ce PC, toutes missions</t>", [_tot] call _dur]];
        private _apps = (_list select { ((_x select 0) select [0, 4]) isEqualTo "app|" }) apply { [_x select 2, _x select 1] };
        _apps sort false;
        _rows pushBack ["section", "Apps", ""];
        { _x params ["_sec", "_label"]; _rows pushBack ["info", _label, format ["%1  %2", [_sec, (_apps select 0) select 0] call _bar, [_sec] call _dur]]; } forEach (_apps select [0, 12]);
        if ((count _apps) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Rien aujourd'hui.</t>"]; };
    };
    case "ROLES": {
        private _roles = ["role"] call _of;
        ([player] call comspec_atak_native_fnc_roleKey) params ["", "_now"];
        _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_screentime.paa", format ["<t size='1.2' font='RobotoCondensedBold'>%1</t><br/><t color='#8a9a93'>rôle tenu en ce moment</t>", [_now, "—"] select (_now isEqualTo "")]];
        _rows pushBack ["section", "Temps par rôle (cette mission)", "Poste d'équipage, sinon rôle d'équipe de feu, sinon spécialité ou slot"];
        private _max = (_roles param [0, [1]]) select 0;
        { _x params ["_sec", "_label"]; _rows pushBack ["info", _label, format ["%1  %2", [_sec, _max, "#7fb6e6"] call _bar, [_sec] call _dur]]; } forEach _roles;
        if ((count _roles) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Pas encore de temps compté.</t>"]; };
        _rows pushBack ["buttons", [["CHOISIR MON RÔLE", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["grpTab", "FT"]; ["GROUP"] call comspec_atak_native_fnc_navigate; }]]];
    };
    default {
        private _scr = ["screen"] call _of;
        private _get = { params ["_c"]; ((_scr select { (_x select 2) isEqualTo _c }) param [0, [0]]) select 0 };
        private _tot = ["total"] call _get;
        _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_screentime.paa", format ["<t size='1.6' font='RobotoCondensedBold'>%1</t><br/><t color='#8a9a93'>d'écran pendant cette mission</t><br/><t size='0.85'>En main %2 · porté %3</t>",
            [_tot] call _dur, [["hand"] call _get] call _dur, [["carry"] call _get] call _dur]];
        private _apps = ["app"] call _of;
        _rows pushBack ["section", "Apps les plus utilisées", ""];
        private _max = (_apps param [0, [1]]) select 0;
        { _x params ["_sec", "_label"]; _rows pushBack ["info", [_label] call _esc, format ["%1  %2 · %3 %%", [_sec, _max] call _bar, [_sec] call _dur, round (100 * _sec / (_tot max 1))]]; } forEach (_apps select [0, 12]);
        if ((count _apps) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Pas encore de temps compté.</t>"]; };
        (missionNamespace getVariable ["COMSPEC_ATAK_ScreenSent", [-1, "jamais"]]) params ["_at", "_st"];
        private _pending = 0;
        { _pending = _pending + (_y select 1); } forEach (missionNamespace getVariable ["COMSPEC_ATAK_ScreenAcc", createHashMap]);
        _rows append [
            ["section", "Athena", "Temps d'écran et temps par rôle remontés sur votre fiche (toutes les 5 min)"],
            ["info", "Dernier envoi", if (_at < 0) then { "jamais" } else { format ["il y a %1 · %2", [diag_tickTime - _at] call _dur, _st] }],
            ["info", "En attente", [_pending] call _dur],
            ["buttons", [["ENVOYER MAINTENANT", { ["flush"] call comspec_atak_native_fnc_screenTime; [{ ['SCREENTIME'] call comspec_atak_native_fnc_pageRender; }, [], 0.5] call CBA_fnc_waitAndExecute; }, true]]]
        ];
    };
};
[_rows, _rect] call comspec_atak_native_fnc_formRender;
true
