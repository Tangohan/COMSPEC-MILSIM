/*
    Injecte les ordres C2 déjà en mémoire dans le chat de groupe IceMan.
    Dédupliqué par id (COMSPEC_OrdersInGroupChat).
    Retourne le nombre d’entrées ajoutées.
*/
if (!hasInterface) exitWith { 0 };

private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
if (!(_orders isEqualType [])) exitWith { 0 };

private _pushed = missionNamespace getVariable ["COMSPEC_OrdersInGroupChat", []];
if (!(_pushed isEqualType [])) then { _pushed = []; };

private _added = 0;
private _messages = +(missionNamespace getVariable ["Iceman_ATAK_Group_messages", []]);
if (!(_messages isEqualType [])) then { _messages = []; };

{
    if (!(_x isEqualType createHashMap)) then { continue };
    private _orderId = _x getOrDefault ["id", ""];
    if (_orderId isEqualTo "") then { continue };
    if (_orderId in _pushed) then { continue };

    private _type = toUpper (_x getOrDefault ["type", "MOVE"]);
    if (_type in ["VIBRATE", "NOTIFY", "HELMET_SNAP", "HELMET_SNAP_HD", "HELMET_STREAM"]) then { continue };

    if (!isNil "comspec_overwatch_connect_fnc_orderConcernsPlayer") then {
        if (!([_x] call comspec_overwatch_connect_fnc_orderConcernsPlayer)) then { continue };
    };

    private _typeLabel = trim (_x getOrDefault ["typeLabel", ""]);
    if (_typeLabel isEqualTo "") then {
        _typeLabel = switch (_type) do {
            case "HOLD": { "Tenir la position" };
            case "RECON": { "Reconnaissance" };
            case "CAS": { "Appui aérien" };
            case "QRF": { "Force de réaction" };
            case "FRAGO": { "Ordre fragmentaire" };
            case "CUSTOM": { "Ordre personnalisé" };
            default { "Se déplacer" };
        };
    };

    private _issuer = _x getOrDefault ["issuer", "C2"];
    private _prio = toUpper (_x getOrDefault ["priority", "IMPORTANT"]);
    private _prioLabel = switch (_prio) do {
        case "URGENT": { "Urgent" };
        case "ROUTINE": { "Routine" };
        default { "Important" };
    };
    private _payload = _x getOrDefault ["payload", ""];
    private _plainPayload = if (_payload isEqualType "") then { _payload } else { str _payload };

    private _gTime = if (!isNil "cTab_fnc_currentTime") then { call cTab_fnc_currentTime } else {
        [daytime, "HH:MM"] call BIS_fnc_timeToString
    };
    private _gPos = getPosATL player;
    private _gGrid = mapGridPosition _gPos;
    private _gId = groupId group player;
    private _gText = format [
        "[ORDRE C2] %1 · %2 · de %3%4 — répondre dans TASK",
        _typeLabel,
        _prioLabel,
        _issuer,
        if ((trim _plainPayload) isEqualTo "") then { "" } else { format [" — %1", trim _plainPayload] }
    ];

    _messages pushBack [_gTime, _issuer, _gId, _gGrid, _gText, _gPos, false];
    _pushed pushBack _orderId;
    _added = _added + 1;
} forEach _orders;

if (_added > 0) then {
    while { (count _messages) > 50 } do { _messages deleteAt 0; };
    while { (count _pushed) > 80 } do { _pushed deleteAt 0; };
    missionNamespace setVariable ["Iceman_ATAK_Group_messages", _messages, false];
    Iceman_ATAK_Group_messages = _messages;
    Iceman_ATAK_Group_selected = (count _messages) - 1;
    missionNamespace setVariable ["COMSPEC_OrdersInGroupChat", _pushed, false];
    if (!isNil "Iceman_fnc_group_updatePanel") then {
        [] call Iceman_fnc_group_updatePanel;
    };
};

_added
