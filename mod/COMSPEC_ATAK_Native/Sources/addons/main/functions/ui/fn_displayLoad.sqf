// _texture : écran 3D de la tablette sur le JPC (texture UI, fn_jpcScreen), non interactif comme le HUD.
params ["_display", ["_interactive", true], ["_texture", false]];
disableSerialization;
if (!hasInterface || {isNull _display}) exitWith {};
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_state set ["display", _display];
_state set ["interactive", _interactive];
_state set ["texture", _texture];
_state set ["history", []];
_state set ["pageSig", []];
_state set ["pageSigPage", ""];
_state set ["mapCentered", false];
_state set ["drag", []];
_state set ["pageDrawn", ""];
uiNamespace setVariable ["COMSPEC_ATAK_Display", _display];
// Proportions d'un carré (hauteur / largeur en unités d'interface) : écran du joueur, ou texture 1024 x 576 du JPC.
uiNamespace setVariable ["COMSPEC_ATAK_Ratio", [pixelH / pixelW, 1024 / 576] select _texture];
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_DockControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_BadgeSig", []];
// Calques Android (fn_lockScreen, fn_quickSettings, fn_toast) : leurs contrôles sont morts avec l'ancien display.
uiNamespace setVariable ["COMSPEC_ATAK_LockUp", false];
uiNamespace setVariable ["COMSPEC_ATAK_LockCtrls", []];
uiNamespace setVariable ["COMSPEC_ATAK_ShadeOpen", false];
uiNamespace setVariable ["COMSPEC_ATAK_ShadeCtrls", []];
uiNamespace setVariable ["COMSPEC_ATAK_ShadeHandle", []];
uiNamespace setVariable ["COMSPEC_ATAK_ToastPill", []];
// Écran 3D du JPC : une carte ne se dessine pas dans une texture UI, vue aérienne à la place (fn_jpcMap).
if (_texture) then { ["init", _display] call comspec_atak_native_fnc_jpcMap; };
// Textes fixes de l'écran (ui/displays.hpp) dans la langue du téléphone (fn_tr ; sans effet en français).
if ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isNotEqualTo "fr") then {
    { if (ctrlType _x in [0, 1, 16]) then { private _t = ctrlText _x; if (_t isNotEqualTo "") then { _x ctrlSetText (_t call comspec_atak_native_fnc_tr); }; }; } forEach (allControls _display); // i18n:brut
};

