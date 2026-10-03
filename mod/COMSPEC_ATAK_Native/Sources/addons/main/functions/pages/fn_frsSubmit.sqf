/*
    Envoie la fiche FRS au bureau SSE (commande SubmitSseFieldNote de la DLL d'Overwatch, même JSON que
    son rédacteur). Liaison coupée : la fiche part en file (libellé unique, pour ne pas être dédoublonnée).
*/
[] call comspec_atak_native_fnc_frsDraftSave;
private _d = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
private _cat = [] call comspec_overwatch_connect_fnc_intelNoteCatalog;
private _say = {
    params ["_t", ["_warn", false], ["_clear", false]];
    uiNamespace setVariable ["COMSPEC_ATAK_FrsHint", [_t, _warn]];
    // Fiche partie : on vide le brouillon sans que le redessin ne reprenne les champs affichés.
    if (_clear) then { uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", createHashMap]; uiNamespace setVariable ["COMSPEC_ATAK_FrsClearing", true]; };
    [{ ["FRS"] call comspec_atak_native_fnc_pageRender; uiNamespace setVariable ["COMSPEC_ATAK_FrsClearing", false]; }] call CBA_fnc_execNextFrame;
};
private _body = trim (_d getOrDefault ["body", ""]);
if ((count _body) > (_cat getOrDefault ["body_max", 1000])) then { _body = _body select [0, _cat getOrDefault ["body_max", 1000]]; };
private _themes = _d getOrDefault ["themes", []];
if ((count _body) < 10) exitWith { ["Écrivez le renseignement (10 caractères au moins) avant d'envoyer.", true] call _say; };
if ((count _themes) isEqualTo 0) exitWith { ["Choisissez au moins un thème.", true] call _say; };
if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) then { ["Liaison Athena absente : la fiche partira en file.", true] call _say; };

