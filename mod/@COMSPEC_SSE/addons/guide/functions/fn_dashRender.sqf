/*
    Tableau de bord SSE : dessine un onglet (comspec_sse_fnc_openDashboard) à partir du relevé Eden
    (comspec_sse_fnc_dashCollect, variable d'écran comspec_sse_dashModel) et des données du site
    (comspec_sse_fnc_dashSite, uiNamespace comspec_sse_dashSite). Sans onglet donné : l'onglet ouvert est redessiné.
    [_display, _tab] call comspec_sse_fnc_dashRender
*/
params [["_d", displayNull, [displayNull]], ["_tab", "", [""]]];
if (isNull _d) exitWith {};
disableSerialization;
if (_tab isEqualTo "") then { _tab = _d getVariable ["comspec_sse_dashTab", "overview"]; };
_d setVariable ["comspec_sse_dashTab", _tab];
{ ctrlDelete _x; } forEach (_d getVariable ["comspec_sse_dashCtrls", []]);
private _ctrls = [];
_d setVariable ["comspec_sse_dashCtrls", _ctrls];
_d setVariable ["comspec_sse_dashDetailCtl", []];
(_d getVariable ["comspec_sse_dashArea", [0, 0, 1, 1, 0.006]]) params ["_ax", "_ay", "_aw", "_ah", "_u"];
{
    _x params ["_id", "_b"];
    _b ctrlSetTextColor ([[0.78, 0.8, 0.82, 1], [0.5, 0.86, 0.56, 1]] select (_id isEqualTo _tab));
    _b ctrlSetBackgroundColor ([[0.2, 0.22, 0.21, 1], [0.1, 0.3, 0.16, 1]] select (_id isEqualTo _tab));
} forEach (_d getVariable ["comspec_sse_dashTabs", []]);

private _m = _d getVariable ["comspec_sse_dashModel", createHashMap];
private _site = uiNamespace getVariable ["comspec_sse_dashSite", createHashMap];
private _ents = _m getOrDefault ["entities", createHashMap];
private _order = _m getOrDefault ["order", []];
private _links = _m getOrDefault ["links", []];
private _orgs = _m getOrDefault ["orgs", createHashMap];
private _roleLabels = _m getOrDefault ["roleLabels", createHashMap];
private _kindLabels = _m getOrDefault ["kindLabels", createHashMap];
private _threatLabels = _m getOrDefault ["threatLabels", createHashMap];
private _c = _site getOrDefault ["campaign", createHashMap];
private _sRoster = _c getOrDefault ["roster", createHashMap];
private _sMembers = _c getOrDefault ["members", createHashMap];
private _sOrgs = _c getOrDefault ["orgs", createHashMap];
private _sCustody = _c getOrDefault ["custody", createHashMap];
private _wanted = _site getOrDefault ["wanted", []];
private _known = ["non identifié", "soupçonné", "confirmé"];
private _typeLabels = createHashMapFromArray [["permanente", "permanente"], ["recurrente", "récurrente (drills)"], ["temporaire", "temporaire (drill jetable)"]];
private _ranks = createHashMapFromArray [
    ["chef", 0], ["adjoint", 1], ["chefcellule", 2], ["financier", 3], ["logisticien", 3], ["artificier", 3], ["recruteur", 3],
    ["propagande", 3], ["transmissions", 3], ["courrier", 4], ["guetteur", 4], ["combattant", 4], ["membre", 4],
    ["facilitateur", 5], ["sympathisant", 5]
];

// ---------------------------------------------------------------- outils
private _esc = {
    params ["_s"];
    if !(_s isEqualType "") then { _s = str _s; };
    _s = [_s, "&", "&amp;"] call CBA_fnc_replace;
    _s = [_s, "<", "&lt;"] call CBA_fnc_replace;
    [_s, ">", "&gt;"] call CBA_fnc_replace
};
private _hd = { params ["_s"]; format ["<t color='#7fd08a' font='RobotoCondensedBold' size='1.1'>%1</t>", _s] };
private _dim = { params ["_s"]; format ["<t color='#8a96a3'>%1</t>", _s] };
private _str = { params ["_mp", "_k"]; private _v = _mp getOrDefault [_k, ""]; if (_v isEqualType "") then { trim _v } else { str _v } };
private _slug = { params ["_s"]; "ORG-" + (toUpper (((trim _s) splitString " '-_/.,:;") joinString "")) };
private _cap = 400;
private _ref = {
    params ["_r"];
    private _v = _r select [2];
    switch (_r select [0, 2]) do {
        case "e:": { [((_ents getOrDefault [parseNumber _v, createHashMap]) getOrDefault ["label", "#" + _v])] call _esc };
        case "o:": { format ["<t color='#e0b050'>%1</t>", [(_orgs getOrDefault [_v, createHashMap]) getOrDefault ["name", ((_sOrgs getOrDefault [_v, createHashMap]) getOrDefault ["name", _v])]] call _esc] };
        default { format ["<t color='#8a96a3'>« %1 »</t>", [_v] call _esc] };
    }
};
private _ctl = {
    params ["_cls", "_pos", ["_parent", controlNull]];
    private _ctrl = if (isNull _parent) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _parent] };
    _ctrl ctrlSetPosition _pos;
    _ctrl ctrlCommit 0;
    if (isNull _parent) then { _ctrls pushBack _ctrl; };
    _ctrl
};
private _setText = {
    params ["_t", "_tw", "_html"];
    _t ctrlSetStructuredText parseText (("<t size='0.92'>" + _html + "</t>") call comspec_sse_fnc_tr);
    _t ctrlSetPosition [0, 0, _tw, 1];
    _t ctrlCommit 0;
    _t ctrlSetPosition [0, 0, _tw, (ctrlTextHeight _t) + 2 * _u];
    _t ctrlCommit 0;
};
private _box = {
    params ["_pos", "_html"];
    private _g = ["ctrlControlsGroup", _pos] call _ctl;
    private _t = ["ctrlStructuredText", [0, 0, 1, 1], _g] call _ctl;
    private _tw = (_pos select 2) - 3 * _u;
    [_t, _tw, _html] call _setText;
    [_t, _tw]
};
private _detailBox = {
    params ["_pos"];
    (_this + [""]) call _box params ["_t", "_tw"];
    _d setVariable ["comspec_sse_dashDetailCtl", [_t, _tw, _u]];
};
_d setVariable ["comspec_sse_dashShow", {
    params ["_d", "_ref"];
    (_d getVariable ["comspec_sse_dashDetailCtl", []]) params ["_t", "_tw", "_u"];
    if (isNil "_t" || {isNull _t}) exitWith {};
    private _html = (_d getVariable ["comspec_sse_dashDetail", createHashMap]) getOrDefault [_ref, ""];
    _t ctrlSetStructuredText parseText (("<t size='0.92'>" + _html + "</t>") call comspec_sse_fnc_tr);
    _t ctrlSetPosition [0, 0, _tw, 1];
    _t ctrlCommit 0;
    _t ctrlSetPosition [0, 0, _tw, (ctrlTextHeight _t) + 2 * _u];
    _t ctrlCommit 0;
    (ctrlParentControlsGroup _t) ctrlSetScrollValues [0, -1];
    _d setVariable ["comspec_sse_dashSel", _ref];
}];
private _selectBtn = {
    params ["_pos"];
    private _b = ["ctrlButton", _pos] call _ctl;
    _b ctrlSetText (("Sélectionner dans Eden") call comspec_sse_fnc_tr);
    _b ctrlSetTooltip (("Ferme le tableau de bord, sélectionne l'entité et y amène la caméra.") call comspec_sse_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", {
        private _d = ctrlParent (_this select 0);
        private _ref = _d getVariable ["comspec_sse_dashSel", ""];
        if ((_ref select [0, 2]) isNotEqualTo "e:") exitWith {};
        private _e = ((((_d getVariable ["comspec_sse_dashModel", createHashMap]) getOrDefault ["entities", createHashMap]) getOrDefault [parseNumber (_ref select [2]), createHashMap]) getOrDefault ["ent", objNull]);
        if (isNull _e) exitWith {};
        _d closeDisplay 1;
        set3DENSelected [_e];
        move3DENCamera [getPos _e, true];
    }];
};

