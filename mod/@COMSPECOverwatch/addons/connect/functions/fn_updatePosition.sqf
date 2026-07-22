/*
    Remonte position + métadonnées vers Athena.
    Params: [_unit, _force]
      _force : true = envoi manuel (ignore état mission, batch, backoff).
    Retour (si _force) : "ok" | "dead" | "origin" | ""
*/
params ["_unit", ["_force", false, [true]]];
if (!hasInterface) exitWith { if (_force) then { "" } else { nil } };
if (isNull _unit || !alive _unit) exitWith { if (_force) then { "dead" } else { nil } };
if (isNull player || _unit != player) exitWith { if (_force) then { "dead" } else { nil } };
// Quit jeu / fin mission : ne plus pousser position ni évaluer d’alerte médicale
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { if (_force) then { "" } else { nil } };

// Log skip throttlé (RPT) — évite le spam PFH
private _fnc_skip = {
    params ["_reason"];
    private _now = diag_tickTime;
    private _last = missionNamespace getVariable ["COMSPEC_PosSkipLogAt", -1e9];
    private _lastReason = missionNamespace getVariable ["COMSPEC_PosSkipReason", ""];
    if ((_now - _last) < 15 && {_reason isEqualTo _lastReason}) exitWith {};
    missionNamespace setVariable ["COMSPEC_PosSkipLogAt", _now, false];
    missionNamespace setVariable ["COMSPEC_PosSkipReason", _reason, false];
    diag_log format ["[COMSPEC] UpdatePosition skip: %1 (state=%2)", _reason, getClientStateNumber];
};

// Hors mission active — pas de POST position (sauf forçage manuel hub)
// BI : 9=BRIEFING SHOWN, 10=BRIEFING READ (en jeu), 11+=fini/débrief ; SP=0 ("NONE")
private _clientState = getClientStateNumber;
if (!_force && {isMultiplayer && {_clientState < 10 || _clientState >= 11}}) exitWith {
    ["client_state"] call _fnc_skip;
};

// Backoff après 429 / disponibilité Athena (évite d’aggraver le lag) — le forçage manuel passe
private _backoffUntil = missionNamespace getVariable ["COMSPEC_ApiBackoffUntil", 0];
if (!_force && {diag_tickTime < _backoffUntil}) exitWith {
    ["api_backoff"] call _fnc_skip;
};

// Alertes KO / rythme cardiaque à zéro (chaque tick PFH, avant le filtre de batch position)
[_unit] call comspec_overwatch_connect_fnc_checkMedicalAlerts;

// BI Position formats :
// - Carte 2D / markers : Position2D = [X,Y] monde (X ouest→est, Y sud→nord)
// - getPosWorld : X/Y stables (évite faux (0,0) menu / transition éditeur)
// - ASL seul format Z absolu pour calculs 3D (getPosASL select 2) — pas ATL
private _pos = getPosWorld _unit;
private _posAsl = getPosASL _unit;
private _aslZ = _posAsl select 2;

// Menu / spawn origine (0,0) : ne jamais appeler UpdatePosition (spam journal Liaison côté Athena)
if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) exitWith {
    ["origin_00"] call _fnc_skip;
    if (_force) then { "origin" } else { nil };
};
private _callSign = [] call comspec_overwatch_connect_fnc_getCallsign;
private _role = [_unit] call comspec_overwatch_connect_fnc_getUnitRole;

private _medicalState = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
private _medicalParts = _medicalState splitString "|";
private _health = if (count _medicalParts >= 1) then { _medicalParts select 0 } else { "stable" };
// Signature « grossière » : ignore le FC (fluctue chaque seconde sous ACE) pour ne pas spammer Athena.
private _medicalSig = format [
    "%1|%2|%3|%4",
    _health,
    if (count _medicalParts >= 2) then { _medicalParts select 1 } else { "100" },
    if (count _medicalParts >= 3) then { _medicalParts select 2 } else { "0" },
    if (count _medicalParts >= 5) then { _medicalParts select 4 } else { "0" }
];

private _veh = vehicle _unit;
private _inVeh = _veh != _unit;
private _fuel = if (_inVeh) then { str (round ((fuel _veh) * 100)) } else { "" };
private _ammo = if (!_inVeh) then { str (count (magazines _unit)) + " mags" } else { "Vehicle" };

private _radioTx = [_unit] call comspec_overwatch_connect_fnc_getRadioTxState;
_radioTx params ["_radioNet", "_radioFreqVal", "_radioChannel", "_radioSpeaking", "_radioTxFlag", "_radioId", "_radioModuleOk"];
private _radioState = [_unit] call comspec_overwatch_connect_fnc_getRadioState;
private _radioFreq = if (_radioFreqVal != "") then { _radioFreqVal } else { "N/A" };
if (_radioFreq == "N/A" && {_radioState != "N/A|N/A|N/A"}) then {
    private _rp = _radioState splitString "|";
    if (count _rp >= 2) then { _radioFreq = _rp select 1; };
};
// Canal / type + état émission (déclenche un push quand PTT change)
private _radioSig = format [
    "%1|%2|%3|%4",
    _radioNet,
    if (_radioChannel == "") then { "0" } else { _radioChannel },
    if (_radioSpeaking) then { "1" } else { "0" },
    if (_radioTxFlag) then { "1" } else { "0" }
];

