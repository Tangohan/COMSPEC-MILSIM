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
    ["messages", 9741],
    ["photo", 9742],
    ["order", 9743],
    ["bda", 9744],
    ["urgences", 9745],
    ["liaison", 9746],
    ["modules", 9747]
];
{
    _x params ["_id", "_idc"];
    private _ctrl = _group controlsGroupCtrl _idc;
    if (!isNull _ctrl) then {
        private _col = if (_tab isEqualTo _id) then { _tabActive } else { _tabIdle };
        _ctrl ctrlSetBackgroundColor _col;
    };
} forEach _tabMap;

// Compteur non lus sur Urgences
private _notifs = missionNamespace getVariable ["COMSPEC_Athena_Notifications", []];
if (!(_notifs isEqualType [])) then { _notifs = []; };
private _unreadCount = {_x select 5} count _notifs;
private _tabUrgCtrl = _group controlsGroupCtrl 9745;
if (!isNull _tabUrgCtrl) then {
    private _tabTxt = if (_unreadCount > 0) then {
        format ["Urgences (%1)", _unreadCount]
    } else {
        "Urgences"
    };
    _tabUrgCtrl ctrlSetText _tabTxt;
};

// Zone notifications (fil compact, plus récent en haut)
private _notifCtrl = _group controlsGroupCtrl 9715;
if (!isNull _notifCtrl) then {
    private _notifPrev = lbCurSel _notifCtrl;
    _notifCtrl setVariable ["COMSPEC_AthenaNotifUpdating", true];
    lbClear _notifCtrl;
    private _notifDisp = +_notifs;
    reverse _notifDisp;
    private _maxNotif = 5;
    private _shown = 0;
    {
        if (_shown >= _maxNotif) exitWith {};
        _x params ["_nid", "_nkind", "_ntype", "_nbrief", "_ntime", "_nunread"];
        private _prefix = if (_nunread) then { "● " } else { "  " };
        private _line = format ["%1%2 · %3 · %4", _prefix, _ntype, _ntime, _nbrief];
        if ((count _line) > 52) then { _line = (_line select [0, 49]) + "…"; };
        private _idx = _notifCtrl lbAdd _line;
        _notifCtrl lbSetData [_idx, str _forEachIndex];
        private _col = switch (_nkind) do {
            case "order": { [0.55, 0.78, 0.92, 1] };
            case "bda": { [0.92, 0.72, 0.48, 1] };
            case "photo": { [0.7, 0.84, 0.9, 1] };
            case "group";
            case "messages";
            case "notify": { [0.75, 0.9, 0.75, 1] };
            case "vibrate": { [0.95, 0.8, 0.45, 1] };
            case "system": { [0.75, 0.82, 0.88, 1] };
            default { [0.95, 0.86, 0.62, 1] };
        };
        _notifCtrl lbSetColor [_idx, _col];
        if (_nunread) then {
            _notifCtrl lbSetColorRight [_idx, [0.35, 0.95, 0.65, 1]];
        };
        _shown = _shown + 1;
    } forEach _notifDisp;
    _notifCtrl setVariable ["COMSPEC_AthenaNotifUpdating", false];
    if ((count _notifDisp) == 0) then {
        private _idx = _notifCtrl lbAdd "Aucune notification récente";
        _notifCtrl lbSetColor [_idx, [0.55, 0.62, 0.68, 0.85]];
        _notifCtrl lbSetCurSel -1;
    } else {
        if (_notifPrev >= 0 && {_notifPrev < _shown}) then {
            _notifCtrl lbSetCurSel _notifPrev;
        } else {
            _notifCtrl lbSetCurSel -1;
        };
    };
};

