/*
    App STORE : le joueur installe et désinstalle les apps de son téléphone (remplace les bascules « cacher l'app » des
    Réglages). L'écran d'accueil et le dock ne montrent que les apps installées (fn_appVisible).
    Imposé par l'encadrement (fn_appInstall) : une app OBLIGATOIRE est installée et ne se désinstalle pas, une app
    INTERDITE ne s'installe pas et ne s'ouvre plus. Réglages, App Store et Alertes font partie du téléphone (SYSTÈME).
    Onglets (clé storeTab de l'état) : TOUT, INSTALLÉES, À INSTALLER, IMPOSÉES (si l'encadrement impose quelque chose).
    Recherche (clé storeQuery) sur le nom, la section et la description. Installation simulée en 1,2 s (clé storeBusy),
    désinstallation en deux appuis (clé storeConfirm).
    Params (app déclarée avec function=) : [page, [x, y, largeur, hauteur]]
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _re = "[{ ['STORE'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;";
private _setS = { params ["_k", "_v"]; compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set [%1, %2]; %3", str _k, str _v, _re] };
private _tab = _s getOrDefault ["storeTab", "ALL"];
private _query = _s getOrDefault ["storeQuery", ""];
private _busy = _s getOrDefault ["storeBusy", ""];
private _confirm = _s getOrDefault ["storeConfirm", ""];
private _acc = ([] call comspec_atak_native_fnc_accent) select 0;
private _athOff = ([] call comspec_atak_native_fnc_athenaMode) isEqualTo "off";
private _civilOff = !(missionNamespace getVariable ["comspec_atak_native_civil_apps", true]);
private _seen = profileNamespace getVariable ["COMSPEC_ATAK_AppSeen", []];
if !(_seen isEqualType []) then { _seen = []; };

// Admin (selon les droits) et Poste TOC (au PC de TOC) ne passent pas par le Store.
// Copies : l'état de chaque app (clé st) ne doit pas rester dans le cache de fn_appList.
private _apps = (([] call comspec_atak_native_fnc_appList) select { !((_x get "id") in ["Admin", "Toc"]) }) apply { +_x };
{ _x set ["st", ["state", _x get "id"] call comspec_atak_native_fnc_appInstall]; } forEach _apps;
private _isIn = { (_this get "st") in ["system", "required", "installed"] };
private _nIn = { _x call _isIn } count _apps;
private _nOut = { (_x get "st") isEqualTo "removed" } count _apps;
private _imposed = _apps select { (_x get "st") in ["required", "forbidden"] };

private _rows = [];
_rows pushBack ["strip", "<t font='COMSPEC_BarlowBold'>APP STORE</t>  <t color='#8a9a93'>COMSPEC ATAK</t>",
    format ["<t align='right' color='#8a9a93'>%1 installée(s) · %2 à installer</t>", _nIn, _nOut], [0.09, 0.12, 0.11, 1]];
if ((count _imposed) > 0) then {
    private _who = [];
    { { _who pushBackUnique _x; } forEach ((["source", _x get "id"] call comspec_atak_native_fnc_appInstall) splitString " +"); } forEach _imposed;
    _rows pushBack ["text", format ["<t size='0.85' color='#e8b84a'>%1 app(s) obligatoire(s) et %2 interdite(s) par l'encadrement (%3)</t>",
        { (_x get "st") isEqualTo "required" } count _imposed, { (_x get "st") isEqualTo "forbidden" } count _imposed, _who joinString ", "], [0.16, 0.13, 0.06, 0.9]];
};
private _search = {
    private _q = ["storeSearch"] call comspec_atak_native_fnc_formValue;
    if !(_q isEqualType "") then { _q = ""; };
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["storeQuery", trim _q];
    [{ ['STORE'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
_rows pushBack ["field", "storeSearch", "Rechercher une app", _query, "CHERCHER", _search, true];
if (_query isNotEqualTo "") then { _rows pushBack ["buttons", [["EFFACER LA RECHERCHE", ["storeQuery", ""] call _setS]]]; };
private _tabBtn = { params ["_t", "_k"]; [_t, ["storeTab", _k] call _setS, _tab isEqualTo _k] };
private _tabs = [
    [format ["TOUT (%1)", count _apps], "ALL"] call _tabBtn,
    [format ["INSTALLÉES (%1)", _nIn], "IN"] call _tabBtn,
    [format ["À INSTALLER (%1)", _nOut], "OUT"] call _tabBtn
];
if ((count _imposed) > 0) then { _tabs pushBack ([format ["IMPOSÉES (%1)", count _imposed], "POLICY"] call _tabBtn); };
if (_tab isEqualTo "POLICY" && {(count _imposed) isEqualTo 0}) then { _tab = "ALL"; };
_rows pushBack ["segment", "", _tabs];

private _list = switch (_tab) do {
    case "IN": { _apps select { _x call _isIn } };
    case "OUT": { _apps select { (_x get "st") in ["removed", "forbidden"] } };
    case "POLICY": { _imposed };
    default { _apps };
};
if (_query isNotEqualTo "") then {
    private _q = toLower _query;
    _list = _list select {
        private _t = toLower ([_x get "name", _x get "section", _x get "desc"] joinString " ");
        (_t find _q) >= 0 || {((toLower ([_x get "name", _x get "section", _x get "desc"] call comspec_atak_native_fnc_tr) joinString " ") find _q) >= 0}
    };
};

// Une ligne d'app : icône, nom et pastilles, description, bouton selon l'état.
private _row = {
    private _a = _this;
    private _id = _a get "id";
    private _st = _a get "st";
    private _page = _a get "page";
    private _wip = _a getOrDefault ["wip", false];
    private _src = ["source", _id] call comspec_atak_native_fnc_appInstall;
    // Indisponible ici quel que soit le choix du joueur (service Athéna refusé, apps civiles coupées par le serveur).
    private _why = switch (true) do {
        case (_athOff && {_id in ["Athena", "Resynch", "Discord", "Wanted"]}): { "Demande le service Athéna (Réglages > Athéna)" };
        case (_civilOff && {(_a get "section") isEqualTo "Civil"}): { "Apps civiles coupées par le serveur" };
        default { "" };
    };
    private _tags = [];
    switch (_st) do {
        case "system": { _tags pushBack "<t size='0.7' color='#8a9a93'>SYSTÈME</t>"; };
        case "required": { _tags pushBack "<t size='0.7' color='#e8b84a'>OBLIGATOIRE</t>"; };
        case "forbidden": { _tags pushBack "<t size='0.7' color='#e5483a'>INTERDITE</t>"; };
        case "installed": { _tags pushBack "<t size='0.7' color='#5cc76b'>INSTALLÉE</t>"; };
    };
    if ((_a getOrDefault ["new", false]) && {!(_page in _seen)}) then { _tags pushBack "<t size='0.7' color='#f2ab33'>NOUVEAU</t>"; };
    if (_wip) then { _tags pushBack "<t size='0.7' color='#d99a1f'>W.I.P</t>"; };
    private _sub = [_a get "section"];
    if ((_a get "desc") isNotEqualTo "") then { _sub pushBack (_a get "desc"); };
    if (_src isNotEqualTo "") then { _sub pushBack format ["imposé par : %1", _src]; };
    if (_why isNotEqualTo "") then { _sub pushBack _why; };
    private _txt = format ["<t font='COMSPEC_BarlowBold'>%1</t>  %2<br/><t size='0.78' color='#8a9a93'>%3</t>", _a get "name", _tags joinString " ", _sub joinString " · "];
    private _open = ["OUVRIR", compile format ["[%1] call comspec_atak_native_fnc_navigate;", str _page], true, !_wip && {_why isEqualTo ""}, "Ouvre l'app"];
    private _btns = switch (true) do {
        case (_busy isEqualTo _id): { [["INSTALLATION…", {}, false, false, "Installation en cours"]] };
        case (_st isEqualTo "forbidden"): { [["INTERDITE", {}, false, false, format ["Interdite par l'encadrement (%1)", _src]]] };
        case (_st in ["system", "required"]): { [_open] };
        case (_st isEqualTo "removed"): {
            // Installation : 1,2 s de chargement, puis l'app arrive sur l'écran d'accueil.
            [["INSTALLER", compile format ["
                private _st = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap];
                if ((_st getOrDefault ['storeBusy', '']) isNotEqualTo '') exitWith {};
                _st set ['storeBusy', %1]; %2
                [{
                    (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['storeBusy', ''];
                    if (['install', _this select 0] call comspec_atak_native_fnc_appInstall) then { [(_this select 1) + ' installée', 2] call comspec_atak_native_fnc_toast; };
                }, [%1, %3], 1.2] call CBA_fnc_waitAndExecute;", str _id, _re, str (_a get "name")], true, _busy isEqualTo "" && {_why isEqualTo ""}, "Ajoute l'app à l'écran d'accueil"]]
        };
        default {
            // Désinstallation en deux appuis : le premier arme, le second retire l'app de l'écran d'accueil.
            private _rm = if (_confirm isEqualTo _id) then {
                ["CONFIRMER", compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['storeConfirm', '']; if (['remove', %1] call comspec_atak_native_fnc_appInstall) then { [%2 + ' désinstallée', 2] call comspec_atak_native_fnc_toast; };", str _id, str (_a get "name")], "red", true, "Retire l'app de l'écran d'accueil et du dock (réinstallable à tout moment)"]
            } else {
                ["DÉSINSTALLER", ["storeConfirm", _id] call _setS, false, true, "Retire l'app de l'écran d'accueil et du dock"]
            };
            [_open, _rm]
        };
    };
    private _tint = [[1, 1, 1, 1], [0.45, 0.48, 0.46, 1]] select (_st in ["removed", "forbidden"] || {_why isNotEqualTo ""});
    ["person", _a get "icon", _txt, _btns, _tint]
};

if ((count _list) isEqualTo 0) then {
    _rows pushBack (switch (true) do {
        case (_query isNotEqualTo ""): { ["empty", "\z\comspec_atak_native\addons\main\data\app_store.paa", "Aucune app trouvée", "Essayez un autre mot"] };
        case (_tab isEqualTo "OUT"): { ["empty", "\z\comspec_atak_native\addons\main\data\ui_check.paa", "Tout est installé", "Désinstallez ce qui ne vous sert pas pour alléger l'écran d'accueil"] };
        default { ["empty", "\z\comspec_atak_native\addons\main\data\app_store.paa", "Aucune app", ""] };
    });
} else {
    // Nouveautés en tête (onglet TOUT, sans recherche), puis par section.
    if (_tab isEqualTo "ALL" && {_query isEqualTo ""}) then {
        private _new = _list select { (_x getOrDefault ["new", false]) && {!((_x get "page") in _seen)} && {(_x get "st") isNotEqualTo "forbidden"} };
        if ((count _new) > 0) then {
            _rows pushBack ["section", "Nouveautés", "Apps arrivées avec les dernières versions du téléphone"];
            { _rows pushBack (_x call _row); } forEach _new;
        };
    };
    private _sections = [];
    { _sections pushBackUnique (_x get "section"); } forEach _list;
    {
        private _sec = _x;
        private _in = _list select { (_x get "section") isEqualTo _sec };
        _rows pushBack ["section", _sec, format ["%1 app(s) · %2 installée(s)", count _in, { _x call _isIn } count _in]];
        { _rows pushBack (_x call _row); } forEach _in;
    } forEach _sections;
};
if (_nOut > 0) then {
    _rows pushBack ["gap"];
    _rows pushBack ["buttons", [["TOUT RÉINSTALLER", compile format ["private _n = ['installAll'] call comspec_atak_native_fnc_appInstall; [(str _n) + ' app(s) réinstallée(s)', 2] call comspec_atak_native_fnc_toast; %1", _re], false, true, "Remet toutes les apps permises sur l'écran d'accueil"]]];
};
_rows pushBack ["text", "<t size='0.75' color='#8a9a93'>Désinstaller une app la retire seulement de l'écran d'accueil et du dock : ce qu'elle fait en fond (BFT, messages reçus, alertes) continue. Vos choix sont gardés d'une partie à l'autre.</t>"];

[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
