/*
    Rafraîchit statut + inbox (alertes, ordres, BDA Iceman, photos locales).
*/
private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (isNull _group) exitWith {};

private _statusCtrl = _group controlsGroupCtrl 9701;
private _listCtrl = _group controlsGroupCtrl 9710;
private _detailCtrl = _group controlsGroupCtrl 9711;
private _tab = missionNamespace getVariable ["COMSPEC_Athena_PanelTab", "all"];

private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };

private _hasBda = !isNil "Iceman_fnc_bda_receive" || {!isNil { missionNamespace getVariable "Iceman_ATAK_BDA_reports" }};
private _hasPhoto = !isNil "Iceman_fnc_photo_getRecords";
private _statusTxt = if (_linked) then {
    format [
        "<t color='#7dffb0'>Athena</t> · %1 · BDA %2 · Photos %3",
        _cs,
        if (_hasBda) then { "<t color='#7dffb0'>OK</t>" } else { "<t color='#888'>—</t>" },
        if (_hasPhoto) then { "<t color='#7dffb0'>OK</t>" } else { "<t color='#888'>—</t>" }
    ]
} else {
    "<t color='#ffd27a'>Liaison Athena en attente</t> — tablette pour lier le compte"
};
if (!isNull _statusCtrl) then {
    _statusCtrl ctrlSetStructuredText parseText _statusTxt;
};

// Entrées : [kind, title, detail, sortKey, meta]
private _entries = [];

// --- Alertes / BDA / groupe journalisés côté COMSPEC ---
private _alerts = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_alerts isEqualType [])) then { _alerts = []; };
{
    if (!(_x isEqualType [])) then { continue };
    _x params [
        ["_kind", "TIC", [""]],
        ["_label", "Alerte", [""]],
        ["_summary", "", [""]],
        ["_grid", "", [""]],
        ["_time", "", [""]],
        ["_from", "", [""]]
    ];
    private _entryKind = switch (toUpper _kind) do {
        case "BDA": { "bda" };
        case "PHOTO": { "photo" };
        case "GROUP": { "alert" };
        default { "alert" };
    };
    private _title = format ["[%1] %2", _label, if (_from isEqualTo "") then { _cs } else { _from }];
    if (_grid isNotEqualTo "") then { _title = _title + format [" · %1", _grid]; };
    private _detail = format [
        "<t color='#ffd27a'>%1</t><br/>De : %2<br/>Grille : %3<br/>Heure : %4<br/><br/>%5",
        _label,
        if (_from isEqualTo "") then { "—" } else { _from },
        if (_grid isEqualTo "") then { "—" } else { _grid },
        if (_time isEqualTo "") then { "—" } else { _time },
        _summary
    ];
    _entries pushBack [_entryKind, _title, _detail, _time, []];
} forEach _alerts;

// --- BDA stockés par ATAK Enhanced (récupération locale) ---
private _bdaReports = missionNamespace getVariable ["Iceman_ATAK_BDA_reports", []];
if (!(_bdaReports isEqualType [])) then { _bdaReports = []; };
{
    if (!(_x isEqualType [])) then { continue };
    _x params [
        ["_time", "", [""]],
        ["_kindText", "BDA", [""]],
        ["_senderName", "", [""]],
        ["_grid", "", [""]],
        ["_body", "", [""]]
    ];
    private _plain = _body;
    _plain = [_plain, "<br/>", " | "] call BIS_fnc_replaceString;
    _plain = [_plain, "<br>", " | "] call BIS_fnc_replaceString;
    private _title = format ["[BDA ATAK] %1 · %2", _senderName, _grid];
    private _detail = format [
        "<t color='#ff9a4a'>Bilan des dégâts (ATAK Enhanced)</t><br/>De : %1<br/>Grille : %2<br/>Heure : %3<br/><br/>%4<br/><br/><t color='#9aa4aa'>Déjà synchro réseau cTab — remontée Athena à l’envoi.</t>",
        _senderName, _grid, _time, _plain
    ];
    _entries pushBack ["bda", _title, _detail, _time, []];
} forEach _bdaReports;

