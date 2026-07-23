/*

    PageLoaded : injecte callsign / statut / grille / effectifs / aperçus / alertes via ExecJS.

*/

params ["_ctrl"];



if (isNull _ctrl) exitWith {};



missionNamespace setVariable ["COMSPEC_WebBrowser_PageReady", true];



private _display = ctrlParent _ctrl;

if (!isNull _display) then {

    private _hint = _display displayCtrl 9403;

    if (!isNull _hint) then {

        private _mode = missionNamespace getVariable ["COMSPEC_WebBrowser_Mode", "local"];

        private _label = if (_mode isEqualTo "athena") then {

            "<t align='right' size='0.55' color='#7dffb3'>Carte Athena</t>"

        } else {

            "<t align='right' size='0.55' color='#7dffb3'>Écran tactique prêt</t>"

        };

        _hint ctrlSetStructuredText parseText _label;

    };

};



// En mode remote Athena, ne pas réinjecter le boot local

if ((missionNamespace getVariable ["COMSPEC_WebBrowser_Mode", "local"]) isEqualTo "athena") exitWith {};



private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;

if (_callsign isEqualTo "") then { _callsign = name player; };

private _myRole = [player] call comspec_overwatch_connect_fnc_getUnitRole;



private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];

private _statusLabel = switch (_state) do {

    case "linked": { "Lié à Athena" };

    case "connecting": { "Connexion…" };

    case "disabled": { "Overwatch désactivé" };

    default { "Hors liaison" };

};



private _grid = [player] call comspec_overwatch_connect_fnc_gridPosition;
if (_grid isEqualTo "") then { _grid = mapGridPosition player; };
private _myHdg = round (getDir player);
private _myOct = [_myHdg] call comspec_overwatch_connect_fnc_formatHeading;

private _h = floor daytime;

private _m = floor ((daytime - _h) * 60);

private _s = floor ((((daytime - _h) * 60) - _m) * 60);

private _timeStr = format ["%1:%2:%3",

    if (_h < 10) then { format ["0%1", _h] } else { str _h },

    if (_m < 10) then { format ["0%1", _m] } else { str _m },

    if (_s < 10) then { format ["0%1", _s] } else { str _s }

];



private _units = [] call comspec_overwatch_connect_fnc_getUnitsList;
missionNamespace setVariable ["COMSPEC_WebBrowser_MapUnits", _units];

// Scan radio à jour pour pastilles tablette
if (missionNamespace getVariable ["comspec_overwatch_radio_proximity_enabled", true]) then {
    private _fresh = [] call comspec_overwatch_connect_fnc_scanRadioProximity;
    missionNamespace setVariable ["COMSPEC_RadioProximityList", _fresh, false];
};

private _radioByCs = createHashMap;
{
    private _csKey = toLower (_x getOrDefault ["callsign", ""]);
    if (_csKey != "") then { _radioByCs set [_csKey, _x]; };
} forEach (missionNamespace getVariable ["COMSPEC_RadioProximityList", []]);

private _unitJs = [];

{
    _x params ["_cs", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0], ["_role", ""]];

    private _safeCs = [_cs] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    private _safeRole = [_role] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    private _rad = _radioByCs getOrDefault [toLower _cs, createHashMap];
    private _tx = _rad getOrDefault ["tx", false];
    private _spk = _rad getOrDefault ["speaking", false];
    private _ch = [_rad getOrDefault ["channel", ""]] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    private _net = [_rad getOrDefault ["net", ""]] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    private _dist = _rad getOrDefault ["dist", -1];

    private _hdg = if (_isSelf) then { round (getDir player) } else { -1 };
    private _oct = if (_hdg >= 0) then {
        [_hdg] call comspec_overwatch_connect_fnc_formatHeading
    } else { "" };
    private _safeOct = [_oct] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

    _unitJs pushBack format [
        "{callsign:'%1',gx:%2,gy:%3,self:%4,wx:%5,wy:%6,role:'%7',tx:%8,speaking:%9,radioChannel:'%10',radioNet:'%11',dist:%12,hdg:%13,octant:'%14'}",
        _safeCs, _gx, _gy,
        if (_isSelf) then { "true" } else { "false" },
        _wx, _wy, _safeRole,
        if (_tx) then { "true" } else { "false" },
        if (_spk) then { "true" } else { "false" },
        _ch, _net, _dist, _hdg, _safeOct
    ];

} forEach _units;



