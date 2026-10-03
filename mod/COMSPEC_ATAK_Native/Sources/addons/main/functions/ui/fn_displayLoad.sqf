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
uiNamespace setVariable ["COMSPEC_ATAK_Display", _display];
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_DockControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_BadgeSig", []];

// Les raccourcis CBA ne passent pas quand le téléphone est en main : on reprend Ctrl+U / Ctrl+Maj+U ici.
if (_interactive) then {
    _display displayAddEventHandler ["KeyDown", {
        params ["", "_key", "_shift", "_ctrl"];
        if (_key isEqualTo 0x16 && {_ctrl}) exitWith {
            if (_shift) then {
                [{ [] call comspec_atak_native_fnc_interactToggle; }] call CBA_fnc_execNextFrame;
            } else {
                [{ [] call comspec_atak_native_fnc_hudToggle; }] call CBA_fnc_execNextFrame;
            };
            true
        };
        false
    }];
};

[] call comspec_atak_native_fnc_schedulerStart;
[profileNamespace getVariable ["COMSPEC_ATAK_LastPage", "MAP"]] call comspec_atak_native_fnc_navigate;
[] call comspec_atak_native_fnc_statusUpdate;

["INFO", "UI", format ["display_created (%1)", ["HUD", "en main"] select _interactive]] call comspec_atak_native_fnc_log;
