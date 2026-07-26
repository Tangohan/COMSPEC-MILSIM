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

// Onglets : idle / actif (teal Athena)
private _tabIdle = [0.06, 0.1, 0.12, 0.92];
private _tabActive = [0.08, 0.32, 0.28, 0.96];
private _tabMap = [
    ["all", 9740],
    ["bda", 9741],
    ["photo", 9742],
    ["order", 9743],
    ["modules", 9744]
];
{
    _x params ["_id", "_idc"];
    private _ctrl = _group controlsGroupCtrl _idc;
    if (!isNull _ctrl) then {
        private _col = if (_tab isEqualTo _id) then { _tabActive } else { _tabIdle };
        _ctrl ctrlSetBackgroundColor _col;
    };
} forEach _tabMap;

private _statusTxt = if (_linked) then {
    private _ms = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
    private _msPart = if (_ms >= 0) then { format [" · <t color='#9aa4aa'>%1 ms</t>", _ms] } else { "" };
    private _bdaPart = if (_hasBda) then { "<t color='#7dffb0'>BDA prêt</t>" } else { "<t color='#6a7c90'>BDA —</t>" };
    private _photoPart = if (_hasPhoto) then { "<t color='#7dffb0'>Photos prêtes</t>" } else { "<t color='#6a7c90'>Photos —</t>" };
    format [
        "<t color='#7dffb0'>●</t> <t color='#e8f4f0'>%1</t>%2<br/><t size='0.9'>%3 · %4</t>",
        _cs,
        _msPart,
        _bdaPart,
        _photoPart
    ]
} else {
    "<t color='#ffd27a'>● Liaison en attente</t><br/><t size='0.9' color='#8aa0b4'>Utilisez Connexion ci-dessous ou l’icône Desktop</t>"
};
if (!isNull _statusCtrl) then {
    _statusCtrl ctrlSetStructuredText parseText _statusTxt;
};

