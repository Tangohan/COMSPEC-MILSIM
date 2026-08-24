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

// Grâce REAPP / respawn : pas de POST ni d’alerte médicale (spike ACE + MRH + handshake)
if (!_force && {diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])}) exitWith {
    ["respawn_grace"] call _fnc_skip;
};

// Équipement requis (réglage CBA) — bloque sync position si manquant
// Exception : géolocalisation téléphone posée en Eden / Zeus (joueur sans tablette).
if !(
    ([_unit] call comspec_overwatch_connect_fnc_hasTerminal)
    || { [_unit, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag }
) exitWith {
    if (!_force) then { ["no_terminal"] call _fnc_skip; };
    if (_force) then { "" } else { nil }
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

// Joueur sans tablette, suivi téléphone Zeus : même filtrage de données que les IA.
if (
    !([_unit] call comspec_overwatch_connect_fnc_hasTerminal)
    && { [_unit, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag }
) exitWith {
    [_unit] call comspec_overwatch_connect_fnc_reportPhonePosition;
    if (_force) then { "ok" } else { nil }
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
// Avant armement des alertes (fenêtre ACE / spawn) : ne jamais remonter KO vers Athena.
if (!(missionNamespace getVariable ["COMSPEC_MedicalAlertsArmed", false])) then {
    if (_health in ["unconscious", "cardiac_arrest"]) then {
        _health = "stable";
        if (count _medicalParts >= 1) then { _medicalParts set [0, "stable"]; };
        if (count _medicalParts >= 5) then { _medicalParts set [4, "0"]; };
    };
} else {
    // Confirmation anti faux positifs ACE (INCAPACITATED / spike spawn).
    private _streak = missionNamespace getVariable ["COMSPEC_MedicalCritStreak", 0];
    if (_health in ["unconscious", "cardiac_arrest"]) then {
        _streak = _streak + 1;
    } else {
        _streak = 0;
    };
    missionNamespace setVariable ["COMSPEC_MedicalCritStreak", _streak, false];
    if (_streak < 3) then {
        if (_health in ["unconscious", "cardiac_arrest"]) then {
            _health = "stable";
            if (count _medicalParts >= 1) then { _medicalParts set [0, "stable"]; };
            if (count _medicalParts >= 5) then { _medicalParts set [4, "0"]; };
        };
    };
};
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

private _heading = getDir (if (_inVeh) then { _veh } else { _unit });
private _airborne = false;
if (_inVeh && {
    _veh isKindOf "Air"
    || {_veh isKindOf "Helicopter"}
    || {_veh isKindOf "Plane"}
    || {unitIsUAV _veh}
}) then {
    _airborne = !(isTouchingGround _veh) && {((getPosASL _veh) select 2) > 5};
};
private _moveTh = missionNamespace getVariable ["COMSPEC_MoveThresholdInf", 3];
if (!(_moveTh isEqualType 0)) then { _moveTh = 3; };
if (_inVeh) then {
    if (_airborne) then {
        _moveTh = missionNamespace getVariable ["COMSPEC_MoveThresholdAir", 15];
        if (!(_moveTh isEqualType 0)) then { _moveTh = 15; };
    } else {
        _moveTh = missionNamespace getVariable ["COMSPEC_MoveThresholdVeh", 10];
        if (!(_moveTh isEqualType 0)) then { _moveTh = 10; };
    };
};
private _headingTh = missionNamespace getVariable ["COMSPEC_HeadingThreshold", 15];
if (!(_headingTh isEqualType 0)) then { _headingTh = 15; };
private _posMin = missionNamespace getVariable ["COMSPEC_PositionMinInterval", 5];
if (!(_posMin isEqualType 0)) then { _posMin = 5; };
private _heartbeat = missionNamespace getVariable ["COMSPEC_HeartbeatInterval", 30];
if (!(_heartbeat isEqualType 0)) then { _heartbeat = 30; };
private _policy = missionNamespace getVariable ["COMSPEC_NetworkPolicy", 2];
if (!(_policy isEqualType 0)) then { _policy = 2; };
_policy = (round _policy) max 0 min 2;
if (_airborne) then {
    _posMin = (_posMin * 0.5) max 0.5;
    _moveTh = (_moveTh * 0.65) max 1;
};

private _lastPos = missionNamespace getVariable ["COMSPEC_lastPos", [0,0,0]];
private _lastName = missionNamespace getVariable ["COMSPEC_lastName", ""];
private _lastRole = missionNamespace getVariable ["COMSPEC_lastRole", ""];
private _lastRadio = missionNamespace getVariable ["COMSPEC_lastRadio", ""];
private _lastMedical = missionNamespace getVariable ["COMSPEC_lastMedical", ""];
private _lastGroup = missionNamespace getVariable ["COMSPEC_lastGroup", ""];
private _lastHeading = missionNamespace getVariable ["COMSPEC_lastHeading", -1];
private _lastVehSig = missionNamespace getVariable ["COMSPEC_lastVehSig", ""];
private _lastSendTime = missionNamespace getVariable ["COMSPEC_lastSendTime", 0];
private _now = diag_tickTime;

private _groupName = trim (groupId (group _unit));
if (!(_groupName isEqualType "")) then { _groupName = str _groupName; };
_groupName = trim _groupName;

private _vehSig = format [
    "%1|%2",
    if (_inVeh) then { typeOf _veh } else { "INF" },
    if (_inVeh) then { str (floor ((fuel _veh) * 10)) } else { "" }
];

private _distance = _pos distance _lastPos;
private _distanceOk = _distance > _moveTh;
private _headingDelta = if (!(_lastHeading isEqualType 0) || {_lastHeading < 0}) then {
    999
} else {
    private _d = abs (_heading - _lastHeading);
    if (_d > 180) then { _d = 360 - _d; };
    _d
};
private _headingChanged = _headingDelta > _headingTh;
private _nameChanged = _callSign != _lastName;
private _roleChanged = _role != _lastRole;
private _radioChanged = _radioSig != _lastRadio;
private _medicalChanged = _medicalSig != _lastMedical;
private _groupChanged = _groupName != _lastGroup;
private _vehChanged = _vehSig != _lastVehSig;
private _stateChanged = _nameChanged || _roleChanged || _radioChanged || _medicalChanged || _groupChanged || _vehChanged;
private _minOk = (_now - _lastSendTime) >= _posMin;
private _heartbeatOk = (_now - _lastSendTime) >= _heartbeat;
private _txUrgent = _radioChanged && {
    _radioSpeaking || _radioTxFlag || ((_lastRadio find "|1|") >= 0) || ((_lastRadio find "|1") >= 0)
};
private _medUrgent = _medicalChanged && {_health in ["unconscious", "cardiac_arrest"]};

private _shouldSend = _force || _txUrgent || _medUrgent;
switch (_policy) do {
    case 0: {
        _shouldSend = _shouldSend || _minOk;
    };
    case 1: {
        _shouldSend = _shouldSend || (_minOk && (_distanceOk || _headingChanged || _stateChanged));
    };
    default {
        _shouldSend = _shouldSend || _heartbeatOk || (_minOk && (_distanceOk || _headingChanged || _stateChanged));
    };
};
if (!_shouldSend) exitWith {};

// Pipeline liaison unifié — position seule si écran cassé, blocage si hors couverture
private _txGate = [false] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith {
    if (_force) then { _txGate getOrDefault ["reason", "blocked"] } else { nil };
};
private _txMode = _txGate getOrDefault ["mode", "full"];
private _linkState = _txGate getOrDefault ["link_state", missionNamespace getVariable ["COMSPEC_LinkState", "linked"]];

private _velocity = if (_inVeh) then { velocity _veh } else { velocity _unit };
private _speed = vectorMagnitude _velocity;
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

// JSON cinématique : vitesse / vecteur toujours, orientation objet distincte du cap de déplacement
private _velDir = (_velocity select 0) atan2 (_velocity select 1);
if (_velDir < 0) then { _velDir = _velDir + 360; };
private _platform = "INFANTRY";
if (_inVeh) then {
    if (_veh isKindOf "UAV" || {unitIsUAV _veh}) then {
        _platform = "UAV";
    } else {
        if (_veh isKindOf "Helicopter") then {
            _platform = "HELICOPTER";
        } else {
            if (_veh isKindOf "Plane" || {_veh isKindOf "Air"}) then {
                _platform = "FIXED_WING";
            } else {
                _platform = "GROUND_VEHICLE";
            };
        };
    };
};
private _terrainZ = getTerrainHeightASL [_pos select 0, _pos select 1];
private _vehJson = format [
    "{""speed"":%1,""in_vehicle"":%2,""asl_z"":%3,""pos_z"":%3,""terrain_z"":%4,""heading_object"":%5,""velocity"":[%6,%7,%8]",
    [((round (_speed * 10)) / 10), 1] call _fnc_num,
    if (_inVeh) then { "true" } else { "false" },
    [_aslZ, 3] call _fnc_num,
    [_terrainZ, 3] call _fnc_num,
    [_heading, 2] call _fnc_num,
    [_velocity select 0, 3] call _fnc_num,
    [_velocity select 1, 3] call _fnc_num,
    [_velocity select 2, 3] call _fnc_num
];
if (_speed > 0.15) then {
    _vehJson = _vehJson + format [",""movement_heading"":%1", [_velDir, 1] call _fnc_num];
};
_vehJson = _vehJson + format [",""platform"":""%1""", _platform];
if (_inVeh && {missionNamespace getVariable ["comspec_overwatch_vehicle_mode", true]}) then {
    private _vd = vectorDir _veh;
    private _vu = vectorUp _veh;
    private _vp = getPosASL _veh;
    _vehJson = _vehJson + format [
        ",""vehicle"":""%1"",""vector_dir"":[%2,%3,%4],""vector_up"":[%5,%6,%7],""pos_asl"":[%8,%9,%10]",
        typeOf _veh,
        [_vd select 0, 5] call _fnc_num, [_vd select 1, 5] call _fnc_num, [_vd select 2, 5] call _fnc_num,
        [_vu select 0, 5] call _fnc_num, [_vu select 1, 5] call _fnc_num, [_vu select 2, 5] call _fnc_num,
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
// Métadonnées Wave Relay / MPU-5 (ATAK Enhanced) — lecture seule des variables joueur
if (
    (["wave_relay"] call comspec_overwatch_connect_fnc_isModModuleEnabled)
    && {_unit getVariable ["Iceman_WR_hasMPU5", false]}
) then {
    private _wrTg = _unit getVariable ["Iceman_WR_activeTG", -1];
    private _wrFreq = str (_unit getVariable ["Iceman_WR_frequency", ""]);
    private _wrNode = str (_unit getVariable ["Iceman_WR_nodeId", ""]);
    private _wrGw = _unit getVariable ["Iceman_WR_gateway", false];
    private _wrBridge = _unit getVariable ["Iceman_WR_bridgeActive", false];
    _wrFreq = (_wrFreq splitString """" joinString "");
    _wrNode = (_wrNode splitString """" joinString "");
    _vehJson = _vehJson + format [
        ",""wr_mpu5"":true,""wr_tg"":%1,""wr_freq"":""%2"",""wr_node"":""%3"",""wr_gateway"":%4,""wr_bridge"":%5",
        _wrTg,
        _wrFreq,
        _wrNode,
        if (_wrGw) then { "true" } else { "false" },
        if (_wrBridge) then { "true" } else { "false" }
    ];
};
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
private _grp = group _unit;
private _grpCount = { alive _x } count units _grp;
private _crewCount = if (_inVeh) then { { alive _x } count crew _veh } else { _grpCount };
private _leaderName = name (leader _grp);
_leaderName = (_leaderName splitString """" joinString "");
private _escWeapon = ((currentWeapon _unit) splitString """" joinString "");
private _bloodPct = if (count _medicalParts >= 2) then { _medicalParts select 1 } else { "100" };
private _vehDmg = if (_inVeh) then { damage _veh } else { -1 };
_vehJson = _vehJson + format [
    ",""group_count"":%1,""crew_count"":%2,""leader"":""%3"",""combat_mode"":""%4"",""behaviour"":""%5"",""formation"":""%6"",""current_weapon"":""%7"",""blood"":%8",
    _grpCount,
    _crewCount,
    _leaderName,
    combatMode _unit,
    behaviour _unit,
    formation _grp,
    _escWeapon,
    _bloodPct
];
if (_vehDmg >= 0) then {
    _vehJson = _vehJson + format [",""vehicle_damage"":%1", [_vehDmg, 3] call _fnc_num];
};
if (isClass (configFile >> "CfgPatches" >> "tfar_core") && {!isNil "TFAR_fnc_getLrFrequency"}) then {
    private _lrFreq = "";
    private _lrRadio = if (!isNil "TFAR_fnc_activeLrRadio") then { _unit call TFAR_fnc_activeLrRadio } else { nil };
    if (!isNil "_lrRadio") then {
        _lrFreq = str (_unit call TFAR_fnc_getLrFrequency);
        _lrFreq = (_lrFreq splitString """" joinString "");
    };
    if (!(_lrFreq isEqualTo "") && {!(_lrFreq in ["any", "<null>", "nil"])}) then {
        _vehJson = _vehJson + format [",""radio_lr"":""%1"",""lr_freq"":""%1""", _lrFreq];
    };
};
// Identifiant BFT lié à l’indicatif (Athena / military_id) — même clé TOC ↔ carte ↔ terminal
private _bftId = trim (missionNamespace getVariable ["COMSPEC_MilitaryId", ""]);
if (_bftId isEqualTo "") then {
    _bftId = trim (missionNamespace getVariable ["COMSPEC_BftId", ""]);
};
if (_bftId isEqualTo "") then {
    _bftId = trim (profileNamespace getVariable ["COMSPEC_MilitaryId", ""]);
};
if (!(_bftId isEqualTo "")) then {
    _bftId = (_bftId splitString """" joinString "");
    _vehJson = _vehJson + format [
        ",""bft_id"":""%1"",""military_id"":""%1"",""atak_id"":""%1""",
        _bftId
    ];
};
private _ftIdPos = missionNamespace getVariable ["COMSPEC_FireTeamId", 0];
if (!(_ftIdPos isEqualType 0)) then { _ftIdPos = parseNumber str _ftIdPos; };
if (_ftIdPos > 0) then {
    _vehJson = _vehJson + format [",""fire_team_id"":%1", round _ftIdPos];
};
private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;
_modVersion = (_modVersion splitString """" joinString "");
if (!(_modVersion isEqualTo "")) then {
    _vehJson = _vehJson + format [",""mod_version"":""%1""", _modVersion];
};
// Handshake mods compagnons (cTab / ATAK Enhanced) → pastilles transmission Tacmap
private _modsDetect = [] call comspec_overwatch_connect_fnc_detectLoadedMods;
if (!(_modsDetect isEqualType createHashMap)) then { _modsDetect = createHashMap; };
private _hasCtab = _modsDetect getOrDefault ["has_ctab", false];
private _hasEnhanced = _modsDetect getOrDefault ["has_atak_enhanced", false];
private _hasAthenaCtab = _modsDetect getOrDefault ["has_athena_ctab", false];
_vehJson = _vehJson + format [
    ",""mod_athena"":true,""has_ctab"":%1,""has_atak_enhanced"":%2,""has_athena_ctab"":%3",
    if (_hasCtab) then { "true" } else { "false" },
    if (_hasEnhanced) then { "true" } else { "false" },
    if (_hasAthenaCtab) then { "true" } else { "false" }
];
private _pktStats = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _escLink = (_linkState splitString """" joinString "");
_vehJson = _vehJson + format [
    ",""link_state"":""%1"",""transmit_mode"":""%2"",""packet_loss"":%3,""packets_sent"":%4,""packets_received"":%5",
    _escLink,
    _txMode,
    _pktStats getOrDefault ["packet_loss_percent", 0],
    _pktStats getOrDefault ["packets_sent", 0],
    _pktStats getOrDefault ["packets_received", 0]
];
private _compromise = toLower (missionNamespace getVariable ["COMSPEC_CompromiseState", "none"]);
if (!(_compromise in ["none", "captured", "compromised"])) then { _compromise = "none"; };
private _terminalUidPos = [] call comspec_overwatch_connect_fnc_getTerminalUid;
if (!(_terminalUidPos isEqualType "")) then { _terminalUidPos = ""; };
_terminalUidPos = (_terminalUidPos splitString """" joinString "");
_vehJson = _vehJson + format [
    ",""compromise_state"":""%1"",""terminal_uid"":""%2"",""zone_type"":""%3""",
    _compromise,
    _terminalUidPos,
    (((missionNamespace getVariable ["COMSPEC_ZoneEffects", createHashMap]) getOrDefault ["type", ""]) splitString """" joinString "")
];
private _latencyMs = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
if (_latencyMs isEqualType 0 && {_latencyMs >= 0}) then {
    _vehJson = _vehJson + format [",""latency_ms"":%1", round _latencyMs];
};
private _certStatus = toLower (str (missionNamespace getVariable ["COMSPEC_CertStatus", ""]));
private _certRef = str (missionNamespace getVariable ["COMSPEC_CertRef", ""]);
_certStatus = (_certStatus splitString """" joinString "");
_certRef = (_certRef splitString """" joinString "");
if (!(_certStatus in ["", "nil", "<null>", "any"])) then {
    _vehJson = _vehJson + format [",""cert_status"":""%1""", _certStatus];
};
if (!(_certRef in ["", "nil", "<null>", "any"])) then {
    _vehJson = _vehJson + format [",""certificate_ref"":""%1""", _certRef];
};
if (count _medicalParts >= 8) then {
    _vehJson = _vehJson + format [
        ",""spo2"":""%1"",""airway"":""%2"",""pneumothorax"":""%3""",
        _medicalParts select 5,
        _medicalParts select 6,
        _medicalParts select 7
    ];
};
private _telKind = "position";
if (!_force && {!_distanceOk} && {!_headingChanged} && {!_stateChanged} && {!_txUrgent} && {!_medUrgent}) then {
    _telKind = "heartbeat";
};
private _histMin = missionNamespace getVariable ["COMSPEC_HistorySampleMin", 15];
if (!(_histMin isEqualType 0)) then { _histMin = 15; };
_vehJson = _vehJson + format [
    ",""telemetry_kind"":""%1"",""history_sample_min"":%2",
    _telKind,
    round _histMin
];
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
    missionNamespace setVariable ["COMSPEC_ImmobileAlerted", false, false];
};
missionNamespace setVariable ["COMSPEC_ImmobileSince", _immobileTime, true];

// Anomalies de suivi = polish UI : coupées en mode milsim / réalisme communauté.
private _realism = missionNamespace getVariable ["COMSPEC_TenantRealism", false];
private _troll = missionNamespace getVariable ["COMSPEC_TenantTrollMode", false];
private _milsimUi = missionNamespace getVariable ["comspec_overwatch_milsim_ui", false];
if (!_realism && !_milsimUi) then {
    private _immobileSec = if (_troll) then { 120 } else { 180 };
    if (((_now - _immobileTime) > _immobileSec) && {!(missionNamespace getVariable ["COMSPEC_ImmobileAlerted", false])}) then {
        missionNamespace setVariable ["COMSPEC_ImmobileAlerted", true, false];
        private _alert = createHashMapFromArray [["kind", "IMMOBILE"], ["unit", _callSign], ["duration", _now - _immobileTime], ["position", _pos]];
        ["OnTrackingAnomaly", _alert] call comspec_overwatch_connect_fnc_publishEvent;
    };
    private _jumpThreshold = if (_troll) then { 150 } else { 250 };
    if ((_distance > _jumpThreshold) && {_posMin > 2}) then {
        private _lastIncoherent = missionNamespace getVariable ["COMSPEC_IncoherentAlertAt", -1e9];
        if ((_now - _lastIncoherent) > 60) then {
            missionNamespace setVariable ["COMSPEC_IncoherentAlertAt", _now, false];
            private _alert2 = createHashMapFromArray [["kind", "INCOHERENT_MOVE"], ["unit", _callSign], ["distance", _distance], ["from", _lastPos], ["to", _pos]];
            ["OnTrackingAnomaly", _alert2] call comspec_overwatch_connect_fnc_publishEvent;
        };
    };
};

missionNamespace setVariable ["COMSPEC_lastPos", _pos, true];
missionNamespace setVariable ["COMSPEC_lastName", _callSign, true];
missionNamespace setVariable ["COMSPEC_lastRole", _role, true];
missionNamespace setVariable ["COMSPEC_lastRadio", _radioSig, true];
missionNamespace setVariable ["COMSPEC_lastMedical", _medicalSig, true];
missionNamespace setVariable ["COMSPEC_lastGroup", _groupName, true];
missionNamespace setVariable ["COMSPEC_lastHeading", _heading, true];
missionNamespace setVariable ["COMSPEC_lastVehSig", _vehSig, true];
missionNamespace setVariable ["COMSPEC_lastSendTime", _now, true];
missionNamespace setVariable ["COMSPEC_LastPositionSync", _now, false];

private _atakSync = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (_atakSync isEqualType createHashMap) then {
    if (_linkState in ["linked", "degraded", "connecting"]) then {
        missionNamespace setVariable ["COMSPEC_LinkState", _linkState, false];
    };
    player setVariable ["COMSPEC_AtakState", _atakSync, true];
    player setVariable ["COMSPEC_LinkState", _linkState, true];
    [] call comspec_overwatch_connect_fnc_syncPlayerAtakPublicVars;
};

[] call comspec_overwatch_connect_fnc_updateStatusBadges;

if (_force) then { "ok" } else { nil };
