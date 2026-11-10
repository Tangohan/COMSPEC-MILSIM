/*
    SIGNER ET ENVOYER (touche SIGN du SEEK II) : une seule action.

      1. vérifie le minimum (une biométrie + un nom ou « inconnu ») ; sinon l'écran dit ce qui
         manque et va à la page où le compléter ;
      2. signe avec l'identité du joueur (indicatif, nom, Steam ID, horodatage) : aucune saisie ;
      3. enrôle le sujet dans la base biométrique de la mission (un QUERY ultérieur le retrouve) ;
      4. range la fiche au fichier local (comspec_sse_fnc_fileClient « seekStore ») ;
      5. envoie à Athena (comspec_sse_fnc_fileSubmit : file hors ligne et renvoi automatique gérés
         dedans) ; l'accusé (n° REC, horodatage, état) s'affiche à l'écran du SEEK.

    [_op, _args] call comspec_overwatch_connect_fnc_seekSend
      "send"                          action de la touche SIGN
      "submitted" [_idem, _state, _ref]   évènement CBA comspec_sse_fileSubmitted (sent | queued | error)
*/
params [["_op", "send", [""]], ["_args", []]];
if (!hasInterface) exitWith {};

private _tr = { _this call comspec_overwatch_connect_fnc_tr };
private _two = { params ["_n"]; if (_n < 10) then { format ["0%1", _n] } else { str _n } };
private _ack = {
    params ["_snd", "_state", ["_ref", ""]];
    private _rec = _snd getOrDefault ["rec", ""];
    private _clock = _snd getOrDefault ["clock", ""];
    private _line = switch (_state) do {
        case "sending": { format ["<t size='0.44' font='TahomaB' color='#0a246a'>%1 %2</t><t size='0.42' color='#5a6670'>  ·  %3 %4</t>", "SIGNÉ · ENVOI" call _tr, _rec, "signé à" call _tr, _clock] };
        case "sent": { format ["<t size='0.44' font='TahomaB' color='#1a7f37'>%1 %2</t><t size='0.42' color='#5a6670'>  ·  %3%4</t>", "ENVOYÉ" call _tr, _rec, _clock, ["", format ["  ·  %1", _ref]] select (_ref isNotEqualTo "")] };
        case "queued": { format ["<t size='0.44' font='TahomaB' color='#9a5b00'>%1 %2</t><t size='0.42' color='#5a6670'>  ·  %3</t>", "EN FILE" call _tr, _rec, "hors ligne : renvoi automatique à la reconnexion" call _tr] };
        case "local": { format ["<t size='0.44' font='TahomaB' color='#9a5b00'>%1 %2</t><t size='0.42' color='#5a6670'>  ·  %3</t>", "ENREGISTRÉ" call _tr, _rec, "au fichier local seulement" call _tr] };
        default { format ["<t size='0.44' font='TahomaB' color='#b3261e'>%1 %2</t><t size='0.42' color='#5a6670'>  ·  %3</t>", "ÉCHEC D'ENVOI" call _tr, _rec, "fiche gardée sur l'appareil : SIGN pour réessayer" call _tr] };
    };
    _snd set ["state", _state];
    _snd set ["line", _line];
    uiNamespace setVariable ["COMSPEC_SeekSend", _snd];
    private _d = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
    if (!isNull _d) then {
        private _c = _d displayCtrl 9513;
        if (!isNull _c) then { _c ctrlSetStructuredText parseText _line; };
    };
    ["render"] call comspec_overwatch_connect_fnc_seekQuick;
    [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
};

if (_op isEqualTo "submitted") exitWith {
    _args params [["_idem", ""], ["_state", "error"], ["_ref", ""]];
    private _snd = uiNamespace getVariable ["COMSPEC_SeekSend", createHashMap];
    if (!(_snd isEqualType createHashMap) || {(_snd getOrDefault ["idem", ""]) isNotEqualTo _idem}) exitWith {};
    if (!(_ref isEqualType "")) then { _ref = str _ref; };
    diag_log text format ["[COMSPEC SEEK] Envoi %1 : %2 %3", _idem, _state, _ref];
    [_snd, toLower _state, _ref] call _ack;
};

// ---------------------------------------------------------------------- SIGN
["events"] call comspec_overwatch_connect_fnc_seekQuick;
if !(["can"] call comspec_overwatch_connect_fnc_seekBattery) exitWith { ["render"] call comspec_overwatch_connect_fnc_seekBattery; };
private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith {};

private _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
private _state = ["steps"] call comspec_overwatch_connect_fnc_seekState;
if (isNull _target) exitWith {
    private _c = _disp displayCtrl 9513;
    if (!isNull _c) then { _c ctrlSetStructuredText parseText format ["<t size='0.44' color='#b3261e' align='center'>%1</t>", "Aucun sujet : lancez le contrôle sur la personne (A1)." call _tr]; };
    [10] call comspec_overwatch_connect_fnc_sseTerminalPage;
};
if !(_state getOrDefault ["minimum", false]) exitWith {
    private _txt = format ["Manque : %1" call _tr, (_state getOrDefault ["missing", []]) joinString ", "];
    private _q = uiNamespace getVariable ["COMSPEC_SeekQuick", createHashMap];
    if (_q isEqualType createHashMap) then { _q set ["msg", format ["<t size='0.44' font='TahomaB' color='#b3261e'>%1</t>", _txt]]; };
    private _c = _disp displayCtrl 9513;
    if (!isNull _c) then { _c ctrlSetStructuredText parseText format ["<t size='0.44' color='#b3261e' align='center'>%1</t>", _txt]; };
    [(_state getOrDefault ["firstMissingPage", 1]) max 1] call comspec_overwatch_connect_fnc_sseTerminalPage;
};
// Déjà parti pour ce sujet et rien de neuf : pas de doublon.
private _prev = uiNamespace getVariable ["COMSPEC_SeekSend", createHashMap];
if (_prev isEqualType createHashMap && {(_prev getOrDefault ["target", objNull]) isEqualTo _target} && {(_prev getOrDefault ["state", ""]) in ["sending", "sent", "queued"]}
    && {(_prev getOrDefault ["sig", ""]) isEqualTo str (uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []])}) exitWith {
    [_prev, _prev get "state", ""] call _ack;
};
["use", 0.5] call comspec_overwatch_connect_fnc_seekBattery;