// Personne de la carte → clé dans la campagne du site (identifiant fixé, sinon nom ou alias).
private _sName = {
    params ["_k"];
    private _n = (_sRoster getOrDefault [_k, createHashMap]) getOrDefault ["name", ""];
    if (_n isEqualTo "") then { _n = (_sMembers getOrDefault [_k, createHashMap]) getOrDefault ["name", ""]; };
    [_n, _k] select (_n isEqualTo "")
};
private _siteByName = createHashMap;
{
    private _n = toLower ([_x] call _sName);
    _siteByName set [_n, _x];
    private _w = _n splitString " ";
    if ((count _w) >= 2) then { _siteByName set [((_w select [1]) + [_w select 0]) joinString " ", _x]; };
    private _a = toLower ((_sRoster getOrDefault [_x, createHashMap]) getOrDefault ["alias", ((_sMembers getOrDefault [_x, createHashMap]) getOrDefault ["alias", ""])]);
    if (_a isNotEqualTo "") then { _siteByName set [_a, _x]; };
} forEach ((keys _sRoster) + (keys _sMembers));
private _siteKeyOf = {
    params ["_p"];
    private _k = _p getOrDefault ["key", ""];
    if (_k isNotEqualTo "" && {_k in _sRoster || {_k in _sMembers}}) exitWith { _k };
    private _n = toLower (_p getOrDefault ["name", ""]);
    if (_n isNotEqualTo "" && {_n in _siteByName}) exitWith { _siteByName get _n };
    private _a = toLower (_p getOrDefault ["alias", ""]);
    if (_a isEqualTo "") exitWith { "" };
    _siteByName getOrDefault [_a, ""]
};
// Avis de recherche du site : nom ou alias → ligne [nature, id, réf., nom, alias, niveau, détails…].
private _wantedBy = createHashMap;
{
    private _n = toLower (trim (_x param [3, ""]));
    if (_n isNotEqualTo "") then {
        _wantedBy set [_n, _x];
        private _w = _n splitString " ";
        if ((count _w) >= 2) then { _wantedBy set [((_w select [1]) + [_w select 0]) joinString " ", _x]; };
    };
    private _a = toLower (trim (_x param [4, ""]));
    if (_a isNotEqualTo "") then { _wantedBy set [_a, _x]; };
} forEach _wanted;
private _wantedOf = {
    params ["_p"];
    private _r = _wantedBy getOrDefault [toLower (_p getOrDefault ["name", ""]), []];
    if (_r isEqualTo [] && {(_p getOrDefault ["alias", ""]) isNotEqualTo ""}) then { _r = _wantedBy getOrDefault [toLower (_p get "alias"), []]; };
    if ((_p getOrDefault ["name", ""]) isEqualTo "" && {(_p getOrDefault ["alias", ""]) isEqualTo ""}) then { _r = []; };
    _r
};
private _kinds = createHashMapFromArray [["person", "personne"], ["object", "objet"], ["module", "module"]];
private _people = _order select { ((_ents get _x) get "kind") isEqualTo "person" };
private _things = _order select { ((_ents get _x) get "kind") isEqualTo "object" };
private _mods = _order select { ((_ents get _x) get "kind") isEqualTo "module" };