// Aperçu messagerie

private _nl = toString [10];

private _log = missionNamespace getVariable ["COMSPEC_Log", ""];

private _logLines = if (_log isEqualTo "") then { [] } else { _log splitString _nl };

private _chatJs = [];

private _chatStart = (count _logLines) - 12;

if (_chatStart < 0) then { _chatStart = 0; };

if ((count _logLines) > 0) then {

    for "_i" from _chatStart to ((count _logLines) - 1) do {

        private _line = trim (_logLines select _i);

        if (!(_line isEqualTo "")) then {

            private _safe = [_line] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

            _chatJs pushBack format ["'%1'", _safe];

        };

    };

};



// Aperçu ordres — sync Athena puis injection

[] call comspec_overwatch_connect_fnc_pollOrders;

private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];

private _orderJs = [];

private _myName = name player;

private _myCallsign = _callsign;

{

    private _order = _x;

    if (!(_order isEqualType createHashMap)) then { continue };

    if (!([_order] call comspec_overwatch_connect_fnc_orderConcernsPlayer)) then { continue };

    private _issuer = _order getOrDefault ["issuer", ""];

    private _target = trim (_order getOrDefault ["target", ""]);

    private _explicitMe = (_target != "") && {

        (toLower _target) isEqualTo (toLower _myCallsign)

        || {(toLower _target) isEqualTo (toLower _myName)}

    };

    if ((_issuer isEqualTo _myName || {_issuer isEqualTo _myCallsign}) && {!_explicitMe}) then { continue };

    private _type = _order getOrDefault ["type", "MOVE"];

    private _status = _order getOrDefault ["status", "PENDING"];

    private _typeLabel = switch (toUpper _type) do {

        case "HOLD": { "Tenir" };

        case "RECON": { "Recon" };

        case "CAS": { "Appui aérien" };

        case "QRF": { "QRF" };

        default { "Mouvement" };

    };

    private _statusLabelO = switch (toUpper _status) do {

        case "ACK": { "Accusé" };

        case "EXEC": { "En cours" };

        case "FAILED": { "Échec" };

        case "DELIVERED": { "Reçu" };

        case "CANCELLED": { "Annulé" };

        default { "À traiter" };

    };

    private _oid = _order getOrDefault ["id", ""];

    private _safeOid = [_oid] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

    private _safeIssuer = [_issuer] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

    private _safeType = [_typeLabel] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

    private _safeSt = [_statusLabelO] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

    _orderJs pushBack format ["{id:'%1',type:'%2',status:'%3',issuer:'%4'}", _safeOid, _safeType, _safeSt, _safeIssuer];

    if ((count _orderJs) >= 8) exitWith {};

} forEach _orders;



// File d’alertes HTML

private _alerts = missionNamespace getVariable ["COMSPEC_HtmlAlerts", []];

private _alertJs = [];

private _alertStart = (count _alerts) - 20;

if (_alertStart < 0) then { _alertStart = 0; };

if ((count _alerts) > 0) then {

    for "_i" from _alertStart to ((count _alerts) - 1) do {

        private _a = _alerts select _i;

        if (!(_a isEqualType []) || {(count _a) < 6}) then { continue };

        _a params ["_id", "_type", "_title", "_body", "_prio", "_ts"];

        private _safeId = [str _id] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

        private _safeType = [str _type] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

        private _safeTitle = [str _title] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

        private _safeBody = [str _body] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

        private _safePrio = [str _prio] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

        _alertJs pushBack format [

            "{id:'%1',type:'%2',title:'%3',body:'%4',priority:'%5',ts:%6}",

            _safeId, _safeType, _safeTitle, _safeBody, _safePrio, _ts

        ];

    };

};



