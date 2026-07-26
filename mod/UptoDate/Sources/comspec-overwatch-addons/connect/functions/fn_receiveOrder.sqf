/*
    Réception d’un ordre C2 (local ou via remoteExec / poll web).
    Affiche notification + journal si le joueur est concerné.
*/
params [["_order", createHashMap]];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!(_order isEqualType createHashMap)) exitWith {};

private _id = _order getOrDefault ["id", ""];
private _issuer = _order getOrDefault ["issuer", ""];
private _type = _order getOrDefault ["type", "MOVE"];
private _priority = _order getOrDefault ["priority", "IMPORTANT"];

if (_id isEqualTo "") exitWith {};

if (!([_order] call comspec_overwatch_connect_fnc_orderConcernsPlayer)) exitWith {};

// Ne pas notifier l’émetteur sauf s’il est aussi destinataire explicite
private _myName = name player;
private _myCallsign = [] call comspec_overwatch_connect_fnc_getCallsign;
private _target = trim (_order getOrDefault ["target", ""]);
private _explicitMe = (_target != "") && {
    (toLower _target) isEqualTo (toLower _myCallsign)
    || {(toLower _target) isEqualTo (toLower _myName)}
};
if (_issuer isEqualTo _myName && {!_explicitMe}) exitWith {};
if (_issuer isEqualTo _myCallsign && {!_explicitMe}) exitWith {};

// Éviter les doublons (remoteExec + bus local + poll)
private _seen = missionNamespace getVariable ["COMSPEC_OrdersSeen", []];
if (_id in _seen) exitWith {};
_seen pushBack _id;
if (count _seen > 80) then { _seen deleteRange [0, (count _seen) - 80]; };
missionNamespace setVariable ["COMSPEC_OrdersSeen", _seen, false];

private _typeLabelCustom = trim (_order getOrDefault ["typeLabel", ""]);
private _typeLabel = if (_typeLabelCustom != "") then {
    _typeLabelCustom
} else {
    switch (toUpper _type) do {
        case "HOLD": { "Tenir la position" };
        case "RECON": { "Reconnaissance" };
        case "CAS": { "Appui aérien" };
        case "QRF": { "Force de réaction" };
        case "CUSTOM": { "Ordre personnalisé" };
        default { "Se déplacer" };
    };
};

private _prioLabel = switch (toUpper _priority) do {
    case "URGENT": { "Urgent" };
    case "CONTACT": { "Contact" };
    case "ROUTINE": { "Routine" };
    default { "Important" };
};

private _msg = format ["Nouvel ordre — %1 (%2) · de %3", _typeLabel, _prioLabel, _issuer];
["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
[_msg, "orders"] call comspec_overwatch_connect_fnc_appendLinkLog;
["COMSPEC_OrderReceived", [_order]] call CBA_fnc_localEvent;
if ([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) then {
    systemChat format ["[COMSPEC] %1", _msg];
};

// Si l’app Athena cTab est ouverte : rester sur le panneau (pas de Chromium forcé).
private _athenaGroup = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
private _athenaOpen = !isNull _athenaGroup && {ctrlShown _athenaGroup};
if (_athenaOpen) exitWith {};

// Sinon ouvre la tablette sur les ordres (plus de dialog 9975) — hors mode ATAK-only
if (missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]) exitWith {};
if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith {};

if (isNull (findDisplay 9974)) then {
    0 spawn {
        uiSleep 0.15;
        ["orders"] call comspec_overwatch_connect_fnc_openTabletView;
    };
} else {
    ["orders"] call comspec_overwatch_connect_fnc_openTabletView;
};