// --- Photos locales Photo Library ---
if (!isNil "Iceman_fnc_photo_getRecords") then {
    private _records = call Iceman_fnc_photo_getRecords;
    if (_records isEqualType []) then {
        {
            if (!(_x isEqualType [])) then { continue };
            if ((count _x) < 9) then { continue };
            private _filePath = _x select 2;
            private _fileName = _x select 3;
            private _author = _x select 4;
            private _grid = _x select 8;
            private _src = _x select 1;
            if (_filePath isEqualTo "" && {_src isEqualTo "received"}) then { continue };
            private _title = format ["[Photo] %1 · %2", _fileName, _grid];
            private _detail = format [
                "<t color='#a8d8ff'>Photo ATAK Enhanced</t><br/>Auteur : %1<br/>Grille : %2<br/>Fichier : %3<br/>Source : %4<br/><br/>Sélectionnez puis « Photo → Athena » pour remonter.",
                _author, _grid, _fileName, _src
            ];
            _entries pushBack ["photo", _title, _detail, _fileName, [_filePath, _fileName]];
        } forEach _records;
    };
};

// --- Ordres Athena ---
private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
if (!(_orders isEqualType [])) then { _orders = []; };
{
    if (!(_x isEqualType createHashMap)) then { continue };
    private _id = _x getOrDefault ["id", ""];
    if (_id isEqualTo "") then { continue };
    private _type = _x getOrDefault ["type", "MOVE"];
    private _typeLabel = trim (_x getOrDefault ["typeLabel", ""]);
    if (_typeLabel isEqualTo "") then {
        _typeLabel = switch (toUpper _type) do {
            case "HOLD": { "Tenir la position" };
            case "RECON": { "Reconnaissance" };
            case "CAS": { "Appui aérien" };
            case "QRF": { "Force de réaction" };
            case "CUSTOM": { "Ordre personnalisé" };
            default { "Se déplacer" };
        };
    };
    private _issuer = _x getOrDefault ["issuer", "C2"];
    private _prio = _x getOrDefault ["priority", "IMPORTANT"];
    private _payload = _x getOrDefault ["payload", ""];
    private _title = format ["[Ordre] %1 · %2", _typeLabel, _issuer];
    private _detail = format [
        "<t color='#7eb8ff'>Ordre</t> — %1<br/>Priorité : %2<br/>Émetteur : %3<br/>Cible : %4<br/><br/>%5",
        _typeLabel, _prio, _issuer, _x getOrDefault ["target", "—"], _payload
    ];
    _entries pushBack ["order", _title, _detail, _id, []];
} forEach _orders;

reverse _entries;

// Filtre onglet
if (_tab isNotEqualTo "all") then {
    _entries = _entries select { (_x select 0) isEqualTo _tab };
};

if (!isNull _listCtrl) then {
    private _prev = lbCurSel _listCtrl;
    _listCtrl setVariable ["COMSPEC_AthenaInboxUpdating", true];
    lbClear _listCtrl;
    {
        _x params ["_kind", "_title", "_detail"];
        private _idx = _listCtrl lbAdd _title;
        _listCtrl lbSetData [_idx, str _forEachIndex];
        _listCtrl lbSetTooltip [_idx, _title];
        switch (_kind) do {
            case "order": { _listCtrl lbSetColor [_idx, [0.5, 0.75, 1, 1]]; };
            case "bda": { _listCtrl lbSetColor [_idx, [1, 0.6, 0.3, 1]]; };
            case "photo": { _listCtrl lbSetColor [_idx, [0.65, 0.85, 1, 1]]; };
            default { _listCtrl lbSetColor [_idx, [1, 0.85, 0.45, 1]]; };
        };
    } forEach _entries;
    _listCtrl setVariable ["COMSPEC_Athena_Entries", _entries];
    _listCtrl setVariable ["COMSPEC_AthenaInboxUpdating", false];
    if ((count _entries) > 0) then {
        private _sel = if (_prev >= 0 && {_prev < count _entries}) then { _prev } else { 0 };
        _listCtrl lbSetCurSel _sel;
        [_listCtrl, _sel] call comspec_overwatch_atak_athena_fnc_athena_selectInbox;
    } else {
        if (!isNull _detailCtrl) then {
            private _empty = switch (_tab) do {
                case "bda": { "Aucun bilan des dégâts pour le moment." };
                case "photo": { "Aucune photo ATAK — capturez depuis l’app Photos." };
                case "order": { "Aucun ordre Athena." };
                default { "Aucune alerte, ordre, BDA ni photo." };
            };
            _detailCtrl ctrlSetStructuredText parseText format ["<t color='#9aa4aa'>%1</t>", _empty];
        };
    };
};
