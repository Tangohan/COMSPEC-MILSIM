/*
    Tableau de bord SSE : relevé de la mission ouverte dans Eden (lecture seule, rien n'est modifié).
    Lit les attributs Eden des catégories COMSPEC SSE (personnes et objets), les modules COMSPEC SSE et leurs
    synchronisations, puis en déduit les organisations, les liaisons et les contrôles de cohérence.

    [] call comspec_sse_fnc_dashCollect → HashMap
      mission     [fichier, carte, titre, auteur]
      entities    id Eden → HashMap {id, ent, kind (person, object, module), type, cat (catégorie de module), label,
                  attrs [[catégorie, libellé, valeur]], raw (propriété ou attribut de module → valeur), syncs [id], key, var}
      order       ids dans l'ordre d'affichage : modules, personnes, objets
      links       [de, relation, vers, origine] ; de / vers : "e:<id Eden>", "o:<id organisation>" ou "t:<texte>"
      orgs        id → HashMap {id, name, kind, parent, threat, desc, known, mods [id], members [[id, rôle, cellule, alias]], assets [id]}
      networks    nom → [id]
      campaignId  identifiant du module Campagne (vide sans module)
      roleLabels, kindLabels, threatLabels   valeur → libellé
      checks      [[niveau 0 info · 1 attention · 2 erreur, texte]]
*/
if (!is3DEN) exitWith { createHashMap };

