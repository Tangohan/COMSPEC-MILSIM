/*
    Centre de notifications : les 60 dernières (fn_notify), la plus récente en haut. L'ouvrir les marque comme lues.
      - Réglages rapides en une barre : mode avion, Bluetooth, silencieux, et le volet rapide complet.
      - État du mod épinglé en tête (fn_integrityCheck : PBO en double, DLL périmée, mise à jour disponible),
        rouge ou orange selon la gravité ; clic : app Debug, onglet DLL. Ces cartes restent tant que l'anomalie existe.
      - Filtres (Toutes, Messages, Missions, Réseau, Alertes, Infos) avec leur nombre : messages d'abord, puis ordres,
        briefing, comptes rendus (Missions) et liaison, relais, hors connexion (Réseau), puis la gravité.
      - Une carte par notification : icône de l'app, source, texte, « il y a 3 min », « ×3 » pour un texte répété ;
        clic : ouvre l'app concernée quand on la connaît ; croix : efface cette notification seule.
      - État vide soigné ; « Tout effacer » efface ce que le filtre affiche.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Non lues à l'ouverture (et arrivées pendant qu'elle est affichée) : pastille « nouveau » jusqu'à la sortie.
private _unread = _s getOrDefault ["notifUnread", 0];
_s set ["notifFresh", [_unread, (_s getOrDefault ["notifFresh", 0]) + _unread] select ((_s getOrDefault ["pagePrev", ""]) isEqualTo "NOTIFS")];
private _fresh = _s getOrDefault ["notifFresh", 0];
_s set ["notifUnread", 0];
_s set ["notifications", []];
private _rerender = { [{ ["NOTIFS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
private _data = "\z\comspec_atak_native\addons\main\data\";

// Réglages rapides : une barre compacte, l'état actif en vert.
private _air = [] call comspec_atak_native_fnc_airplaneMode;
private _btOn = (["state"] call comspec_atak_native_fnc_btAction) get "on";
private _silent = profileNamespace getVariable ["COMSPEC_ATAK_Silent", false];
private _qs = { params ["_k"]; compile format ["['act', '%1'] call comspec_atak_native_fnc_quickSettings; [{ ['NOTIFS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k] };
private _rows = [["bar", [["Réglages rapides", [
    ["MODE AVION", ["airplane"] call _qs, ["", "on"] select _air, "Coupe toutes les liaisons radio du téléphone"],
    ["BLUETOOTH", ["bt"] call _qs, ["", "on"] select _btOn, "Casque et appareils appairés"],
    ["SILENCIEUX", ["silent"] call _qs, ["", "on"] select _silent, "Aucun son de notification"],
    ["VOLET RAPIDE", { ["open"] call comspec_atak_native_fnc_quickSettings; }, "", "Luminosité, mode nuit, vibration, réseau, batterie"]
]]]]];

// État du mod (contrôle d'installation), épinglé.
private _integ = missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []];
private _integTexts = _integ apply { _x select 2 };
if ((count _integ) > 0) then {
    private _nErr = { (_x select 1) isEqualTo "ERROR" } count _integ;
    _rows pushBack ["section", "État du mod", [format ["%1 point(s) à vérifier", count _integ], format ["%1 erreur(s) : le téléphone peut mal fonctionner", _nErr]] select (_nErr > 0)];
    {
        _x params ["_key", "_level", "_text"];
        private _meta = [createHashMapFromArray [["type", _level], ["message", _text], ["app", "DEBUG"]]] call comspec_atak_native_fnc_notifMeta;
        private _upd = ((toLower _key) find "update") >= 0 || {((toLower _text) find "mise à jour") >= 0};
        _rows pushBack ["notif", _data + (["ui_help.paa", "ui_sort_up.paa"] select _upd), _meta get "rgb",
            [["Installation", "Mise à jour disponible"] select _upd, "Erreur d'installation"] select (_level isEqualTo "ERROR"),
            format ["<t color='%1'>%2</t>", _meta get "hex", toUpper (_meta get "label")], _meta get "body",
            { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["dbgTab", "DLL"]; ["DEBUG"] call comspec_atak_native_fnc_navigate; },
            {}, "Ouvrir le diagnostic (Debug, onglet DLL)"];
    } forEach _integ;
};

// Historique, le plus récent en haut, sans les anomalies déjà épinglées.
private _all = +(_s getOrDefault ["notifHistory", []]);
reverse _all;
_all = _all select { !((_x getOrDefault ["message", ""]) in _integTexts) };
private _metas = _all apply { [_x] call comspec_atak_native_fnc_notifMeta };
private _missionPages = ["TASK", "BRIEFING", "REPORTS", "PLAN", "LOGI", "MEDICAL", "JTAC", "FIRES", "WAYPOINTS"];
private _netPages = ["NETWORK", "WAVERELAY", "RESYNCH", "ATHENA", "BLUETOOTH"];
private _kindOf = { params ["_n", "_m"]; switch (true) do {
    case ((_n getOrDefault ["type", ""]) isEqualTo "MESSAGE"): { "MSG" };
    case ((_m get "page") in _missionPages): { "MISSION" };
    case ((_m get "page") in _netPages): { "NET" };
    case ((_m get "sev") isNotEqualTo "INFO"): { "ALERT" };
    default { "INFO" };
} };
private _counts = createHashMapFromArray [["ALL", count _all], ["ALERT", 0], ["INFO", 0], ["MSG", 0], ["MISSION", 0], ["NET", 0]];
{ private _k = [_x, _metas select _forEachIndex] call _kindOf; _counts set [_k, (_counts get _k) + 1]; } forEach _all;
private _filter = _s getOrDefault ["notifFilter", "ALL"];
if ((_counts getOrDefault [_filter, 0]) isEqualTo 0) then { _filter = "ALL"; };
private _shown = [];
{ if (_filter isEqualTo "ALL" || {([_x, _metas select _forEachIndex] call _kindOf) isEqualTo _filter}) then { _shown pushBack [_x, _metas select _forEachIndex, _forEachIndex]; }; } forEach _all;

private _nAlert = _counts get "ALERT";
_rows pushBack ["section", "Notifications", switch (true) do {
    case ((count _all) isEqualTo 0): { "Rien de nouveau" };
    case (_nAlert > 0): { format ["%1 notification(s) dont %2 alerte(s)", count _all, _nAlert] };
    default { format ["%1 notification(s), la plus récente en haut", count _all] };
}];
if ((count _all) > 0) then {
    private _fBtn = { params ["_label", "_k"]; [format ["%1 (%2)", _label, _counts get _k], compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['notifFilter', '%1']; [{ ['NOTIFS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _filter isEqualTo _k] };
    private _opts = [["TOUTES", "ALL"] call _fBtn];
    { if ((_counts get (_x select 1)) > 0) then { _opts pushBack (_x call _fBtn); }; } forEach [["MESSAGES", "MSG"], ["MISSIONS", "MISSION"], ["RÉSEAU", "NET"], ["ALERTES", "ALERT"], ["INFOS", "INFO"]];
    if ((count _opts) > 2) then { _rows pushBack ["segment", "", _opts]; };
};

// Heure relative : « à l'instant », « il y a 12 min », sinon l'heure du jeu.
private _ago = {
    params ["_n"];
    private _tk = _n getOrDefault ["tick", -1];
    if (_tk < 0) exitWith { _n getOrDefault ["time", ""] };
    private _dt = diag_tickTime - _tk;
    switch (true) do {
        case (_dt < 60): { "à l'instant" };
        case (_dt < 3600): { format ["il y a %1 min", floor (_dt / 60)] };
        default { _n getOrDefault ["time", ""] };
    }
};
{
    _x params ["_n", "_m", "_idx"];
    private _times = _n getOrDefault ["count", 1];
    private _new = _idx < _fresh;
    private _meta = format ["%1%2%3", ["", "<t color='#5cc76b'>●</t>  "] select _new, [_n] call _ago, ["", format ["  ·  ×%1", _times]] select (_times > 1)];
    private _page = _m get "page";
    private _open = if (_page isEqualTo "") then { {} } else { compile format ["[%1] call comspec_atak_native_fnc_navigate;", str _page] };
    // Fermer : par identifiant (fn_notify), à défaut par texte et heure (entrées d'avant la mise à jour).
    private _close = compile format ["
        private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap];
        private _id = %1; private _msg = %2; private _tm = %3;
        _s set ['notifHistory', (_s getOrDefault ['notifHistory', []]) select { if (_id >= 0) then { (_x getOrDefault ['id', -1]) isNotEqualTo _id } else { !((_x getOrDefault ['message', '']) isEqualTo _msg && {(_x getOrDefault ['time', '']) isEqualTo _tm}) } }];
        [{ ['NOTIFS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;",
        _n getOrDefault ["id", -1], str (_n getOrDefault ["message", ""]), str (_n getOrDefault ["time", ""])];
    _rows pushBack ["notif", _m get "icon", _m get "rgb", _m get "title", _meta, _m get "body", _open, _close,
        ["", format ["Ouvrir %1", _m get "title"]] select (_page isNotEqualTo "")];
} forEach _shown;

if ((count _all) isEqualTo 0) then {
    _rows pushBack ["empty", _data + "ui_bell.paa", "Tout est calme", "Les alertes, messages et comptes rendus des apps apparaîtront ici."];
} else {
    _rows pushBack ["gap"];
    private _ids = _shown apply { (_x select 0) getOrDefault ["id", -1] };
    _rows pushBack ["buttons", [[["TOUT EFFACER", format ["EFFACER CES %1", count _shown]] select (_filter isNotEqualTo "ALL"), compile format ["
        private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap];
        private _f = '%1'; private _ids = %2;
        _s set ['notifHistory', if (_f isEqualTo 'ALL') then { [] } else { (_s getOrDefault ['notifHistory', []]) select { !((_x getOrDefault ['id', -1]) in _ids) } }];
        _s set ['notifFilter', 'ALL'];
        [{ ['NOTIFS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _filter, _ids], false, true, "Les anomalies du mod restent affichées tant qu'elles ne sont pas corrigées"]]];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
