/*
    Listener noyau des événements COMSPEC SSE.
    Appelé depuis XEH_postInit — enregistre les handlers CBA une seule fois.
*/
if (!isNil "comspec_sse_eventBusReady") exitWith {};
comspec_sse_eventBusReady = true;

private _handler = {
    params ["_payload"];
    if (isNil "_payload") exitWith {};
    if !(_payload isEqualType createHashMap) exitWith {};

    private _type = _payload getOrDefault ["event_type", "?"];
    private _src = _payload getOrDefault ["source_system", "?"];
    private _sum = _payload getOrDefault ["summary", ""];
    [
        format ["SSE EVT %1 [%2] %3", _type, _src, _sum]
    ] call comspec_sse_fnc_log;
};

{
    [_x, _handler] call CBA_fnc_addEventHandler;
} forEach [
    "COMSPEC_SSE_ENTITY_IDENTIFIED",
    "COMSPEC_SSE_EVIDENCE_COLLECTED",
    "COMSPEC_SSE_BIOMETRIC_CAPTURED",
    "COMSPEC_SSE_PHOTO_TAKEN",
    "COMSPEC_SSE_TASK_RECEIVED",
    "COMSPEC_SSE_DOMEX_STAGE",
    "COMSPEC_SSE_DOMEX_PACKET",
    "COMSPEC_SSE_DOMEX_MAP_POINT"
];

if (hasInterface) then {
    ["COMSPEC_SSE_DOMEX_MAP_POINT", {
        params ["_payload"];
        if (isNil "_payload" || {!(_payload isEqualType createHashMap)}) exitWith {};
        if (isNull getAssignedCuratorLogic player) exitWith {};
        private _uid = _payload getOrDefault ["packet_uid", ""];
        private _pos = _payload getOrDefault ["position", []];
        if (_uid isEqualTo "" || {!(_pos isEqualType [])} || {count _pos < 2}) exitWith {};
        private _name = format ["comspec_sse_domex_pin_%1", _uid];
        if (markerType _name isNotEqualTo "") exitWith {};
        createMarkerLocal [_name, _pos];
        _name setMarkerTypeLocal "mil_dot";
        _name setMarkerColorLocal "ColorOrange";
        _name setMarkerSizeLocal [0.85, 0.85];
        _name setMarkerTextLocal (_payload getOrDefault ["label", "Renseignement"]);
    }] call CBA_fnc_addEventHandler;
};

["COMSPEC SSE event bus listeners ready"] call comspec_sse_fnc_log;