// Zone Feedback (retours photo / actions) — hors bandeau carte
private _fbCtrl = _group controlsGroupCtrl 9712;
if (!isNull _fbCtrl) then {
    private _fbData = missionNamespace getVariable ["COMSPEC_Athena_PanelFeedback", []];
    if ((_fbData isEqualType []) && {(count _fbData) >= 3} && {diag_tickTime < (_fbData select 2)}) then {
        _fbCtrl ctrlShow true;
        _fbCtrl ctrlSetBackgroundColor (_fbData select 1);
        _fbCtrl ctrlSetStructuredText parseText (_fbData select 0);
        _fbCtrl ctrlSetFade 0;
        _fbCtrl ctrlCommit 0;
    } else {
        if ((_fbData isEqualType []) && {(count _fbData) > 0}) then {
            missionNamespace setVariable ["COMSPEC_Athena_PanelFeedback", nil, false];
        };
        _fbCtrl ctrlSetStructuredText parseText "";
        _fbCtrl ctrlShow false;
    };
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
    private _fromTxt = if (_from isEqualTo "") then { _cs } else { _from };
    private _title = format ["%1 · %2", _label, _fromTxt];
    if (_grid isNotEqualTo "") then { _title = _title + format [" · %1", _grid]; };
    private _detail = format [
        "<t color='#ffd27a'>%1</t><br/><t color='#8aa0b4'>De</t>  %2<br/><t color='#8aa0b4'>Grille</t>  %3<br/><t color='#8aa0b4'>Heure</t>  %4<br/>%5",
        _label,
        if (_from isEqualTo "") then { "—" } else { _from },
        if (_grid isEqualTo "") then { "—" } else { _grid },
        if (_time isEqualTo "") then { "—" } else { _time },
        if (_summary isEqualTo "") then { "" } else { format ["<br/>%1", _summary] }
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
    private _title = format ["BDA · %1 · %2", _senderName, _grid];
    private _detail = format [
        "<t color='#e0a060'>Bilan des dégâts</t><br/><t color='#8aa0b4'>De</t>  %1<br/><t color='#8aa0b4'>Grille</t>  %2<br/><t color='#8aa0b4'>Heure</t>  %3<br/><br/>%4",
        _senderName, _grid, _time, _plain
    ];
    _entries pushBack ["bda", _title, _detail, _time, []];
} forEach _bdaReports;

// --- Messages de groupe ATAK Enhanced ---
private _groupMsgs = missionNamespace getVariable ["Iceman_ATAK_Group_messages", []];
if (!(_groupMsgs isEqualType [])) then { _groupMsgs = []; };
{
    if (!(_x isEqualType [])) then { continue };
    if ((count _x) < 5) then { continue };
    private _gTime = _x select 0;
    private _gSender = _x select 1;
    private _gId = _x select 2;
    private _gGrid = _x select 3;
    private _gText = _x select 4;
    private _title = format ["Groupe · %1 · %2", _gSender, _gGrid];
    private _detail = format [
        "<t color='#c8e6c9'>Message de groupe</t><br/><t color='#8aa0b4'>De</t>  %1<br/><t color='#8aa0b4'>Groupe</t>  %2<br/><t color='#8aa0b4'>Grille</t>  %3<br/><t color='#8aa0b4'>Heure</t>  %4<br/><br/>%5",
        _gSender,
        if (_gId isEqualTo "") then { "—" } else { _gId },
        if (_gGrid isEqualTo "") then { "—" } else { _gGrid },
        if (_gTime isEqualTo "") then { "—" } else { _gTime },
        _gText
    ];
    _entries pushBack ["alert", _title, _detail, _gTime, []];
} forEach _groupMsgs;

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
            private _srcLabel = switch (_src) do {
                case "received": { "Reçue" };
                case "local";
                case "captured": { "Locale" };
                default { "ATAK" };
            };
            private _title = format ["Photo · %1 · %2", _fileName, _grid];
            private _detail = format [
                "<t size='0.85' color='#c8e8ff'>Photo</t><br/><t color='#8aa0b4'>Auteur</t>  %1<br/><t color='#8aa0b4'>Grille</t>  %2<br/><t color='#8aa0b4'>Nom</t>  %3<br/><t color='#8aa0b4'>Source</t>  %4<br/><br/><t color='#b8c8d4'>Sélectionnez puis utilisez « Photo Athena » pour remonter.</t>",
                _author, _grid, _fileName, _srcLabel
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
    private _prioLabel = switch (toUpper _prio) do {
        case "URGENT": { "Urgent" };
        case "ROUTINE": { "Routine" };
        default { "Important" };
    };
    private _payload = _x getOrDefault ["payload", ""];
    private _title = format ["Ordre · %1 · %2", _typeLabel, _issuer];
    private _detail = format [
        "<t color='#7eb8ff'>Ordre</t> — %1<br/><t color='#8aa0b4'>Priorité</t>  %2<br/><t color='#8aa0b4'>Émetteur</t>  %3<br/><t color='#8aa0b4'>Cible</t>  %4<br/>%5",
        _typeLabel, _prioLabel, _issuer, _x getOrDefault ["target", "—"],
        if (_payload isEqualTo "") then { "" } else { format ["<br/>%1", _payload] }
    ];
    _entries pushBack ["order", _title, _detail, _id, []];
} forEach _orders;

reverse _entries;

// --- Onglet Modules : état + journal données ---
if (_tab isEqualTo "modules") then {
    _entries = [];
    private _mods = missionNamespace getVariable ["COMSPEC_AthenaModules", createHashMap];
    private _labels = missionNamespace getVariable ["COMSPEC_AthenaModuleLabels", createHashMap];
    if (!(_mods isEqualType createHashMap)) then { _mods = createHashMap; };
    if (!(_labels isEqualType createHashMap)) then { _labels = createHashMap; };

    private _ids = keys _mods;
    if ((count _ids) == 0) then {
        _entries pushBack [
            "modules",
            "En attente de synchronisation",
            "<t color='#8aa0b4'>Les modules apparaîtront dès que la liaison Athena aura récupéré le réglage de la communauté.</t>",
            "0",
            []
        ];
    } else {
        {
            private _id = _x;
            private _en = _mods getOrDefault [_id, true];
            private _lab = _labels getOrDefault [_id, _id];
            private _stateTxt = if (_en) then { "Actif" } else { "Désactivé" };
            private _dot = if (_en) then { "<t color='#7dffb0'>●</t>" } else { "<t color='#ff9a4a'>●</t>" };
            private _color = if (_en) then { "#7dffb0" } else { "#ff9a4a" };
            private _title = format ["%1  %2", if (_en) then { "●" } else { "○" }, _lab];
            private _detail = format [
                "%1 <t color='%2'>%3</t><br/><t color='#8aa0b4'>Module</t>  %4<br/><br/><t color='#9aa4aa'>Réglable depuis l’administration Athena (Configuration ATAK).</t>",
                _dot,
                _color,
                _stateTxt,
                _lab
            ];
            _entries pushBack ["modules", _title, _detail, _id, []];
        } forEach _ids;
    };

    private _mlog = missionNamespace getVariable ["COMSPEC_ModuleLog", []];
    if (!(_mlog isEqualType [])) then { _mlog = []; };
    private _start = (count _mlog) - 25;
    if (_start < 0) then { _start = 0; };
    if ((count _mlog) > 0) then {
        for "_i" from ((count _mlog) - 1) to _start step -1 do {
            private _line = _mlog select _i;
            private _short = _line;
            if ((count _short) > 48) then {
                _short = (_short select [0, 45]) + "…";
            };
            _entries pushBack [
                "modules",
                format ["%1", _short],
                format [
                    "<t color='#5a9e88'>Journal des modules</t><br/><t color='#8aa0b4'>Événement synchronisé</t><br/><br/>%1",
                    _line
                ],
                format ["log%1", _i],
                []
            ];
        };
    };
};

// Filtre onglet
if (_tab isNotEqualTo "all" && {_tab isNotEqualTo "modules"}) then {
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
        // Couleurs listes — sobres, lisibles sur fond sombre
        switch (_kind) do {
            case "order": { _listCtrl lbSetColor [_idx, [0.55, 0.78, 0.92, 1]]; };
            case "bda": { _listCtrl lbSetColor [_idx, [0.92, 0.72, 0.48, 1]]; };
            case "photo": { _listCtrl lbSetColor [_idx, [0.7, 0.84, 0.9, 1]]; };
            case "modules": { _listCtrl lbSetColor [_idx, [0.55, 0.88, 0.68, 1]]; };
            default { _listCtrl lbSetColor [_idx, [0.95, 0.86, 0.62, 1]]; };
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
                case "photo": { "Aucune photo à afficher — capturez d’abord depuis l’app Photos d’ATAK." };
                case "order": { "Aucun ordre reçu pour le moment." };
                case "modules": { "Aucun module synchronisé pour le moment." };
                default { "Aucune alerte, ordre, bilan ni photo pour le moment." };
            };
            private _hint = if (_tab isEqualTo "photo") then {
                "Ouvrez Photos sur ATAK, prenez une vue, puis revenez ici pour la remonter."
            } else {
                "Sélectionnez une entrée du journal ci-dessus."
            };
            _detailCtrl ctrlSetStructuredText parseText format [
                "<t size='0.88' color='#e8f4f0'>%1</t><br/><br/><t color='#9aa4aa'>%2</t>",
                _empty,
                _hint
            ];
        };
    };
};
