/*
    Resynch Athena : renvoie l’état courant du terminal vers le poste de commandement.

    Couvre :
      - localisation (position, groupe, radio, santé)
      - données carte (marqueurs, cTab, météo, drones, itinéraires, saut)
      - SEEK / SSE (fiches personnes + file d’attente)
      - FRS (fiches de renseignement encore en attente)
      - groupe (effectif + derniers messages de groupe)
      - messages (derniers échanges déjà émis depuis ce terminal)

    Les photos ne sont pas rejouées : elles restent celles déjà transmises.
    Cooldown ~15 s pour éviter le spam.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
    ["Overwatch est désactivé.", "link", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};
if (isNull player || {!alive player}) exitWith {
    ["Impossible de transmettre (opérateur hors service).", "link", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _cooldown = 15;
private _now = diag_tickTime;
private _last = missionNamespace getVariable ["COMSPEC_ForceSyncAt", -1e9];
private _remain = ceil (_cooldown - (_now - _last));
if (_remain > 0) exitWith {
    private _wait = [format ["Patientez %1 s avant un nouveau Resynch.", _remain]];
    missionNamespace setVariable ["COMSPEC_LastResynchSummary", [
        format ["<t size='0.78' color='#ffe08a'>%1</t>", _wait select 0]
    ], false];
    [_wait select 0, "link", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};

missionNamespace setVariable ["COMSPEC_ForceSyncAt", _now, false];

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    private _msg = "Liaison Athena coupée — impossible de tout renvoyer pour le moment.";
    missionNamespace setVariable ["COMSPEC_LastResynchSummary", [
        format ["<t size='0.78' color='#ffb0a0'>%1</t>", _msg]
    ], false];
    [_msg, "link", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _lines = ["<t size='0.82'>Resynch envoyé vers le poste de commandement</t>", ""];
private _parts = [];

// --- LOCALISATION ---
private _result = [player, true] call comspec_overwatch_connect_fnc_updatePosition;
private _posOk = (_result isEqualTo "ok");
if (_posOk) then {
    _parts pushBack "localisation";
    _lines pushBack "<t color='#9dffc4'>Localisation renvoyée (position, groupe, radio).</t>";
} else {
    private _why = switch (_result) do {
        case "origin": { "position non valide — déplacez-vous un peu" };
        case "dead": { "opérateur hors service" };
        default { "transmission de position impossible" };
    };
    _lines pushBack format ["<t color='#ffe08a'>Localisation : %1.</t>", _why];
};

// --- DATA (carte / capteurs) ---
missionNamespace setVariable ["COMSPEC_LastFactionSettingsBody", "", false];
if (!isNil "comspec_overwatch_connect_fnc_sendFactionSettings") then {
    [] call comspec_overwatch_connect_fnc_sendFactionSettings;
};

missionNamespace setVariable ["COMSPEC_Athena_LastWeatherSig", "", false];
missionNamespace setVariable ["COMSPEC_Athena_RouteSig", "", false];
missionNamespace setVariable ["COMSPEC_Athena_JumpSig", "", false];
missionNamespace setVariable ["COMSPEC_Athena_LastVideoFeedsSig", "", false];
missionNamespace setVariable ["COMSPEC_Athena_LastVideoFeedsAt", 0, false];
missionNamespace setVariable ["COMSPEC_Athena_DroneContactSeen", createHashMap, false];

private _mkCount = 0;
if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
    _mkCount = [false] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
    if (!(_mkCount isEqualType 0)) then { _mkCount = 0; };
};
if (!isNil "comspec_overwatch_connect_fnc_queueMapMarker") then {
    [] call comspec_overwatch_connect_fnc_queueMapMarker;
};

{
    if (!isNil _x) then { [] call (missionNamespace getVariable [_x, {}]); };
} forEach [
    "comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers",
    "comspec_overwatch_atak_athena_fnc_athena_bridgeWeather",
    "comspec_overwatch_atak_athena_fnc_athena_bridgeDroneContacts",
    "comspec_overwatch_atak_athena_fnc_athena_bridgeRoute",
    "comspec_overwatch_atak_athena_fnc_athena_bridgeJump",
    "comspec_overwatch_atak_athena_fnc_athena_bridgeVideoFeeds"
];

_parts pushBack "données carte";
_lines pushBack format ["<t color='#9dffc4'>Données carte renvoyées — %1 marqueur(s).</t>", _mkCount];

// --- FRS (file hors ligne, pas le brouillon ouvert) ---
private _frsSent = 0;
if (!isNil "comspec_overwatch_connect_fnc_outboxFlush") then {
    _frsSent = [true] call comspec_overwatch_connect_fnc_outboxFlush;
    if (!(_frsSent isEqualType 0)) then { _frsSent = 0; };
};
if (_frsSent > 0) then {
    _parts pushBack "fiches";
    _lines pushBack format ["<t color='#9dffc4'>Fiches en attente transmises : %1.</t>", _frsSent];
} else {
    _lines pushBack "<t color='#B9C0E0'>Aucune fiche de renseignement en attente à renvoyer.</t>";
};

// --- SEEK ---
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_sendSeekData") then {
    [true] call comspec_overwatch_atak_athena_fnc_athena_sendSeekData;
    _parts pushBack "SEEK";
    _lines pushBack "<t color='#9dffc4'>Fiches personnes / SEEK renvoyées.</t>";
};

// --- GROUP (effectif courant) ---
private _grp = group player;
private _gid = trim (groupId _grp);
private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_cs isEqualTo "") then { _cs = name player; };
private _grid = mapGridPosition player;
private _names = [];
{
    if (alive _x) then { _names pushBack (name _x); };
} forEach (units _grp);
private _roster = format ["EFFECTIF|%1|%2", count _names, _names joinString ", "];
if (!isNil "comspec_overwatch_connect_fnc_sendIntel") then {
    [player, "CHAT", format ["GROUPE|%1|%2|%3|%4", _gid, _cs, _grid, _roster], "", "INFANTRY", 0.9]
        call comspec_overwatch_connect_fnc_sendIntel;
};
_parts pushBack "groupe";
_lines pushBack format ["<t color='#9dffc4'>Groupe renvoyé — %1 personne(s).</t>", count _names];

// --- GROUP messages + MESSAGE (rejeu limité, ce terminal uniquement) ---
private _msgCount = 0;
private _groupMsgCount = 0;
if (!isNil "comspec_overwatch_connect_fnc_sendIntel") then {
    private _gMessages = +(missionNamespace getVariable ["Iceman_ATAK_Group_messages", []]);
    if (!(_gMessages isEqualType [])) then { _gMessages = []; };
    private _gTail = [];
    private _gStart = 0 max ((count _gMessages) - 8);
    if ((count _gMessages) > 0) then {
        _gTail = _gMessages select [_gStart, 8];
    };
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", true, false];
    {
        if (!(_x isEqualType [])) then { continue };
        private _author = _x param [1, "", [""]];
        private _gIdMsg = _x param [2, _gid, [""]];
        private _gGrid = _x param [3, _grid, [""]];
        private _gText = trim (_x param [4, "", [""]]);
        if (_gText isEqualTo "") then { continue };
        if (_author isNotEqualTo "" && {_author isNotEqualTo _cs} && {_author isNotEqualTo (name player)}) then { continue };
        [player, "CHAT", format ["GROUPE|%1|%2|%3|%4", _gIdMsg, _cs, _gGrid, _gText], "", "INFANTRY", 0.85]
            call comspec_overwatch_connect_fnc_sendIntel;
        _groupMsgCount = _groupMsgCount + 1;
    } forEach _gTail;
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];

    private _radioLog = +(missionNamespace getVariable ["COMSPEC_RadioReplay", []]);
    if (!(_radioLog isEqualType [])) then { _radioLog = []; };
    private _rTail = [];
    private _rStart = 0 max ((count _radioLog) - 8);
    if ((count _radioLog) > 0) then {
        _rTail = _radioLog select [_rStart, 8];
    };
    {
        if (!(_x isEqualType [])) then { continue };
        private _channel = _x param [2, "SQUAD", [""]];
        private _priority = _x param [3, "ROUTINE", [""]];
        private _kind = _x param [4, "FREE", [""]];
        private _text = trim (_x param [5, "", [""]]);
        if (_text isEqualTo "") then { continue };
        private _formatted = [_cs, _channel, _priority, _text, _kind] call comspec_overwatch_connect_fnc_formatCommsMessage;
        [player, "CHAT", _formatted, "", "INFANTRY", 0.7] call comspec_overwatch_connect_fnc_sendIntel;
        _msgCount = _msgCount + 1;
    } forEach _rTail;
};

if (_groupMsgCount + _msgCount > 0) then {
    _parts pushBack "messages";
    _lines pushBack format [
        "<t color='#9dffc4'>Messages renvoyés : %1 de groupe, %2 d’échange.</t>",
        _groupMsgCount,
        _msgCount
    ];
} else {
    _lines pushBack "<t color='#B9C0E0'>Aucun message récent à renvoyer depuis ce terminal.</t>";
};

_lines pushBack "";
_lines pushBack "<t size='0.66' color='#8A90A8'>Les photos ne sont pas renvoyées : elles restent celles déjà transmises.</t>";

missionNamespace setVariable ["COMSPEC_LastResynchSummary", _lines, false];

private _announce = if (_parts isEqualTo []) then {
    "Resynch : rien n’a pu partir — vérifiez la liaison."
} else {
    format ["Resynch Athena : %1.", _parts joinString ", "]
};
[_announce, "link", if (_posOk || {_mkCount > 0} || {_frsSent > 0} || {_msgCount > 0}) then { "info" } else { "warn" }, true]
    call comspec_overwatch_connect_fnc_announce;

if (!isNil "comspec_overwatch_connect_fnc_appendModuleLog") then {
    [format [
        "[Resynch] pos=%1 mk=%2 frs=%3 grp=%4 gmsg=%5 msg=%6",
        _posOk, _mkCount, _frsSent, count _names, _groupMsgCount, _msgCount
    ]] call comspec_overwatch_connect_fnc_appendModuleLog;
};

(_posOk || {_mkCount > 0} || {_frsSent > 0} || {_groupMsgCount > 0} || {_msgCount > 0})
