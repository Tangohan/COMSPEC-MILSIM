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
if (_issuer isEqualTo _myName && {!_explicitMe} && {!((toUpper _type) in ["PHONE_GEOLOC", "PHONE_GEOLOC_OFF"])}) exitWith {};
if (_issuer isEqualTo _myCallsign && {!_explicitMe} && {!((toUpper _type) in ["PHONE_GEOLOC", "PHONE_GEOLOC_OFF"])}) exitWith {};

// Éviter les doublons (remoteExec + bus local + poll)
private _seen = missionNamespace getVariable ["COMSPEC_OrdersSeen", []];
if (_id in _seen) exitWith {};
_seen pushBack _id;
if (count _seen > 80) then { _seen deleteRange [0, (count _seen) - 80]; };
missionNamespace setVariable ["COMSPEC_OrdersSeen", _seen, false];

private _typeLabel = [_order] call comspec_overwatch_connect_fnc_orderTypeLabel;

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

private _orderStatus = toUpper (_order getOrDefault ["status", "PENDING"]);
private _alreadyConsumed = _orderStatus in ["ACK", "DONE", "EXEC", "CANCELLED", "FAILED", "CLOSED"];

// Signal haptique TOC : buzz réel du terminal — pas un ordre C2 à acquitter manuellement
if ((toUpper _type) isEqualTo "VIBRATE") exitWith {
    // Déjà reçu / confirmé : ne pas rejouer à la reconnexion
    if (_alreadyConsumed) exitWith {};
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
    if (_alreadyConsumed) exitWith {};
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

// Demande caméra casque TOC (photo / HD / flux aperçus)
if ((toUpper _type) in ["HELMET_SNAP", "HELMET_SNAP_HD", "HELMET_STREAM"]) exitWith {
    if (_alreadyConsumed) exitWith {};
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_onHelmetMediaRequest") then {
        [_order] call comspec_overwatch_atak_athena_fnc_athena_onHelmetMediaRequest;
    } else {
        ["COMSPEC_Warning", ["Demande caméra casque reçue — module Athena requis."]] call comspec_overwatch_connect_fnc_showNotification;
    };
    [_id, "Demande caméra reçue"] call _ackTerminalSignal;
};

// Géolocalisation téléphone demandée depuis le poste ATAK (comme Zeus)
if ((toUpper _type) in ["PHONE_GEOLOC", "PHONE_GEOLOC_OFF"]) exitWith {
    if (_alreadyConsumed) exitWith {};
    private _ok = false;
    if (!isNil "comspec_overwatch_connect_fnc_applyPhoneGeolocOrder") then {
        _ok = [_order] call comspec_overwatch_connect_fnc_applyPhoneGeolocOrder;
    };
    if (!_ok) then {
        _seen = _seen - [_id];
        missionNamespace setVariable ["COMSPEC_OrdersSeen", _seen, false];
    } else {
        [_id, "Localisation telephone"] call _ackTerminalSignal;
    };
};

private _prioLabel = switch (toUpper _priority) do {
    case "URGENT": { "Urgent" };
    case "CONTACT": { "Contact" };
    case "ROUTINE": { "Routine" };
    default { "Important" };
};

private _payloadRaw = trim (_order getOrDefault ["payload", ""]);
private _wp = [_payloadRaw] call comspec_overwatch_connect_fnc_orderParseWaypoint;
private _hasWp = (count _wp) >= 2;

private _msg = if (_hasWp && {(toUpper _type) isEqualTo "MOVE"}) then {
    private _grid = _wp getOrDefault ["grid", ""];
    private _eta = _wp getOrDefault ["eta_min", -1];
    private _lbl = _wp getOrDefault ["label", ""];
    private _human = _wp getOrDefault ["text", ""];
    private _detail = if (_human != "") then { _human } else { _lbl };
    if (_detail isEqualTo "") then { _detail = "Point de mission"; };
    if (_grid != "") then { _detail = _detail + format [" · %1", _grid]; };
    if (_eta >= 0) then { _detail = _detail + format [" · ETA ~%1 min", round _eta]; };
    format [
        "Ordre de déplacement — %1 (%2) · %3 · de %4 — confirmez ou refusez sur l’ATAK",
        _typeLabel,
        _prioLabel,
        _detail,
        _issuer
    ]
} else {
    format ["Nouvel ordre — %1 (%2) · de %3", _typeLabel, _prioLabel, _issuer]
};
["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
[_msg, "orders"] call comspec_overwatch_connect_fnc_appendLinkLog;
["COMSPEC_OrderReceived", [_order]] call CBA_fnc_localEvent;

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
