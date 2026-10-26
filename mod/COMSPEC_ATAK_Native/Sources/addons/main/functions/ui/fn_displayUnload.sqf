params ["_display", ["_exitCode", 0]];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Un autre display du terminal a déjà pris la place (bascule HUD / en main) : rien à nettoyer.
if ((uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull]) isNotEqualTo _display) exitWith {};
[] call comspec_atak_native_fnc_livecamStop;
// Écran 3D du JPC : la caméra de la vue aérienne (fn_jpcMap) ne doit pas survivre au display.
if (_state getOrDefault ["texture", false]) then { ["close"] call comspec_atak_native_fnc_jpcMap; };
private _wasInteractive = _state getOrDefault ["interactive", false];
[] call comspec_atak_native_fnc_schedulerStop;
["stop"] call comspec_atak_native_fnc_foreignHud;
_state set ["display", displayNull];
private _chat = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
if (!isNull _chat) then { uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ctrlText _chat]; };
uiNamespace setVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_DockControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", []];
// Profil réécrit sur disque seulement si la dernière page a changé (chaque bascule HUD / en main ferme un display).
private _lastPage = _state getOrDefault ["activePage", "MAP"];
if ((profileNamespace getVariable ["COMSPEC_ATAK_LastPage", "MAP"]) isNotEqualTo _lastPage) then {
    profileNamespace setVariable ["COMSPEC_ATAK_LastPage", _lastPage];
    [] call comspec_atak_native_fnc_saveProfile;
};
uiNamespace setVariable ["COMSPEC_ATAK_Display", displayNull];
uiNamespace setVariable ["COMSPEC_ATAK_Ratio", nil];
["INFO", "UI", format ["Display closed (%1); handlers cleaned", _exitCode]] call comspec_atak_native_fnc_log;

// Téléphone reposé (Échap ou Ctrl+Maj+U) : il revient en HUD s'il était sorti.
if (_wasInteractive && {uiNamespace getVariable ["COMSPEC_ATAK_HudWanted", false]} && {!(_state getOrDefault ["suppressHudRestore", false])}) then {
    [{ if (isNull ([] call comspec_atak_native_fnc_display)) then { [false] call comspec_atak_native_fnc_open; }; }] call CBA_fnc_execNextFrame;
};
