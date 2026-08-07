/*
    Révélation progressive digital selon le mode.
    [_entity, _mode, _quality] call comspec_sse_fnc_revealDigitalFog
*/
params [
    ["_entity", objNull, [objNull]],
    ["_mode", "identify", [""]],
    ["_quality", 50, [0]]
];

[_entity] call comspec_sse_fnc_ensureGenerated;
private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
private _lines = [];
private _title = "DIGITAL ACQUISITION";
private _uid = "?";
private _dtype = "UNKNOWN";

if (isNil "_devices" || {!(_devices isEqualType [])} || {count _devices == 0}) exitWith {
    createHashMapFromArray [
        ["title", _title],
        ["uid", _uid],
        ["type", _dtype],
        ["lines", ["Aucun support numérique détecté."]],
        ["quality", _quality],
        ["qualityLabel", [_quality] call comspec_sse_fnc_qualityLabel],
        ["level", "none"]
    ]
};

// Choisir le device le plus pertinent selon le mode
private _d = _devices select 0;
private _isPcMode = (toLower _mode) in ["system","users","files","browser","mail","usb","credentials","computer_full"];
if (_isPcMode) then {
    private _pcs = _devices select {
        private _t = toUpper (_x getOrDefault ["deviceType", ""]);
        _t in ["LAPTOP", "COMPUTER", "PC"]
    };
    if (count _pcs > 0) then { _d = _pcs select 0; };
};

_uid = _d getOrDefault ["uid", "?"];
_dtype = _d getOrDefault ["deviceType", "DEVICE"];
private _modeL = toLower _mode;