private _statusTxt = if (_linked) then {
    private _ms = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
    private _msPart = if (_ms >= 0) then { format [" · <t color='#9aa4aa'>%1 ms</t>", _ms] } else { "" };
    private _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
    private _loss = _pkt getOrDefault ["packet_loss_percent", 0];
    private _lossPart = format [" · <t color='#9aa4aa'>pertes %1%%</t>", (_loss toFixed 1)];
    private _mapId = missionNamespace getVariable ["comspec_overwatch_map_id", 1];
    private _dataCh = ((floor ((abs _mapId) mod 11)) + 1);
    private _dataMhz = 2400 + (_dataCh * 5);
    private _freqPart = format [" · <t color='#9aa4aa'>%1 MHz</t>", _dataMhz];
    private _bdaPart = if (_hasBda) then { "<t color='#7dffb0'>BDA prêt</t>" } else { "<t color='#6a7c90'>BDA —</t>" };
    private _photoPart = if (_hasPhoto) then { "<t color='#7dffb0'>Photos prêtes</t>" } else { "<t color='#6a7c90'>Photos —</t>" };
    format [
        "<t color='#7dffb0'>●</t> <t color='#e8f4f0'>%1</t>%2%3%4<br/><t size='0.9'>%5 · %6</t>",
        _cs,
        _msPart,
        _lossPart,
        _freqPart,
        _bdaPart,
        _photoPart
    ]
} else {
    "<t color='#ffd27a'>● Liaison en attente</t><br/><t size='0.9' color='#8aa0b4'>Onglet Liaison ou icône Compte sur le bureau ATAK</t>"
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
        ["_from", "", [""]],
        ["_alertId", "", [""]]
    ];
    private _entryKind = switch (toUpper _kind) do {
        case "BDA": { "bda" };
        case "PHOTO": { "alert" }; // journal « photo remontée » ≠ sélectionnable pour renvoi
        case "HQ": { "messages" };
        case "GROUP": { "messages" };
        case "NOTIFY": { "messages" };
        case "VIBRATE": { "alert" };
        case "MEDICAL": { "medical" };
        default { "alert" };
    };
    private _fromTxt = if (_from isEqualTo "") then { _cs } else { _from };
    private _title = format ["%1 · %2", _label, _fromTxt];
    if (_grid isNotEqualTo "") then { _title = _title + format [" · %1", _grid]; };

    // Corps lisible : FRAGO SMEAC en lignes, sans répéter type/indicatif/grille
    private _bodyHtml = "";
    private _sum = _summary;
    // Retirer ORDER_ID=… éventuel
    if ((_sum find "ORDER_ID=") == 0) then {
        private _bar = _sum find "|";
        if (_bar > 0) then {
            _sum = _sum select [_bar + 1, count _sum];
        } else {
            private _sp = _sum find "—";
            if (_sp < 0) then { _sp = _sum find " - "; };
            if (_sp > 0) then { _sum = trim (_sum select [_sp + 1, count _sum]); } else { _sum = ""; };
        };
    };
    if ((toUpper _kind) isEqualTo "FRAGO" || {(toLower _label) find "fragmentaire" >= 0}) then {
        private _lines = [];
        {
            _x params ["_key", "_fr"];
            private _needle = _key + ": ";
            private _p = _sum find _needle;
            if (_p < 0) then { _needle = _fr + ": "; _p = _sum find _needle; };
            if (_p >= 0) then {
                private _rest = _sum select [_p + count _needle, count _sum];
                private _cut = 1e9;
                {
                    private _n2 = _x + ": ";
                    private _q = _rest find _n2;
                    if (_q >= 0 && {_q < _cut}) then { _cut = _q; };
                } forEach ["Situation", "Mission", "Exécution", "Soutien", "Commandement", " — "];
                // Couper sur " — Situation" etc.
                private _dashKeys = [" — Situation:", " — Mission:", " — Exécution:", " — Soutien:", " — Commandement:"];
                {
                    private _q = _rest find _x;
                    if (_q >= 0 && {_q < _cut}) then { _cut = _q; };
                } forEach _dashKeys;
                private _val = if (_cut < 1e9) then { trim (_rest select [0, _cut]) } else { trim _rest };
                // Retirer séparateur final
                if ((_val find " — ") == ((count _val) - 3) && {(count _val) >= 3}) then {
                    _val = trim (_val select [0, (count _val) - 3]);
                };
                if (_val isNotEqualTo "") then {
                    _lines pushBack format ["<t color='#8aa0b4'>%1</t><br/><t color='#e8f4f0'>%2</t>", _fr, _val];
                };
            };
        } forEach [
            ["Situation", "Situation"],
            ["Mission", "Mission"],
            ["Exécution", "Exécution"],
            ["Soutien", "Soutien"],
            ["Commandement", "Commandement"]
        ];
        if ((count _lines) > 0) then {
            _bodyHtml = "<br/><br/>" + (_lines joinString "<br/><br/>");
        } else {
            if (_sum isNotEqualTo "") then {
                _bodyHtml = format ["<br/><br/><t color='#e8f4f0'>%1</t>", _sum];
            };
        };
    } else {
        if (_sum isNotEqualTo "") then {
            _bodyHtml = format ["<br/><br/><t color='#e8f4f0'>%1</t>", _sum];
        };
    };

    private _detail = format [
        "<t color='#ffd27a' size='1.05'>%1</t><br/><t color='#8aa0b4'>De</t>  %2<br/><t color='#8aa0b4'>Grille</t>  %3<br/><t color='#8aa0b4'>Heure</t>  %4%5",
        _label,
        if (_from isEqualTo "") then { "—" } else { _from },
        if (_grid isEqualTo "") then { "—" } else { _grid },
        if (_time isEqualTo "") then { "—" } else { _time },
        _bodyHtml
    ];
    private _sortKey = if (_alertId isNotEqualTo "") then { _alertId } else { _time };
    private _pushKind = if ((toUpper _kind) isEqualTo "FRAGO") then { "order" } else { _entryKind };
    _entries pushBack [_pushKind, _title, _detail, _sortKey, []];
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
    private _myCs = [] call comspec_overwatch_connect_fnc_getCallsign;
    private _isOut = (_myCs isNotEqualTo "") && {(toLower _gSender) isEqualTo (toLower _myCs)};
    private _dirLabel = if (_isOut) then { "Transmis" } else { "Reçu" };
    private _dirColor = if (_isOut) then { "#7dd3fc" } else { "#86efac" };
    private _title = if (_isOut) then {
        format ["Groupe · Transmis · %1", _gSender]
    } else {
        format ["Groupe · Reçu · %1", _gSender]
    };
    private _detail = format [
        "<t color='%6'>%7</t>  <t color='#c8e6c9'>Message de groupe</t><br/><br/><t color='#f8fafc' size='1.05'>%5</t><br/><br/><t color='#8aa0b4'>De</t>  %1<br/><t color='#8aa0b4'>Groupe</t>  %2<br/><t color='#8aa0b4'>Grille</t>  %3<br/><t color='#8aa0b4'>Heure</t>  %4",
        _gSender,
        if (_gId isEqualTo "") then { "—" } else { _gId },
        if (_gGrid isEqualTo "") then { "—" } else { _gGrid },
        if (_gTime isEqualTo "") then { "—" } else { _gTime },
        _gText,
        _dirColor,
        _dirLabel
    ];
    _entries pushBack ["messages", _title, _detail, _gTime, []];
} forEach _groupMsgs;

