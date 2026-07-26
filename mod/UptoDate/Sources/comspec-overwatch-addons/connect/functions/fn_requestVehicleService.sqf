/*
 * Demande service pour véhicule (ravitaillement, réparation, etc.)
 */

params [
    ["_vehicle", objNull, [objNull]],
    ["_serviceType", "MAINTENANCE", [""]],
    ["_priority", "MEDIUM", [""]],
    ["_details", "", [""]]
];

if (!hasInterface) exitWith { false };

if (isNull _vehicle) exitWith {
    ["Aucun véhicule sélectionné.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

if (_vehicle isEqualTo player) exitWith {
    ["Placez-vous dans un véhicule pour demander un service.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

private _pos = getPosWorld _vehicle;

private _serviceData = createHashMap;
_serviceData set ["vehicle_callsign", getText (configOf _vehicle >> "displayName")];
_serviceData set ["request_type", _serviceType];
_serviceData set ["priority", _priority];
_serviceData set ["request_details", _details];
_serviceData set ["service_pos_x", _pos select 0];
_serviceData set ["service_pos_y", _pos select 1];
_serviceData set ["requested_by_callsign", name player];

private _jsonString = [_serviceData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _parsed = [
    "COMSPECExtension" callExtension ["RequestVehicleService", [_jsonString]]
] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
_parsed params ["_ok", "", "_detail"];

private _serviceLabel = switch (toUpper _serviceType) do {
    case "REFUEL": { "ravitaillement carburant" };
    case "REARM": { "réapprovisionnement munitions" };
    case "REPAIR": { "réparation" };
    case "MAINTENANCE": { "maintenance" };
    case "RECOVERY": { "récupération" };
    default { "service" };
};

if (_ok) then {
    [format ["Demande de %1 transmise.", _serviceLabel], "system", "info"] call comspec_overwatch_connect_fnc_announce;

    if (_priority in ["HIGH", "CRITICAL"] && {[] call comspec_overwatch_connect_fnc_shouldShowScreenNotification}) then {
        private _prioLabel = if (_priority isEqualTo "CRITICAL") then { "critique" } else { "haute" };
        hint parseText format [
            "<t color='#ff9900' size='1.3'>SERVICE VÉHICULE</t><br/>" +
            "<t size='1.1'>%1 — priorité %2</t><br/>" +
            "<t size='1'>Restez à proximité du véhicule</t>",
            _serviceLabel,
            _prioLabel
        ];
    };

    if (_priority isEqualTo "CRITICAL") then {
        private _smoke = "SmokeShellYellow" createVehicle _pos;
        _smoke setPos [_pos select 0, _pos select 1, (_pos select 2) + 1];
    };

    private _markerName = format ["vehicle_service_%1_%2", floor time, floor random 10000];
    private _marker = createMarkerLocal [_markerName, _pos];
    private _svcText = format ["Service — %1", _serviceLabel];
    _marker setMarkerTypeLocal "hd_service";
    _marker setMarkerColorLocal "ColorYellow";
    _marker setMarkerTextLocal _svcText;
    _marker setMarkerAlphaLocal 0.8;
    [_markerName, _pos, "mil_box", "ColorYellow", _svcText, "ace_service"] call comspec_overwatch_connect_fnc_sendLocalTacticalMarker;

    [{
        params ["_n"];
        "COMSPECExtension" callExtension ["SendMarker", [_n, "{}", "1", "1"]];
        deleteMarkerLocal _n;
    }, [_markerName], 600] call CBA_fnc_waitAndExecute;

    ["VEHICLE_SERVICE_REQUESTED", createHashMapFromArray [
        ["service_type", _serviceType],
        ["priority", _priority]
    ]] call comspec_overwatch_connect_fnc_publishEvent;

    true
} else {
    [([
        _detail,
        format ["Impossible de transmettre la demande de %1 — vérifiez la liaison Athena.", _serviceLabel]
    ] call comspec_overwatch_connect_fnc_atakExtFailMessage), "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};
