/*
    App FRS (mise en page façon ATAK : texte au centre, volets Entête et Pièces jointes, barre du bas).
    Params : [action, argument]
      "head" / "pj" : ouvre ou ferme le volet Entête / Pièces jointes     "fab" : menu rond des pièces jointes
      "gallery" : photothèque à joindre     "folder" : fiches envoyées     "camera" : capture de la vue jointe
      "add" n : joint la photo n de la photothèque     "del" n : retire la pièce n
      "home" : lanceur     "full" : mini / plein écran     "send" : envoie la fiche et ses pièces
    Pièces (4 au plus) : uiNamespace COMSPEC_ATAK_FrsPieces = [[nature, chemin, nom, carroyage, auteur, légende]...]
*/
params [["_act", ""], ["_arg", -1]];
[] call comspec_atak_native_fnc_frsDraftSave;
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_FrsUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_FrsUi", _ui];
private _pieces = uiNamespace getVariable ["COMSPEC_ATAK_FrsPieces", []];
uiNamespace setVariable ["COMSPEC_ATAK_FrsPieces", _pieces];
private _max = 4;
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "FRS") then { ["FRS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _full = { if ((count _pieces) >= _max) exitWith { ["WARNING", format ["%1 pièces jointes au maximum par fiche", _max], 3, 20] call comspec_atak_native_fnc_notify; true }; false };
switch (_act) do {
    case "head": { _ui set ["head", !(_ui getOrDefault ["head", false])]; _ui set ["pj", false]; _ui set ["fab", false]; call _render; };
    case "pj": { _ui set ["pj", !(_ui getOrDefault ["pj", false])]; _ui set ["head", false]; _ui set ["view", ""]; if !(_ui get "pj") then { _ui set ["fab", false]; }; call _render; };
    case "fab": {
        _ui set ["fab", !(_ui getOrDefault ["fab", false])];
        if (_ui get "fab") then { _ui set ["pj", true]; _ui set ["head", false]; };
        call _render;
    };
    case "gallery": {
        ["list"] call comspec_atak_native_fnc_photoLibrary;
        _ui set ["view", "gallery"]; _ui set ["pj", true]; _ui set ["head", false];
        call _render;
    };
    case "back": { _ui set ["view", ""]; _ui set ["pj", true]; call _render; };
    case "folder": { _ui set ["view", "sent"]; _ui set ["pj", true]; _ui set ["head", false]; call _render; };
    case "add": {
        if (call _full) exitWith {};
        private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
        (_lib param [_arg, []]) params [["_path", ""], ["_name", ""]];
        if (_path isEqualTo "") exitWith {};
        if ((_pieces findIf { (_x select 1) isEqualTo _path }) >= 0) exitWith { ["INFO", "Photo déjà jointe", 2, 10] call comspec_atak_native_fnc_notify; };
        _pieces pushBack ["photo", _path, _name, [player, 8] call comspec_atak_native_fnc_gridRef, "", _name];
        _ui set ["view", ""];
        call _render;
    };
    case "camera": {
        if (call _full) exitWith {};
        // Capture de la vue, téléphone masqué le temps de la prise : envoyée avec la fiche.
        [] spawn {
            disableSerialization;
            private _d = [] call comspec_atak_native_fnc_display;
            private _hidden = [];
            if (!isNull _d) then { { if (ctrlShown _x) then { _x ctrlShow false; _hidden pushBack _x; }; } forEach (allControls _d); };
            uiSleep 0.15;
            private _name = format ["COMSPEC_FRS_%1.png", (floor (diag_tickTime * 1000)) toFixed 0];
            screenshot _name;
            uiSleep 0.3;
            { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;
            private _pieces = uiNamespace getVariable ["COMSPEC_ATAK_FrsPieces", []];
            _pieces pushBack ["capture", _name, format ["Capture %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString], [player, 8] call comspec_atak_native_fnc_gridRef, "", ""];
            uiNamespace setVariable ["COMSPEC_ATAK_FrsPieces", _pieces];
            ["SUCCESS", "Capture jointe à la fiche", 2, 20] call comspec_atak_native_fnc_notify;
            [{ ["FRS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
    };
    case "del": { _pieces deleteAt _arg; call _render; };
    case "home": { [{ ["LAUNCHER"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame; };
    case "full": { [{ [] call comspec_atak_native_fnc_modeToggle; }] call CBA_fnc_execNextFrame; };
    case "send": { [{ [] call comspec_atak_native_fnc_frsSubmit; }] call CBA_fnc_execNextFrame; };
};
true