private _quiet = if (missionNamespace getVariable ["comspec_overwatch_quiet_mode", false]) then { "true" } else { "false" };

private _radioModuleOk = if (missionNamespace getVariable ["COMSPEC_RadioModuleOk", false]) then { "true" } else { "false" };
private _radioRadius = missionNamespace getVariable ["comspec_overwatch_radio_proximity_radius", 75];
private _radioMon = if (missionNamespace getVariable ["COMSPEC_RadioMonitorActive", false]) then { "true" } else { "false" };
private _radioMonCh = [missionNamespace getVariable ["COMSPEC_RadioMonitorChannel", ""]] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
private _radioProxJs = [];
{
    private _safeCs2 = [_x getOrDefault ["callsign", ""]] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    private _safeCh2 = [_x getOrDefault ["channel", ""]] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    private _safeNet2 = [_x getOrDefault ["net", ""]] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    private _safeFreq2 = [_x getOrDefault ["freq", ""]] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    private _safeRid2 = [_x getOrDefault ["radio_id", ""]] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
    _radioProxJs pushBack format [
        "{callsign:'%1',dist:%2,tx:%3,speaking:%4,channel:'%5',net:'%6',freq:'%7',radioId:'%8',self:%9}",
        _safeCs2,
        _x getOrDefault ["dist", 0],
        if (_x getOrDefault ["tx", false]) then { "true" } else { "false" },
        if (_x getOrDefault ["speaking", false]) then { "true" } else { "false" },
        _safeCh2, _safeNet2, _safeFreq2, _safeRid2,
        if (_x getOrDefault ["self", false]) then { "true" } else { "false" }
    ];
} forEach (missionNamespace getVariable ["COMSPEC_RadioProximityList", []]);



private _footer = if ((count _units) == 0) then {

    "Aucun contact — vérifiez la liaison"

} else {

    format ["%1 contact(s) synchronisé(s)", count _units]

};

private _mapHint = if (_state isEqualTo "linked") then {

    "Contacts en liaison · positions jeu + Athena"

} else {

    "Contacts locaux · liez Athena pour le réseau"

};



private _safeCallsign = [_callsign] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

private _safeRole = [_myRole] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

private _safeStatus = [_statusLabel] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

private _safeFooter = [_footer] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

private _safeHint = [_mapHint] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
private _safeOct = [_myOct] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

private _js = format [
    "window.COMSPEC_BOOT={callsign:'%1',role:'%2',status:'%3',statusLabel:'%4',grid:'%5',time:'%6',units:[%7],chat:[%8],orders:[%9],alerts:[%10],quiet:%11,footer:'%12',mapHint:'%13',heading:%14,octant:'%15',radio:{moduleOk:%16,radius:%17,monitoring:%18,monitorChannel:'%19',contacts:[%20]}}; if(window.COMSPEC_onBoot){window.COMSPEC_onBoot(window.COMSPEC_BOOT);}",
    _safeCallsign,
    _safeRole,
    _state,
    _safeStatus,
    _grid,
    _timeStr,
    _unitJs joinString ",",
    _chatJs joinString ",",
    _orderJs joinString ",",
    _alertJs joinString ",",
    _quiet,
    _safeFooter,
    _safeHint,
    _myHdg,
    _safeOct,
    _radioModuleOk,
    _radioRadius,
    _radioMon,
    _radioMonCh,
    _radioProxJs joinString ","
];

_ctrl ctrlWebBrowserAction ["ExecJS", _js];

if (
    missionNamespace getVariable ["COMSPEC_WebBrowser_MapVisible", false]
    && {missionNamespace getVariable ["COMSPEC_WebBrowser_MapAutoCenter", true]}
) then {
    [] call comspec_overwatch_connect_fnc_webBrowserMapCenter;
};


