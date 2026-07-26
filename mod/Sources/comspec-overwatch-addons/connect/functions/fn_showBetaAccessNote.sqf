/*
    Note d’accès anticipé (bêta) — une fois par profil jusqu’à confirmation.
    Affiche un dialogue Arma natif (NDA FR/EN) ; MessageBox Windows en repli.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _alreadyAck = profileNamespace getVariable ["comspec_overwatch_beta_note_ack", false];
if (_alreadyAck) exitWith {
    // Si la note est déjà acceptée mais l’envoi a échoué (hors ligne), retenter une fois.
    if (!(profileNamespace getVariable ["comspec_overwatch_beta_registered", false])) then {
        [] call comspec_overwatch_connect_fnc_registerBetaClient;
    };
};

if (!isNull (uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull])) exitWith {};
if (missionNamespace getVariable ["COMSPEC_BetaAccessNoteShown", false]) exitWith {};

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

// Repli : MessageBox Windows via l’extension
missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", true, false];
private _raw = ["COMSPECExtension" callExtension ["ShowBetaAccessNote", []]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _action = if (count _parts >= 2) then { _parts select 1 } else { "" };

if (_prefix isEqualTo "OK" && {_action in ["ack", "dismissed", "busy"]}) then {
    if (_action isEqualTo "busy") exitWith {};
    profileNamespace setVariable ["comspec_overwatch_beta_note_ack", true];
    saveProfileNamespace;
    [] call comspec_overwatch_connect_fnc_registerBetaClient;
};