private _threshold = missionNamespace getVariable ["comspec_overwatch_position_threshold", 5];
private _batchInterval = missionNamespace getVariable ["comspec_overwatch_batch_interval", 3];
if (!(_batchInterval isEqualType 0)) then { _batchInterval = 3; };
_batchInterval = (_batchInterval max 1) min 30;
private _lastPos = missionNamespace getVariable ["COMSPEC_lastPos", [0,0,0]];
private _lastName = missionNamespace getVariable ["COMSPEC_lastName", ""];
private _lastRole = missionNamespace getVariable ["COMSPEC_lastRole", ""];
private _lastRadio = missionNamespace getVariable ["COMSPEC_lastRadio", ""];
private _lastMedical = missionNamespace getVariable ["COMSPEC_lastMedical", ""];
private _lastGroup = missionNamespace getVariable ["COMSPEC_lastGroup", ""];
private _lastSendTime = missionNamespace getVariable ["COMSPEC_lastSendTime", 0];
private _now = diag_tickTime;

private _groupName = trim (groupId (group _unit));
if (!(_groupName isEqualType "")) then { _groupName = str _groupName; };
_groupName = trim _groupName;

private _distance = _pos distance _lastPos;
private _distanceOk = _distance > _threshold;
private _nameChanged = _callSign != _lastName;
private _roleChanged = _role != _lastRole;
private _radioChanged = _radioSig != _lastRadio;
private _medicalChanged = _medicalSig != _lastMedical;
private _groupChanged = _groupName != _lastGroup;
private _batchOk = (_now - _lastSendTime) >= _batchInterval;
// Keep-alive : rafraîchir updated_at même immobile (TTL Tacmap ~180 s)
private _heartbeatOk = (_now - _lastSendTime) >= 45;
// Émission radio : pousser hors batch pour pastille « Émet » quasi temps réel
private _txUrgent = _radioChanged && {
    _radioSpeaking || _radioTxFlag || ((_lastRadio find "|1|") >= 0) || ((_lastRadio find "|1") >= 0)
};

private _shouldSend = _force
    || _heartbeatOk
    || (_batchOk && (_distanceOk || _nameChanged || _roleChanged || _radioChanged || _medicalChanged || _groupChanged))
    || _txUrgent;
if (!_shouldSend) exitWith {};

private _velocity = velocity _unit;
private _speed = vectorMagnitude _velocity;
private _heading = getDir _unit;
private _future = [
    (_pos select 0) + ((_velocity select 0) * 10),
    (_pos select 1) + ((_velocity select 1) * 10),
    _aslZ
];

private _stealthMode = if ((unitPos _unit) in ["DOWN", "MIDDLE"] || {captive _unit}) then { "ON" } else { "OFF" };
private _reportedPos = if (_stealthMode == "ON") then {
    [(_pos select 0) + (random 20) - 10, (_pos select 1) + (random 20) - 10, _aslZ]
} else {
    [(_pos select 0), (_pos select 1), _aslZ]
};
// Stealth peut ramener près de (0,0) — re-vérifier Position2D avant envoi
if ((abs (_reportedPos select 0) < 1) && { abs (_reportedPos select 1) < 1 }) exitWith {
    ["origin_00"] call _fnc_skip;
    if (_force) then { "origin" } else { nil };
};

// toFixed : point décimal invariant (évite « 1850,12 » localisé → JSON invalide / parse 0,0).
// Même piège que SALUTE avant toFixed : sous locale FR, str/format cassent POST /api/atak/position.
private _fnc_num = { (_this select 0) toFixed (_this select 1) };