// Fiche d'une personne de la campagne du site.
private _siteHtml = {
    params ["_k"];
    private _r = _sRoster getOrDefault [_k, createHashMap];
    private _mm = _sMembers getOrDefault [_k, createHashMap];
    private _l = [];
    private _al = _r getOrDefault ["alias", _mm getOrDefault ["alias", ""]];
    if (_al isNotEqualTo "") then { _l pushBack format ["Alias : %1", [_al] call _esc]; };
    { _x params ["_lbl", "_v"]; if (_v isNotEqualTo "") then { _l pushBack format ["%1 : %2", _lbl, [_v] call _esc]; }; } forEach [
        ["Nationalité", [_r, "nationality"] call _str], ["Statut", [_r, "status"] call _str], ["Vu en dernier", [_r, "lastSeen"] call _str]
    ];
    if (count _mm > 0) then {
        private _role = [_mm, "role"] call _str;
        _l pushBack format ["Organisation : %1 · %2", [format ["o:%1", [_mm, "org"] call _str]] call _ref, _roleLabels getOrDefault [_role, _role]];
        private _kv = _mm getOrDefault ["known", 0];
        _l pushBack format ["Connu des joueurs : %1", _known select ((([0, _kv] select (_kv isEqualType 0)) max 0) min 2)];
        if (([_mm, "how"] call _str) isNotEqualTo "") then { _l pushBack format ["Comment : %1", [[_mm, "how"] call _str] call _esc]; };
    };
    _l pushBack ([format ["Clé de campagne : %1%2", _k, ["", " · personnage récurrent"] select (count _r > 0)]] call _dim);
    _l joinString "<br/>"
};
// Fiche d'une entité de la carte.
private _entHtml = {
    params ["_id"];
    private _p = _ents get _id;
    private _e = _p get "ent";
    private _l = [format ["<t size='1.2' font='RobotoCondensedBold'>%1</t>", [_p get "label"] call _esc]];
    _l pushBack ([format ["%1 · %2%3", _kinds getOrDefault [_p get "kind", ""], _p get "type", [format [" · grille %1", mapGridPosition _e], ""] select (isNull _e)]] call _dim);
    private _cat = "#";
    {
        _x params ["_ct", "_lbl", "_v"];
        if (_ct isNotEqualTo _cat) then {
            _cat = _ct;
            _l pushBack "";
            _l pushBack ([[_ct, "Réglages du module"] select (_ct isEqualTo "")] call _hd);
        };
        _l pushBack format ["%1 : %2", [_lbl] call _esc, [_v] call _esc];
    } forEach (_p get "attrs");
    if ((_p get "attrs") isEqualTo []) then { _l pushBack ""; _l pushBack (["Aucun attribut SSE : objet relié à un module SSE."] call _dim); };
    private _sy = (_p get "syncs") select { _x in _ents };
    if (_sy isNotEqualTo []) then {
        _l pushBack "";
        _l pushBack (["Synchronisé avec"] call _hd);
        { _l pushBack ("· " + (["e:" + str _x] call _ref)); } forEach _sy;
    };
    private _me = "e:" + str _id;
    private _mine = _links select { (_x select 0) isEqualTo _me || {(_x select 2) isEqualTo _me} };
    if (_mine isNotEqualTo []) then {
        _l pushBack "";
        _l pushBack ([format ["Liaisons (%1)", count _mine]] call _hd);
        {
            _x params ["_a", "_rel", "_b", "_src"];
            _l pushBack format ["· %1 <t color='#8a96a3'>%2</t> %3", [_a] call _ref, [_rel] call _esc, [_b] call _ref];
        } forEach (_mine select [0, 60]);
    };
    if ((_p get "kind") isEqualTo "person") then {
        private _k = [_p] call _siteKeyOf;
        if (_k isNotEqualTo "") then {
            _l pushBack "";
            _l pushBack ([format ["Dans la campagne du site (%1)", _site getOrDefault ["key", ""]]] call _hd);
            _l pushBack ([_k] call _siteHtml);
        };
        private _wr = [_p] call _wantedOf;
        if (_wr isNotEqualTo []) then {
            _l pushBack "";
            _l pushBack (["<t color='#e06060'>Avis de recherche sur le site</t>"] call _hd);
            _l pushBack format ["%1 · niveau %2 · %3", [_wr param [3, ""]] call _esc, [_wr param [5, ""]] call _esc, [_wr param [2, ""]] call _esc];
            if ((_wr param [6, ""]) isNotEqualTo "") then { _l pushBack ([[_wr param [6, ""]] call _esc] call _dim); };
        };
    };
    _l joinString "<br/>"
};

// Organisations : carte et campagne du site fusionnées par identifiant (ORG-…).
private _morgs = createHashMap;
{
    private _oid = _x;
    private _o = createHashMapFromArray [
        ["id", _oid], ["name", _y get "name"], ["kind", _y get "kind"], ["parent", _y get "parent"], ["threat", _y get "threat"],
        ["desc", _y get "desc"], ["known", _y get "known"], ["eden", true], ["site", false], ["members", []], ["assets", +(_y get "assets")]
    ];
    {
        _x params ["_pid", "_role", "_cell", "_alias"];
        private _p = _ents get _pid;
        private _sk = [_p] call _siteKeyOf;
        private _both = _sk isNotEqualTo "" && {((_sMembers getOrDefault [_sk, createHashMap]) getOrDefault ["org", ""]) isEqualTo _oid};
        (_o get "members") pushBack [_p get "label", _role, _cell, ["Eden", "Eden + site"] select _both, "e:" + str _pid, _sk];
    } forEach (_y get "members");
    _morgs set [_oid, _o];
} forEach _orgs;
{
    private _oid = _x;
    private _so = _y;
    if !(_oid in _morgs) then {
        _morgs set [_oid, createHashMapFromArray [
            ["id", _oid], ["name", [_so, "name"] call _str], ["kind", ""], ["parent", ""], ["threat", ""], ["desc", ""], ["known", 0],
            ["eden", false], ["site", true], ["members", []], ["assets", []]
        ]];
    };
    private _o = _morgs get _oid;
    _o set ["site", true];
    { if ((_o get _x) in ["", 0]) then { _o set [_x, _so getOrDefault [_x, _o get _x]]; }; } forEach ["kind", "parent", "threat", "desc", "known"];
} forEach _sOrgs;
private _orphans = [];
{
    private _k = _x;
    private _oid = [_y, "org"] call _str;
    if (_oid in _morgs) then {
        private _mem = (_morgs get _oid) get "members";
        if ((_mem findIf { (_x select 5) isEqualTo _k }) < 0) then {
            private _al = [_y, "alias"] call _str;
            _mem pushBack [([_k] call _sName) + ([format [" « %1 »", _al], ""] select (_al isEqualTo "")), [_y, "role"] call _str, "", "site", "s:" + _k, _k];
        };
    };
} forEach _sMembers;
{ if !(_x in _sMembers) then { _orphans pushBack _x; }; } forEach _sRoster;
private _parentOf = {
    params ["_o"];
    private _p = _o get "parent";
    if !(_p isEqualType "") exitWith { "" };
    if (_p in _morgs) exitWith { _p };
    if (_p isEqualTo "") exitWith { "" };
    [_p] call _slug
};
private _orgHtml = {
    params ["_o"];
    private _l = [format ["<t size='1.2' font='RobotoCondensedBold'>%1</t>", [_o get "name"] call _esc]];
    _l pushBack ([format ["%1 · %2", _o get "id", ["", "carte Eden", "campagne du site", "carte Eden et campagne du site"] select (([0, 1] select (_o get "eden")) + ([0, 2] select (_o get "site")))]] call _dim);
    _l pushBack "";
    { _x params ["_lbl", "_v"]; if (_v isNotEqualTo "") then { _l pushBack format ["%1 : %2", _lbl, [_v] call _esc]; }; } forEach [
        ["Nature", _kindLabels getOrDefault [_o get "kind", _o get "kind"]], ["Menace", _threatLabels getOrDefault [_o get "threat", _o get "threat"]],
        ["Connue des joueurs", _known select ((([0, _o get "known"] select ((_o get "known") isEqualType 0)) max 0) min 2)],
        ["Organisation parente", [((_morgs getOrDefault [[_o] call _parentOf, createHashMap]) getOrDefault ["name", _o get "parent"]), ""] select ((_o get "parent") isEqualTo "")]
    ];
    if ((_o get "desc") isNotEqualTo "") then { _l pushBack ""; _l pushBack ([_o get "desc"] call _esc); };
    _l pushBack "";
    _l pushBack ([format ["Membres (%1)", count (_o get "members")]] call _hd);
    { _l pushBack format ["· %1 : %2 <t color='#8a96a3'>[%3]</t>", _roleLabels getOrDefault [_x select 1, _x select 1], [_x select 0] call _esc, _x select 3]; } forEach (_o get "members");
    if ((_o get "assets") isNotEqualTo []) then {
        _l pushBack "";
        _l pushBack ([format ["Objets rattachés (%1)", count (_o get "assets")]] call _hd);
        { _l pushBack ("· " + (["e:" + str _x] call _ref)); } forEach (_o get "assets");
    };
    _l joinString "<br/>"
};

