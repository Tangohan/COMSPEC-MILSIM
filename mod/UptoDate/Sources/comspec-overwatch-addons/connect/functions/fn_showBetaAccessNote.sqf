/*
    Note bêta / CGU — fenêtre Windows au menu principal Arma (une fois par profil).
    Jamais de popup automatique en mission (parade de dialogues + gel REAPP).
    Params: [_force] — relire depuis le hub / réglages.
*/
params [["_force", false]];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _fnc_isTrue = {
    params ["_v"];
    (_v isEqualTo true) || {_v isEqualTo 1} || {
        (_v isEqualType "") && { (toLower _v) in ["true", "1", "yes"] }
    }
};

private _cguAck = [profileNamespace getVariable ["comspec_overwatch_cgu_ack", false]] call _fnc_isTrue;
private _betaAck = [profileNamespace getVariable ["comspec_overwatch_beta_note_ack", false]] call _fnc_isTrue;

if (!_force && {_cguAck}) exitWith {
    if (!_betaAck) then {
        profileNamespace setVariable ["comspec_overwatch_beta_note_ack", true];
        saveProfileNamespace;
    };
    if (!(profileNamespace getVariable ["comspec_overwatch_beta_registered", false])) then {
        [] call comspec_overwatch_connect_fnc_registerBetaClient;
    };
};

if (!_force && {!isNull findDisplay 46}) exitWith {};

if (!_force && {missionNamespace getVariable ["COMSPEC_BetaAccessNoteShown", false]}) exitWith {};

if (
    !_force
    && {
        (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9]))
        || {missionNamespace getVariable ["COMSPEC_CancelPendingAthenaHelp", false]}
        || {(missionNamespace getVariable ["COMSPEC_SuppressWinMessageBoxUntil", -1e9]) > diag_tickTime}
    }
) exitWith {};

if (_force && {!([] call comspec_overwatch_connect_fnc_canShowWinMessageBox)} && {!isNull findDisplay 46}) exitWith {};

missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", true, false];

private _raw = ["COMSPECExtension" callExtension ["ShowBetaAccessNote", []]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _action = if (count _parts >= 2) then { toLower (_parts select 1) } else { "" };

if (_prefix isEqualTo "OK" && {_action in ["ack", "dismissed"]}) exitWith {
    profileNamespace setVariable ["comspec_overwatch_cgu_ack", true];
    profileNamespace setVariable ["comspec_overwatch_beta_note_ack", true];
    saveProfileNamespace;
    [] call comspec_overwatch_connect_fnc_registerBetaClient;
};

if (_prefix isEqualTo "OK") exitWith {};

// Repli dialogue Arma uniquement au menu principal (jamais en mission).
if (isNull findDisplay 46 && {!isNull findDisplay 0}) then {
    if (!isNull (uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull])) exitWith {};
    private _parent = findDisplay 0;
    private _child = _parent createDisplay "COMSPEC_NDA_Dialog";
    if (isNull _child) then {
        createDialog "COMSPEC_NDA_Dialog";
    };
};