private _esc = {
    params ["_s"];
    if !(_s isEqualType "") then { _s = str _s; };
    private _o = "";
    private _bs = toString [92];
    {
        switch (true) do {
            case (_x == 34): { _o = _o + _bs + toString [34]; };
            case (_x == 92): { _o = _o + _bs + _bs; };
            case (_x == 10): { _o = _o + _bs + "n"; };
            case (_x == 13): { _o = _o + _bs + "r"; };
            case (_x == 9): { _o = _o + _bs + "t"; };
            case (_x < 32): { _o = _o + " "; };
            default { _o = _o + toString [_x]; };
        };
    } forEach toArray _s;
    _o
};
// Date « JJ/MM/AAAA HH:MM » → « AAAA-MM-JJ HH:MM:00 ».
private _observed = "";
private _dp = ((trim (_d getOrDefault ["date", ""])) splitString " ") select { _x isNotEqualTo "" };
if ((count _dp) >= 1) then {
    private _dmy = ((_dp select 0) splitString "/-.") select { _x isNotEqualTo "" };
    if ((count _dmy) >= 3) then {
        private _hm = ((_dp param [1, "00:00"]) splitString ":h") select { _x isNotEqualTo "" };
        private _p2 = { params ["_s"]; if ((count _s) < 2) then { "0" + _s } else { _s select [0, 2] } };
        _observed = format ["%1-%2-%3 %4:%5:00", _dmy select 2, [_dmy select 1] call _p2, [_dmy select 0] call _p2, [_hm param [0, "00"]] call _p2, [_hm param [1, "00"]] call _p2];
    };
};
private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callsign isEqualTo "") then { _callsign = groupId group player; };
private _steam = getPlayerUID player;
private _pos = getPosASL player;
private _grid = trim (_d getOrDefault ["grid", ""]);
if (_grid isEqualTo "") then { _grid = mapGridPosition player; };
private _case = toUpper trim (_d getOrDefault ["case", ""]);
if (_case isEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_sseActiveCase"}) then { _case = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase; };
private _kind = _d getOrDefault ["kind", "FRM"];
private _idem = format ["fiche-%1-%2", _steam, floor (diag_tickTime * 1000)];
private _mapId = missionNamespace getVariable ["COMSPEC_MapId", missionNamespace getVariable ["comspec_overwatch_map_id", 1]];
if (_mapId isEqualType "") then { _mapId = parseNumber _mapId; }; if !(_mapId isEqualType 0) then { _mapId = 1; };
if (_mapId <= 0) then { _mapId = 1; };
private _parts = [
    format ['"mapId":%1', _mapId],
    format ['"body":"%1"', [_body] call _esc],
    format ['"note_kind":"%1"', [_kind] call _esc],
    format ['"themes":[%1]', (_themes apply { format ['"%1"', [_x] call _esc] }) joinString ","],
    format ['"urgency":"%1"', [_d getOrDefault ["urgency", "routine"]] call _esc],
    format ['"intel_source":"%1"', [_d getOrDefault ["source", ""]] call _esc],
    format ['"place_label":"%1"', [trim (_d getOrDefault ["place", ""])] call _esc],
    format ['"grid_reference":"%1"', [_grid] call _esc],
    format ['"pos_x":%1', (_pos select 0) toFixed 2], format ['"pos_y":%1', (_pos select 1) toFixed 2], format ['"pos_z":%1', (_pos select 2) toFixed 2],
    format ['"author_label":"%1"', [_callsign] call _esc],
    format ['"author_steam_id":"%1"', [_steam] call _esc],
    format ['"author_unit":"%1"', [groupId group player] call _esc],
    format ['"submitter_callsign":"%1"', [_callsign] call _esc],
    format ['"case_code":"%1"', [_case] call _esc],
    format ['"idempotency_key":"%1"', [_idem] call _esc],
    '"origin":"atak"', '"source_reliability":"C"', '"info_credibility":3',
    '"mod_name":"COMSPEC ATAK"', format ['"mod_version":"%1"', missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "?"]], '"mod_cfg":"comspec_atak_native"'
];
if (_observed isNotEqualTo "") then { _parts pushBack format ['"observed_at":"%1"', [_observed] call _esc]; };
private _json = "{" + (_parts joinString ",") + "}";
(["SubmitSseFieldNote", [_json], format ["Fiche %1 %2", _kind, _idem select [count _idem - 6, 6]], true, true, "system", true] call comspec_overwatch_connect_fnc_callExtLogged) params ["_ok", "_status", "_detail"];
private _sent = uiNamespace getVariable ["COMSPEC_ATAK_FrsSent", []];
private _excerpt = if ((count _body) > 80) then { (_body select [0, 80]) + "…" } else { _body };
private _time = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
private _pieces = +(uiNamespace getVariable ["COMSPEC_ATAK_FrsPieces", []]);
if (!_ok && {_status isEqualTo "QUEUED"}) exitWith {
    // Sans numéro de fiche, les pièces ne peuvent pas suivre : elles restent jointes au brouillon suivant.
    _sent pushBack [_time, _kind, "", ["en file", "en file, pièces non envoyées"] select ((count _pieces) > 0), _excerpt, 0];
    uiNamespace setVariable ["COMSPEC_ATAK_FrsSent", _sent];
    ["Liaison coupée : fiche gardée en file, elle partira au retour de la liaison. Ne la ressaisissez pas.", false, true] call _say;
};
if (!_ok) exitWith { [format ["Fiche non transmise : %1", _detail], true] call _say; };
private _bits = ([str _detail, """", ""] call CBA_fnc_replace) splitString "|";
private _ref = _bits param [1, ""];
private _noteId = floor (parseNumber (_bits param [0, "0"]));
_sent pushBack [_time, _kind, _ref, "transmise", _excerpt, [0, count _pieces] select (_noteId > 0)];
uiNamespace setVariable ["COMSPEC_ATAK_FrsSent", _sent];
[format ["Fiche %1 transmise au bureau SSE.%2", _ref, ["", format [" %1 pièce(s) jointe(s) en cours d'envoi.", count _pieces]] select (_noteId > 0 && {(count _pieces) > 0})], false, true] call _say;
// Pièces jointes : une par une après la fiche (même chaîne que le rédacteur d'Overwatch : captures recopiées puis envoyées).
if (_noteId > 0 && {(count _pieces) > 0}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_FrsPieces", []];
    [str _noteId, _pieces, _callsign, _pos] spawn {
        params ["_noteId", "_pieces", "_callsign", "_pos"];
        private _okN = 0;
        {
            _x params ["_kind", "_path", "_name", "_grid", ["_author", ""], ["_caption", ""]];
            private _target = _path;
            if (_kind isEqualTo "capture" && {!isNil "comspec_overwatch_connect_fnc_extResult"}) then {
                private _staged = ["COMSPECExtension" callExtension ["StageCapture", [_target]]] call comspec_overwatch_connect_fnc_extResult;
                if ((_staged isEqualType "") && {(count _staged) >= 4} && {(_staged select [0, 3]) isEqualTo "OK|"}) then {
                    private _b = trim (_staged select [3, (count _staged) - 3]);
                    if (_b isNotEqualTo "") then { _target = _b; };
                };
            };
            (["UploadSseNoteAttachment", [_noteId, _target, [_author, _callsign] select (_author isEqualTo ""), ["photo", "capture"] select (_kind isEqualTo "capture"),
                (_pos select 0) toFixed 2, (_pos select 1) toFixed 2, (_pos select 2) toFixed 2, _caption, _grid], "Pièce jointe de fiche", true, true, "system", false] call comspec_overwatch_connect_fnc_callExtLogged) params ["_pOk"];
            if (_pOk) then { _okN = _okN + 1; };
            uiSleep 1;
        } forEach _pieces;
        [["WARNING", "SUCCESS"] select (_okN isEqualTo (count _pieces)), format ["Fiche %1 : %2/%3 pièce(s) jointe(s) en file d'envoi", _noteId, _okN, count _pieces], 4, 30] call comspec_atak_native_fnc_notify;
    };
};
["SUCCESS", "Fiche de renseignement transmise", 3, 40] call comspec_atak_native_fnc_notify;