// Valeur par défaut d'un attribut (texte de config) : seulement les littéraux, rien n'est exécuté.
private _parseDef = {
    params ["_d"];
    _d = trim _d;
    switch (true) do {
        case (_d isEqualTo "true"): { true };
        case (_d isEqualTo "false"): { false };
        case ((count _d) >= 2 && {(_d select [0, 1]) in ["'", """"]}): { _d select [1, (count _d) - 2] };
        case (_d regexMatch "^-?[0-9]+(\.[0-9]+)?$"): { parseNumber _d };
        default { "#?" };
    }
};
private _choicesOf = {
    params ["_cfg"];
    private _m = createHashMap;
    {
        // Pas de [getText, getNumber] select … : les deux seraient évalués, et getNumber sur une valeur texte
        // ("RANDOM", "INSURGENT"…) l'exécute comme une expression → erreur « Invalid number in expression ».
        private _val = if (isNumber (_x >> "value")) then { getNumber (_x >> "value") } else { getText (_x >> "value") };
        _m set [_val, getText (_x >> "name")];
    } forEach ("true" configClasses (_cfg >> "Values"));
    _m
};
private _fmt = {
    params ["_v", "_choices"];
    private _c = _choices getOrDefault [_v, ""];
    if (_c isNotEqualTo "") exitWith { _c };
    if (_v isEqualType true) exitWith { ["non", "oui"] select _v };
    if (_v isEqualType "") exitWith { (_v splitString (toString [13, 10])) joinString " / " };
    str _v
};
private _str = {
    params ["_m", "_k"];
    private _v = _m getOrDefault [_k, ""];
    if (_v isEqualType "") then { trim _v } else { str _v }
};
private _slug = { params ["_s"]; "ORG-" + (toUpper (((trim _s) splitString " '-_/.,:;") joinString "")) };

// Attributs SSE des objets (catégories COMSPEC_SSE*) : [propriété, libellé, défaut, catégorie, choix]. Gardé pour la session.
private _schema = uiNamespace getVariable ["comspec_sse_dashSchema", []];
if (_schema isEqualTo []) then {
    {
        private _catCfg = _x;
        if (((toUpper configName _catCfg) find "COMSPEC_SSE") == 0) then {
            private _catName = getText (_catCfg >> "displayName");
            {
                private _prop = getText (_x >> "property");
                if (_prop isNotEqualTo "") then {
                    _schema pushBack [_prop, getText (_x >> "displayName"), [getText (_x >> "defaultValue")] call _parseDef, _catName, [_x] call _choicesOf];
                };
            } forEach ("true" configClasses (_catCfg >> "Attributes"));
        };
    } forEach ("true" configClasses (configFile >> "Cfg3DEN" >> "Object" >> "AttributeCategories"));
    uiNamespace setVariable ["comspec_sse_dashSchema", _schema];
};
private _roleLabels = createHashMap;
{ if ((_x select 0) isEqualTo "COMSPEC_SSE_Org_Role") exitWith { _roleLabels = _x select 4; }; } forEach _schema;
private _orgCfg = configFile >> "CfgVehicles" >> "COMSPEC_SSE_Module_Organisation" >> "Attributes";
private _kindLabels = [_orgCfg >> "Kind"] call _choicesOf;
private _threatLabels = [_orgCfg >> "Threat"] call _choicesOf;

private _syncsOf = {
    params ["_e"];
    ((get3DENConnections _e) select { (_x select 0) isEqualTo "Sync" }) apply { get3DENEntityID (_x select 1) }
};

private _all = all3DENEntities;
private _ents = createHashMap;
private _mods = [];
private _people = [];
private _things = [];
private _modSynced = [];

// ---------------------------------------------------------------- modules COMSPEC SSE
{
    private _e = _x;
    private _type = typeOf _e;
    if (((toUpper _type) find "COMSPEC_SSE_MODULE") == 0) then {
        private _cfg = configFile >> "CfgVehicles" >> _type;
        private _attrs = [];
        private _raw = createHashMap;
        {
            if ((configName _x) != "ModuleDescription") then {
                private _prop = getText (_x >> "property");
                if (_prop isEqualTo "") then { _prop = _type + "_" + configName _x; };
                private _r = _e get3DENAttribute _prop;
                if (_r isNotEqualTo []) then {
                    private _v = _r select 0;
                    if (!isNil "_v") then {
                        _raw set [configName _x, _v];
                        _attrs pushBack ["", getText (_x >> "displayName"), [_v, [_x] call _choicesOf] call _fmt];
                    };
                };
            };
        } forEach ("true" configClasses (_cfg >> "Attributes"));
        private _id = get3DENEntityID _e;
        private _syncs = [_e] call _syncsOf;
        _modSynced append _syncs;
        private _name = "";
        { if (_name isEqualTo "") then { _name = [_raw, _x] call _str; }; } forEach ["Name", "Id", "Reference"];
        private _lbl = getText (_cfg >> "displayName");
        if (_name isNotEqualTo "") then { _lbl = _lbl + " : " + _name; };
        _ents set [_id, createHashMapFromArray [
            ["id", _id], ["ent", _e], ["kind", "module"], ["type", _type], ["cat", getText (_cfg >> "category")], ["label", _lbl],
            ["attrs", _attrs], ["raw", _raw], ["syncs", _syncs], ["key", ""], ["var", ""]
        ]];
        _mods pushBack _id;
    };
} forEach (_all select 3);

// ---------------------------------------------------------------- personnes et objets
{
    private _e = _x;
    private _type = typeOf _e;
    private _id = get3DENEntityID _e;
    private _raw = createHashMap;
    private _attrs = [];
    {
        _x params ["_prop", "_lbl", "_def", "_cat", "_choices"];
        private _r = _e get3DENAttribute _prop;
        if (_r isNotEqualTo []) then {
            private _v = _r select 0;
            if (!isNil "_v" && {!(_v isEqualTo _def)} && {!(_v isEqualTo "")} && {!(_v isEqualTo [])}) then {
                _raw set [_prop, _v];
                _attrs pushBack [_cat, _lbl, [_v, _choices] call _fmt];
            };
        };
    } forEach _schema;
    if (_attrs isNotEqualTo [] || {((toUpper _type) find "COMSPEC_SSE") == 0} || {_id in _modSynced}) then {
        private _man = _e isKindOf "CAManBase";
        private _var = (_e get3DENAttribute "name") param [0, ""];
        if !(_var isEqualType "") then { _var = ""; };
        private _name = trim (format ["%1 %2", toUpper ([_raw, "COMSPEC_SSE_LastName"] call _str), [_raw, "COMSPEC_SSE_FirstName"] call _str]);
        private _alias = [_raw, "COMSPEC_SSE_Alias"] call _str;
        private _lbl = [getText (configFile >> "CfgVehicles" >> _type >> "displayName"), _name] select (_name isNotEqualTo "");
        if (_alias isNotEqualTo "") then { _lbl = _lbl + format [" « %1 »", _alias]; };
        if (_var isNotEqualTo "") then { _lbl = _lbl + format [" (%1)", _var]; } else { if (_name isEqualTo "") then { _lbl = _lbl + format [" #%1", _id]; }; };
        _ents set [_id, createHashMapFromArray [
            ["id", _id], ["ent", _e], ["kind", ["object", "person"] select _man], ["type", _type], ["cat", ""], ["label", _lbl],
            ["attrs", _attrs], ["raw", _raw], ["syncs", [_e] call _syncsOf], ["key", toUpper ([_raw, "COMSPEC_SSE_BioId"] call _str)],
            ["var", _var], ["name", _name], ["alias", _alias]
        ]];
        if (_man) then { _people pushBack _id; } else { _things pushBack _id; };
    };
} forEach (_all select 0);

// ---------------------------------------------------------------- résolution d'un nom (variable, identifiant biométrique, nom, alias)
private _byName = createHashMap;
{
    private _p = _ents get _x;
    if ((_p get "var") isNotEqualTo "") then { _byName set [toLower (_p get "var"), _x]; };
    if ((_p get "key") isNotEqualTo "") then { _byName set [toLower (_p get "key"), _x]; };
    private _n = _p getOrDefault ["name", ""];
    if (_n isNotEqualTo "") then {
        _byName set [toLower _n, _x];
        private _w = _n splitString " ";
        if ((count _w) >= 2) then { _byName set [toLower (((_w select [1]) + [_w select 0]) joinString " "), _x]; };
    };
    if ((_p getOrDefault ["alias", ""]) isNotEqualTo "") then { _byName set [toLower (_p get "alias"), _x]; };
} forEach (_people + _things);
private _resolve = {
    params ["_s"];
    _s = trim _s;
    if (_s isEqualTo "") exitWith { "" };
    private _id = _byName getOrDefault [toLower _s, -1];
    if (_id isEqualTo -1) then { "t:" + _s } else { "e:" + str _id }
};

private _links = [];
private _orgs = createHashMap;
private _checks = [];
private _ensureOrg = {
    params ["_name"];
    private _oid = [_name] call _slug;
    if !(_oid in _orgs) then {
        _orgs set [_oid, createHashMapFromArray [
            ["id", _oid], ["name", trim _name], ["kind", ""], ["parent", ""], ["threat", ""], ["desc", ""], ["known", 0],
            ["mods", []], ["members", []], ["assets", []]
        ]];
    };
    _orgs get _oid
};
private _addMember = {
    params ["_o", "_pid"];
    private _p = _ents get _pid;
    private _raw = _p get "raw";
    if (((_o get "members") findIf { (_x select 0) isEqualTo _pid }) >= 0) exitWith {};
    private _role = [_raw, "COMSPEC_SSE_Org_Role"] call _str;
    if (_role in ["", "auto"]) then { _role = "membre"; };
    (_o get "members") pushBack [_pid, _role, [_raw, "COMSPEC_SSE_Org_Cell"] call _str, [_raw, "COMSPEC_SSE_Org_Alias"] call _str];
};
private _relLabels = createHashMapFromArray [["ASSOCIATE", "associé à"], ["CONTACT", "en contact avec"], ["OWNER", "propriétaire de"], ["REFERENCES", "fait référence à"]];

// Modules : organisations, liens, ce à quoi chaque module s'applique.
private _campaignMods = [];
private _caseMods = [];
{
    private _mo = _ents get _x;
    private _raw = _mo get "raw";
    private _targets = (_mo get "syncs") select { ((_ents getOrDefault [_x, createHashMap]) getOrDefault ["kind", "module"]) != "module" };
    switch (_mo get "type") do {
        case "COMSPEC_SSE_Module_Organisation": {
            private _name = [_raw, "Name"] call _str;
            if (_name isEqualTo "") then {
                _name = format ["(organisation sans nom #%1)", _x];
                _checks pushBack [1, format ["Module Organisation #%1 sans nom : un nom au hasard sera tiré au lancement.", _x]];
            };
            private _o = [_name] call _ensureOrg;
            _o set ["kind", [_raw, "Kind"] call _str];
            _o set ["parent", [_raw, "Parent"] call _str];
            _o set ["threat", [_raw, "Threat"] call _str];
            _o set ["desc", [_raw, "Description"] call _str];
            _o set ["known", parseNumber ([_raw, "Known"] call _str)];
            (_o get "mods") pushBack _x;
            {
                if (((_ents get _x) get "kind") isEqualTo "person") then {
                    [_o, _x] call _addMember;
                    _links pushBack ["e:" + str _x, "membre de", "o:" + (_o get "id"), "Module Organisation"];
                } else {
                    (_o get "assets") pushBackUnique _x;
                    _links pushBack ["e:" + str _x, "rattaché à", "o:" + (_o get "id"), "Module Organisation"];
                };
            } forEach _targets;
            if (_targets isEqualTo []) then { _checks pushBack [0, format ["Organisation « %1 » : aucune personne ni aucun objet synchronisé au module.", _name]]; };
        };
        case "COMSPEC_SSE_Module_LinkEntities": {
            private _rel = toUpper ([_raw, "Relation"] call _str);
            private _lbl = format ["%1 · confiance %2 %3", _relLabels getOrDefault [_rel, toLower _rel], round (100 * (parseNumber ([_raw, "Confidence"] call _str))), "%"];
            if ((count _targets) < 2) then {
                _checks pushBack [2, format ["Module Lier des entités #%1 : %2 entité(s) synchronisée(s), il en faut au moins deux.", _x, count _targets]];
            } else {
                if ((toUpper ([_raw, "Mode"] call _str)) isEqualTo "STAR") then {
                    for "_i" from 1 to ((count _targets) - 1) do { _links pushBack ["e:" + str (_targets select 0), _lbl, "e:" + str (_targets select _i), "Module Lier des entités"]; };
                } else {
                    for "_i" from 1 to ((count _targets) - 1) do { _links pushBack ["e:" + str (_targets select (_i - 1)), _lbl, "e:" + str (_targets select _i), "Module Lier des entités"]; };
                };
            };
        };
        case "COMSPEC_SSE_Module_Campaign": { _campaignMods pushBack _x; };
        case "COMSPEC_SSE_Module_CaseReference": { _caseMods pushBack _x; };
        default {
            { _links pushBack ["e:" + str (_mo get "id"), "s'applique à", "e:" + str _x, "Modules"]; } forEach _targets;
        };
    };
} forEach _mods;

// Appartenances saisies sur les personnes, puis organisations parentes.
{
    private _p = _ents get _x;
    private _raw = _p get "raw";
    private _org = [_raw, "COMSPEC_SSE_Org_Name"] call _str;
    if (_org isNotEqualTo "") then {
        private _o = [_org] call _ensureOrg;
        [_o, _x] call _addMember;
        private _role = [_raw, "COMSPEC_SSE_Org_Role"] call _str;
        _links pushBack ["e:" + str _x, format ["membre de (%1)", _roleLabels getOrDefault [_role, ["membre", _role] select (_role isNotEqualTo "")]], "o:" + (_o get "id"), "Attributs Organisation"];
    } else {
        if (([_raw, "COMSPEC_SSE_Org_Role"] call _str) isNotEqualTo "" || {([_raw, "COMSPEC_SSE_Org_Cell"] call _str) isNotEqualTo ""}) then {
            _checks pushBack [1, format ["%1 : rôle ou cellule saisi sans nom d'organisation (ignoré au lancement).", _p get "label"]];
        };
    };
} forEach _people;
{
    private _par = _y get "parent";
    if (_par isNotEqualTo "" && {!(([_par] call _slug) in _orgs)}) then {
        _checks pushBack [1, format ["Organisation « %1 » : organisation parente « %2 » absente de la carte (créée vide au lancement).", _y get "name", _par]];
    };
} forEach +_orgs;

// Liens portés par les attributs des personnes et des objets.
private _networks = createHashMap;
private _seenKeys = createHashMap;
{
    private _id = _x;
    private _p = _ents get _id;
    private _raw = _p get "raw";
    private _me = "e:" + str _id;
    private _net = [_raw, "comspec_sse_networkId"] call _str;
    if (_net isNotEqualTo "") then {
        if !(_net in _networks) then { _networks set [_net, []]; };
        (_networks get _net) pushBack _id;
    };
    private _key = _p get "key";
    if (_key isNotEqualTo "") then {
        if (_key in _seenKeys) then {
            _checks pushBack [2, format ["Identifiant biométrique %1 en double : %2 et %3 (une identité = une seule personne).", _key, (_ents get (_seenKeys get _key)) get "label", _p get "label"]];
        } else { _seenKeys set [_key, _id]; };
    };
    private _rel = [_raw, "COMSPEC_SSE_BioRelativeOf"] call _str;
    if (_rel isNotEqualTo "") then {
        private _to = [_rel] call _resolve;
        private _kin = _raw getOrDefault ["COMSPEC_SSE_BioRelation", ""];
        private _si = _schema findIf { (_x select 0) isEqualTo "COMSPEC_SSE_BioRelation" };
        _kin = if (_kin isEqualTo "" || {_si < 0}) then { "" } else { [_kin, (_schema select _si) select 4] call _fmt };
        _links pushBack [_me, format ["apparenté à (%1)", ["lien familial", _kin] select (_kin isNotEqualTo "")], _to, "Biométrie"];
        if ((_to select [0, 2]) isEqualTo "t:") then { _checks pushBack [1, format ["%1 : parenté vers « %2 », introuvable sur la carte (nom de variable ou identifiant biométrique).", _p get "label", _rel]]; };
    };
    {
        private _to = [_x] call _resolve;
        if (_to isNotEqualTo "") then {
            _links pushBack [_me, "porte les empreintes de", _to, "Biométrie"];
            if ((_to select [0, 2]) isEqualTo "t:") then { _checks pushBack [1, format ["%1 : empreintes de « %2 », introuvable sur la carte.", _p get "label", trim _x]]; };
        };
    } forEach (([_raw, "COMSPEC_SSE_BioLatentOwners"] call _str) splitString ",;");
    {
        _x params ["_prop", "_lbl"];
        private _v = [_raw, _prop] call _str;
        if (_v isNotEqualTo "") then { _links pushBack [_me, _lbl, [_v] call _resolve, "Contenu des objets"]; };
    } forEach [["comspec_sse_domex_owner", "a pour propriétaire apparent"], ["comspec_sse_pcOwner", "a pour compte utilisateur"]];
    {
        private _v = [_raw, _x] call _str;
        if (_v isNotEqualTo "") then {
            private _oid = [_v] call _slug;
            _links pushBack [_me, "appartient à", ["t:" + _v, "o:" + _oid] select (_oid in _orgs), "Contenu des objets"];
            if (_oid in _orgs) then { ((_orgs get _oid) get "assets") pushBackUnique _id; };
        };
    } forEach ["comspec_sse_domex_org", "COMSPEC_SSE_OrgLinked"];
    {
        private _to = [_x] call _resolve;
        if ((_to select [0, 2]) isEqualTo "e:") then { _links pushBack [_me, "a en contact", _to, "Contenu des objets"]; };
    } forEach (([_raw, "comspec_sse_phoneContacts"] call _str) splitString (toString [13, 10]));
} forEach (_people + _things);
{ if ((count _y) < 2) then { _networks deleteAt _x; }; } forEach +_networks;

// ---------------------------------------------------------------- mission et contrôles
private _campaignId = "";
if (_campaignMods isEqualTo []) then {
    _checks pushBack [0, "Aucun module Campagne : la partie est autonome (rien n'est gardé d'une mission à l'autre)."];
} else {
    if ((count _campaignMods) > 1) then { _checks pushBack [2, format ["%1 modules Campagne sur la carte : un seul est pris en compte.", count _campaignMods]]; };
    _campaignId = [(_ents get (_campaignMods select 0)) get "raw", "Id"] call _str;
    if (_campaignId isEqualTo "") then { _checks pushBack [2, "Module Campagne sans identifiant : aucune campagne ne sera chargée."]; };
};
if (_caseMods isEqualTo []) then {
    _checks pushBack [0, "Aucun module Référence de dossier : les fiches transmises iront dans un dossier numéroté automatiquement."];
} else {
    if ((count _caseMods) > 1) then { _checks pushBack [1, format ["%1 modules Référence de dossier : le dernier lancé l'emporte.", count _caseMods]]; };
};
if (_people isEqualTo [] && {_things isEqualTo []}) then { _checks pushBack [1, "Aucune personne ni aucun objet SSE sur la carte."]; };
if ((_checks findIf { (_x select 0) > 0 }) < 0) then { _checks pushBack [0, "Aucune incohérence relevée dans la carte."]; };

private _title = ("Scenario" get3DENMissionAttribute "IntelBriefingName");
private _author = ("Scenario" get3DENMissionAttribute "Author");
createHashMapFromArray [
    ["mission", [missionName, worldName, [_title, ""] select (isNil "_title"), [_author, ""] select (isNil "_author")]],
    ["entities", _ents], ["order", _mods + _people + _things], ["links", _links], ["orgs", _orgs], ["networks", _networks],
    ["campaignId", _campaignId], ["roleLabels", _roleLabels], ["kindLabels", _kindLabels], ["threatLabels", _threatLabels],
    ["checks", _checks], ["at", diag_tickTime]
]
