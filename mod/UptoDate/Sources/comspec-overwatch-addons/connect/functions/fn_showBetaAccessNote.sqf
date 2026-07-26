/*
    Note d’accès anticipé (bêta) — une fois par profil jusqu’à confirmation.
    Affiche un dialogue Arma natif (NDA FR/EN).
    Pas de MessageBox Windows en mission (CTD REAPP / JIP).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _alreadyAck = profileNamespace getVariable ["comspec_overwatch_beta_note_ack", false];
if (_alreadyAck) exitWith {
    if (!(profileNamespace getVariable ["comspec_overwatch_beta_registered", false])) then {
        [] call comspec_overwatch_connect_fnc_registerBetaClient;
    };
};

if (!isNull (uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull])) exitWith {};
if (missionNamespace getVariable ["COMSPEC_BetaAccessNoteShown", false]) exitWith {};

// Pendant REAPP / grâce : reporter (évite createDisplay + MessageBox sur UI respawn)
if (
    (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9]))
    || {missionNamespace getVariable ["COMSPEC_CancelPendingAthenaHelp", false]}
    || {(missionNamespace getVariable ["COMSPEC_SuppressWinMessageBoxUntil", -1e9]) > diag_tickTime}
) exitWith {
    private _retries = missionNamespace getVariable ["COMSPEC_BetaNoteRetries", 0];
    if (_retries >= 8) exitWith {};
    missionNamespace setVariable ["COMSPEC_BetaNoteRetries", _retries + 1, false];
    [{
        if !(profileNamespace getVariable ["comspec_overwatch_beta_note_ack", false]) then {
            [] call comspec_overwatch_connect_fnc_showBetaAccessNote;
        };
    }, [], 20] call CBA_fnc_waitAndExecute;
};

private _opened = false;
private _parent = findDisplay 0;
if (isNull _parent) then { _parent = findDisplay 46; };
if (!isNull _parent) then {
    private _child = _parent createDisplay "COMSPEC_NDA_Dialog";
    _opened = !isNull _child;
};
if (!_opened) then {
    _opened = createDialog "COMSPEC_NDA_Dialog";
};

if (_opened) exitWith {
    missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", true, false];
};

// Repli MessageBox Win32 : UNIQUEMENT hors mission (menu principal). En jeu = CTD.
if (isNull findDisplay 46) then {
    missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", true, false];
    private _raw = ["COMSPECExtension" callExtension ["ShowBetaAccessNote", []]] call comspec_overwatch_connect_fnc_extResult;
    private _parts = _raw splitString "|";
    private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
    private _action = if (count _parts >= 2) then { _parts select 1 } else { "" };
    if (_prefix isEqualTo "OK" && {_action in ["ack", "dismissed"]}) then {
        profileNamespace setVariable ["comspec_overwatch_beta_note_ack", true];
        saveProfileNamespace;
        [] call comspec_overwatch_connect_fnc_registerBetaClient;
    };
} else {
    private _retries = missionNamespace getVariable ["COMSPEC_BetaNoteRetries", 0];
    if (_retries >= 8) exitWith {};
    missionNamespace setVariable ["COMSPEC_BetaNoteRetries", _retries + 1, false];
    [{
        if !(profileNamespace getVariable ["comspec_overwatch_beta_note_ack", false]) then {
            [] call comspec_overwatch_connect_fnc_showBetaAccessNote;
        };
    }, [], 25] call CBA_fnc_waitAndExecute;
};
