/*
    Émet un événement télémétrie structuré vers Athena (bus Phase A/B).
    Params:
      0: type <STRING> — med | med_clear | unit | combat | pos | veh | …
      1: payload <HASHMAP>
      2: priorité optionnelle <SCALAR> — 0 critique … 3 bulk (−1 = auto)
*/
params [
    ["_type", "", [""]],
    ["_payload", createHashMap, [createHashMap]],
    ["_priority", -1, [0]]
];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (_type isEqualTo "") exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

private _typeNorm = toLower _type;
private _prio = _priority;
if (_prio < 0) then {
    _prio = switch (_typeNorm) do {
        case "med";
        case "medical";
        case "medical_alert": { 0 };
        case "med_clear";
        case "medical_clear": { 1 };
        case "combat";
        case "fire";
        case "unit";
        case "unit_event";
        case "comms";
        case "acre";
        case "salute";
        case "bda";
        case "bda_confirm": { 1 };
        case "veh";
        case "vehicle";
        case "logstat";
        case "state";
        case "flight";
        case "obs";
        case "observation";
        case "recon";
        case "sigint": { 2 };
        case "weather";
        case "wx": { 3 };
        default { 1 };
    };
};
_prio = (round _prio) max 0 min 3;

if !(_payload isEqualType createHashMap) then {
    _payload = createHashMap;
};
_payload set ["t", _typeNorm];
private _csExisting = _payload getOrDefault ["call_sign", ""];
if (!(_csExisting isEqualType "")) then {
    _payload set ["call_sign", str _csExisting];
    _csExisting = str _csExisting;
};
if (_csExisting isEqualTo "") then {
    private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (_cs isEqualTo "") then { _cs = name player; };
    _payload set ["call_sign", _cs];
};

private _coalesce = switch (_typeNorm) do {
    case "med";
    case "medical";
    case "medical_alert": {
        format ["med:%1", toLower (_payload getOrDefault ["call_sign", "op"])]
    };
    case "med_clear";
    case "medical_clear": {
        format ["medclear:%1", toLower (_payload getOrDefault ["call_sign", "op"])]
    };
    case "unit";
    case "unit_event": {
        format ["unit:%1:%2", toLower (_payload getOrDefault ["action", "evt"]), toLower (_payload getOrDefault ["call_sign", "op"])]
    };
    case "combat";
    case "fire": {
        format ["combat:%1:%2", toLower (_payload getOrDefault ["kind", "fire"]), floor diag_tickTime]
    };
    default { format ["%1:%2", _typeNorm, floor (diag_tickTime * 10)] };
};

private _json = [_payload] call comspec_overwatch_connect_fnc_hashMapToJson;
if (!(_json isEqualType "") || {_json isEqualTo ""}) exitWith { false };

private _raw = ["COMSPECExtension" callExtension [
    "EmitTelemetry",
    [str _prio, _typeNorm, _coalesce, _json]
]] call comspec_overwatch_connect_fnc_extResult;

if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
private _parts = _raw splitString "|";
((count _parts) >= 1) && {(_parts select 0) isEqualTo "OK"}
