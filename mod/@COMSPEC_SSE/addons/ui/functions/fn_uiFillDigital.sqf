private _disp = findDisplay 93250;
if (isNull _disp) exitWith { false };

private _rec = [] call comspec_sse_fnc_uiGetRecord;
private _tab = missionNamespace getVariable ["comspec_sse_uiDigitalTab", "overview"];
(_disp displayCtrl 93252) ctrlSetStructuredText parseText format [
    "<t size='0.85'>Onglet: <t color='#8f8'>%1</t> · Overview / Contacts / Messages / Calls / Files / Photos / Locs / Deleted / Network</t>",
    toUpper _tab
];

if (isNull _rec) exitWith {
    (_disp displayCtrl 93253) ctrlSetStructuredText parseText "<t color='#f88'>Aucun record SSE lié.</t>";
    false
};

[_rec] call comspec_sse_fnc_ensureGenerated;
private _devices = [_rec, "digitalDevices"] call comspec_sse_fnc_getSection;
if !(_devices isEqualType []) then { _devices = []; };
private _dev = if (count _devices > 0) then { _devices select 0 } else { createHashMap };
private _sec = [_rec, "sections"] call comspec_sse_fnc_getSection;
private _access = if (_sec isEqualType createHashMap) then { _sec getOrDefault ["accessState", createHashMap] } else { createHashMap };

private _body = "";
private _list = [];

switch (_tab) do {
    case "overview": {
        private _devType = _dev getOrDefault ["deviceType", ""];
        if (_devType isEqualTo "") then { _devType = _dev getOrDefault ["model", "?"]; };
        _body = format [
            "<t color='#8f8'>APPAREIL</t><br/>Type: %1<br/>Numéro: %2<br/>Proprio: %3<br/>Accès: %4<br/>Batterie morte: %5<br/>Disque retiré: %6<br/><br/>Utilisez les onglets pour explorer.",
            _devType,
            _dev getOrDefault ["phoneNumber", "—"],
            _dev getOrDefault ["owner", "—"],
            _access getOrDefault ["state", "OPEN"],
            _access getOrDefault ["batteryDead", false],
            _access getOrDefault ["driveRemoved", false]
        ];
        _list = ["Overview actif", format ["Devices liés: %1", count _devices]];
    };
    case "contacts": {
        private _c = _dev getOrDefault ["contacts", []];
        _body = format ["<t color='#8f8'>CONTACTS</t><br/>%1 entrée(s)", count _c];
        { _list pushBack (if (_x isEqualType "") then {_x} else {str _x}); } forEach _c;
    };
    case "messages": {
        private _m = _dev getOrDefault ["sms", []];
        _body = format ["<t color='#8f8'>MESSAGES</t><br/>%1 message(s)", count _m];
        {
            if (_x isEqualType createHashMap) then {
                _list pushBack format ["%1: %2", _x getOrDefault ["from", "?"], _x getOrDefault ["text", ""]];
            } else { _list pushBack str _x; };
        } forEach _m;
    };
    case "calls": {
        private _c = _dev getOrDefault ["calls", []];
        _body = format ["<t color='#8f8'>APPELS</t><br/>%1", count _c];
        {
            if (_x isEqualType createHashMap) then {
                _list pushBack format ["%1 — %2", _x getOrDefault ["time", "?"], _x getOrDefault ["number", "?"]];
            };
        } forEach _c;
    };
    case "files": {
        private _f = _dev getOrDefault ["files", []];
        if (!(_f isEqualType []) || {_f isEqualTo []}) then {
            private _docs = _dev getOrDefault ["documents", []];
            if (_docs isEqualType [] && {_docs isNotEqualTo []}) then { _f = _docs; };
        };
        if !(_f isEqualType []) then { _f = []; };
        _body = format ["<t color='#8f8'>FICHIERS</t><br/>%1", count _f];
        {
            if (_x isEqualType createHashMap) then {
                private _fname = _x getOrDefault ["name", ""];
                if (_fname isEqualTo "") then { _fname = _x getOrDefault ["title", "file"]; };
                _list pushBack _fname;
            } else { _list pushBack str _x; };
        } forEach _f;
    };
    case "photos": {
        private _p = _dev getOrDefault ["photos", []];
        private _opt = if (_sec isEqualType createHashMap) then { _sec getOrDefault ["optical", createHashMap] } else { createHashMap };
        if (_p isEqualTo [] && {_opt isEqualType createHashMap}) then { _p = _opt getOrDefault ["photos", []]; };
        _body = format ["<t color='#8f8'>PHOTOS</t><br/>%1", count _p];
        {
            if (_x isEqualType createHashMap) then {
                _list pushBack format ["%1 (%2)", _x getOrDefault ["caption", "photo"], _x getOrDefault ["grid", ""]];
            };
        } forEach _p;
    };
    case "locations": {
        private _l = _dev getOrDefault ["locations", []];
        if (_l isEqualTo []) then { _l = [_rec, "locations"] call comspec_sse_fnc_getSection; };
        if !(_l isEqualType []) then { _l = []; };
        _body = format ["<t color='#8f8'>LOCATIONS</t><br/>%1", count _l];
        {
            if (_x isEqualType createHashMap) then {
                _list pushBack format ["%1 — %2", _x getOrDefault ["label", "POI"], _x getOrDefault ["grid", ""]];
            };
        } forEach _l;
    };
    case "deleted": {
        private _d = if (_sec isEqualType createHashMap) then { _sec getOrDefault ["deletedData", []] } else { [] };
        _body = "<t color='#8f8'>DELETED</t><br/>Récupérable en exploitation DETAILED+";
        {
            if (_x isEqualType createHashMap) then {
                _list pushBack format ["%1 [%2]", _x getOrDefault ["label", "?"], if (_x getOrDefault ["recovered", false]) then {"OK"} else {"LOCKED"}];
            };
        } forEach _d;
    };
    case "network": {
        private _rad = [_rec, "radio"] call comspec_sse_fnc_getSection;
        if (_rad isEqualType createHashMap) then {
            _body = format [
                "<t color='#8f8'>NETWORK / RADIO</t><br/>Modèle: %1<br/>Net: %2<br/>CS: %3<br/>Freq: %4",
                _rad getOrDefault ["model", "?"],
                _rad getOrDefault ["netName", "?"],
                _rad getOrDefault ["callsign", "?"],
                (_rad getOrDefault ["frequencies", []]) joinString " / "
            ];
            { if (_x isEqualType createHashMap) then { _list pushBack format ["%1 — %2", _x getOrDefault ["when","?"], _x getOrDefault ["text","?"]]; }; } forEach (_rad getOrDefault ["trafficLog", []]);
        } else {
            _body = "<t color='#8f8'>NETWORK</t><br/>Pas de radio liée — contacts téléphone ci-contre.";
            { _list pushBack (if (_x isEqualType "") then {_x} else {str _x}); } forEach (_dev getOrDefault ["contacts", []]);
        };
    };
};

(_disp displayCtrl 93253) ctrlSetStructuredText parseText _body;
private _lb = _disp displayCtrl 93254;
lbClear _lb;
{ _lb lbAdd _x; } forEach _list;
if (_list isEqualTo []) then { _lb lbAdd "(vide)"; };
true
