/*

 * Demande QRF (Quick Reaction Force) pour appui immédiat

 */



params [

    ["_threatType", "ATTACK", [""]],

    ["_priority", "IMMEDIATE", [""]],

    ["_threatDescription", "", [""]],

    ["_enemyStrength", "UNKNOWN", [""]],

    ["_friendlyCasualties", 0, [0]],

    ["_friendlyStatus", "ENGAGED", [""]],

    ["_contactPos", [], [[]]]

];



if (!hasInterface) exitWith { false };



if (_threatDescription isEqualTo "") exitWith {

    ["Décrivez la menace pour demander le renfort.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;

    false

};



if (_contactPos isEqualTo []) then {

    _contactPos = getPosWorld player;

};



// Alias jeu → valeurs acceptées côté Athena (ENUM SQL)

private _threatNorm = switch (toUpper _threatType) do {

    case "ATTACK";

    case "TROOPS_IN_CONTACT";

    case "TIC";

    case "CONTACT": { "INFANTRY" };

    case "IED_STRIKE";

    case "IED": { "OTHER" };

    case "VEHICLE";

    case "ARMOR": { "ARMORED" };

    case "AIR";

    case "CAS": { "AIRCRAFT" };

    case "AMBUSH": { "AMBUSH" };

    case "OVERRUN": { "OVERRUN" };

    case "SURROUNDED": { "SURROUNDED" };

    case "INFANTRY";

    case "ARMORED";

    case "AIRCRAFT";

    case "OTHER": { toUpper _threatType };

    default { "OTHER" };

};



private _statusNorm = switch (toUpper _friendlyStatus) do {

    case "ENGAGED": { "PINNED" };

    case "SECURE";

    case "HOLD": { "HOLDING" };

    case "RETREATING";

    case "WITHDRAWING": { "FALLING_BACK" };

    case "HOLDING";

    case "PINNED";

    case "FALLING_BACK";

    case "SURROUNDED";

    case "OVERRUN": { toUpper _friendlyStatus };

    default { "PINNED" };

};



private _priorityNorm = switch (toUpper _priority) do {

    case "ROUTINE";

    case "PRIORITY";

    case "IMMEDIATE";

    case "FLASH": { toUpper _priority };

    default { "IMMEDIATE" };

};



private _strengthNorm = switch (toUpper _enemyStrength) do {

    case "FIRE_TEAM";

    case "SQUAD";

    case "PLATOON";

    case "COMPANY";

    case "OVERWHELMING";

    case "UNKNOWN": { toUpper _enemyStrength };

    default { "UNKNOWN" };

};



private _friendlyStrength = { alive _x } count units (group player);

private _unitLabel = groupId (group player);

if (_unitLabel isEqualTo "") then { _unitLabel = name player; };

if (_unitLabel isEqualTo "") then { _unitLabel = "Unité"; };



private _qrfData = createHashMap;

_qrfData set ["priority", _priorityNorm];

_qrfData set ["contact_pos_x", _contactPos select 0];

_qrfData set ["contact_pos_y", _contactPos select 1];

_qrfData set ["pos_x", _contactPos select 0];

_qrfData set ["pos_y", _contactPos select 1];

_qrfData set ["grid_reference", mapGridPosition _contactPos];

_qrfData set ["threat_type", _threatNorm];

_qrfData set ["threat_description", _threatDescription];

_qrfData set ["enemy_strength", _strengthNorm];

_qrfData set ["requesting_unit", _unitLabel];

_qrfData set ["requesting_callsign", name player];

_qrfData set ["friendly_strength", _friendlyStrength];

_qrfData set ["friendly_casualties", _friendlyCasualties];

_qrfData set ["friendly_status", _statusNorm];



_qrfData set ["support_requested", ["infantry"]];

if (_strengthNorm in ["PLATOON", "COMPANY", "OVERWHELMING"]) then {

    _qrfData set ["support_requested", ["infantry", "armor", "cas"]];

};

if (_friendlyCasualties > 0) then {

    private _support = _qrfData get "support_requested";

    _support pushBackUnique "medevac";

    _qrfData set ["support_requested", _support];

};



private _threatLabel = switch (toUpper _threatType) do {

    case "AMBUSH": { "embuscade" };

    case "ATTACK";

    case "INFANTRY": { "attaque" };

    case "TROOPS_IN_CONTACT";

    case "TIC";

    case "CONTACT": { "contact" };

    case "IED_STRIKE";

    case "IED": { "engin explosif" };

    case "ARMORED";

    case "ARMOR";

    case "VEHICLE": { "blindés" };

    case "AIRCRAFT";

    case "AIR";

    case "CAS": { "aérien" };

    case "OVERRUN": { "débordement" };

    case "SURROUNDED": { "encerclement" };

    default { "menace" };

};

private _enemyLabel = switch (_strengthNorm) do {

    case "FIRE_TEAM": { "équipe" };

    case "SQUAD": { "groupe" };

    case "PLATOON": { "section" };

    case "COMPANY": { "compagnie" };

    case "OVERWHELMING": { "écrasant" };

    default { "inconnu" };

};

private _statusLabel = switch (toUpper _friendlyStatus) do {

    case "SECURE";

    case "HOLDING";

    case "HOLD": { "sécurisé" };

    case "ENGAGED";

    case "PINNED": { "engagé" };

    case "FALLING_BACK";

    case "RETREATING";

    case "WITHDRAWING": { "repli" };

    case "SURROUNDED": { "encerclé" };

    case "OVERRUN": { "débordé" };

    default { toLower _friendlyStatus };

};



private _applyLocalQrfFeedback = {

    params ["_okAthena"];



    if (_priorityNorm isEqualTo "FLASH") then {

        playSound "RadioAmbient5";

    } else {

        playSound "RadioAmbient3";

    };



    private _markerName = format ["qrf_contact_%1_%2", floor time, floor random 10000];

    private _marker = createMarkerLocal [_markerName, _contactPos];

    private _qrfText = format ["Renfort — %1", _threatLabel];

    _marker setMarkerTypeLocal "hd_destroy";

    _marker setMarkerColorLocal "ColorRed";

    _marker setMarkerTextLocal _qrfText;

    _marker setMarkerAlphaLocal 1.0;

    [_markerName, _contactPos, "hd_destroy", "ColorRed", _qrfText, "ace_qrf"] call comspec_overwatch_connect_fnc_sendLocalTacticalMarker;



    if (sunOrMoon < 0.5) then {

        private _flare = "F_40mm_Red" createVehicle _contactPos;

        _flare setPos [_contactPos select 0, _contactPos select 1, (_contactPos select 2) + 150];

        _flare setVelocity [0, 0, -5];

    };



    if (_okAthena && {[] call comspec_overwatch_connect_fnc_shouldShowScreenNotification}) then {

        private _urgencyColor = if (_priorityNorm isEqualTo "FLASH") then { "#ff0000" } else { "#ff9900" };

        hint parseText format [

            "<t color='%1' size='1.5' align='center'>RENFORT DEMANDÉ</t><br/>" +

            "<t size='1.2'>Menace : %2</t><br/>" +

            "<t size='1.1'>Tenez la position, le renfort arrive</t><br/>" +

            "<t size='1'>Effectif adverse estimé : %3</t><br/>" +

            "<t size='1'>Statut : %4</t>",

            _urgencyColor,

            _threatLabel,

            _enemyLabel,

            _statusLabel

        ];

    };



    ["QRF_REQUESTED", createHashMapFromArray [

        ["threat_type", _threatNorm],

        ["priority", _priorityNorm],

        ["enemy_strength", _strengthNorm],

        ["casualties", _friendlyCasualties],

        ["athena_ok", _okAthena]

    ]] call comspec_overwatch_connect_fnc_publishEvent;

};



private _jsonString = [_qrfData] call comspec_overwatch_connect_fnc_hashMapToJson;

private _parsed = [
    "RequestQRF",
    [_jsonString],
    "QRF",
    true,
    true,
    "liaison",
    true
] call comspec_overwatch_connect_fnc_callExtLogged;

_parsed params ["_ok", "", "_detail"];



if (_ok) then {

    ["Demande de renfort transmise — tenez la position.", "tactical", "critical"] call comspec_overwatch_connect_fnc_announce;

    [true] call _applyLocalQrfFeedback;

    true

} else {

    // Marqueur / son locaux même si TOC n’a pas reçu — le terrain ne doit pas rester sans repère

    [false] call _applyLocalQrfFeedback;

    [([

        _detail,

        "Impossible de transmettre la demande de renfort — vérifiez la liaison Athena."

    ] call comspec_overwatch_connect_fnc_atakExtFailMessage), "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;

    false

};

