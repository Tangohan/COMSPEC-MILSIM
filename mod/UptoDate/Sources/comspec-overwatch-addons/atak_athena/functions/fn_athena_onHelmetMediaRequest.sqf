/*
    Demande TOC : photo casque (standard / HD) ou flux d’aperçus rapides.
    Types : HELMET_SNAP | HELMET_SNAP_HD | HELMET_STREAM
*/
params [["_order", createHashMap]];

if (!hasInterface) exitWith {};

private _type = "HELMET_SNAP";
private _issuer = "Athena";
private _orderId = format ["hmd_%1", diag_tickTime];
if (_order isEqualType createHashMap) then {
    _type = toUpper (_order getOrDefault ["type", "HELMET_SNAP"]);
    _issuer = _order getOrDefault ["issuer", "Athena"];
    private _oid = trim (_order getOrDefault ["id", ""]);
    if (_oid isNotEqualTo "") then { _orderId = _oid; };
};

private _mode = switch (_type) do {
    case "HELMET_SNAP_HD": { "hd" };
    case "HELMET_STREAM": { "stream" };
    default { "snap" };
};

private _helmetClasses = missionNamespace getVariable ["cTab_helmetClass_has_HCam", []];
if (!(_helmetClasses isEqualType [])) then { _helmetClasses = []; };
private _gear = (items player) + (assignedItems player);
private _goggles = goggles player;
if (_goggles isNotEqualTo "") then { _gear pushBackUnique _goggles; };
private _hasHcam = ("ItemcTabHCam" in _gear) || {((headgear player) in _helmetClasses)};

private _ackFail = {
    params ["_oid", "_note"];
    if (_oid isEqualTo "") exitWith {};
    private _acked = [_oid, "FAILED", _note] call comspec_overwatch_connect_fnc_updateOrderStatus;
    if (!_acked) then {
        private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
        private _by = [] call comspec_overwatch_connect_fnc_getCallsign;
        if (_by isEqualTo "") then { _by = name player; };
        ["COMSPECExtension" callExtension ["UpdateOrderStatus", [_oid, "FAILED", _by, _mapId, _note]]] call comspec_overwatch_connect_fnc_extResult;
    };
};

if (!_hasHcam) exitWith {
    private _msg = "Caméra casque indisponible sur votre équipement.";
    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
    [_orderId, _msg] call _ackFail;
};

private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_cs isEqualTo "") then { _cs = name player; };
private _uid = getPlayerUID player;
if (_uid isEqualTo "") then { _uid = str player; };
private _grid = mapGridPosition player;
private _feedId = format ["helmet:%1", _uid];
if (_mode isEqualTo "hd") then { _feedId = _feedId + ":hd"; };

private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;
private _inboxLabel = switch (_mode) do {
    case "hd": { format ["Photo casque HD demandée par %1", _issuer] };
    case "stream": { format ["Flux casque demandé par %1 (~3 min)", _issuer] };
    default { format ["Photo casque demandée par %1", _issuer] };
};
private _toast = switch (_mode) do {
    case "hd": { format ["%1 demande une photo casque HD — capture en cours…", _issuer] };
    case "stream": { format ["%1 demande votre flux casque — aperçus rapides (~3 min)", _issuer] };
    default { format ["%1 demande une photo casque — capture en cours…", _issuer] };
};
["COMSPEC_Info", [_toast]] call comspec_overwatch_connect_fnc_showNotification;
["ATHENA", _toast, 8] call comspec_overwatch_connect_fnc_addScreenToast;

private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_inbox isEqualType [])) then { _inbox = []; };
_inbox pushBack ["CAM", "Caméra casque", _inboxLabel, _grid, _timeStr, _issuer, _orderId];
while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

if (_mode isEqualTo "stream") then {
    [180] call comspec_overwatch_connect_fnc_markBcePhotoCapture;
    missionNamespace setVariable ["COMSPEC_HelmetStreamUntil", diag_tickTime + 180, false];
    missionNamespace setVariable ["COMSPEC_HelmetStreamActive", true, false];
} else {
    [] call comspec_overwatch_connect_fnc_markBcePhotoCapture;
};

private _caption = switch (_mode) do {
    case "hd": { format ["Aperçu casque HD — %1 · grille %2", _cs, _grid] };
    case "stream": { format ["Flux casque — %1 · grille %2", _cs, _grid] };
    default { format ["Aperçu casque — %1 · grille %2", _cs, _grid] };
};

if (_mode isEqualTo "stream") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_snapshotVideoFeed;
} else {
    private _stem = if (_mode isEqualTo "hd") then { "COMSPEC_AthenaHD.png" } else { "COMSPEC_AthenaFeed.png" };
    if (_mode isEqualTo "hd") then {
        screenshot _stem;
        [_stem, _caption, "HELMET", _feedId] spawn {
            params ["_stem", "_caption", "_device", "_feedId"];
            uiSleep 1.1;
            [_stem, _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;
        };
    } else {
        ["", _caption, "HELMET", _feedId] call comspec_overwatch_connect_fnc_captureReconImage;
    };
};
