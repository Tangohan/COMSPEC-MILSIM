/*
    Met à jour la barre de transmission + latence (ms) du dialog Connexion Athena.
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull];
if (isNull _display) exitWith {};

private _bar = _display displayCtrl 9207;
if (isNull _bar) exitWith {};

[] call comspec_overwatch_connect_fnc_measureLatency;

private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
private _ms = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
private _lastSync = missionNamespace getVariable ["COMSPEC_LastPositionSync", -1];
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];

if (_ready && {_state isEqualTo "offline"}) then {
    private _key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
    if ((_key isEqualType "") && {(count (trim _key)) >= 4}) then {
        _state = "linked";
    };
};

private _txLabel = switch (_state) do {
    case "linked": { "Transmission active" };
    case "connecting": { "Connexion en cours…" };
    case "disabled": { "Liaison désactivée" };
    default { "Transmission coupée" };
};

private _txColor = switch (_state) do {
    case "linked": { "#7dffb3" };
    case "connecting": { "#ffd27a" };
    case "disabled": { "#8899aa" };
    default { "#ff8a7a" };
};

private _msLabel = if (_ms >= 0) then {
    format ["%1 ms", _ms]
} else {
    "— ms"
};

private _msColor = if (_ms < 0) then {
    "#8899aa"
} else {
    if (_ms <= 80) then { "#7dffb3" } else {
        if (_ms <= 200) then { "#ffd27a" } else { "#ff8a7a" }
    };
};

private _ago = "";
if (_lastSync >= 0) then {
    private _sec = round (diag_tickTime - _lastSync);
    if (_sec < 0) then { _sec = 0; };
    _ago = if (_sec < 60) then {
        format ["Dernière position · il y a %1 s", _sec]
    } else {
        format ["Dernière position · il y a %1 min", (round (_sec / 60)) max 1]
    };
} else {
    _ago = if (_detail isNotEqualTo "") then { _detail } else { "Aucune position envoyée pour l’instant" };
};

_bar ctrlSetStructuredText parseText format [
    "<t align='left' size='0.58'><t color='%1'>●</t> <t color='#e8f4f0'>%2</t>  ·  <t color='%3'>%4</t></t><br/><t align='left' size='0.48' color='#7a8c9e'>%5</t>",
    _txColor,
    _txLabel,
    _msColor,
    _msLabel,
    _ago
];