// --- Signature : l'identité du joueur, sans saisie ----------------------------------------------
private _callsign = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then { _callsign = [] call comspec_overwatch_connect_fnc_getCallsign; };
if (!(_callsign isEqualType "") || {_callsign isEqualTo ""}) then { _callsign = groupId (group player); };
private _uid = getPlayerUID player;
private _terminal = missionNamespace getVariable ["COMSPEC_TerminalUid", ""];
if (!(_terminal isEqualType "") || {_terminal isEqualTo ""}) then { _terminal = format ["SEEK-%1", _uid select [((count _uid) - 6) max 0]]; };
private _stNow = systemTime;
private _clock = format ["%1:%2", [_stNow select 3] call _two, [_stNow select 4] call _two];
private _stamp = format ["%1-%2-%3 %4", _stNow select 0, [_stNow select 1] call _two, [_stNow select 2] call _two, _clock];
uiNamespace setVariable ["COMSPEC_SsePerson_Signature", [_callsign, _terminal, missionNamespace getVariable ["COMSPEC_AtakId", ""], _stamp]];

// --- Sujet, document, relevés ----------------------------------------------------------------
(["subject"] call comspec_overwatch_connect_fnc_seekState) params ["_last", "_first", "_dob", "_unknown"];
if (_last isEqualTo "" && {_first isEqualTo ""}) then { _unknown = true; };
private _doc = ["doc", [_target]] call comspec_overwatch_connect_fnc_seekState;
if (_dob isEqualTo "") then { _dob = _doc getOrDefault ["dob", ""]; };
private _bioKey = if (isNil "comspec_sse_fnc_bioPersonKey") then { "" } else { [_target] call comspec_sse_fnc_bioPersonKey };
private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
if (!(_samples isEqualType [])) then { _samples = []; };
private _rowOf = {
    params ["_k"];
    private _i = _samples findIf { (_x isEqualType []) && {(_x param [0, ""]) isEqualTo _k} };
    if (_i < 0) then { [] } else { _samples select _i }
};
private _fpRow = ["empreintes"] call _rowOf;
private _irRow = ["iris"] call _rowOf;
private _nf = (_fpRow param [3, []]) select { _x isEqualType 0 && {_x >= 1} && {_x <= 4} };
private _ni = (_irRow param [3, []]) select { _x isEqualType 0 && {_x >= 40} };
if (_fpRow isNotEqualTo [] && {_nf isEqualTo []}) then { _nf = [1]; };
if (_irRow isNotEqualTo [] && {_ni isEqualTo []}) then { _ni = [1]; };
private _meta = _target getVariable ["comspec_sse_facePhotoMeta", []];
if (!(_meta isEqualType [])) then { _meta = []; };
private _face = (count _meta) >= 3;
private _qFp = _fpRow param [1, 0];
private _qIr = _irRow param [1, 0];
private _qFace = [0, _meta param [1, 0]] select _face;

private _v = uiNamespace getVariable ["COMSPEC_SeekVerdict", createHashMap];
if (!(_v isEqualType createHashMap)) then { _v = createHashMap; };
private _verdict = _v getOrDefault ["verdict", "inconnu"];
private _enroll = _verdict isEqualTo "inconnu";

private _rec = _target getVariable ["comspec_seek_rec", ""];
if (!(_rec isEqualType "") || {_rec isEqualTo ""}) then {
    private _h = 7;
    { _h = ((_h * 31) + _x) mod 1000003; } forEach (toArray (_bioKey + str (missionNamespace getVariable ["COMSPEC_MapId", 1])));
    _rec = format ["REC-%1%2%3-%4", (_stNow select 0) mod 100, [_stNow select 1] call _two, [_stNow select 2] call _two, 1000 + (_h mod 9000)];
    _target setVariable ["comspec_seek_rec", _rec, true];
};
private _idem = format ["SEEK-%1-%2", _rec, _uid];
private _grid = mapGridPosition _target;
private _reasonCtrl = _disp displayCtrl 9506;
private _reason = if (!isNull _reasonCtrl && {(lbCurSel _reasonCtrl) >= 0}) then { _reasonCtrl lbText (lbCurSel _reasonCtrl) } else { "Contrôle" call _tr };
private _unitName = groupId (group player);

