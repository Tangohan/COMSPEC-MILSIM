/*
    Interroge Athena (GetTacticalAlerts) et fusionne dans COMSPEC_Athena_AlertInbox.
    Alimente l’app Athena cTab avec les signalements issus du C2 / autres joueurs.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _raw = ["COMSPECExtension" callExtension ["GetTacticalAlerts", [_mapId, "40"]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith { false };

private _body = _raw select [3];
private _lines = _body splitString (toString [10]);
private _tab = toString [9];

private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_inbox isEqualType [])) then { _inbox = []; };

private _seen = missionNamespace getVariable ["COMSPEC_Athena_RemoteAlertIds", []];
if (!(_seen isEqualType [])) then { _seen = []; };

private _myCs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _myCs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_myCs isEqualTo "") then { _myCs = name player; };

private _added = 0;
private _notifyTitles = [];

{
    private _line = _x;
    if (_line isEqualTo "") then { continue };
    private _cols = _line splitString _tab;
    if ((count _cols) < 6) then { continue };

    private _id = _cols select 0;
    if (_id isEqualTo "" || {_id in _seen}) then { continue };

    private _kindRaw = toLower (_cols select 1);
    private _kindLabel = _cols select 2;
    private _from = _cols select 3;
    private _grid = _cols select 4;
    private _summary = _cols select 5;
    private _created = if ((count _cols) > 6) then { _cols select 6 } else { "" };

    private _kindKey = switch (_kindRaw) do {
        case "tic": { "TIC" };
        case "tic_clear": { "TIC_CLEAR" };
        case "frago": { "FRAGO" };
        case "salute": { "SALUTE" };
        case "eagle_down": { "EAGLE_DOWN" };
        case "bda": { "BDA" };
        default { toUpper _kindRaw };
    };
    if (_kindLabel isEqualTo "") then {
        _kindLabel = switch (_kindKey) do {
            case "TIC": { "Contact" };
            case "TIC_CLEAR": { "Fin de contact" };
            case "FRAGO": { "Ordre fragmentaire" };
            case "SALUTE": { "Compte rendu SALUTE" };
            case "EAGLE_DOWN": { "Opérateur à terre" };
            case "BDA": { "Bilan des dégâts" };
            default { "Alerte" };
        };
    };

    private _timeStr = _created;
    if ((count _created) >= 16) then {
        // ISO …T12:34:… → 12:34
        private _tPos = _created find "T";
        if (_tPos >= 0 && {(count _created) >= (_tPos + 6)}) then {
            _timeStr = _created select [_tPos + 1, 5];
        };
    };
    if (_timeStr isEqualTo "") then {
        _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;
    };

    _seen pushBack _id;
    _inbox pushBack [_kindKey, _kindLabel, _summary, _grid, _timeStr, _from, _id];
    _added = _added + 1;

    private _fromMe = (toLower _from) isEqualTo (toLower _myCs) || {(toLower _from) isEqualTo (toLower (name player))};
    if (!_fromMe) then {
        _notifyTitles pushBack format ["%1 — %2", _kindLabel, if (_from isEqualTo "") then { "Athena" } else { _from }];
        private _detail = format [
            "<t color='#ffd27a'>%1</t><br/><t color='#8aa0b4'>De</t>  %2<br/><t color='#8aa0b4'>Grille</t>  %3<br/><t color='#8aa0b4'>Heure</t>  %4<br/>%5",
            _kindLabel,
            if (_from isEqualTo "") then { "Athena" } else { _from },
            if (_grid isEqualTo "") then { "—" } else { _grid },
            _timeStr,
            if (_summary isEqualTo "") then { "" } else { format ["<br/>%1", _summary] }
        ];
        [
            "alert",
            _kindLabel,
            format ["%1 — %2", _kindLabel, if (_from isEqualTo "") then { "Athena" } else { _from }],
            _detail,
            format ["remote_%1", _id],
            _timeStr
        ] call comspec_overwatch_atak_athena_fnc_athena_pushNotification;
    };
} forEach _lines;

while { (count _seen) > 80 } do { _seen deleteAt 0; };
while { (count _inbox) > 40 } do { _inbox deleteAt 0; };

missionNamespace setVariable ["COMSPEC_Athena_RemoteAlertIds", _seen, false];
missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];

if (_added > 0) then {
    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
    if ((count _notifyTitles) > 0) then {
        private _title = _notifyTitles select ((count _notifyTitles) - 1);
        ["ATHENA", _title, 6] call comspec_overwatch_connect_fnc_addScreenToast;
    };
};

_added > 0