// --- Photos sélectionnables (Photo Library + captures récentes / Quick Pictures) ---
private _localPhotos = [];
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos") then {
    _localPhotos = [] call comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos;
};
if (!(_localPhotos isEqualType [])) then { _localPhotos = []; };
{
    if (!(_x isEqualType [])) then { continue };
    if ((count _x) < 1) then { continue };
    private _filePath = _x select 0;
    private _fileName = if ((count _x) > 1) then { _x select 1 } else { "" };
    private _grid = if ((count _x) > 2) then { _x select 2 } else { mapGridPosition player };
    private _author = if ((count _x) > 3) then { _x select 3 } else { name player };
    if (_filePath isEqualTo "") then { continue };
    private _photoKey = toLower _filePath;
    private _alreadyUp = missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []];
    if (!(_alreadyUp isEqualType [])) then { _alreadyUp = []; };
    private _pendingUp = missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []];
    if (!(_pendingUp isEqualType [])) then { _pendingUp = []; };
    private _failedUp = missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []];
    if (!(_failedUp isEqualType [])) then { _failedUp = []; };
    private _short = if (_fileName isEqualTo "") then { "Capture" } else { _fileName };
    private _title = format ["Photo - %1 - %2", _short, _grid];
    private _detail = if (_photoKey in _alreadyUp) then {
        format [
            "<t size='0.85' color='#7dffb0'>Photo recue sur ATAK web</t><br/><t color='#8aa0b4'>Auteur</t>  %1<br/><t color='#8aa0b4'>Grille</t>  %2<br/><t color='#8aa0b4'>Nom</t>  %3<br/><br/><t color='#b8c8d4'>Visible dans l'onglet Photos du poste de commandement. Utilisez Renvoyer seulement en cas de besoin.</t>",
            _author, _grid, _short
        ]
    } else {
        if (_photoKey in _failedUp) then {
            format [
                "<t size='0.85' color='#ff8a80'>Echec d'envoi vers ATAK web</t><br/><t color='#8aa0b4'>Auteur</t>  %1<br/><t color='#8aa0b4'>Grille</t>  %2<br/><t color='#8aa0b4'>Nom</t>  %3<br/><br/><t color='#b8c8d4'>La photo n'est pas arrivee au poste de commandement. Utilisez Renvoyer.</t>",
                _author, _grid, _short
            ]
        } else {
            if (_photoKey in _pendingUp) then {
                format [
                    "<t size='0.85' color='#ffe082'>Envoi vers ATAK web...</t><br/><t color='#8aa0b4'>Auteur</t>  %1<br/><t color='#8aa0b4'>Grille</t>  %2<br/><t color='#8aa0b4'>Nom</t>  %3<br/><br/><t color='#b8c8d4'>Transmission en cours. Elle n'apparaitra sur le web qu'apres confirmation.</t>",
                    _author, _grid, _short
                ]
            } else {
                format [
                    "<t size='0.85' color='#c8e8ff'>En attente de remontee</t><br/><t color='#8aa0b4'>Auteur</t>  %1<br/><t color='#8aa0b4'>Grille</t>  %2<br/><t color='#8aa0b4'>Nom</t>  %3<br/><br/><t color='#b8c8d4'>La photo partira seule vers ATAK web des qu'elle sera prise en charge.</t>",
                    _author, _grid, _short
                ]
            }
        }
    };
    _entries pushBack ["photo", _title, _detail, _short, [_filePath, _short]];
} forEach _localPhotos;

