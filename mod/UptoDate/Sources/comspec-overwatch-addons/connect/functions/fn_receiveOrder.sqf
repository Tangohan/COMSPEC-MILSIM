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
        case "VIBRATE": { "Faire vibrer le terminal" };
        case "NOTIFY": { "Notification terminal" };
        default { "Se déplacer" };
    };
};

private _ackTerminalSignal = {
    params ["_oid", "_note"];
    private _acked = [_oid, "ACK", _note] call comspec_overwatch_connect_fnc_updateOrderStatus;
    if (!_acked) then {
        private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
        private _by = [] call comspec_overwatch_connect_fnc_getCallsign;
        if (_by isEqualTo "") then { _by = name player; };
        ["COMSPECExtension" callExtension ["UpdateOrderStatus", [_oid, "ACK", _by, _mapId, _note]]] call comspec_overwatch_connect_fnc_extResult;
    };
};

// Signal haptique TOC : buzz réel du terminal — pas un ordre C2 à acquitter manuellement
if ((toUpper _type) isEqualTo "VIBRATE") exitWith {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_onVibrate") then {
        [_order] call comspec_overwatch_atak_athena_fnc_athena_onVibrate;
    } else {
        // Fallback si atak_athena absent — son packé connect, UI (toujours audible)
        playSoundUI ["COMSPEC_ATAK_Vibrate", 1.6, 1];
        [] spawn {
            uiSleep 0.35;
            playSoundUI ["COMSPEC_ATAK_Vibrate", 1.6, 1];
        };
        private _msg = format ["Votre terminal vibre — appel de %1", _issuer];
        ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
    };
    [_id, "Vibration reçue"] call _ackTerminalSignal;
};

// Notification TOC : entrée cliquable dans Athena — pas un ordre C2
if ((toUpper _type) isEqualTo "NOTIFY") exitWith {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_onNotify") then {
        [_order] call comspec_overwatch_atak_athena_fnc_athena_onNotify;
    } else {
        private _payload = trim (_order getOrDefault ["payload", ""]);
        private _msg = if (_payload isEqualTo "") then {
            format ["Notification Athena — de %1", _issuer]
        } else {
            format ["%1 — %2", _issuer, _payload]
        };
        ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
        ["ATHENA", _msg, 7] call comspec_overwatch_connect_fnc_addScreenToast;
    };
    [_id, "Notification reçue"] call _ackTerminalSignal;
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
if (_athenaOpen) exitWith {
    missionNamespace setVariable ["COMSPEC_Athena_PanelTab", "order", false];
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};

// Mode ATAK-only : notification + pastille déjà poussées — pas d’auto-ouverture intrusive.
if (missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]) exitWith {};

// Legacy tablette (option ATAK-only désactivée)
if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith {};
["order"] call comspec_overwatch_connect_fnc_openAthenaFeature;
