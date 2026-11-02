/*
    Touches du téléphone à bord d'un véhicule ou d'un aéronef, et main rendue proprement au pilote.
    ["tick"]   (XEH_postInitClient, chaque seconde) : à bord, les fenêtres ouvertes par-dessus le jeu (écran de cockpit
               ou interface de bord d'un autre mod, qui gardent le clavier : les raccourcis CBA, posés sur le display de
               mission 46, ne les voient pas) reçoivent les touches « prendre en main » (principale et alternative) et
               « interagir avec la miniature ». Un appui vu à la fois ici et par CBA ne compte qu'une fois
               (fn_interactToggle et fn_miniInteract ignorent un second appel dans la même image).
    ["open"]   (fn_displayLoad, téléphone en main) : pilote aux commandes d'un aéronef, avertissement discret (la souris
               sert au téléphone, plus au vol ; le clavier reste au vol) ; hélicoptère presque immobile en vol :
               vol stationnaire automatique engagé si le joueur le permet (réglage comspec_atak_native_phone_autohover).
    ["close"]  (fn_displayUnload) : vol stationnaire engagé par le téléphone rendu au pilote.
*/
params [["_mode", "tick"]];
if (!hasInterface) exitWith { false };
switch (_mode) do {
    case "tick": {
        if (isNull objectParent player) exitWith { false };
        private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
        {
            private _d = _x;
            if (!isNull _d && {_d isNotEqualTo _phone} && {!((ctrlIDD _d) in [46, 49, 312, 313, 88500])} && {(_d getVariable ["COMSPEC_ATAK_VehKeyEh", -1]) < 0}) then {
                _d setVariable ["COMSPEC_ATAK_VehKeyEh", _d displayAddEventHandler ["KeyDown", {
                    params ["", "_key", "_shift", "_ctrl", "_alt"];
                    if (isNull objectParent player) exitWith { false };
                    // Téléphone déjà en main : ses propres gestionnaires de touches (fn_open, fn_displayLoad) s'en chargent.
                    if (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull]) && {(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false]}) exitWith { false };
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
        }, [_hover]] call CBA_fnc_execNextFrame;
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
