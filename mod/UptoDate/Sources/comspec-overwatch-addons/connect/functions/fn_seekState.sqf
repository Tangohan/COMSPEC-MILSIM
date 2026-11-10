/*
    État du SEEK II : ce qui est fait, ce qui manque, document présenté, sujet saisi.
    Lecture seule : ne change rien à l'écran.

    ["doc", [_target]]  -> HashMap du document présenté par la cible lors du contrôle d'identité
                           en cours (last, first, dob, number, country, type), vide sinon
    ["subject"]         -> [nom, prénom, date de naissance, inconnu (bool)] tel que saisi à l'écran
    ["steps"]           -> HashMap : subject, context, fp, iris, face, query, sent (bool),
                           minimum (bool : une biométrie + un nom ou « inconnu »),
                           missing (libellés du minimum), advised (modalités conseillées absentes),
                           firstMissingPage (page où compléter)
*/
params [["_op", "steps", [""]], ["_args", []]];

private _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];

switch (_op) do {
    case "doc": {
        _args params [["_t", _target, [objNull]]];
        if (isNull _t || {isNil "comspec_sse_fnc_idHistory"}) exitWith { createHashMap };
        private _key = if (isNil "comspec_sse_fnc_bioPersonKey") then { "" } else { [_t] call comspec_sse_fnc_bioPersonKey };
        private _mine = {
            params ["_f"];
            (_f isEqualType createHashMap) && {((_f getOrDefault ["subj", objNull]) isEqualTo _t) || {_key isNotEqualTo "" && {(_f getOrDefault ["subjKey", ""]) isEqualTo _key}}}
        };
        private _fiche = ["current"] call comspec_sse_fnc_idHistory;
        if !([_fiche] call _mine) then {
            _fiche = createHashMap;
            private _list = ["list"] call comspec_sse_fnc_idHistory;
            if (_list isEqualType []) then {
                // La plus récente d'abord.
                for "_i" from ((count _list) - 1) to 0 step -1 do {
                    if ([_list select _i] call _mine) exitWith { _fiche = _list select _i; };
                };
            };
        };
        private _doc = _fiche getOrDefault ["doc", createHashMap];
        if (_doc isEqualType createHashMap) then { _doc } else { createHashMap }
    };
    case "subject": {
        private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
        if (isNull _disp) then { _disp = findDisplay 9991; };
        private _cache = uiNamespace getVariable ["COMSPEC_SsePerson_IdentityCache", []];
        if (!(_cache isEqualType [])) then { _cache = []; };
        private _last = "";
        private _first = "";
        if (!isNull _disp) then {
            _last = trim (ctrlText (_disp displayCtrl 9501));
            _first = trim (ctrlText (_disp displayCtrl 9502));
        };
        if (_last isEqualTo "") then { _last = _cache param [0, ""]; };
        if (_first isEqualTo "") then { _first = _cache param [1, ""]; };
        private _dob = uiNamespace getVariable ["COMSPEC_SeekDob", ""];
        if (!(_dob isEqualType "")) then { _dob = ""; };
        [_last, _first, _dob, uiNamespace getVariable ["COMSPEC_SeekUnknown", false]]
    };
    default {
        private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
        if (!(_samples isEqualType [])) then { _samples = []; };
        private _has = { params ["_k"]; (_samples findIf { (_x isEqualType []) && {(_x param [0, ""]) isEqualTo _k} }) >= 0 };
        private _fp = ["empreintes"] call _has;
        private _iris = ["iris"] call _has;
        private _meta = if (isNull _target) then { [] } else { _target getVariable ["comspec_sse_facePhotoMeta", []] };
        private _face = (_meta isEqualType []) && {(count _meta) >= 3};
        (["subject"] call comspec_overwatch_connect_fnc_seekState) params ["_last", "_first", "", "_unknown"];
        private _named = _unknown || {_last isNotEqualTo ""} || {_first isNotEqualTo ""};
        private _bio = _fp || _iris || _face;
        private _query = (count (uiNamespace getVariable ["COMSPEC_SsePerson_Query", []])) >= 3;
        private _send = uiNamespace getVariable ["COMSPEC_SeekSend", createHashMap];
        private _sent = (_send isEqualType createHashMap) && {(_send getOrDefault ["state", ""]) in ["sent", "queued", "local"]}
            && {(_send getOrDefault ["target", objNull]) isEqualTo _target};
        private _missing = [];
        private _page = -1;
        if (!_bio) then { _missing pushBack ("une biométrie" call comspec_overwatch_connect_fnc_tr); _page = 10; };
        if (!_named) then { _missing pushBack ("nom ou « inconnu »" call comspec_overwatch_connect_fnc_tr); if (_page < 0) then { _page = 1; }; };
        private _advised = [];
        if (!_fp) then { _advised pushBack ("empreintes" call comspec_overwatch_connect_fnc_tr); };
        if (!_iris) then { _advised pushBack ("iris" call comspec_overwatch_connect_fnc_tr); };
        if (!_face) then { _advised pushBack ("visage" call comspec_overwatch_connect_fnc_tr); };
        createHashMapFromArray [
            ["subject", _named], ["context", true], ["fp", _fp], ["iris", _iris], ["face", _face],
            ["query", _query], ["sent", _sent], ["minimum", _bio && _named],
            ["missing", _missing], ["advised", _advised], ["firstMissingPage", _page]
        ]
    };
};
