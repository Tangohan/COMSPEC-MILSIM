/*
 * Demande évacuation médicale MEDEVAC 9-Line
 */

params [
    ["_priority", "URGENT", [""]],
    ["_patientsT1", 0, [0]],
    ["_patientsT2", 0, [0]],
    ["_patientsT3", 0, [0]],
    ["_securityStatus", "NO_ENEMY", [""]],
    ["_lzMarking", "SMOKE", [""]],
    ["_lzMarkingColor", "GREEN", [""]],
    ["_pickupPos", [], [[]]],
    ["_remarks", "", [""]],
    ["_gridOverride", "", [""]]
];

if (!hasInterface) exitWith { false };

private _totalPatients = _patientsT1 + _patientsT2 + _patientsT3;
if (_totalPatients isEqualTo 0) exitWith {
    ["Indiquez au moins un blessé pour la demande d'évacuation.", "medical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

if (_pickupPos isEqualTo []) then {
    _pickupPos = getPosWorld player;
};

private _patientsLitter = _patientsT1 + _patientsT2;
private _patientsAmbulatory = _patientsT3;
private _pickupGrid = if (_gridOverride isNotEqualTo "") then { _gridOverride } else { mapGridPosition _pickupPos };

private _medevacData = createHashMap;
_medevacData set ["priority", _priority];
_medevacData set ["pickup_grid", _pickupGrid];
_medevacData set ["pickup_pos_x", _pickupPos select 0];
_medevacData set ["pickup_pos_y", _pickupPos select 1];
_medevacData set ["pickup_elevation", round ((getPosASL player) select 2)];
private _radioFreq = "";
if (!isNil "acre_api_fnc_getCurrentRadio" && {!isNil "acre_api_fnc_getRadioChannel"}) then {
    private _radio = [] call acre_api_fnc_getCurrentRadio;
    if (_radio isEqualType "" && {_radio != ""}) then {
        _radioFreq = str ([_radio] call acre_api_fnc_getRadioChannel);
    };
};
_medevacData set ["radio_frequency", _radioFreq];
_medevacData set ["radio_callsign", groupId (group player)];
_medevacData set ["patients_t1_urgent", _patientsT1];
_medevacData set ["patients_t2_urgent", _patientsT2];
_medevacData set ["patients_t3_delayed", _patientsT3];
_medevacData set ["patients_t4_expectant", 0];
_medevacData set ["patients_litter", _patientsLitter];
_medevacData set ["patients_ambulatory", _patientsAmbulatory];
_medevacData set ["security_status", _securityStatus];
_medevacData set ["lz_marking", _lzMarking];
_medevacData set ["lz_marking_color", _lzMarkingColor];
_medevacData set ["patient_nationality", "FRIENDLY"];
_medevacData set ["patient_status", "MILITARY"];
_medevacData set ["nbc_contamination", "NONE"];
_medevacData set ["remarks", _remarks];
_medevacData set ["requested_by_callsign", name player];
_medevacData set ["requested_by_unit", groupId (group player)];
// Lignes 9-line lisibles côté TOC (historique atak_nine_line)
_medevacData set ["line1", _pickupGrid];
_medevacData set ["line2", format ["%1 / %2", _radioFreq, groupId (group player)]];
_medevacData set ["line3", format ["A %1 / B %2 / C %3", _patientsT1, _patientsT2, _patientsT3]];
_medevacData set ["line5", format ["L %1 / A %2", _patientsLitter, _patientsAmbulatory]];
_medevacData set ["line6", _securityStatus];
_medevacData set ["line7", format ["%1 %2", _lzMarking, _lzMarkingColor]];
if (_remarks isNotEqualTo "") then {
    _medevacData set ["line4", _remarks];
};

private _jsonString = [_medevacData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _parsed = [
    "RequestMEDEVAC",
    [_jsonString],
    "MEDEVAC",
    true,
    true,
    "medical",
    true
] call comspec_overwatch_connect_fnc_callExtLogged;
_parsed params ["_ok", "", "_detail"];

if (_ok) then {
    ["Demande d'évacuation transmise — tenez la zone d'extraction.", "medical", "critical"] call comspec_overwatch_connect_fnc_announce;

    private _markLabel = switch (toUpper _lzMarking) do {
        case "SMOKE": { "fumée" };
        case "PANEL": { "panneau" };
        case "PYRO": { "signal pyrotechnique" };
        default { "marquage" };
    };
    private _colorLabel = switch (toUpper _lzMarkingColor) do {
        case "RED": { "rouge" };
        case "GREEN": { "verte" };
        case "YELLOW": { "jaune" };
        case "PURPLE": { "violette" };
        default { toLower _lzMarkingColor };
    };

    if ([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) then {
        hint parseText format [
            "<t color='#ff3333' size='1.5' align='center'>ÉVACUATION DEMANDÉE</t><br/>" +
            "<t size='1.1'>Marquez la zone avec %1 %2</t><br/>" +
            "<t size='1.1'>Sécurisez le périmètre</t><br/>" +
            "<t size='1'>Blessés : %3 urgents / %4 prioritaires / %5 différés</t>",
            _markLabel,
            _colorLabel,
            _patientsT1,
            _patientsT2,
            _patientsT3
        ];
    };

    playSound "RadioAmbient1";

    private _markerName = format ["medevac_lz_%1_%2", floor time, floor random 10000];
    private _marker = createMarkerLocal [_markerName, _pickupPos];
    private _lzText = format ["LZ évacuation — %1 blessé(s)", _totalPatients];
    _marker setMarkerTypeLocal "hd_pickup";
    _marker setMarkerColorLocal "ColorRed";
    _marker setMarkerTextLocal _lzText;
    _marker setMarkerAlphaLocal 1.0;
    [_markerName, _pickupPos, "hd_pickup", "ColorRed", _lzText, "ace_medevac"] call comspec_overwatch_connect_fnc_sendLocalTacticalMarker;

    ["MEDEVAC_REQUESTED", createHashMapFromArray [
        ["priority", _priority],
        ["patients_total", _totalPatients],
        ["patients_t1", _patientsT1]
    ]] call comspec_overwatch_connect_fnc_publishEvent;

    true
} else {
    [([
        _detail,
        "Impossible de transmettre la demande d'évacuation — vérifiez la liaison Athena."
    ] call comspec_overwatch_connect_fnc_atakExtFailMessage), "medical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};