// Enrôlement dans la base biométrique de la mission : un QUERY ultérieur (SEEK ou téléphone) le retrouve.
if (!isNil "comspec_sse_fnc_bioRequest" && {missionNamespace getVariable ["comspec_sse_bio_enabled", true]}) then {
    private _probe = createHashMapFromArray [
        ["unit", _target], ["key", _bioKey], ["nfiqs", _fpRow param [3, []]], ["eyes", _irRow param [3, []]],
        ["face", [-1, _qFace] select _face], ["by", _callsign], ["grid", _grid]
    ];
    _target setVariable ["comspec_sse_bioEnrolledAt", time];
    ["enroll", _probe, {}] call comspec_sse_fnc_bioRequest;
};

// Fichier local : une recherche ultérieure (téléphone ou SEEK) retrouve la fiche.
if (!isNil "comspec_sse_fnc_fileClient") then {
    ["seekStore", [createHashMapFromArray [
        ["rec", _rec], ["bioKey", _bioKey], ["last", _last], ["first", _first], ["dob", _dob], ["unknown", _unknown],
        ["docType", _doc getOrDefault ["type", ""]], ["docCountry", _doc getOrDefault ["country", ""]], ["docNumber", _doc getOrDefault ["number", ""]],
        ["verdict", _verdict],
        ["modalities", createHashMapFromArray [["fingers", count _nf], ["iris", count _ni], ["face", _face]]],
        ["quality", createHashMapFromArray [["fp", _qFp], ["iris", _qIr], ["face", _qFace]]],
        ["grid", _grid], ["clock", _clock], ["by", name player], ["byUid", _uid], ["reason", _reason], ["unit", _target]
    ]]] call comspec_sse_fnc_fileClient;
};

private _snd = createHashMapFromArray [
    ["rec", _rec], ["idem", _idem], ["clock", _clock], ["target", _target], ["sig", str _samples]
];
[_target, "Fiche SEEK signée" call _tr, _rec] call comspec_overwatch_connect_fnc_sseTeamLog;
if (!isNil "comspec_overwatch_connect_fnc_seekHistoryPush") then {
    [_target, ["none", "confirmed"] select (_verdict in ["concordant", "signale"]), 0, _rec] call comspec_overwatch_connect_fnc_seekHistoryPush;
};

// Athena : la DLL envoie, ou met en file hors liaison (renvoi automatique) ; réponse par évènement.
if (isNil "comspec_sse_fnc_fileSubmit") exitWith {
    diag_log text format ["[COMSPEC SEEK] %1 gardé au fichier local (comspec_sse_fnc_fileSubmit absent).", _rec];
    [_snd, "local"] call _ack;
};
private _subj = createHashMapFromArray [["last", _last], ["first", _first], ["dob", _dob], ["unknown", _unknown], ["netid", netId _target]];
if (isPlayer _target) then { _subj set ["steam_id", getPlayerUID _target]; };
private _payload = createHashMapFromArray [
    ["kind", "seek_record"], ["idempotency_key", _idem], ["rec", _rec], ["bio_key", _bioKey], ["verdict", _verdict], ["enroll", _enroll],
    ["subject", _subj],
    ["document", createHashMapFromArray [["type", _doc getOrDefault ["type", ""]], ["country", _doc getOrDefault ["country", ""]], ["number", _doc getOrDefault ["number", ""]]]],
    ["modalities", createHashMapFromArray [["fingers", count _nf], ["iris", count _ni], ["face", _face]]],
    ["quality", createHashMapFromArray [["fp", _qFp], ["iris", _qIr], ["face", _qFace]]],
    ["grid", _grid], ["game_time", _stamp],
    ["controller", createHashMapFromArray [["callsign", _callsign], ["steam_id", _uid]]],
    ["reason", _reason], ["unit", _unitName],
    ["map", str (missionNamespace getVariable ["COMSPEC_MapId", 1])],
    ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]]
];
[_snd, "sending"] call _ack;
[_payload] call comspec_sse_fnc_fileSubmit;
// Sans accusé en 20 s : l'envoi reste dans la file du SSE, on le dit.
[{
    params ["_idem"];
    private _s = uiNamespace getVariable ["COMSPEC_SeekSend", createHashMap];
    if (_s isEqualType createHashMap && {(_s getOrDefault ["idem", ""]) isEqualTo _idem} && {(_s getOrDefault ["state", ""]) isEqualTo "sending"}) then {
        ["submitted", [_idem, "queued", ""]] call comspec_overwatch_connect_fnc_seekSend;
    };
}, [_idem], 20] call CBA_fnc_waitAndExecute;
