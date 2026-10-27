/*
    Touches d'autres mods laissées passer quand le téléphone est en main. Le téléphone en main est un display ouvert
    par createDisplay au-dessus du display de mission (46) : les raccourcis CBA, posés sur le display 46, ne le voient
    plus. Sans ce relais, l'alternat radio TFAR (Verr. Maj, Ctrl+Verr. Maj, touches additionnelles) et ACRE2 ne
    marchait pas en plein écran (question d'un joueur relayée par TTRD, 26/10).
    Mods relayés : réglage « Touches d'autres mods avec le téléphone en main » (comspec_atak_native_pass_mods), noms des
    mods comme dans Options > Contrôles > Configurer les addons, séparés par des virgules (« TFAR, ACRE2 » par défaut).
    Rien ne part pendant une saisie dans un champ du téléphone (fn_keyBlock garde la touche).
    ["down", params KeyDown] -> true si un raccourci relayé a gardé la touche (son code a renvoyé true)
    ["up", params KeyUp]     -> relâche les raccourcis appuyés sur cette touche
    ["release"]              relâche tout ce qui est encore appuyé (téléphone refermé : pas d'émission bloquée)
    ["reset"]                liste des raccourcis relue au prochain appui (ouverture du téléphone)
*/
params [["_mode", ""], ["_a", []]];
private _held = uiNamespace getVariable ["COMSPEC_ATAK_KeyPassHeld", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_KeyPassHeld", _held];
// Code « relâché » de chaque raccourci appuyé, appelé une seule fois.
private _release = {
    params ["_dik", "_args"];
    { _args call _x; } forEach ((_held getOrDefault [_dik, [[]]]) select 0);
    _held deleteAt _dik;
};

switch (_mode) do {
    case "reset": { uiNamespace setVariable ["COMSPEC_ATAK_KeyPass", nil]; };
    case "release": {
        { [_x, [displayNull, _x, false, false, false]] call _release; } forEach (keys _held);
    };
    case "up": {
        private _dik = _a param [1, -1];
        if !(_dik in _held) exitWith { false };
        [_dik, _a] call _release;
        false
    };
    case "down": {
        _a params ["_disp", "_dik", ["_shift", false], ["_ctrl", false], ["_alt", false]];
        if (isNil "cba_keybinding_actions") exitWith { false };
        // Saisie dans un champ (téléphone ou fenêtre qui reçoit la touche) : pas d'alternat en tapant ; fn_keyBlock
        // garde ensuite la touche pour le champ.
        private _isEdit = { !isNull _this && {(ctrlType _this) isEqualTo 2} && {ctrlEnabled _this} && {ctrlShown _this} };
        private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
        if (!isNull _phone && {(focusedCtrl _phone) call _isEdit}) exitWith { false };
        if (!isNull _disp && {(focusedCtrl _disp) call _isEdit}) exitWith { false };
        // Touche déjà appuyée (répétition du clavier) : le code « appuyé » ne repart pas, comme dans CBA.
        if (_dik in _held) exitWith { (_held get _dik) select 1 };
        // Raccourcis des mods relayés : [touche, [maj, ctrl, alt], code appuyé, code relâché], relus à chaque ouverture.
        private _list = uiNamespace getVariable "COMSPEC_ATAK_KeyPass";
        if (isNil "_list") then {
            private _mods = ((missionNamespace getVariable ["comspec_atak_native_pass_mods", "TFAR, ACRE2"]) splitString ",") apply { toLower (_x trim [" ", 0]) };
            _mods = _mods select { _x isNotEqualTo "" };
            _list = [];
            {
                private _id = _x;
                private _mod = (_id splitString "$") param [0, ""];
                if (_mods findIf { (_mod find _x) isEqualTo 0 } >= 0) then {
                    private _i = cba_keybinding_actions getVariable _id;
                    if (!isNil "_i" && {_i isEqualType []}) then {
                        _i params ["", "", ["_binds", []], "", ["_down", {}], ["_up", {}]];
                        if (_down isEqualType "") then { _down = compile _down; };
                        if (_up isEqualType "") then { _up = compile _up; };
                        {
                            _x params [["_k", -1], ["_m", [false, false, false]]];
                            if (_k isEqualType 0 && {_k > 1}) then { _list pushBack [_k, _m, _down, _up]; };
                        } forEach _binds;
                    };
                };
            } forEach (allVariables cba_keybinding_actions);
            uiNamespace setVariable ["COMSPEC_ATAK_KeyPass", _list];
        };
        private _ups = [];
        private _hit = false;
        private _block = false;
        {
            _x params ["_k", "_m", "_down", "_up"];
            if (_k isEqualTo _dik && {_m isEqualTo [_shift, _ctrl, _alt]}) then {
                _hit = true;
                if (_down isNotEqualTo {} && {(_a call _down) isEqualTo true}) then { _block = true; };
                if (_up isNotEqualTo {}) then { _ups pushBack _up; };
            };
        } forEach _list;
        if (_hit) then { _held set [_dik, [_ups, _block]]; };
        _block
    };
    default { false };
};