// Les raccourcis CBA ne passent pas quand le téléphone est en main : on reprend ici les touches « porter » et « interagir ».
if (_interactive) then {
    _display displayAddEventHandler ["KeyDown", {
        params ["", "_key", "_shift", "_ctrl"];
        // Suppr : efface le marqueur pointé (ou sélectionné), sauf pendant la saisie d'un texte.
        if (_key isEqualTo 0xD3 && {(ctrlType (focusedCtrl (_this select 0))) isNotEqualTo 2}) exitWith {
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            if ((_s getOrDefault ["activePage", ""]) isNotEqualTo "MAP") exitWith { false };
            private _map = (_this select 0) displayCtrl 88530;
            private _m = [_map, getMousePosition] call comspec_atak_native_fnc_markerAt;
            if (_m isEqualTo "") then { _m = _s getOrDefault ["selectedMarker", ""]; };
            if (_m isEqualTo "") exitWith { false };
            [_m] call comspec_atak_native_fnc_markerDelete;
            true
        };
        // Mêmes touches que les raccourcis CBA (y compris si le joueur les a changées).
        params ["", "", "", "", "_alt"];
        private _hit = {
            params ["_action", "_default"];
            private _kb = ["COMSPEC ATAK", _action] call CBA_fnc_getKeybind;
            private _bind = if (isNil "_kb") then { _default } else { _kb select 5 };
            _bind params [["_k", -1], ["_mods", [false, false, false]]];
            _k > 0 && {_k isEqualTo _key} && {_mods isEqualTo [_shift, _ctrl, _alt]}
        };
        if (["PhoneHold", [0x16, [true, true, false]]] call _hit) exitWith {
            [{ [] call comspec_atak_native_fnc_interactToggle; }] call CBA_fnc_execNextFrame;
            true
        };
        if (["PhoneZoomIn", [0xC9, [false, true, false]]] call _hit) exitWith { [0.7] call comspec_atak_native_fnc_mapZoom; true };
        if (["PhoneZoomOut", [0xD1, [false, true, false]]] call _hit) exitWith { [1 / 0.7] call comspec_atak_native_fnc_mapZoom; true };
        if (["PhoneCarry", [0x16, [false, true, false]]] call _hit) exitWith {
            [{ [] call comspec_atak_native_fnc_hudToggle; }] call CBA_fnc_execNextFrame;
            true
        };
        // Saisie dans un champ du téléphone (« a », « e »… faisaient pencher le personnage) : touche gardée (fn_keyBlock).
        _this call comspec_atak_native_fnc_keyBlock
    }];
    // Même garde sur le display de mission, si la touche y passe quand même (posée une fois par partie).
    private _d46 = findDisplay 46;
    if (!isNull _d46 && {(_d46 getVariable ["COMSPEC_KeyBlockEh", -1]) < 0}) then {
        _d46 setVariable ["COMSPEC_KeyBlockEh", _d46 displayAddEventHandler ["KeyDown", { _this call comspec_atak_native_fnc_keyBlock }]];
    };
    _display displayAddEventHandler ["MouseButtonDown", { params ["", "_button"]; ["DOWN", _button] call comspec_atak_native_fnc_phoneDrag; }];
    _display displayAddEventHandler ["MouseMoving", { ["MOVE"] call comspec_atak_native_fnc_phoneDrag; }];
    _display displayAddEventHandler ["MouseButtonUp", { ["UP"] call comspec_atak_native_fnc_phoneDrag; }];
    // Appui sur l'écran (fn_fx « tap ») : onde, clic doux, vibration légère. Pas après un glissé (carte, curseur) :
    // la position du bouton enfoncé est gardée, au-delà de quelques pixels le relâchement n'est pas un appui.
    _display displayAddEventHandler ["MouseButtonDown", {
        params ["", "_button", "_mx", "_my"];
        if (_button isEqualTo 0) then { uiNamespace setVariable ["COMSPEC_ATAK_TapDown", [_mx, _my, diag_tickTime]]; };
        false
    }];
    _display displayAddEventHandler ["MouseButtonUp", {
        params ["", "_button", "_mx", "_my"];
        if (_button isNotEqualTo 0) exitWith { false };
        (uiNamespace getVariable ["COMSPEC_ATAK_TapDown", [_mx, _my, -10]]) params ["_ix", "_iy", "_it"];
        private _moved = (diag_tickTime - _it) < 2 && {((abs (_mx - _ix)) / pixelW) + ((abs (_my - _iy)) / pixelH) > 12};
        if (!_moved) then { ["tap", [_mx, _my]] call comspec_atak_native_fnc_fx; };
        // Volet rapide ou écran de verrouillage affiché : ils repassent devant l'app cliquée.
        if ((uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) || {uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false]}) then {
            [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame;
        };
        false
    }];
    // Après un clic dans une app : effets d'écran (fêlures, filtre de nuit) remis devant (fn_overlayFront).
    _display displayAddEventHandler ["MouseButtonUp", {
        if (uiNamespace getVariable ["COMSPEC_ATAK_DevFront", false]) then { [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame; };
        false
    }];
};

[] call comspec_atak_native_fnc_schedulerStart;
// HUD des autres mods (F-PANO / ECOTI...) masqué sous le téléphone tant qu'il est affiché (fn_foreignHud).
["start"] call comspec_atak_native_fnc_foreignHud;
[profileNamespace getVariable ["COMSPEC_ATAK_LastPage", "MAP"]] call comspec_atak_native_fnc_navigate;
// Écran de verrouillage : première sortie de la mission, après une minute rangé ou un redémarrage (fn_lockScreen).
// Avant fn_statusUpdate, qui note l'heure du dernier affichage.
["check"] call comspec_atak_native_fnc_lockScreen;
[] call comspec_atak_native_fnc_statusUpdate;

["INFO", "UI", format ["display_created (%1)", [["HUD", "écran JPC"] select _texture, "en main"] select _interactive]] call comspec_atak_native_fnc_log;
