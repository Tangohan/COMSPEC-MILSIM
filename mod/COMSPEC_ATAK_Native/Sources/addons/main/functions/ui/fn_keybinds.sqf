/*
    Raccourcis clavier de l'ATAK, réglables dans le téléphone (Réglages > Accès et touches) en plus du menu CBA
    (Échap > Options > Contrôles > Configurer les addons > COMSPEC ATAK). Même enregistrement que le bouton OK du menu
    CBA : touches de l'action dans cba_keybinding_actions, gestionnaires de touches CBA remplacés, registre du profil
    (cba_keybinding_registry_v3). Les gestionnaires du téléphone en main (fn_open, fn_displayLoad) relisent
    CBA_fnc_getKeybind : la nouvelle touche marche tout de suite, téléphone rangé, porté ou en main.
    Seule la première touche de l'action change ; les touches alternatives posées dans le menu CBA restent.
    ["list"]                  -> [[action, groupe, titre, aide, icône, en chantier]...] (en chantier : grisé W.I.P,
                                 touche non modifiable)
    ["get", action]           -> [touche [DIK, [maj, ctrl, alt]] ou [], touche par défaut ou []]
    ["name", touche]          -> texte de la touche (« Ctrl+U »), « AUCUNE » sans touche
    ["set", action, touche]   enregistre ([] : aucune touche) ; renvoie true si c'est fait
    ["reset", action]         remet la touche par défaut ; ["resetAll"] : toutes
    ["capture", action]       attend la prochaine touche (téléphone en main, 10 s) ; ["capture", ""] annule
    ["capturing"]             action en attente de touche, ou ""
    ["users", touche, action] titres des autres actions COMSPEC ATAK sur cette touche
    ["key", params KeyDown]   en tête des gestionnaires KeyDown du téléphone : true si la touche est prise ici
*/
params [["_mode", ""], ["_a", ""], ["_b", []]];
private _addon = "COMSPEC ATAK";
private _list = [
    ["PhoneMain", "PHONE", "Prendre en main / reposer", "Miniature ou rangé : le téléphone passe en grand, à la souris. En grand : il redevient la miniature.", "ui_hand"],
    ["PhoneCarry", "PHONE", "Sortir / ranger (porté)", "Affiche ou range la miniature dans un coin de l'écran : on continue à jouer.", "ui_collapse"],
    ["PhoneHold", "PHONE", "Prendre en main (touche alternative)", "Même effet que la touche principale.", "ui_hand"],
    ["PhoneJpc", "PHONE", "Tablette sur le JPC", "Relève ou rabat la tablette du porte-plaques : vue libre, arme disponible.", "app_accessories", ["wip"] call comspec_atak_native_fnc_jpcScreen],
    ["PhoneOrient", "MINI", "Orientation", "Miniature verticale ou horizontale.", "ui_rotate"],
    ["PhonePosition", "MINI", "Position de la miniature", "Coin suivant : bas droit, bas gauche, haut gauche, haut droit, milieu.", "ui_expand"],
    ["PhoneZoomIn", "MINI", "Zoomer la carte", "Zoom avant, aussi téléphone porté en marchant ou en conduisant.", "ui_sort_up"],
    ["PhoneZoomOut", "MINI", "Dézoomer la carte", "Zoom arrière, aussi téléphone porté.", "ui_sort_down"],
    ["PhoneNight", "QUICK", "Mode nuit", "Normal, filtre rouge ou écran sombre.", "qs_night"],
    ["PhoneSilent", "QUICK", "Mode discrétion", "Coupe tous les sons du téléphone.", "qs_silent"],
    ["PhonePanic", "QUICK", "Bouton PANIQUE", "Deux appuis pour envoyer l'alerte.", "app_alerts"],
    ["LockMark", "DRONE", "Marquer la cible (réticule)", "Caméra du drone si sa vue est à l'écran, sinon l'appareil photo.", "app_reco"],
    ["DroneView", "DRONE", "Vue drone sur la carte", "Carte, vue drone ou vue incrustée.", "app_drone"],
    ["DroneHunt", "DRONE", "Drone : RECHERCHE", "Le drone appairé cherche autour de lui.", "app_drone"],
    ["DroneFollowMe", "DRONE", "Drone : ME SUIVRE", "Le drone appairé vous suit.", "app_drone"],
    ["DroneRtb", "DRONE", "Drone : RTB", "Retour au point de décollage et atterrissage.", "app_drone"],
    ["DroneLand", "DRONE", "Drone : ATTERRIR", "Le drone appairé se pose sur place.", "app_drone"],
    ["GestureFinger", "GESTURE", "Doigt d'honneur", "Geste du bras gauche (réglage d'addon « Gestes doigt d'honneur »).", "ui_hand"],
    ["GestureFingerAim", "GESTURE", "Doigt d'honneur libre (maintenir)", "Bras tendu vers le joueur visé tant que la touche est maintenue.", "ui_hand"]
];
private _key = { toLower format ["%1$%2", _addon, _this] };
// Fiche CBA de l'action : [titre, aide, touches, défaut, code bas, code haut, maintien, délai, sous-catégorie].
private _info = {
    if (isNil "cba_keybinding_actions") exitWith { nil };
    cba_keybinding_actions getVariable (_this call _key)
};
private _rerender = {
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "SETTINGS") then {
        [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
};
// Touche nulle (DIK 0 ou Échap) = pas de touche, comme dans CBA.
private _valid = { params ["_k"]; _k isEqualType [] && {(count _k) isEqualTo 2} && {(_k select 0) isEqualType 0} && {(_k select 0) > 1} };

switch (_mode) do {
    case "list": { _list };
    case "get": {
        private _i = _a call _info;
        if (isNil "_i") exitWith { [[], []] };
        private _cur = (_i select 2) param [0, []];
        private _def = _i select 3;
        [[[], _cur] select ([_cur] call _valid), [[], _def] select ([_def] call _valid)]
    };
    case "name": {
        if !([_a] call _valid) exitWith { "AUCUNE" };
        private _n = _a call CBA_fnc_localizeKey;
        [_n, "?"] select (isNil "_n")
    };
    case "users": {
        if !([_a] call _valid) exitWith { [] };
        private _out = [];
        {
            _x params ["_id", "", "_title"];
            if (_id isNotEqualTo _b) then {
                private _i = _id call _info;
                if (!isNil "_i" && {_a in (_i select 2)}) then { _out pushBack _title; };
            };
        } forEach _list;
        _out
    };
    case "set": {
        private _i = _a call _info;
        if (isNil "_i") exitWith { false };
        _i params ["", "", "_old", "", "_down", "_up", "_hold", "_delay"];
        private _binds = +_old;
        if ([_b] call _valid) then {
            // Touche de modification seule (Maj, Ctrl, Alt) : le modificateur compte comme appuyé, comme dans CBA.
            _b params ["_dik", "_mods"];
            _mods = +_mods;
            if (_dik in [0x2A, 0x36]) then { _mods set [0, true]; };
            if (_dik in [0x1D, 0x9D]) then { _mods set [1, true]; };
            if (_dik in [0x38, 0xB8]) then { _mods set [2, true]; };
            _b = [_dik, _mods];
            // Touches alternatives gardées ; la même touche parmi elles est retirée pour ne pas la poser deux fois.
            _binds = [_b] + ((_old select [1]) select { _x isNotEqualTo _b });
        } else {
            if ((count _binds) > 0) then { _binds deleteAt 0; };
        };
        _i set [2, _binds];
        private _act = _a call _key;
        {
            if (_down isNotEqualTo {}) then {
                [_x select 0, _x select 1, _down, "keyDown", format ["%1_down_%2", _act, _forEachIndex], _hold, _delay] call CBA_fnc_addKeyHandler;
            };
            if (_up isNotEqualTo {}) then {
                [_x select 0, _x select 1, _up, "keyUp", format ["%1_up_%2", _act, _forEachIndex]] call CBA_fnc_addKeyHandler;
            };
        } forEach _binds;
        for "_n" from (count _binds) to ((count _old) - 1) do {
            [format ["%1_down_%2", _act, _n], "keydown"] call CBA_fnc_removeKeyHandler;
            [format ["%1_up_%2", _act, _n], "keyup"] call CBA_fnc_removeKeyHandler;
        };
        private _reg = profileNamespace getVariable "cba_keybinding_registry_v3";
        if (!isNil "_reg") then {
            [_reg, _act, _binds] call CBA_fnc_hashSet;
            [] call comspec_atak_native_fnc_saveProfile;
        };
        // Pastille « prendre en main » du téléphone porté (fn_layoutApply) : affiche la nouvelle touche.
        if (!isNull ([] call comspec_atak_native_fnc_display)) then { [{ [] call comspec_atak_native_fnc_layoutApply; }] call CBA_fnc_execNextFrame; };
        ["INFO", "KEYS", format ["%1 -> %2", _a, ["name", _b] call comspec_atak_native_fnc_keybinds]] call comspec_atak_native_fnc_log;
        true
    };
    case "reset": {
        (["get", _a] call comspec_atak_native_fnc_keybinds) params ["_cur", "_def"];
        if (_cur isEqualTo _def) exitWith { false };
        ["set", _a, _def] call comspec_atak_native_fnc_keybinds
    };
    case "resetAll": {
        private _n = 0;
        { if (["reset", _x select 0] call comspec_atak_native_fnc_keybinds) then { _n = _n + 1; }; } forEach _list;
        _n
    };
    case "capture": {
        if ((_list findIf { (_x select 0) isEqualTo _a && {_x param [5, false]} }) >= 0) exitWith { false };
        private _cap = [_a, diag_tickTime + 10];
        uiNamespace setVariable ["COMSPEC_ATAK_KeyCapture", _cap];
        call _rerender;
        // Fin d'attente sans touche : la ligne revient à la touche actuelle (sauf si une autre attente a commencé).
        if (_a isNotEqualTo "") then {
            [{
                if ((uiNamespace getVariable ["COMSPEC_ATAK_KeyCapture", ["", 0]]) isNotEqualTo _this) exitWith {};
                uiNamespace setVariable ["COMSPEC_ATAK_KeyCapture", ["", 0]];
                if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "SETTINGS") then {
                    ["SETTINGS"] call comspec_atak_native_fnc_pageRender;
                };
            }, _cap, 10.1] call CBA_fnc_waitAndExecute;
        };
        true
    };
    case "capturing": {
        (uiNamespace getVariable ["COMSPEC_ATAK_KeyCapture", ["", 0]]) params [["_act", ""], ["_until", 0]];
        if (_act isEqualTo "" || {diag_tickTime > _until}) exitWith { "" };
        // Autre page ouverte entre-temps : plus d'attente.
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "SETTINGS") exitWith { "" };
        _act
    };
    case "key": {
        _a params ["", "_dik", "_shift", "_ctrl", "_alt"];
        // Même appui vu par le second gestionnaire du téléphone (fn_open) : déjà pris.
        if ((uiNamespace getVariable ["COMSPEC_ATAK_KeyCapFrame", -1]) isEqualTo diag_frameNo) exitWith { true };
        private _act = ["capturing"] call comspec_atak_native_fnc_keybinds;
        if (_act isEqualTo "") exitWith { false };
        // Maj, Ctrl ou Alt seuls : on attend la touche qui suit.
        if (_dik in [0x2A, 0x36, 0x1D, 0x9D, 0x38, 0xB8]) exitWith { true };
        uiNamespace setVariable ["COMSPEC_ATAK_KeyCapture", ["", 0]];
        uiNamespace setVariable ["COMSPEC_ATAK_KeyCapFrame", diag_frameNo];
        // Échap : annule sans fermer le téléphone.
        if (_dik isEqualTo 0x01) exitWith { call _rerender; true };
        private _bind = [_dik, [_shift, _ctrl, _alt]];
        ["set", _act, _bind] call comspec_atak_native_fnc_keybinds;
        private _title = ((_list select { (_x select 0) isEqualTo _act }) param [0, ["", "", _act]]) select 2;
        [format ["%1 : %2", (_title) call comspec_atak_native_fnc_tr, ["name", _bind] call comspec_atak_native_fnc_keybinds], 2.5] call comspec_atak_native_fnc_toast;
        call _rerender;
        true
    };
    default { nil };
};
