/*
    Applique le contenu Eden (documents, téléphone, ordinateur, identité)
    sur les sections déjà générées.

    Modes (comspec_sse_*Mode) :
      AUTO    — génération conservée, champs Eden non vides en remplacement
      NONE    — section absente
      CUSTOM  — uniquement le contenu Eden (pièces / appareils renseignés)

    [_entity] call comspec_sse_fnc_applyAuthoredContent
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { false };

private _fnc_txt = {
    params ["_key", ["_alt", ""]];
    private _v = _entity getVariable [_key, ""];
    if (!(_v isEqualType "")) then { _v = str _v; };
    _v = trim _v;
    if (_v isEqualTo "" && {_alt isNotEqualTo ""}) then {
        private _b = _entity getVariable [_alt, ""];
        if (_b isEqualType "") then { _v = trim _b; };
    };
    _v
};

private _fnc_lines = {
    params ["_key"];
    private _s = [_key] call _fnc_txt;
    if (_s isEqualTo "") exitWith { [] };
    private _raw = _s splitString (toString [10]);
    private _out = [];
    {
        private _ln = [_x, toString [13], ""] call BIS_fnc_replaceString;
        _ln = trim _ln;
        if (_ln isNotEqualTo "") then { _out pushBack _ln; };
    } forEach _raw;
    _out
};

private _fnc_splitOnce = {
    params ["_s", "_sep"];
    private _p = _s find _sep;
    if (_p < 0) exitWith { ["", _s] };
    [trim (_s select [0, _p]), trim (_s select [_p + count _sep])]
};

private _fnc_isPhone = {
    params ["_d"];
    if (!(_d isEqualType createHashMap)) exitWith { false };
    private _t = toUpper (_d getOrDefault ["deviceType", ""]);
    (_t in ["SMARTPHONE", "PHONE", "FEATUREPHONE"]) || {
        (_d getOrDefault ["phoneNumber", ""]) isNotEqualTo "" && {(_d getOrDefault ["hostname", ""]) isEqualTo ""}
    }
};

private _fnc_isPc = {
    params ["_d"];
    if (!(_d isEqualType createHashMap)) exitWith { false };
    private _t = toUpper (_d getOrDefault ["deviceType", ""]);
    _t in ["LAPTOP", "COMPUTER", "PC", "DESKTOP"]
};

private _fnc_uid = {
    params ["_salt"];
    format ["SSE-%1", [0, format ["%1-%2", netId _entity, _salt], 9] call comspec_sse_fnc_idToken]
};

private _fnc_mode = {
    params ["_key"];
    private _raw = _entity getVariable [_key, "AUTO"];
    if (!(_raw isEqualType "")) then { _raw = "AUTO"; };
    toUpper (trim _raw)
};

private _docMode = ["comspec_sse_documentsMode"] call _fnc_mode;
private _phoneMode = ["comspec_sse_phoneMode"] call _fnc_mode;
private _digMode = ["comspec_sse_digitalMode"] call _fnc_mode;
private _bioMode = ["comspec_sse_bioMode"] call _fnc_mode;

// --- Identité (champs Eden / Overwatch) ---
private _id = [_entity, "identity"] call comspec_sse_fnc_getSection;
if (!(_id isEqualType createHashMap)) then { _id = createHashMap; };
private _role = ["comspec_sse_personRole"] call _fnc_txt;
private _nat = ["comspec_sse_personNationality", "COMSPEC_SSE_Nationality"] call _fnc_txt;
private _lang = ["comspec_sse_personLanguage", "COMSPEC_SSE_Language"] call _fnc_txt;
private _phoneNo = ["comspec_sse_personPhone"] call _fnc_txt;
if (_role isNotEqualTo "") then { _id set ["role", _role]; };
if (_nat isNotEqualTo "") then { _id set ["nationality", _nat]; };
if (_lang isNotEqualTo "") then { _id set ["language", _lang]; };
if (_phoneNo isNotEqualTo "") then { _id set ["phone", _phoneNo]; };
[_entity, "identity", _id, false] call comspec_sse_fnc_setSection;

// --- Documents ---
private _docs = [_entity, "documents"] call comspec_sse_fnc_getSection;
if (!(_docs isEqualType [])) then { _docs = []; };

if (_docMode isEqualTo "NONE") then {
    _docs = [];
} else {
    private _authoredDocs = [];
    for "_i" from 1 to 3 do {
        private _title = [format ["comspec_sse_doc%1_title", _i]] call _fnc_txt;
        private _sum = [format ["comspec_sse_doc%1_summary", _i]] call _fnc_txt;
        private _grid = [format ["comspec_sse_doc%1_grid", _i]] call _fnc_txt;
        private _cw = [format ["comspec_sse_doc%1_codeword", _i]] call _fnc_txt;
        if (_title isEqualTo "" && {_sum isEqualTo ""} && {_grid isEqualTo ""} && {_cw isEqualTo ""}) then {
        } else {
            private _idx = _i - 1;
            private _doc = createHashMap;
            if (_docMode isNotEqualTo "CUSTOM" && {_idx < count _docs} && {(_docs select _idx) isEqualType createHashMap}) then {
                _doc = +(_docs select _idx);
            };
            if (_title isNotEqualTo "") then { _doc set ["title", _title]; };
            if (_sum isNotEqualTo "") then { _doc set ["summary", _sum]; };
            if (_grid isNotEqualTo "") then { _doc set ["grid", _grid]; };
            if (_cw isNotEqualTo "") then { _doc set ["codeword", _cw]; };
            if ((_doc getOrDefault ["title", ""]) isEqualTo "") then {
                _doc set ["title", format ["Pièce %1", _i]];
            };
            if ((_doc getOrDefault ["uid", ""]) isEqualTo "" || {((_doc getOrDefault ["uid", ""]) find "e+") >= 0}) then {
                _doc set ["uid", format ["SSE-DOC-%1-%2", [_i, format ["doc%1", _i], 9] call comspec_sse_fnc_idToken, _i]];
            };
            _doc set ["noise", false];
            _authoredDocs pushBack [_idx, _doc];
        };
    };

    if (_docMode isEqualTo "CUSTOM") then {
        _docs = [];
        { _docs pushBack (_x select 1); } forEach _authoredDocs;
    } else {
        {
            _x params ["_idx", "_doc"];
            if (_idx < count _docs) then {
                _docs set [_idx, _doc];
            } else {
                _docs pushBack _doc;
            };
        } forEach _authoredDocs;
    };
};
[_entity, "documents", _docs, false] call comspec_sse_fnc_setSection;

if (_bioMode isEqualTo "NONE") then {
    [_entity, "biometrics", createHashMap, false] call comspec_sse_fnc_setSection;
};

// --- Appareils ---
private _devs = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
if (!(_devs isEqualType [])) then { _devs = []; };

private _phones = [];
private _pcs = [];
private _other = [];
{
    if ([_x] call _fnc_isPhone) then { _phones pushBack _x; } else {
        if ([_x] call _fnc_isPc) then { _pcs pushBack _x; } else { _other pushBack _x; };
    };
} forEach _devs;

if (_phoneMode isEqualTo "NONE") then {
    _phones = [];
} else {
    private _hasPhoneFields = (
        (["comspec_sse_phoneNumber"] call _fnc_txt) isNotEqualTo ""
        || {(["comspec_sse_phoneModel"] call _fnc_txt) isNotEqualTo ""}
        || {(count (["comspec_sse_phoneContacts"] call _fnc_lines)) > 0}
        || {(count (["comspec_sse_phoneMessages"] call _fnc_lines)) > 0}
        || {(count (["comspec_sse_phoneNotes"] call _fnc_lines)) > 0}
        || {(count (["comspec_sse_phonePlaces"] call _fnc_lines)) > 0}
    );
    if (_phoneMode isEqualTo "CUSTOM" || {_hasPhoneFields}) then {
        private _ph = if ((count _phones) > 0 && {(_phones select 0) isEqualType createHashMap}) then {
            +(_phones select 0)
        } else {
            createHashMapFromArray [
                ["uid", ["PH"] call _fnc_uid],
                ["deviceType", "SMARTPHONE"],
                ["owner", _id getOrDefault ["name", ""]],
                ["contacts", []],
                ["sms", []],
                ["calls", []],
                ["photos", []],
                ["locations", []],
                ["notes", []],
                ["deletedData", []]
            ]
        };
        private _num = ["comspec_sse_phoneNumber"] call _fnc_txt;
        if (_num isEqualTo "") then { _num = _phoneNo; };
        private _model = ["comspec_sse_phoneModel"] call _fnc_txt;
        if (_num isNotEqualTo "") then { _ph set ["phoneNumber", _num]; };
        if (_model isNotEqualTo "") then { _ph set ["model", _model]; };
        private _contacts = ["comspec_sse_phoneContacts"] call _fnc_lines;
        if (count _contacts > 0) then { _ph set ["contacts", _contacts]; };
        private _msgs = ["comspec_sse_phoneMessages"] call _fnc_lines;
        if (count _msgs > 0) then {
            private _sms = [];
            {
                private _pair = [_x, " : "] call _fnc_splitOnce;
                if ((_pair select 0) isEqualTo "") then { _pair = [_x, ": "] call _fnc_splitOnce; };
                if ((_pair select 0) isEqualTo "") then { _pair = ["—", _x]; };
                _sms pushBack (createHashMapFromArray [
                    ["from", _pair select 0],
                    ["text", _pair select 1],
                    ["noise", false]
                ]);
            } forEach _msgs;
            _ph set ["sms", _sms];
        };
        private _notes = ["comspec_sse_phoneNotes"] call _fnc_lines;
        if (count _notes > 0) then { _ph set ["notes", _notes]; };
        private _places = ["comspec_sse_phonePlaces"] call _fnc_lines;
        if (count _places > 0) then {
            private _locs = [];
            {
                private _pair = [_x, " | "] call _fnc_splitOnce;
                if ((_pair select 0) isEqualTo "") then { _pair = [_x, " — "] call _fnc_splitOnce; };
                if ((_pair select 0) isEqualTo "") then { _pair = [_x, ""] };
                if ((_pair select 1) isEqualTo "") then {
                    _locs pushBack (createHashMapFromArray [["label", _x], ["grid", ""]]);
                } else {
                    _locs pushBack (createHashMapFromArray [["label", _pair select 0], ["grid", _pair select 1]]);
                };
            } forEach _places;
            _ph set ["locations", _locs];
        };
        if ((_ph getOrDefault ["uid", ""]) isEqualTo "") then { _ph set ["uid", ["PH"] call _fnc_uid]; };
        _ph set ["deviceType", "SMARTPHONE"];
        if (_phoneMode isEqualTo "CUSTOM") then { _phones = [_ph]; } else { _phones = [_ph] + (_phones select [1, count _phones]); };
    };
};

if (_digMode isEqualTo "NONE") then {
    _pcs = [];
} else {
    private _hasPcFields = (
        (["comspec_sse_pcHostname"] call _fnc_txt) isNotEqualTo ""
        || {(["comspec_sse_pcOwner"] call _fnc_txt) isNotEqualTo ""}
        || {(count (["comspec_sse_pcFiles"] call _fnc_lines)) > 0}
        || {(["comspec_sse_pcMailSubject"] call _fnc_txt) isNotEqualTo ""}
        || {(["comspec_sse_pcMailSnippet"] call _fnc_txt) isNotEqualTo ""}
        || {(["comspec_sse_pcWifi"] call _fnc_txt) isNotEqualTo ""}
        || {(["comspec_sse_pcAccessHint"] call _fnc_txt) isNotEqualTo ""}
    );
    if (_digMode isEqualTo "CUSTOM" || {_hasPcFields}) then {
        private _pc = if ((count _pcs) > 0 && {(_pcs select 0) isEqualType createHashMap}) then {
            +(_pcs select 0)
        } else {
            createHashMapFromArray [
                ["uid", ["PC"] call _fnc_uid],
                ["deviceType", "LAPTOP"],
                ["hostname", "PC-SSE"],
                ["owner", _id getOrDefault ["name", ""]],
                ["users", []],
                ["files", []],
                ["browser", []],
                ["mail", []],
                ["usbHistory", []],
                ["credentials", []],
                ["encryptedFiles", []],
                ["network", createHashMap]
            ]
        };
        private _host = ["comspec_sse_pcHostname"] call _fnc_txt;
        private _owner = ["comspec_sse_pcOwner"] call _fnc_txt;
        if (_host isNotEqualTo "") then { _pc set ["hostname", _host]; };
        if (_owner isNotEqualTo "") then { _pc set ["owner", _owner]; };
        private _files = ["comspec_sse_pcFiles"] call _fnc_lines;
        if (count _files > 0) then {
            private _fileObjs = [];
            {
                _fileObjs pushBack (createHashMapFromArray [
                    ["name", _x],
                    ["path", format ["Documents\\%1", _x]],
                    ["relevant", true]
                ]);
            } forEach _files;
            _pc set ["files", _fileObjs];
        };
        private _subj = ["comspec_sse_pcMailSubject"] call _fnc_txt;
        private _snip = ["comspec_sse_pcMailSnippet"] call _fnc_txt;
        if (_subj isNotEqualTo "" || {_snip isNotEqualTo ""}) then {
            _pc set ["mail", [createHashMapFromArray [
                ["from", "inconnu"],
                ["subject", if (_subj isEqualTo "") then {"Message"} else {_subj}],
                ["snippet", _snip],
                ["relevant", true]
            ]]];
        };
        private _wifi = ["comspec_sse_pcWifi"] call _fnc_txt;
        private _hint = ["comspec_sse_pcAccessHint"] call _fnc_txt;
        if (_wifi isNotEqualTo "" || {_hint isNotEqualTo ""}) then {
            private _net = _pc getOrDefault ["network", createHashMap];
            if (!(_net isEqualType createHashMap)) then { _net = createHashMap; };
            if (_wifi isNotEqualTo "") then { _net set ["ssid", _wifi]; };
            _pc set ["network", _net];
            if (_hint isNotEqualTo "") then {
                private _creds = _pc getOrDefault ["credentials", []];
                if (!(_creds isEqualType [])) then { _creds = []; };
                _creds pushBack (createHashMapFromArray [
                    ["service", "accès"],
                    ["user", "admin"],
                    ["hint", _hint]
                ]);
                _pc set ["credentials", _creds];
            };
        };
        if ((_pc getOrDefault ["uid", ""]) isEqualTo "") then { _pc set ["uid", ["PC"] call _fnc_uid]; };
        _pc set ["deviceType", "LAPTOP"];
        if (_digMode isEqualTo "CUSTOM") then { _pcs = [_pc]; } else { _pcs = [_pc] + (_pcs select [1, count _pcs]); };
    };
};

[_entity, "digitalDevices", _phones + _pcs + _other, false] call comspec_sse_fnc_setSection;

private _status = [_entity, "sectionStatus"] call comspec_sse_fnc_getSection;
if (!(_status isEqualType createHashMap)) then { _status = createHashMap; };
_status set ["documents", if (count _docs > 0) then {"complete"} else {"none"}];
_status set ["digital", if ((count _phones) + (count _pcs) > 0) then {"complete"} else {"none"}];
if (_bioMode isEqualTo "NONE") then { _status set ["biometrics", "none"]; };
[_entity, "sectionStatus", _status, false] call comspec_sse_fnc_setSection;

true
