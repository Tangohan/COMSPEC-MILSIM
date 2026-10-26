/*
    Synchronise l’état de compromission / capture du terminal vers Athena.
    Params: aucun (utilise le terminal local + COMSPEC_CompromiseState)
*/
if (!hasInterface) exitWith { false };

private _terminalUid = [] call comspec_overwatch_connect_fnc_getTerminalUid;
if (!(_terminalUid isEqualType "") || {_terminalUid isEqualTo ""}) exitWith { false };

private _state = missionNamespace getVariable ["COMSPEC_CompromiseState", "none"];
if (!(_state isEqualType "")) then { _state = "none"; };
_state = toLower _state;
if (!(_state in ["none", "captured", "compromised"])) then { _state = "none"; };

private _reason = if (_state isEqualTo "none") then { "" } else { "Action terrain / Zeus" };

private _fn = if (_state isEqualTo "none") then { "ClearCompromise" } else { "CompromiseTerminal" };
// POST synchrone côté DLL (jusqu'à 8 s) et appelant non planifié (remoteExecCall Zeus) : envoi en spawn
// via extAwait pour ne pas figer le jeu. Le retour n'est pas utilisé par fn_applyZeusAtakEffect.
[_fn, [_terminalUid, _state, _reason]] spawn {
    params ["_fn", "_args"];
    ["COMSPECExtension", _fn, _args] call comspec_overwatch_connect_fnc_extAwait;
};

if (_state in ["captured", "compromised"]) then {
    ["Certificat invalide — trafic illisible", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
};

true
