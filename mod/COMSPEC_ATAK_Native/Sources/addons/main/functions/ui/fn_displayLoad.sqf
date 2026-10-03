params ["_display", ["_interactive", true]];
disableSerialization;
if (!hasInterface || {isNull _display}) exitWith {};
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_state set ["display", _display];
_state set ["interactive", _interactive];
_state set ["history", []];
_state set ["pageSig", []];
_state set ["pageSigPage", ""];
_state set ["mapCentered", false];
_state set ["drag", []];
uiNamespace setVariable ["COMSPEC_ATAK_Display", _display];
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_DockControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_BadgeSig", []];

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
        false
    }];
    _display displayAddEventHandler ["MouseButtonDown", { params ["", "_button"]; ["DOWN", _button] call comspec_atak_native_fnc_phoneDrag; }];
    _display displayAddEventHandler ["MouseMoving", { ["MOVE"] call comspec_atak_native_fnc_phoneDrag; }];
    _display displayAddEventHandler ["MouseButtonUp", { ["UP"] call comspec_atak_native_fnc_phoneDrag; }];
};

[] call comspec_atak_native_fnc_schedulerStart;
[profileNamespace getVariable ["COMSPEC_ATAK_LastPage", "MAP"]] call comspec_atak_native_fnc_navigate;
[] call comspec_atak_native_fnc_statusUpdate;

["INFO", "UI", format ["display_created (%1)", ["HUD", "en main"] select _interactive]] call comspec_atak_native_fnc_log;
