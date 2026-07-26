/*
 * Auteur: COMSPEC
 * Demande évacuation médicale MEDEVAC 9-Line
 *
 * Arguments:
 * 0: Priorité <STRING> - "URGENT", "PRIORITY", "ROUTINE"
 * 1: Patients T1 (urgent chirurgical) <NUMBER>
 * 2: Patients T2 (urgent) <NUMBER>
 * 3: Patients T3 (différé) <NUMBER>
 * 4: (Optional) Statut sécurité LZ <STRING> - "NO_ENEMY", "POSSIBLE_ENEMY", "ENEMY_IN_AREA", "HOT_LZ"
 * 5: (Optional) Marquage LZ <STRING> - "NONE", "SMOKE", "PANEL", "PYRO"
 * 6: (Optional) Couleur marquage <STRING> - "RED", "GREEN", "YELLOW", "PURPLE"
 * 7: (Optional) Position pickup <ARRAY> - [x, y] (défaut: joueur)
 *
 * Valeur de retour:
 * <BOOL> - true si succès
 *
 * Exemple:
 * ["URGENT", 1, 0, 2, "POSSIBLE_ENEMY", "SMOKE", "GREEN"] call comspec_overwatch_connect_fnc_requestMEDEVAC;
 */

params [
    ["_priority", "URGENT", [""]],
    ["_patientsT1", 0, [0]],
    ["_patientsT2", 0, [0]],
    ["_patientsT3", 0, [0]],
    ["_securityStatus", "NO_ENEMY", [""]],
    ["_lzMarking", "SMOKE", [""]],
    ["_lzMarkingColor", "GREEN", [""]],
    ["_pickupPos", [], [[]]]
];

// Validation
private _totalPatients = _patientsT1 + _patientsT2 + _patientsT3;
if (_totalPatients isEqualTo 0) exitWith {
    systemChat "❌ Au moins un patient requis";
    false
};

// Position par défaut
if (_pickupPos isEqualTo []) then {
    _pickupPos = getPosWorld player;
};

// Calculer patients litter vs ambulatory (simplifié: T1+T2 = litter, T3 = ambulatory)
private _patientsLitter = _patientsT1 + _patientsT2;
private _patientsAmbulatory = _patientsT3;

// Préparer données 9-Line
private _medevacData = createHashMap;
_medevacData set ["priority", _priority];
_medevacData set ["pickup_grid", mapGridPosition _pickupPos];
_medevacData set ["pickup_pos_x", _pickupPos select 0];
_medevacData set ["pickup_pos_y", _pickupPos select 1];
_medevacData set ["pickup_elevation", round((getPosASL player) select 2)];
_medevacData set ["radio_frequency", (call acre_api_fnc_getCurrentRadio) call acre_api_fnc_getRadioChannel]; // ACRE
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
_medevacData set ["requested_by_callsign", name player];
_medevacData set ["requested_by_unit", groupId (group player)];

// Envoyer via extension
private _jsonString = [_medevacData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["RequestMEDEVAC", [_jsonString]];

// Feedback
if ((_result select 0) isEqualTo "OK") then {
    systemChat "✅ MEDEVAC demandée, standby pour extraction";
    
    // Hint visuel important
    hint parseText format [
        "<t color='#ff3333' size='1.5' align='center'>MEDEVAC EN ROUTE</t><br/>" +
        "<t size='1.1'>Marquez LZ avec %1 %2</t><br/>" +
        "<t size='1.1'>Sécurisez périmètre</t><br/>" +
        "<t size='1'>Patients: %3×T1 %4×T2 %5×T3</t>",
        _lzMarking,
        _lzMarkingColor,
        _patientsT1,
        _patientsT2,
        _patientsT3
    ];
    
    // Son alerte
    playSound "RadioAmbient1";
    
    // Marker LZ local
    private _markerName = format ["medevac_lz_%1", time];
    private _marker = createMarkerLocal [_markerName, _pickupPos];
    _marker setMarkerTypeLocal "hd_pickup";
    _marker setMarkerColorLocal "ColorRed";
    _marker setMarkerTextLocal format ["MEDEVAC LZ - %1 patients", _totalPatients];
    _marker setMarkerAlphaLocal 1.0;
    
    // Log activité
    ["MEDEVAC_REQUESTED", createHashMapFromArray [
        ["priority", _priority],
        ["patients_total", _totalPatients],
        ["patients_t1", _patientsT1]
    ]] call comspec_overwatch_connect_fnc_publishEvent;
    
    true
} else {
    systemChat format ["❌ Erreur demande MEDEVAC: %1", _result select 1];
    false
};
