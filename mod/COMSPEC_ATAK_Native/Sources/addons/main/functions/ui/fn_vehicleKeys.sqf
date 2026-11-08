/*
    Touches du téléphone à bord d'un véhicule ou d'un aéronef, fenêtres de bord d'autres mods, et main rendue proprement.
    ["tick"]    (XEH_postInitClient, chaque seconde) : à bord, les fenêtres ouvertes par-dessus le jeu (écran de cockpit
                ou interface de bord d'un autre mod, qui gardent le clavier et la souris : les raccourcis CBA, posés sur le
                display de mission 46, ne les voient pas) reçoivent les touches « prendre en main » (principale et
                alternative) et « interagir avec la miniature ». Chaque fenêtre relayée est journalisée une fois [ATAK].
                Un appui vu à la fois ici et par CBA ne compte qu'une fois (fn_interactToggle / fn_miniInteract).
    ["parent"]  (fn_open) : display sur lequel ouvrir le téléphone en main. Hors véhicule : 46 (12 à défaut). À bord :
                la fenêtre la plus haute au-dessus de 46 (cockpit cliquable A-10C, MFD…) : un display créé sur 46 passait
                SOUS elle, invisible alors que la souris et les commandes de vol étaient prises (2.9.41).
    ["covered", display] : vrai si une fenêtre qui n'est pas au téléphone est au-dessus de lui (téléphone invisible).
    ["reset", raison]   : remet à zéro tout l'état d'interaction (descente, changement de véhicule ou de place, mort,
                nouvelle unité, action ACE « ATAK : réparer l'affichage ») : téléphone en main rendu au jeu (la miniature
                revient si elle était affichée ou si le téléphone était en main), displays du téléphone orphelins fermés,
                mode miniature / grand du joueur remis, drapeaux, vol stationnaire, alternat radio, menu d'actions,
                relais posés sur les fenêtres de bord retirés. Journal [ATAK].
    ["open"]    (fn_displayLoad, téléphone en main) : pilote aux commandes d'un aéronef, avertissement discret (la souris
                sert au téléphone, plus au vol ; le clavier reste au vol) ; hélicoptère presque immobile en vol :
                vol stationnaire automatique engagé si le joueur le permet (réglage comspec_atak_native_phone_autohover).
    ["repair"]  action ACE « ATAK : réparer l'affichage » : « reset », puis téléphone refermé et miniature rouverte.
    ["close"]   (fn_displayUnload) : vol stationnaire engagé par le téléphone rendu au pilote.
*/
params [["_mode", "tick"], ["_arg", nil]];
if (!hasInterface) exitWith { false };
disableSerialization;
// Le téléphone ou une fenêtre ouverte sur lui (parents jusqu'au display 88500).
private _ours = {
    params ["_d"];
    private _n = 0;
    private _ok = false;
    while { !isNull _d && {_n < 16} } do {
        if ((ctrlIDD _d) isEqualTo 88500) exitWith { _ok = true; };
        _d = displayParent _d;
        _n = _n + 1;
    };
    _ok
};
// Fenêtre la plus haute au-dessus du display de mission (enfants successifs de 46).
private _top = {
    private _t = findDisplay 46;
    private _n = 0;
    while { !isNull _t && {_n < 16} } do {
        private _c = displayChild _t;
        if (isNull _c) exitWith {};
        _t = _c;
        _n = _n + 1;
    };
    _t
};
private _dname = {
    params ["_d"];
    format ["IDD %1, %2 contrôle(s)", ctrlIDD _d, count (allControls _d)]
};
private _log = { ["INFO", "ATAK", _this] call comspec_atak_native_fnc_log; };
switch (_mode) do {
    case "parent": {
        private _p = findDisplay 46;
        if (isNull _p) exitWith { findDisplay 12 };
        if (isNull objectParent player) exitWith { _p };
        // Fenêtre la plus haute qui n'est pas au téléphone (l'ancien téléphone peut encore se fermer) ni le menu pause ;
        // la carte (12) seulement affichée (cachée, ses enfants le seraient aussi : on regarde au-dessus d'elle).
        private _c = _p;
        for "_i" from 1 to 16 do {
            _c = displayChild _c;
            if (isNull _c || {[_c] call _ours} || {(ctrlIDD _c) isEqualTo 49}) exitWith {};
            if ((ctrlIDD _c) isNotEqualTo 12 || {visibleMap}) then { _p = _c; };
        };
        if ((ctrlIDD _p) isNotEqualTo 46) then {
            format ["Téléphone ouvert par-dessus une fenêtre de bord d'un autre mod (%1, véhicule %2) : elle prenait la souris", [_p] call _dname, typeOf (objectParent player)] call _log;
        };
        _p
    };
    // Avant une ouverture par une touche : displays du téléphone orphelins (plus suivis par l'état) fermés, état « en main »
    // sans display remis à « rangé ». Vrai si quelque chose a été réparé (journal [ATAK]).
    case "heal": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
        private _fixed = false;
        {
            if ((ctrlIDD _x) isEqualTo 88500 && {_x isNotEqualTo _d}) then {
                format ["Display du téléphone orphelin fermé avant l'ouverture (%1)", [_x] call _dname] call _log;
                _x closeDisplay 2;
                _fixed = true;
            };
        } forEach allDisplays;
        if (isNull _d && {(uiNamespace getVariable ["COMSPEC_ATAK_MiniPrevMode", ""]) isNotEqualTo ""}) then {
            "Interaction miniature restée active sans téléphone affiché : mode du joueur remis" call _log;
            profileNamespace setVariable ["COMSPEC_ATAK_Mode", uiNamespace getVariable ["COMSPEC_ATAK_MiniPrevMode", "FULL"]];
            uiNamespace setVariable ["COMSPEC_ATAK_MiniPrevMode", ""];
            _fixed = true;
        };
        if (isNull _d && {_s getOrDefault ["interactive", false]}) then { _s set ["interactive", false]; };
        _fixed
    };
    case "covered": {
        private _d = _arg;
        if (isNil "_d" || {isNull _d}) exitWith { false };
        // Hors véhicule : jamais (le téléphone s'ouvre sur 46, comportement d'avant la 2.9.41).
        if (isNull objectParent player) exitWith { false };
        private _t = call _top;
        !isNull _t && {(ctrlIDD _t) isNotEqualTo 12} && {!([_t] call _ours)}
    };
    case "reset": {
        private _why = if (isNil "_arg") then { "?" } else { _arg };
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
        private _inHand = !isNull _d && {_s getOrDefault ["interactive", false]};
        private _hud = !isNull _d && {!(_s getOrDefault ["interactive", false])};
        private _down = _why in ["mort ou inconscient", "nouvelle unité"];
        format ["Remise à zéro de l'interaction (%1) : téléphone %2%3", _why, ["rangé", ["porté (miniature)", "en main"] select _inHand] select (!isNull _d),
            ["", " (interaction miniature)"] select ((uiNamespace getVariable ["COMSPEC_ATAK_MiniPrevMode", ""]) isNotEqualTo "")] call _log;
        // Mode miniature / grand du joueur remis (interaction miniature, fn_miniInteract) avant la fermeture.
        private _prev = uiNamespace getVariable ["COMSPEC_ATAK_MiniPrevMode", ""];
        if (_prev isNotEqualTo "") then {
            profileNamespace setVariable ["COMSPEC_ATAK_Mode", _prev];
            uiNamespace setVariable ["COMSPEC_ATAK_MiniPrevMode", ""];
        };
        uiNamespace setVariable ["COMSPEC_ATAK_ToggleFrame", -1];
        uiNamespace setVariable ["COMSPEC_ATAK_PilotWarnAt", -1e9];
        _s set ["drag", []];
        // Displays du téléphone restés ouverts en dehors du display courant (téléphone caché sous une fenêtre de bord).
        {
            if ((ctrlIDD _x) isEqualTo 88500 && {_x isNotEqualTo _d}) then {
                format ["Display du téléphone orphelin fermé (%1)", [_x] call _dname] call _log;
                _x closeDisplay 2;
            };
        } forEach allDisplays;
        // Téléphone en main : rendu au jeu ; la miniature revient (sauf mort ou nouvelle unité : rangé).
        if (_down) then { uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false]; } else {
            if (_inHand || _hud) then { uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true]; };
        };
        if (_inHand || {_down && _hud}) then { [] call comspec_atak_native_fnc_close; };
        if (isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull])) then { _s set ["interactive", false]; };
        ["close"] call comspec_atak_native_fnc_vehicleKeys;
        ["release"] call comspec_atak_native_fnc_keyPass;
        // Relais posés sur les fenêtres de bord : retirés (reposés par « tick » tant que l'on est à bord).
        {
            private _eh = _x getVariable ["COMSPEC_ATAK_VehKeyEh", -1];
            if (_eh >= 0) then {
                _x displayRemoveEventHandler ["KeyDown", _eh];
                _x setVariable ["COMSPEC_ATAK_VehKeyEh", -1];
            };
        } forEach allDisplays;
        // Descente : une fenêtre de bord encore ouverte 2 s après (mod tiers qui garde souris et clavier) est signalée.
        if (isNull objectParent player && {!_down}) then { [{ ["leftover"] call comspec_atak_native_fnc_vehicleKeys; }, [], 2] call comspec_atak_native_fnc_waitAndExec; };
        // Menu d'actions rendu si plus rien n'est affiché (fn_open le réinstalle à la prochaine ouverture).
        if (isNull ([] call comspec_atak_native_fnc_display)) then { { inGameUISetEventHandler [_x, ""]; } forEach ["PrevAction", "NextAction", "Action"]; };
        true
    };
    // Action ACE sur soi « ATAK : réparer l'affichage » : tout remis à zéro, téléphone refermé (miniature comprise),
    // état forcé à « rangé » si un display ne se ferme pas, puis la miniature revient si elle était affichée.
    case "repair": {
        private _was = !isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull]);
        ["reset", "réparation manuelle (action ACE)"] call comspec_atak_native_fnc_vehicleKeys;
        uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
        if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_close; };
        ("COMSPEC_ATAK_Hud" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
        [{
            params ["_was"];
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            private _d = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
            if (!isNull _d) then {
                ["WARNING", "ATAK", format ["Réparation : display du téléphone qui ne se fermait pas (IDD %1) oublié, état remis à rangé", ctrlIDD _d]] call comspec_atak_native_fnc_log;
                _d closeDisplay 2;
                uiNamespace setVariable ["COMSPEC_ATAK_Display", displayNull];
                _s set ["display", displayNull];
            };
            _s set ["interactive", false];
            _s set ["drag", []];
            uiNamespace setVariable ["COMSPEC_ATAK_Arrange", false];
            if (_was && {([true] call comspec_atak_native_fnc_canUse) isEqualTo ""}) then {
                uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
                [false] call comspec_atak_native_fnc_open;
            };
            ["Affichage de l'ATAK remis à zéro", 3] call comspec_atak_native_fnc_toast;
            ["INFO", "ATAK", format ["Réparation de l'affichage terminée (miniature %1)", ["rangée", "rouverte"] select _was]] call comspec_atak_native_fnc_log;
        }, [_was], 0.5] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "leftover": {
        private _t = call _top;
        if (isNull _t || {(ctrlIDD _t) in [12, 46, 49]} || {[_t] call _ours}) exitWith { false };
        format ["Fenêtre d'un autre mod encore ouverte après la descente (%1) : elle prend la souris et le clavier (action ACE « ATAK : réparer l'affichage », ou fermer cette fenêtre)", [_t] call _dname] call _log;
        true
    };
    case "tick": {
        if (isNull objectParent player) exitWith { false };
        private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
        // Fenêtres empilées au-dessus du display de mission : seules celles-ci sont journalisées (la carte 12 et les
        // displays permanents reçoivent le relais sans bruit dans le journal).
        private _chain = [];
        private _t = findDisplay 46;
        for "_i" from 1 to 16 do {
            if (isNull _t) exitWith {};
            _t = displayChild _t;
            if (!isNull _t) then { _chain pushBack _t; };
        };
        {
            private _d = _x;
            if (!isNull _d && {!([_d] call _ours)} && {!((ctrlIDD _d) in [0, 46, 49, 312, 313])} && {(_d getVariable ["COMSPEC_ATAK_VehKeyEh", -1]) < 0}) then {
                if (_d in _chain && {(ctrlIDD _d) isNotEqualTo 12}) then {
                    format ["Fenêtre au-dessus du jeu à bord (écran de cockpit ou autre mod : %1, véhicule %2) : touches du téléphone relayées", [_d] call _dname, typeOf (objectParent player)] call _log;
                };
                _d setVariable ["COMSPEC_ATAK_VehKeyEh", _d displayAddEventHandler ["KeyDown", {
                    params ["", "_key", "_shift", "_ctrl", "_alt"];
                    // Téléphone en main ET visible : ses propres gestionnaires de touches (fn_open, fn_displayLoad) s'en
                    // chargent. Caché sous cette fenêtre (2.9.41) : la touche est prise ici, sinon plus rien ne répondait.
                    private _ph = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
                    if (!isNull _ph && {(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false]} && {!(["covered", _ph] call comspec_atak_native_fnc_vehicleKeys)}) exitWith { false };
                    private _hit = {
                        params ["_action", "_default"];
                        private _kb = ["COMSPEC ATAK", _action] call CBA_fnc_getKeybind;
                        private _bind = if (isNil "_kb") then { _default } else { _kb select 5 };
                        _bind params [["_k", -1], ["_mods", [false, false, false]]];
                        _k > 0 && {_k isEqualTo _key} && {_mods isEqualTo [_shift, _ctrl, _alt]}
                    };
                    if ((["PhoneMain", [0x16, [false, false, true]]] call _hit) || {["PhoneHold", [0x16, [true, true, false]]] call _hit}) exitWith {
                        [] call comspec_atak_native_fnc_interactToggle;
                        true
                    };
                    if (["PhoneMiniUse", [0x16, [true, false, false]]] call _hit) exitWith { [] call comspec_atak_native_fnc_miniInteract; true };
                    false
                }]];
            };
        } forEach allDisplays;
        // Téléphone en main recouvert par une fenêtre ouverte après lui : signalé une fois (la touche le remet devant).
        if (!isNull _phone && {(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false]}) then {
            private _t = call _top;
            if (!isNull _t && {(ctrlIDD _t) isNotEqualTo 12} && {!([_t] call _ours)} && {!(_t getVariable ["COMSPEC_ATAK_CoverLogged", false])}) then {
                _t setVariable ["COMSPEC_ATAK_CoverLogged", true];
                format ["Téléphone en main recouvert par une fenêtre d'un autre mod (%1) : la touche Prendre en main le remet au premier plan", [_t] call _dname] call _log;
            };
        };
        true
    };
    case "open": {
        private _v = objectParent player;
        if (isNull _v || {!(_v isKindOf "Air")} || {(currentPilot _v) isNotEqualTo player}) exitWith { false };
        private _airborne = !(isTouchingGround _v) && {((getPos _v) select 2) > 2};
        private _hover = false;
        if (_airborne && {_v isKindOf "Helicopter"} && {missionNamespace getVariable ["comspec_atak_native_phone_autohover", true]}
            && {!(isAutoHoverOn _v)} && {(abs (speed _v)) < 40}) then {
            player action ["AutoHover", _v];
            uiNamespace setVariable ["COMSPEC_ATAK_PhoneHover", _v];
            _hover = true;
        };
        // Une fois par minute au plus (bascule miniature / grand répétée).
        if (!_hover && {(diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PilotWarnAt", -1e9])) < 60}) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_PilotWarnAt", diag_tickTime];
        [{
            params ["_hover"];
            [["Commandes de vol à la souris suspendues tant que l'ATAK est en main (clavier actif) · Échap : rendre la main", "Vol stationnaire automatique engagé tant que l'ATAK est en main · Échap : rendre la main"] select _hover, 5] call comspec_atak_native_fnc_toast;
        }, [_hover]] call comspec_atak_native_fnc_nextFrame;
        true
    };
    case "close": {
        private _v = uiNamespace getVariable ["COMSPEC_ATAK_PhoneHover", objNull];
        uiNamespace setVariable ["COMSPEC_ATAK_PhoneHover", objNull];
        if (isNull _v || {(objectParent player) isNotEqualTo _v} || {(currentPilot _v) isNotEqualTo player}) exitWith { false };
        if (isAutoHoverOn _v) then { player action ["AutoHoverCancel", _v]; };
        true
    };
    default { false };
};