switch (_modeL) do {
    case "identify": {
        _lines pushBack format ["TYPE : %1", _dtype];
        _lines pushBack format ["MODÈLE : %1", _d getOrDefault ["model", _d getOrDefault ["hostname", "n/a"]]];
        if ((_d getOrDefault ["sim", ""]) != "") then { _lines pushBack "SIM présente."; };
        if ((_d getOrDefault ["imei", ""]) != "" && {_quality >= 40}) then {
            _lines pushBack format ["IMEI : %1", _d get "imei"];
        };
        _lines pushBack format ["Propriétaire estimé : %1", _d getOrDefault ["owner", "inconnu"]];
    };
    case "contacts": {
        private _c = _d getOrDefault ["contacts", []];
        _lines pushBack format ["Contacts récupérés : %1", count _c];
        if (_quality >= 60) then {
            private _shown = _c select [0, (count _c) min 8];
            _lines pushBack (_shown joinString ", ");
        };
    };
    case "messages": {
        private _sms = _d getOrDefault ["sms", []];
        _lines pushBack format ["Messages : %1", count _sms];
        if (_quality >= 55) then {
            {
                if (_x isEqualType createHashMap) then {
                    _lines pushBack format ["[%1] %2", _x getOrDefault ["from","?"], _x getOrDefault ["text",""]];
                };
            } forEach (_sms select [0, (count _sms) min (if (_quality >= 80) then {6} else {3})]);
        };
    };
    case "calls": {
        private _calls = _d getOrDefault ["calls", []];
        _lines pushBack format ["Appels : %1", count _calls];
        if (_quality >= 50) then {
            {
                _lines pushBack format ["%1 %2 (%3s)", _x getOrDefault ["dir","?"], _x getOrDefault ["with","?"], _x getOrDefault ["duration",0]];
            } forEach (_calls select [0, (count _calls) min 6]);
        };
    };
    case "photos": {
        private _pics = _d getOrDefault ["photos", []];
        _lines pushBack format ["Images : %1", count _pics];
        { _lines pushBack format ["• %1", _x getOrDefault ["caption", "photo"]]; } forEach (_pics select [0, (count _pics) min 5]);
    };
    case "locations": {
        private _locs = _d getOrDefault ["locations", []];
        _lines pushBack format ["Positions : %1", count _locs];
        {
            _lines pushBack format ["%1 — %2", _x getOrDefault ["label","?"], _x getOrDefault ["grid",""]];
        } forEach _locs;
    };
    case "system": {
        _lines pushBack format ["HOSTNAME : %1", _d getOrDefault ["hostname", "?"]];
        _lines pushBack format ["OWNER : %1", _d getOrDefault ["owner", "?"]];
        private _net = _d getOrDefault ["network", createHashMap];
        if (_net isEqualType createHashMap) then {
            _lines pushBack format ["SSID : %1", _net getOrDefault ["ssid", "?"]];
            _lines pushBack format ["IP : %1", _net getOrDefault ["lastIP", "?"]];
        };
    };
    case "users": {
        private _users = _d getOrDefault ["users", []];
        _lines pushBack format ["Comptes : %1", count _users];
        { _lines pushBack format ["• %1 (%2)", _x getOrDefault ["name","?"], _x getOrDefault ["role",""]]; } forEach _users;
    };
    case "files": {
        private _files = _d getOrDefault ["files", []];
        private _relevant = _files select { _x getOrDefault ["relevant", false] };
        _lines pushBack format ["Fichiers : %1 (%2 pertinents)", count _files, count _relevant];
        {
            if (_quality >= 50 || {_x getOrDefault ["relevant", false]}) then {
                _lines pushBack format ["• %1", _x getOrDefault ["name", "?"]];
            };
        } forEach (_files select [0, (count _files) min 8]);
    };
    case "browser": {
        private _br = _d getOrDefault ["browser", []];
        _lines pushBack format ["Historique : %1 entrées", count _br];
        { _lines pushBack format ["• %1 — %2", _x getOrDefault ["title","?"], _x getOrDefault ["url",""]]; } forEach _br;
    };
    case "mail": {
        private _mail = _d getOrDefault ["mail", []];
        _lines pushBack format ["Messages mail : %1", count _mail];
        {
            _lines pushBack format ["De: %1 | %2", _x getOrDefault ["from","?"], _x getOrDefault ["subject",""]];
            if (_quality >= 70) then { _lines pushBack format ["  %1", _x getOrDefault ["snippet",""]]; };
        } forEach _mail;
    };
    case "usb": {
        private _usb = _d getOrDefault ["usbHistory", []];
        _lines pushBack format ["Supports connectés : %1", count _usb];
        { _lines pushBack format ["• %1", _x]; } forEach _usb;
    };
    case "credentials": {
        private _creds = _d getOrDefault ["credentials", []];
        _lines pushBack format ["Identifiants : %1", count _creds];
        if (_quality >= 65) then {
            {
                _lines pushBack format ["%1 / %2 — %3", _x getOrDefault ["service","?"], _x getOrDefault ["user","?"], _x getOrDefault ["hint",""]];
            } forEach _creds;
        } else {
            _lines pushBack "Qualité insuffisante pour lire les secrets.";
        };
        private _enc = _d getOrDefault ["encryptedFiles", []];
        if (count _enc > 0) then { _lines pushBack format ["Chiffrés : %1", _enc joinString ", "]; };
    };
    case "full";
    case "computer_full": {
        private _sumPhone = [_entity] call comspec_sse_fnc_getDeviceSummary;
        if (_sumPhone getOrDefault ["ok", false]) then {
            _lines pushBack format ["Contacts %1 | Messages %2 | Images %3 | Locations %4",
                _sumPhone get "contacts", _sumPhone get "messages", _sumPhone get "images", _sumPhone get "locations"];
        };
        private _sumPc = [_entity] call comspec_sse_fnc_getComputerSummary;
        if (_sumPc getOrDefault ["ok", false]) then {
            _lines pushBack format ["PC %1 | Fichiers %2 | Mail %3 | USB %4",
                _sumPc get "hostname", _sumPc get "files", _sumPc get "mail", _sumPc get "usb"];
        };
        if (_quality >= 75) then {
            private _sms = _d getOrDefault ["sms", []];
            if (count _sms > 0 && {(_sms select 0) isEqualType createHashMap}) then {
                _lines pushBack format ["SMS clé : « %1 »", (_sms select 0) getOrDefault ["text", ""]];
            };
            private _files = _d getOrDefault ["files", []];
            private _rel = _files select { _x getOrDefault ["relevant", false] };
            if (count _rel > 0) then {
                _lines pushBack format ["Fichier clé : %1", (_rel select 0) getOrDefault ["name", ""]];
            };
        };
    };
    default {
        _lines pushBack format ["Mode %1 — observation limitée.", _mode];
    };
};

if (count _lines == 0) then { _lines pushBack "Aucune donnée à ce niveau."; };

createHashMapFromArray [
    ["title", _title],
    ["uid", _uid],
    ["type", _dtype],
    ["lines", _lines],
    ["quality", _quality],
    ["qualityLabel", [_quality] call comspec_sse_fnc_qualityLabel],
    ["level", _modeL],
    ["mode", _modeL]
]
