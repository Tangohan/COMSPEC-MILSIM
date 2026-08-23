/*
    Note bêta / CGU — overlay dans le menu principal Arma (une fois par profil).
    Fenêtre Windows en repli si l’overlay n’a pas pu s’ouvrir.
    Jamais de popup automatique en mission (parade de dialogues + gel REAPP).
    Params: [_force] — relire depuis le bandeau menu / hub / réglages.
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

private _onMainMenu = isNull findDisplay 46 && {!isNull findDisplay 0};
private _opened = false;
if (_onMainMenu || _force) then {
    _opened = [] call comspec_overwatch_connect_fnc_openNdaDialog;
};
if (_opened) exitWith {
    missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", true, false];
};

if (!_force && {!_onMainMenu}) exitWith {};
if (_force && {!isNull findDisplay 46} && {!([] call comspec_overwatch_connect_fnc_canShowWinMessageBox)}) exitWith {};

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
    [] call comspec_overwatch_connect_fnc_refreshMainMenuBetaBanner;
};

if (_prefix isEqualTo "OK") exitWith {};

if (_onMainMenu || {_force && {isNull findDisplay 46}}) then {
    [] call comspec_overwatch_connect_fnc_openNdaDialog;
};