// JSON véhicule / cinématique + asl_z joueur (altitude mer, pas ATL)
private _vehJson = format [
    "{""speed"":%1,""in_vehicle"":%2,""asl_z"":%3,""pos_z"":%3",
    [((round (_speed * 10)) / 10), 1] call _fnc_num,
    if (_inVeh) then { "true" } else { "false" },
    [_aslZ, 3] call _fnc_num
];
if (_inVeh && {missionNamespace getVariable ["comspec_overwatch_vehicle_mode", true]}) then {
    private _vd = vectorDir _veh;
    private _vu = vectorUp _veh;
    private _vv = velocity _veh;
    private _vp = getPosASL _veh;
    _vehJson = _vehJson + format [
        ",""vehicle"":""%1"",""vector_dir"":[%2,%3,%4],""vector_up"":[%5,%6,%7],""velocity"":[%8,%9,%10],""pos_asl"":[%11,%12,%13]",
        typeOf _veh,
        [_vd select 0, 5] call _fnc_num, [_vd select 1, 5] call _fnc_num, [_vd select 2, 5] call _fnc_num,
        [_vu select 0, 5] call _fnc_num, [_vu select 1, 5] call _fnc_num, [_vu select 2, 5] call _fnc_num,
        [_vv select 0, 3] call _fnc_num, [_vv select 1, 3] call _fnc_num, [_vv select 2, 3] call _fnc_num,
        [_vp select 0, 2] call _fnc_num, [_vp select 1, 2] call _fnc_num, [_vp select 2, 3] call _fnc_num
    ];
};
// Métadonnées radio (pas d’audio serveur) — pastilles Tacmap / tablette
private _escCh = (_radioChannel splitString """" joinString "");
private _escNet = (_radioNet splitString """" joinString "");
private _escRid = (_radioId splitString """" joinString "");
_vehJson = _vehJson + format [
    ",""radio_speaking"":%1,""radio_tx"":%2,""radio_channel"":""%3"",""radio_net"":""%4"",""radio_module"":%5,""radio_id"":""%6""",
    if (_radioSpeaking) then { "true" } else { "false" },
    if (_radioTxFlag) then { "true" } else { "false" },
    _escCh,
    _escNet,
    if (_radioModuleOk) then { "true" } else { "false" },
    _escRid
];
// Camp / affiliation (filtres Tacmap — fusionné dans extra via la DLL existante)
private _side = side group _unit;
private _sideStr = switch (_side) do {
    case east: { "EAST" };
    case resistance: { "GUER" };
    case civilian: { "CIV" };
    default { "WEST" };
};
private _affiliation = switch (_side) do {
    case east: { "hostile" };
    case resistance: { "unknown" };
    case civilian: { "neutral" };
    default { "friend" };
};
_vehJson = _vehJson + format [
    ",""side"":""%1"",""affiliation"":""%2""",
    _sideStr,
    _affiliation
];
private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;
_modVersion = (_modVersion splitString """" joinString "");
if (!(_modVersion isEqualTo "")) then {
    _vehJson = _vehJson + format [",""mod_version"":""%1""", _modVersion];
};
_vehJson = _vehJson + "}";

private _steamUid = getPlayerUID player;
if ((count _steamUid) < 15) then {
    _steamUid = profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""];
};

"COMSPECExtension" callExtension ["UpdatePosition", [
    [_reportedPos select 0, 2] call _fnc_num,
    [_reportedPos select 1, 2] call _fnc_num,
    [_heading, 2] call _fnc_num,
    _callSign, _role, _health, _fuel, _ammo, _radioFreq, _vehJson, _steamUid, _groupName,
    [_aslZ, 3] call _fnc_num,
    _modVersion
]];

private _trail = missionNamespace getVariable ["COMSPEC_PositionTrail", []];
_trail pushBack [_now, _callSign, [_pos select 0, _pos select 1, _aslZ], _speed, _heading, _future];
if (count _trail > 150) then {
    _trail deleteRange [0, (count _trail) - 150];
};
missionNamespace setVariable ["COMSPEC_PositionTrail", _trail, true];

private _immobileTime = missionNamespace getVariable ["COMSPEC_ImmobileSince", _now];
if (_distance < 0.5 && {_speed < 0.2}) then {
    // keep existing timer
} else {
    _immobileTime = _now;
};
missionNamespace setVariable ["COMSPEC_ImmobileSince", _immobileTime, true];

if ((_now - _immobileTime) > 180) then {
    private _alert = createHashMapFromArray [["kind", "IMMOBILE"], ["unit", _callSign], ["duration", _now - _immobileTime], ["position", _pos]];
    ["OnTrackingAnomaly", _alert] call comspec_overwatch_connect_fnc_publishEvent;
};

if (_distance > 250 && {_batchInterval > 2}) then {
    private _alert2 = createHashMapFromArray [["kind", "INCOHERENT_MOVE"], ["unit", _callSign], ["distance", _distance], ["from", _lastPos], ["to", _pos]];
    ["OnTrackingAnomaly", _alert2] call comspec_overwatch_connect_fnc_publishEvent;
};

missionNamespace setVariable ["COMSPEC_lastPos", _pos, true];
missionNamespace setVariable ["COMSPEC_lastName", _callSign, true];
missionNamespace setVariable ["COMSPEC_lastRole", _role, true];
missionNamespace setVariable ["COMSPEC_lastRadio", _radioSig, true];
missionNamespace setVariable ["COMSPEC_lastMedical", _medicalSig, true];
missionNamespace setVariable ["COMSPEC_lastGroup", _groupName, true];
missionNamespace setVariable ["COMSPEC_lastSendTime", _now, true];
missionNamespace setVariable ["COMSPEC_LastPositionSync", _now, false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;

if (_force) then { "ok" } else { nil };