// ---------------------------------------------------------------- barre d'état (site)
private _state = _site getOrDefault ["state", ""];
private _stTxt = switch (_state) do {
    case "busy": { format ["<t color='#e0b050'>Site : %1</t>", _site getOrDefault ["detail", ""]] };
    case "ok": {
        format ["<t color='#7fd08a'>Site : relié</t> <t color='#8a96a3'>· %1 campagne(s) · campagne lue : %2 · lu à %3</t>", count (_site getOrDefault ["campaigns", []]),
            [_site getOrDefault ["key", ""], "aucune"] select ((_site getOrDefault ["key", ""]) isEqualTo ""), _site getOrDefault ["at", ""]]
    };
    case "": { "<t color='#8a96a3'>Site : pas encore lu</t>" };
    default { format ["<t color='#e06060'>Site : %1</t>", [_site getOrDefault ["detail", ""]] call _esc] };
};
(_d getVariable ["comspec_sse_dashStatus", controlNull]) ctrlSetStructuredText parseText ((_stTxt) call comspec_sse_fnc_tr);

private _detail = createHashMap;
_d setVariable ["comspec_sse_dashDetail", _detail];

// ---------------------------------------------------------------- onglets
switch (_tab) do {
    case "overview": {
        (_m getOrDefault ["mission", ["", "", "", ""]]) params ["_file", "_world", "_title", "_author"];
        private _l = [["Mission"] call _hd];
        { _x params ["_lbl", "_v"]; if (_v isNotEqualTo "") then { _l pushBack format ["%1 : %2", _lbl, [_v] call _esc]; }; } forEach [["Fichier", _file], ["Carte", _world], ["Titre", _title], ["Auteur", _author]];
        _l pushBack "";
        _l pushBack (["Contenu SSE de la carte"] call _hd);
        _l pushBack format ["Personnes : %1", count _people];
        _l pushBack format ["Objets : %1", count _things];
        _l pushBack format ["Modules : %1", count _mods];
        _l pushBack format ["Organisations : %1", count _orgs];
        _l pushBack format ["Liaisons : %1", count _links];
        _l pushBack format ["Réseaux : %1", count (_m getOrDefault ["networks", createHashMap])];
        _l pushBack "";
        _l pushBack (["Campagne"] call _hd);
        private _cid = _m getOrDefault ["campaignId", ""];
        private _cm = _mods select { ((_ents get _x) get "type") isEqualTo "COMSPEC_SSE_Module_Campaign" };
        if (_cm isEqualTo []) then { _l pushBack "Aucun module Campagne : partie autonome."; } else {
            private _raw = (_ents get (_cm select 0)) get "raw";
            _l pushBack format ["Module Campagne : %1 · %2", [[_cid, "sans identifiant"] select (_cid isEqualTo "")] call _esc, _typeLabels getOrDefault [[_raw, "Type"] call _str, [_raw, "Type"] call _str]];
        };
        _l pushBack "";
        _l pushBack (["Site Athena"] call _hd);
        switch (_state) do {
            case "ok": {
                _l pushBack format ["%1 campagne(s) de la communauté sur le site.", count (_site getOrDefault ["campaigns", []])];
                if (count _c > 0) then {
                    _l pushBack format ["Campagne lue : %1 (%2)", [[_c, "name"] call _str] call _esc, _site getOrDefault ["key", ""]];
                    _l pushBack format ["Personnages : %1 · organisations : %2 · pièces saisies : %3", count (((keys _sRoster) + (keys _sMembers)) arrayIntersect ((keys _sRoster) + (keys _sMembers))), count _sOrgs, count _sCustody];
                } else {
                    if ((_site getOrDefault ["campaignError", ""]) isNotEqualTo "") then { _l pushBack format ["<t color='#e06060'>Campagne : %1</t>", [_site get "campaignError"] call _esc]; } else { _l pushBack "Aucune campagne lue."; };
                };
                _l pushBack format ["Avis de recherche : %1", count _wanted];
            };
            case "busy": { _l pushBack format ["<t color='#e0b050'>%1</t>", _site getOrDefault ["detail", ""]]; };
            case "": { _l pushBack "Pas encore lu : bouton Actualiser depuis le site."; };
            default { _l pushBack format ["<t color='#e06060'>%1</t>", [_site getOrDefault ["detail", ""]] call _esc]; };
        };
        [[_ax, _ay, _aw * 0.48, _ah], _l joinString "<br/>"] call _box;

        // Contrôles : ceux du relevé, puis ceux qui croisent la carte et le site.
        private _checks = +(_m getOrDefault ["checks", []]);
        if (_state isEqualTo "ok") then {
            private _key = toUpper (((trim _cid) splitString " '/\.,:;") joinString "-");
            if (_cid isNotEqualTo "" && {((_site getOrDefault ["campaigns", []]) findIf { (_x select 0) isEqualTo _key }) < 0}) then {
                _checks pushBack [0, format ["Campagne « %1 » pas encore sur le site : elle y sera copiée à la première sauvegarde d'un serveur relié.", _cid]];
            };
            if (count _c > 0 && {_cm isNotEqualTo []} && {(_site getOrDefault ["key", ""]) isEqualTo _key}) then {
                private _et = [(_ents get (_cm select 0)) get "raw", "Type"] call _str;
                private _st = [_c, "type"] call _str;
                if (_et isNotEqualTo "" && {_st isNotEqualTo ""} && {_et isNotEqualTo _st}) then {
                    _checks pushBack [1, format ["Type de campagne différent : %1 dans Eden, %2 sur le site (le type déjà enregistré l'emporte).", _typeLabels getOrDefault [_et, _et], _typeLabels getOrDefault [_st, _st]]];
                };
            };
            {
                private _wr = [_ents get _x] call _wantedOf;
                if (_wr isNotEqualTo []) then { _checks pushBack [1, format ["%1 fait l'objet d'un avis de recherche sur le site (niveau %2).", (_ents get _x) get "label", _wr param [5, ""]]]; };
            } forEach _people;
            private _matched = _people apply { [_ents get _x] call _siteKeyOf };
            private _absent = ((keys _sRoster) select { !(_x in _matched) });
            if (_absent isNotEqualTo []) then { _checks pushBack [0, format ["%1 personnage(s) récurrent(s) de la campagne absent(s) de la carte (voir Organigramme).", count _absent]]; };
        };
        private _r = [format ["Contrôles (%1)", count _checks] call _hd];
        {
            _x params ["_lvl", "_txt"];
            _r pushBack format ["<t color='%1'>%2</t> %3", ["#8a96a3", "#e0b050", "#e06060"] select ((_lvl max 0) min 2), ["Info", "Attention", "Erreur"] select ((_lvl max 0) min 2), [_txt] call _esc];
        } forEach ([_checks, [], { 2 - (_x select 0) }, "ASCEND"] call BIS_fnc_sortBy);
        [[_ax + _aw * 0.5, _ay, _aw * 0.5, _ah], _r joinString "<br/>"] call _box;
    };

    case "entities": {
        private _flt = _d getVariable ["comspec_sse_dashFilter", "all"];
        private _lw = _aw * 0.34;
        private _cb = ["ctrlCombo", [_ax, _ay, _lw, 4.5 * _u]] call _ctl;
        private _opts = [["all", "Toutes les entités"], ["person", "Personnes"], ["object", "Objets"], ["module", "Modules"]];
        { private _i = _cb lbAdd (((_x select 1)) call comspec_sse_fnc_tr); _cb lbSetData [_i, _x select 0]; } forEach _opts;
        _cb lbSetCurSel ((_opts findIf { (_x select 0) isEqualTo _flt }) max 0);
        _cb ctrlAddEventHandler ["LBSelChanged", {
            params ["_cb", "_i"];
            private _d = ctrlParent _cb;
            _d setVariable ["comspec_sse_dashFilter", _cb lbData _i];
            [_d, "entities"] spawn { uiSleep 0.01; _this call comspec_sse_fnc_dashRender; };
        }];
        private _lb = ["ctrlListbox", [_ax, _ay + 5.5 * _u, _lw, _ah - 5.5 * _u]] call _ctl;
        private _icons = createHashMapFromArray [
            ["person", "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa"], ["object", "\a3\ui_f\data\igui\cfg\simpleTasks\types\box_ca.paa"],
            ["module", "\a3\ui_f\data\igui\cfg\simpleTasks\types\whiteboard_ca.paa"]
        ];
        private _shown = _order select { _flt isEqualTo "all" || {((_ents get _x) get "kind") isEqualTo _flt} };
        {
            private _p = _ents get _x;
            private _i = _lb lbAdd (_p get "label");
            _lb lbSetData [_i, "e:" + str _x];
            _lb lbSetPicture [_i, _icons getOrDefault [_p get "kind", ""]];
            _lb lbSetTooltip [_i, _p get "type"];
            _detail set ["e:" + str _x, [_x] call _entHtml];
        } forEach _shown;
        [[_ax + _lw + 2 * _u, _ay, _aw - _lw - 2 * _u, _ah - 6.5 * _u]] call _detailBox;
        [[_ax + _aw - _aw * 0.2, _ay + _ah - 5 * _u, _aw * 0.2, 5 * _u]] call _selectBtn;
        _lb ctrlAddEventHandler ["LBSelChanged", {
            params ["_lb", "_i"];
            private _d = ctrlParent _lb;
            [_d, _lb lbData _i] call (_d getVariable "comspec_sse_dashShow");
        }];
        if (_shown isEqualTo []) then {
            _detail set ["", "Aucune entité SSE de ce type sur la carte."];
            [_d, ""] call (_d getVariable "comspec_sse_dashShow");
        } else {
            private _sel = (_shown findIf { ("e:" + str _x) isEqualTo (_d getVariable ["comspec_sse_dashSel", ""]) }) max 0;
            _lb lbSetCurSel _sel;
        };
        ctrlSetFocus _lb;
    };

    case "links": {
        private _l = [];
        if (_links isEqualTo []) then {
            _l pushBack "Aucune liaison : synchronisez des entités à un module Lier des entités ou Organisation, ou renseignez les attributs Organisation, Biométrie et contenu des objets.";
        } else {
            private _srcs = [];
            { _srcs pushBackUnique (_x select 3); } forEach _links;
            private _n = 0;
            {
                private _src = _x;
                private _sl = _links select { (_x select 3) isEqualTo _src };
                _l pushBack ([format ["%1 (%2)", _src, count _sl]] call _hd);
                {
                    _n = _n + 1;
                    if (_n <= _cap) then { _l pushBack format ["· %1 <t color='#8a96a3'>%2</t> %3", [_x select 0] call _ref, [_x select 1] call _esc, [_x select 2] call _ref]; };
                } forEach _sl;
                _l pushBack "";
            } forEach _srcs;
            if (_n > _cap) then { _l pushBack ([format ["… et %1 autre(s).", _n - _cap]] call _dim); };
        };
        private _nets = _m getOrDefault ["networks", createHashMap];
        if (count _nets > 0) then {
            _l pushBack ([format ["Réseaux : même identifiant de réseau (%1)", count _nets]] call _hd);
            { _l pushBack format ["· <t color='#e0b050'>%1</t> : %2", [_x] call _esc, (_y apply { ["e:" + str _x] call _ref }) joinString ", "]; } forEach _nets;
        };
        [[_ax, _ay, _aw, _ah], _l joinString "<br/>"] call _box;
    };

    case "orgchart": {
        private _tvw = _aw * 0.56;
        private _tv = ["ctrlTree", [_ax, _ay, _tvw, _ah]] call _ctl;
        private _iconOrg = "\A3\ui_f\data\map\markers\nato\o_hq.paa";
        private _iconMan = "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa";
        private _iconBox = "\a3\ui_f\data\igui\cfg\simpleTasks\types\box_ca.paa";
        private _addNode = {
            params ["_path", "_text", "_data", ["_icon", ""]];
            private _i = _tv tvAdd [_path, (_text) call comspec_sse_fnc_tr];
            private _np = _path + [_i];
            _tv tvSetData [_np, _data];
            if (_icon isNotEqualTo "") then { _tv tvSetPicture [_np, _icon]; };
            _np
        };
        private _addMembers = {
            params ["_path", "_rows"];
            private _sorted = _rows apply { [_ranks getOrDefault [_x select 1, 4], toLower (_x select 0), _forEachIndex, _x] };
            _sorted sort true;
            {
                (_x select 3) params ["_lbl", "_role", "_cell", "_src", "_r", "_sk"];
                [_path, format ["%1 : %2 [%3]", _roleLabels getOrDefault [_role, [_role, "membre"] select (_role isEqualTo "")], _lbl, _src], _r, _iconMan] call _addNode;
                if ((_r select [0, 2]) isEqualTo "s:") then { _detail set [_r, format ["<t size='1.2' font='RobotoCondensedBold'>%1</t><br/>%2", [_lbl] call _esc, [_sk] call _siteHtml]]; };
            } forEach _sorted;
        };
        private _done = [];
        private _addOrg = {
            params ["_oid", "_path", "_depth"];
            if (_oid in _done || {_depth > 8}) exitWith {};
            _done pushBack _oid;
            private _o = _morgs get _oid;
            private _src = ["", "Eden", "site", "Eden + site"] select (([0, 1] select (_o get "eden")) + ([0, 2] select (_o get "site")));
            private _txt = format ["%1 · %2 · menace %3 [%4]", _o get "name", _kindLabels getOrDefault [_o get "kind", ["?", _o get "kind"] select ((_o get "kind") isNotEqualTo "")], _threatLabels getOrDefault [_o get "threat", ["?", _o get "threat"] select ((_o get "threat") isNotEqualTo "")], _src];
            private _np = [_path, _txt, "o:" + _oid, _iconOrg] call _addNode;
            _detail set ["o:" + _oid, [_o] call _orgHtml];
            private _mem = _o get "members";
            [_np, _mem select { (_x select 2) isEqualTo "" }] call _addMembers;
            private _cells = [];
            { if ((_x select 2) isNotEqualTo "") then { _cells pushBackUnique (_x select 2); }; } forEach _mem;
            {
                private _cell = _x;
                private _cp = [_np, format ["Cellule %1", _cell], "o:" + _oid, _iconOrg] call _addNode;
                [_cp, _mem select { (_x select 2) isEqualTo _cell }] call _addMembers;
            } forEach _cells;
            if ((_o get "assets") isNotEqualTo []) then {
                private _ap = [_np, format ["Objets rattachés (%1)", count (_o get "assets")], "o:" + _oid, _iconBox] call _addNode;
                { [_ap, (_ents getOrDefault [_x, createHashMap]) getOrDefault ["label", str _x], "e:" + str _x, _iconBox] call _addNode; } forEach (_o get "assets");
            };
            { if (([_morgs get _x] call _parentOf) isEqualTo _oid && {_x isNotEqualTo _oid}) then { [_x, _np, _depth + 1] call _addOrg; }; } forEach (keys _morgs);
        };
        private _roots = (keys _morgs) select { private _p = [_morgs get _x] call _parentOf; _p isEqualTo "" || {!(_p in _morgs)} || {_p isEqualTo _x} };
        _roots sort true;
        { [_x, [], 0] call _addOrg; } forEach _roots;
        // Cycle de parentés : organisations jamais atteintes depuis une racine.
        { if !(_x in _done) then { [_x, [], 0] call _addOrg; }; } forEach (keys _morgs);
        if (_orphans isNotEqualTo []) then {
            private _op = [[], format ["Personnages récurrents sans organisation (site, %1)", count _orphans], "", _iconMan] call _addNode;
            {
                private _al = (_sRoster get _x) getOrDefault ["alias", ""];
                private _lbl = ([_x] call _sName) + ([format [" « %1 »", _al], ""] select (_al isEqualTo ""));
                [_op, _lbl, "s:" + _x, _iconMan] call _addNode;
                _detail set ["s:" + _x, format ["<t size='1.2' font='RobotoCondensedBold'>%1</t><br/>%2", [_lbl] call _esc, [_x] call _siteHtml]];
            } forEach _orphans;
        };
        { _detail set ["e:" + str _x, [_x] call _entHtml]; } forEach (_people + _things);
        tvExpandAll _tv;
        private _intro = if (count _morgs == 0) then {
            "Aucune organisation : posez un module Organisation, renseignez l'attribut Organisation des personnes, ou lisez une campagne du site."
        } else {
            format ["%1 organisation(s). [Eden] : posée sur la carte, [site] : lue dans la campagne du site. Choisissez un élément pour sa fiche.", count _morgs]
        };
        _detail set ["", _intro];
        [[_ax + _tvw + 2 * _u, _ay, _aw - _tvw - 2 * _u, _ah - 6.5 * _u]] call _detailBox;
        [[_ax + _aw - _aw * 0.2, _ay + _ah - 5 * _u, _aw * 0.2, 5 * _u]] call _selectBtn;
        [_d, ""] call (_d getVariable "comspec_sse_dashShow");
        _tv ctrlAddEventHandler ["TreeSelChanged", {
            params ["_tv", "_path"];
            private _d = ctrlParent _tv;
            [_d, _tv tvData _path] call (_d getVariable "comspec_sse_dashShow");
        }];
    };

    case "items": {
        private _l = [];
        private _isP = { params ["_r"]; (_r select [0, 2]) isEqualTo "e:" && {((_ents getOrDefault [parseNumber (_r select [2]), createHashMap]) getOrDefault ["kind", ""]) isEqualTo "person"} };
        private _isO = { params ["_r"]; (_r select [0, 2]) isEqualTo "e:" && {((_ents getOrDefault [parseNumber (_r select [2]), createHashMap]) getOrDefault ["kind", ""]) isEqualTo "object"} };
        private _byP = createHashMap;
        private _linkedObj = [];
        {
            _x params ["_a", "_rel", "_b"];
            if ([_a] call _isO) then { _linkedObj pushBackUnique _a; };
            if ([_b] call _isO) then { _linkedObj pushBackUnique _b; };
            private _pr = ""; private _or = "";
            if ([_a] call _isP && {[_b] call _isO}) then { _pr = _a; _or = _b; };
            if ([_b] call _isP && {[_a] call _isO}) then { _pr = _b; _or = _a; };
            if (_pr isNotEqualTo "") then {
                if !(_pr in _byP) then { _byP set [_pr, []]; };
                (_byP get _pr) pushBack [_or, _rel];
            };
        } forEach _links;
        _l pushBack ([format ["Objets reliés à une personne (%1 personne(s))", count _byP]] call _hd);
        if (count _byP == 0) then { _l pushBack ("Aucun objet relié à une personne (propriétaire apparent, compte, empreintes, module Lier des entités…)." call _dim); };
        {
            _l pushBack format ["<t font='RobotoCondensedBold'>%1</t>", [_x] call _ref];
            { _l pushBack format ["   · %1 <t color='#8a96a3'>(%2)</t>", [_x select 0] call _ref, [_x select 1] call _esc]; } forEach _y;
        } forEach _byP;
        private _assets = [];
        { private _o = _y; { _assets pushBack [_o get "id", _x]; } forEach (_o get "assets"); } forEach _orgs;
        if (_assets isNotEqualTo []) then {
            _l pushBack "";
            _l pushBack ([format ["Objets rattachés à une organisation (%1)", count _assets]] call _hd);
            { _l pushBack format ["· %1 → %2", ["e:" + str (_x select 1)] call _ref, ["o:" + (_x select 0)] call _ref]; } forEach _assets;
        };
        private _loose = (_things select { !(("e:" + str _x) in _linkedObj) });
        _l pushBack "";
        _l pushBack ([format ["Objets SSE sans lien (%1)", count _loose]] call _hd);
        if (_loose isEqualTo []) then { _l pushBack ("Tous les objets SSE sont reliés." call _dim); };
        { _l pushBack format ["· %1 <t color='#8a96a3'>%2</t>", ["e:" + str _x] call _ref, (_ents get _x) get "type"]; } forEach (_loose select [0, _cap]);
        _l pushBack "";
        _l pushBack ([format ["Registre des scellés de la campagne du site (%1)", count _sCustody]] call _hd);
        if (count _sCustody == 0) then { _l pushBack ([["Aucune campagne lue sur le site.", "Aucune pièce saisie dans cette campagne."] select (count _c > 0)] call _dim); };
        private _pieces = (keys _sCustody);
        _pieces sort true;
        {
            private _p = _sCustody get _x;
            _l pushBack format ["· %1 %2 · %3 · détenteur : %4%5%6", [[_p, "seal"] call _str, _x] select (([_p, "seal"] call _str) isEqualTo ""), [[_p, "label"] call _str] call _esc,
                [[_p, "state"] call _str] call _esc, [[[_p, "holder"] call _str, "?"] select (([_p, "holder"] call _str) isEqualTo "")] call _esc,
                ["", " <t color='#e06060'>· altérée</t>"] select ((_p getOrDefault ["tampered", false]) isEqualTo true),
                ["", " <t color='#8a96a3'>· mission précédente</t>"] select ((_p getOrDefault ["archived", false]) isEqualTo true)];
        } forEach (_pieces select [0, _cap]);
        [[_ax, _ay, _aw, _ah], _l joinString "<br/>"] call _box;
    };

    case "campaign": {
        (_m getOrDefault ["mission", ["", "", "", ""]]) params ["_file", "_world", "_title", "_author"];
        private _l = [["Mission dans Eden"] call _hd];
        { _x params ["_lbl", "_v"]; if (_v isNotEqualTo "") then { _l pushBack format ["%1 : %2", _lbl, [_v] call _esc]; }; } forEach [["Fichier", _file], ["Carte", _world], ["Titre", _title], ["Auteur", _author]];
        private _mm = _mods select { ((_ents get _x) get "cat") isEqualTo "COMSPEC_SSE_Mission" };
        if (_mm isEqualTo []) then { _l pushBack ""; _l pushBack ("Aucun module de mission SSE (Modules › COMSPEC SSE · Mission)." call _dim); };
        {
            private _p = _ents get _x;
            _l pushBack "";
            _l pushBack ([_p get "label"] call _hd);
            { _l pushBack format ["%1 : %2", [_x select 1] call _esc, [_x select 2] call _esc]; } forEach (_p get "attrs");
        } forEach _mm;
        [[_ax, _ay, _aw * 0.42, _ah], _l joinString "<br/>"] call _box;

        private _r = [["Campagne sur le site"] call _hd];
        if (count _c == 0) then {
            _r pushBack (switch (true) do {
                case (_state isEqualTo "busy"): { _site getOrDefault ["detail", ""] };
                case ((_site getOrDefault ["campaignError", ""]) isNotEqualTo ""): { format ["<t color='#e06060'>%1</t>", [_site get "campaignError"] call _esc] };
                case (_state in ["offline", "error"]): { format ["<t color='#e06060'>%1</t>", [_site getOrDefault ["detail", ""]] call _esc] };
                default { "Aucune campagne lue : onglet Site Athena." };
            });
        } else {
            private _cid = _m getOrDefault ["campaignId", ""];
            private _key = toUpper (((trim _cid) splitString " '/\.,:;") joinString "-");
            _r pushBack ([[format ["Correspond au module Campagne de la carte (%1).", _cid], format ["Le module Campagne de la carte vise « %1 » : campagne lue choisie dans l'onglet Site Athena.", _cid]] select (_key isNotEqualTo (_site getOrDefault ["key", ""]))] call _dim);
            if (_cid isEqualTo "") then { _r set [(count _r) - 1, ("Aucun module Campagne sur la carte : campagne lue pour consultation." call _dim)]; };
            _r pushBack "";
            {
                _x params ["_lbl", "_k"];
                private _v = [_c, _k] call _str;
                if (_k isEqualTo "type") then { _v = _typeLabels getOrDefault [_v, _v]; };
                if (_v isNotEqualTo "") then { _r pushBack format ["%1 : %2", _lbl, [_v] call _esc]; };
            } forEach [["Identifiant", "id"], ["Nom", "name"], ["Type", "type"], ["Graine biométrique", "seed"], ["Créée", "created"], ["Mise à jour", "updated"], ["Missions jouées", "missions"], ["Structure copiée de", "copyFrom"]];
            private _all = ((keys _sRoster) + (keys _sMembers)) arrayIntersect ((keys _sRoster) + (keys _sMembers));
            private _kn = { private _kv = _x getOrDefault ["known", 0]; _kv isEqualType 0 && {_kv >= 1} } count (values _sMembers);
            _r pushBack format ["Personnages : %1 (récurrents : %2, connus des joueurs : %3)", count _all, count _sRoster, _kn];
            _r pushBack format ["Organisations : %1 · rencontrés : %2 · pièces saisies : %3", count _sOrgs, count (_c getOrDefault ["seen", createHashMap]), count _sCustody];
            private _log = _c getOrDefault ["log", []];
            if (_log isNotEqualTo []) then {
                private _lg = _log select [((count _log) - 15) max 0];
                reverse _lg;
                _r pushBack "";
                _r pushBack ([format ["Journal de campagne (%1)", count _log]] call _hd);
                {
                    _x params [["_when", ""], ["_mis", ""], ["_wld", ""], ["_why", ""], ["_sum", ""]];
                    _r pushBack format ["· %1 · %2 (%3) · %4", _when, [_mis] call _esc, _wld, [_sum] call _esc];
                } forEach _lg;
            };
            if (count _sRoster > 0) then {
                _r pushBack "";
                _r pushBack ([format ["Personnages récurrents (%1)", count _sRoster]] call _hd);
                private _n = 0;
                {
                    _n = _n + 1;
                    if (_n <= 80) then {
                        private _al = [_y, "alias"] call _str;
                        _r pushBack format ["· %1%2 <t color='#8a96a3'>%3 %4 %5</t>", [[_y, "name"] call _str] call _esc, [format [" « %1 »", [_al] call _esc], ""] select (_al isEqualTo ""),
                            [_y, "nationality"] call _str, [_y, "status"] call _str, [[format ["· vu : %1", [_y, "lastSeen"] call _str], ""] select (([_y, "lastSeen"] call _str) isEqualTo "")] call _esc];
                    };
                } forEach _sRoster;
                if (_n > 80) then { _r pushBack ([format ["… et %1 autre(s).", _n - 80]] call _dim); };
            };
        };
        [[_ax + _aw * 0.44, _ay, _aw * 0.56, _ah], _r joinString "<br/>"] call _box;
    };

    case "site": {
        private _list = _site getOrDefault ["campaigns", []];
        private _cb = ["ctrlCombo", [_ax, _ay, _aw * 0.5, 4.5 * _u]] call _ctl;
        {
            private _i = _cb lbAdd format ["%1 (%2) · %3", _x param [1, ""], _x param [0, ""], (_typeLabels getOrDefault [_x param [2, ""], _x param [2, ""]]) call comspec_sse_fnc_tr];
            _cb lbSetData [_i, _x param [0, ""]];
        } forEach _list;
        _cb lbSetCurSel ((_list findIf { (_x select 0) isEqualTo (_site getOrDefault ["key", ""]) }) max 0);
        private _b = ["ctrlButton", [_ax + _aw * 0.5 + _u, _ay, _aw * 0.22, 4.5 * _u]] call _ctl;
        _b ctrlSetText (("Lire cette campagne") call comspec_sse_fnc_tr);
        _b setVariable ["comspec_sse_combo", _cb];
        _b ctrlAddEventHandler ["ButtonClick", {
            params ["_b"];
            private _cb = _b getVariable ["comspec_sse_combo", controlNull];
            if (isNull _cb || {lbCurSel _cb < 0}) exitWith {};
            ["pull", [_cb lbData (lbCurSel _cb)], ctrlParent _b] spawn comspec_sse_fnc_dashSite;
        }];
        if (_list isEqualTo []) then { _cb ctrlEnable false; _b ctrlEnable false; };

        private _l = [["Liaison avec le site"] call _hd];
        _l pushBack (switch (_state) do {
            case "ok": { format ["<t color='#7fd08a'>Relié</t> · lu à %1", _site getOrDefault ["at", ""]] };
            case "busy": { format ["<t color='#e0b050'>%1</t>", _site getOrDefault ["detail", ""]] };
            case "": { "Pas encore lu." };
            default { format ["<t color='#e06060'>%1</t>", [_site getOrDefault ["detail", ""]] call _esc] };
        });
        _l pushBack ("Lecture seule : rien n'est envoyé au site depuis l'éditeur. Les campagnes arrivent sur le site à la sauvegarde d'une partie (serveur relié) ou par Bureau SSE › Campagnes." call _dim);
        _l pushBack "";
        _l pushBack ([format ["Campagnes de la communauté (%1)", count _list]] call _hd);
        if (_list isEqualTo [] && {_state isEqualTo "ok"}) then { _l pushBack "Aucune campagne sur le site pour cette communauté."; };
        {
            _x params [["_k", ""], ["_n", ""], ["_t", ""], ["_mis", ""], ["_pe", ""], ["_or", ""], ["_up", ""]];
            _l pushBack format ["%1 <t font='RobotoCondensedBold'>%2</t> (%3) <t color='#8a96a3'>· %4 · %5 mission(s) · %6 personnage(s) · %7 organisation(s) · %8</t>",
                ["·", "<t color='#7fd08a'>»</t>"] select (_k isEqualTo (_site getOrDefault ["key", ""])), [_n] call _esc, _k, _typeLabels getOrDefault [_t, _t], _mis, _pe, _or, _up];
        } forEach _list;
        _l pushBack "";
        _l pushBack ([format ["Avis de recherche (%1)", count _wanted]] call _hd);
        if ((_site getOrDefault ["wantedError", ""]) isNotEqualTo "") then { _l pushBack format ["<t color='#e06060'>%1</t>", [_site get "wantedError"] call _esc]; };
        private _onMap = createHashMap;
        { private _wr = [_ents get _x] call _wantedOf; if (_wr isNotEqualTo []) then { _onMap set [_wr param [1, ""], _x]; }; } forEach _people;
        {
            _x params [["_kind", ""], ["_id", ""], ["_ref", ""], ["_n", ""], ["_al", ""], ["_lvl", ""], ["_det", ""]];
            private _hit = _onMap getOrDefault [_id, -1];
            _l pushBack format ["· %1%2 <t color='#8a96a3'>· %3 · niveau %4 · %5</t>%6", [_n] call _esc, [format [" « %1 »", [_al] call _esc], ""] select (_al isEqualTo ""), _kind, _lvl, _ref,
                ["", format [" <t color='#e0b050'>· sur la carte : %1</t>", ["e:" + str _hit] call _ref]] select (_hit isNotEqualTo -1)];
        } forEach (_wanted select [0, 120]);
        [[_ax, _ay + 5.5 * _u, _aw, _ah - 5.5 * _u], _l joinString "<br/>"] call _box;
    };
};
