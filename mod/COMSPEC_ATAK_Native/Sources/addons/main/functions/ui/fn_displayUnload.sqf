params ["_display", ["_exitCode", 0]];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Un autre display du terminal a déjà pris la place (bascule HUD / en main) : rien à nettoyer.
if ((uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull]) isNotEqualTo _display) exitWith {};
private _wasInteractive = _state getOrDefault ["interactive", false];
[] call comspec_atak_native_fnc_schedulerStop;
_state set ["display", displayNull];
private _chat = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
if (!isNull _chat) then { uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ctrlText _chat]; };
uiNamespace setVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_DockControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", []];
profileNamespace setVariable ["COMSPEC_ATAK_LastPage", _state getOrDefault ["activePage", "MAP"]];
saveProfileNamespace;
uiNamespace setVariable ["COMSPEC_ATAK_Display", displayNull];
["INFO", "UI", format ["Display closed (%1); handlers cleaned", _exitCode]] call comspec_atak_native_fnc_log;

// Téléphone reposé (Échap ou Ctrl+Maj+U) : il revient en HUD s'il était sorti.
if (_wasInteractive && {uiNamespace getVariable ["COMSPEC_ATAK_HudWanted", false]} && {!(_state getOrDefault ["suppressHudRestore", false])}) then {
    [{ if (isNull ([] call comspec_atak_native_fnc_display)) then { [false] call comspec_atak_native_fnc_open; }; }] call CBA_fnc_execNextFrame;
};