// --- Ordres Athena ---
private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
if (!(_orders isEqualType [])) then { _orders = []; };
{
    if (!(_x isEqualType createHashMap)) then { continue };
    private _id = _x getOrDefault ["id", ""];
    if (_id isEqualTo "") then { continue };
    private _type = _x getOrDefault ["type", "MOVE"];
    // Signaux terminal déjà notifiés via onVibrate / onNotify — pas une ligne « ordre »
    if ((toUpper _type) in ["VIBRATE", "NOTIFY", "HELMET_SNAP", "HELMET_SNAP_HD", "HELMET_STREAM"]) then { continue };
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

// --- Alertes médicales (triage) ---
private _medAlerts = missionNamespace getVariable ["COMSPEC_MedicalAlerts", []];
if (!(_medAlerts isEqualType [])) then { _medAlerts = []; };
{
    if (!(_x isEqualType createHashMap)) then { continue };
    private _mid = _x getOrDefault ["id", ""];
    if (_mid isEqualTo "") then { continue };
    private _mcs = _x getOrDefault ["call_sign", ""];
    private _mlb = _x getOrDefault ["label", "Assistance médicale"];
    private _mgrid = _x getOrDefault ["grid", ""];
    private _mcreated = _x getOrDefault ["created_at", ""];
    private _mtriage = _x getOrDefault ["triage_label", "À secourir"];
    private _mkind = toLower (_x getOrDefault ["kind", ""]);
    private _kindFr = switch (_mkind) do {
        case "cardiac_arrest";
        case "cardiac-arrest";
        case "death";
        case "dead";
        case "kia": { "Arrêt cardiaque" };
        case "unconscious": { "Inconscient" };
        default { "Médical" };
    };
    private _title = format ["Médical · %1 · %2", if (_mcs isEqualTo "") then { _kindFr } else { _mcs }, _mgrid];
    private _detail = format [
        "<t color='#ff9a4a'>Alerte médicale</t> — %1<br/><t color='#8aa0b4'>Blessé</t>  %2<br/><t color='#8aa0b4'>Grille</t>  %3<br/><t color='#8aa0b4'>Triage</t>  %4<br/><t color='#8aa0b4'>Heure</t>  %5<br/>%6<br/><br/><t color='#b8c8d4'>Sélectionnez cette ligne, puis utilisez les boutons de triage (médecin ou chef d’équipe).</t>",
        _kindFr,
        if (_mcs isEqualTo "") then { "—" } else { _mcs },
        if (_mgrid isEqualTo "") then { "—" } else { _mgrid },
        _mtriage,
        if (_mcreated isEqualTo "") then { "—" } else { _mcreated },
        _mlb
    ];
    _entries pushBack ["medical", _title, _detail, _mid, [_mid]];
} forEach _medAlerts;

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
if (_tab isEqualTo "urgences" || {_tab isEqualTo "notif"} || {_tab isEqualTo "alert"}) then {
    _entries = _entries select { (_x select 0) in ["alert", "order", "medical", "messages"] };
} else {
    if (_tab isEqualTo "liaison") then {
        private _linkState = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
        private _cs = if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
            [] call comspec_overwatch_connect_fnc_getCallsign
        } else { "" };
        if (_cs isEqualTo "") then { _cs = name player; };
        private _termUid = missionNamespace getVariable ["COMSPEC_TerminalUid", ""];
        private _certSt = missionNamespace getVariable ["COMSPEC_CertStatus", ""];
        private _certExp = missionNamespace getVariable ["COMSPEC_CertExpires", ""];
        private _certRef = missionNamespace getVariable ["COMSPEC_CertRef", ""];
        private _certTxt = [_certSt, _certExp] call comspec_overwatch_connect_fnc_certStatusLabel;
        private _mid = missionNamespace getVariable ["COMSPEC_MilitaryId", ""];
        if (_mid isEqualTo "") then { _mid = profileNamespace getVariable ["COMSPEC_MilitaryId", ""]; };
        private _atakId = missionNamespace getVariable ["COMSPEC_AtakId", ""];
        private _termOk = (_termUid isEqualType "") && {_termUid isNotEqualTo ""} && {(toLower _termUid) find "<null" < 0};
        private _certOk = (_certRef isEqualType "") && {_certRef isNotEqualTo ""} && {(toLower _certRef) find "<null" < 0};

        _entries = [
            [
                "liaison",
                "Identité réseau",
                format [
                    "<t color='#5a9e88'>Identité</t><br/><t color='#b8c8d4'>Indicatif</t>  %1<br/><t color='#b8c8d4'>ID militaire</t>  %2<br/><t color='#b8c8d4'>ID ATAK</t>  %3<br/><t color='#b8c8d4'>Liaison</t>  %4",
                    _cs,
                    if (_mid isEqualTo "") then { "—" } else { _mid },
                    if (_atakId isEqualTo "") then { "—" } else { _atakId },
                    if (_linkState isEqualTo "linked") then { "En liaison" } else { "Hors liaison" }
                ],
                "identity",
                []
            ],
            [
                "liaison",
                "Terminal & certificat",
                format [
                    "<t color='#5a9e88'>Terminal</t><br/><t color='#b8c8d4'>Identifiant</t>  %1<br/><t color='#b8c8d4'>Certificat</t>  %2<br/><t color='#b8c8d4'>Référence</t>  %3",
                    if (_termOk) then { _termUid } else { "Non synchronisé — rouvrez État ATAK" },
                    _certTxt,
                    if (_certOk) then { _certRef } else { "—" }
                ],
                "terminal",
                []
            ],
            [
                "liaison",
                "Adresse mobile + code",
                "<t color='#5a9e88'>Liaison téléphone</t><br/><t color='#b8c8d4'>Utilisez le bouton « Adresse mobile » pour afficher l’adresse dédiée et le code d’appariement de votre terminal.</t>",
                "mobile",
                []
            ],
            [
                "liaison",
                "Compte Athena",
                "<t color='#5a9e88'>Compte</t><br/><t color='#b8c8d4'>Liez votre compte Athena (code ou Steam) via le bouton « Compte Athena ».</t>",
                "account",
                []
            ]
        ];
    } else {
        if (_tab isNotEqualTo "all" && {_tab isNotEqualTo "modules"}) then {
            _entries = _entries select { (_x select 0) isEqualTo _tab };
        };
    };
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
            case "medical": { _listCtrl lbSetColor [_idx, [0.95, 0.55, 0.45, 1]]; };
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
                case "photo": { "Aucune photo récente pour le moment." };
                case "order": { "Aucun ordre reçu pour le moment." };
                case "urgences";
                case "alert";
                case "notif": { "Aucune urgence ni alerte médicale pour le moment." };
                case "modules": { "Aucun module synchronisé pour le moment." };
                default { "Aucune alerte, ordre, bilan ni photo pour le moment." };
            };
            private _hint = switch (_tab) do {
                case "photo": { "Ouvrez l’app Photos d’ATAK et prenez une vue : elle remonte seule vers ATAK web." };
                case "notif": { "Les nouveaux ordres et alertes apparaissent dans la zone ci-dessus." };
                default { "Sélectionnez une entrée du journal ci-dessous." };
            };
            _detailCtrl ctrlSetStructuredText parseText format [
                "<t size='0.88' color='#e8f4f0'>%1</t><br/><br/><t color='#9aa4aa'>%2</t>",
                _empty,
                _hint
            ];
        };
    };
};
