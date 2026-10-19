/*
    Construit le graphe du tableau des liens : ce que les joueurs savent,
    ou tout (avec ce qu'ils ignorent en grisé) pour le Zeus.

    Sources : organisations et appartenances révélées, personnes rencontrées,
    pièces saisies, objets exploités, liens du graphe SSE, objets rattachés à une
    organisation, téléphones, documents et lieux des personnes exploitées.

    [_zeus] call comspec_sse_fnc_chartBuild → [nœuds (HashMap id → nœud), liens [[a, b, libellé, connu]]]
      nœud : id, kind (org, person, object, vehicle, device, doc, weapon, place), label, sub, known, netId, parent
*/
params [["_zeus", false, [false]]];

private _orgs = missionNamespace getVariable ["comspec_sse_orgs", createHashMap];
private _mem = missionNamespace getVariable ["comspec_sse_orgMembers", createHashMap];
private _seen = missionNamespace getVariable ["comspec_sse_anaSeen", createHashMap];
private _custody = missionNamespace getVariable ["comspec_sse_custody", createHashMap];
private _nodes = createHashMap;
private _edges = [];

private _addNode = {
    params ["_id", "_kind", "_label", "_sub", "_known", ["_netId", ""], ["_parent", ""]];
    if (_id in _nodes) exitWith { _nodes get _id };
    private _n = createHashMapFromArray [
        ["id", _id], ["kind", _kind], ["label", _label], ["sub", _sub], ["known", _known],
        ["netId", _netId], ["parent", _parent], ["pos", [0, 0, 0]]
    ];
    _nodes set [_id, _n];
    _n
};
private _edge = { params ["_a", "_b", "_lbl", "_k"]; _edges pushBack [_a, _b, _lbl, _k]; };
private _personLabel = {
    params ["_name", "_alias"];
    if (_alias isEqualTo "") then { _name } else { format ["%1 « %2 »", _name, _alias] }
};
private _worked = {
    params ["_o"];
    private _s = [_o] call comspec_sse_fnc_getState;
    (!isNil "_s" && {(toUpper _s) in ["COLLECTED", "EXPLOITED", "PARTIALLY_EXPLOITED"]})
    || {(_o getVariable ["COMSPEC_SSE_PieceId", ""]) isNotEqualTo ""}
};
private _kindOf = {
    params ["_o"];
    if (_o isKindOf "LandVehicle" || {_o isKindOf "Air"} || {_o isKindOf "Ship"}) exitWith { "vehicle" };
    private _t = "";
    private _d = [_o] call comspec_sse_fnc_getData;
    if (!isNil "_d") then { _t = [_d, "type", ""] call BIS_fnc_getFromPairs; };
    if (!(_t isEqualType "")) then { _t = ""; };
    switch (toUpper _t) do {
        case "PHONE"; case "COMPUTER"; case "RADIO"; case "MEDIA": { "device" };
        case "DOCUMENT": { "doc" };
        case "WEAPON": { "weapon" };
        default { "object" };
    }
};

// 1. Personnes : appartenances révélées (toutes pour le Zeus).
private _personIds = createHashMap;
{
    private _k = _y getOrDefault ["known", 0];
    if (_zeus || {_k >= 1}) then {
        private _u = objectFromNetId (_y getOrDefault ["netId", ""]);
        private _lbl = if (isNull _u) then { [_y getOrDefault ["name", "?"], ""] } else { [_u] call comspec_sse_fnc_anaLabel };
        private _alias = _y getOrDefault ["alias", ""];
        if (_alias isEqualTo "") then { _alias = _lbl select 1; };
        private _id = "P:" + _x;
        private _sub = ["roleLabel", _y getOrDefault ["role", "membre"]] call comspec_sse_fnc_orgTables;
        if (!isNull _u && {!alive _u}) then { _sub = _sub + ", décédé"; };
        [_id, "person", [_lbl select 0, _alias] call _personLabel, _sub, _k, _y getOrDefault ["netId", ""], "O:" + (_y getOrDefault ["org", ""])] call _addNode;
        if (!isNull _u) then { _personIds set [netId _u, _id]; };
    };
} forEach _mem;

// Personnes rencontrées (contrôlées, enrôlées) sans lien connu.
{
    private _u = objectFromNetId _y;
    if (!isNull _u && {!(("P:" + _x) in _nodes)}) then {
        private _lbl = [_u] call comspec_sse_fnc_anaLabel;
        ["P:" + _x, "person", _lbl call _personLabel, ["Rencontré, aucun lien connu", "Décédé, aucun lien connu"] select (!alive _u), 2, _y] call _addNode;
        _personIds set [_y, "P:" + _x];
    };
} forEach _seen;

// 2. Organisations : connues, ou contenant une personne visible ; leurs parents suivent.
private _visOrg = createHashMap;
{ if (_zeus || {(_y getOrDefault ["known", 0]) >= 1}) then { _visOrg set [_x, true]; }; } forEach _orgs;
{
    if ((_y get "kind") isEqualTo "person") then {
        private _p = _y get "parent";
        if (_p isNotEqualTo "") then {
            private _o = _p select [2];
            if (_o in _orgs) then { _visOrg set [_o, true]; };
        };
    };
} forEach _nodes;
{
    private _walk = (_orgs getOrDefault [_x, createHashMap]) getOrDefault ["parent", ""];
    for "_i" from 0 to 20 do {
        if (_walk isEqualTo "" || {!(_walk in _orgs)}) exitWith {};
        _visOrg set [_walk, true];
        _walk = (_orgs get _walk) getOrDefault ["parent", ""];
    };
} forEach (keys _visOrg);
{
    private _o = _orgs get _x;
    private _k = _o getOrDefault ["known", 0];
    if (!_zeus) then { _k = _k max 1; };
    private _p = _o getOrDefault ["parent", ""];
    [
        "O:" + _x, "org", _o getOrDefault ["name", "?"],
        format ["%1 · menace %2", ["kindLabel", _o getOrDefault ["kind", "reseau"]] call comspec_sse_fnc_orgTables, toLower (["threatLabel", _o getOrDefault ["threat", "moyenne"]] call comspec_sse_fnc_orgTables)],
        _k, "", ["", "O:" + _p] select (_p in _visOrg)
    ] call _addNode;
} forEach (keys _visOrg);

// 3. Objets : pièces saisies, objets exploités liés à ce qui est visible.
private _objIds = createHashMap;
private _addObj = {
    params ["_o", "_why"];
    if (isNull _o || {_o isKindOf "CAManBase"}) exitWith { "" };
    private _nid = netId _o;
    if (_nid in _objIds) exitWith { _objIds get _nid };
    private _pid = _o getVariable ["COMSPEC_SSE_PieceId", ""];
    private _lbl = getText (configOf _o >> "displayName");
    if (_lbl isEqualTo "") then { _lbl = typeOf _o; };
    private _id = "X:" + _nid;
    [_id, [_o] call _kindOf, _lbl, [_pid, _why] select (_pid isEqualTo ""), [2, 0] select (_zeus && {!([_o] call _worked)}), _nid] call _addNode;
    _objIds set [_nid, _id];
    _id
};
{
    private _o = objectFromNetId (_y getOrDefault ["netId", ""]);
    if (!isNull _o && {!(_o isKindOf "CAManBase")}) then { [_o, "pièce saisie"] call _addObj; };
} forEach _custody;

private _resolve = {
    params ["_o"];
    if (_o isKindOf "CAManBase") exitWith { _personIds getOrDefault [netId _o, ""] };
    private _have = _objIds getOrDefault [netId _o, ""];
    if (_have isNotEqualTo "") exitWith { _have };
    if (_zeus || {[_o] call _worked}) exitWith { [_o, "exploité"] call _addObj };
    ""
};
private _rel = createHashMapFromArray [
    ["OWNER", "propriétaire"], ["ASSOCIATE", "associé"], ["CONTACT", "contact"], ["LOCATION", "lieu"],
    ["MEMBER", "membre"], ["FAMILY", "famille"], ["FINANCE", "finance"], ["SUPPLIES", "fournit"],
    ["USES", "utilise"], ["COMMANDS", "commande"], ["COMMS", "communications"], ["OWNS", "possède"]
];
if (!isNil "comspec_sse_linkGraph") then {
    {
        private _a = objectFromNetId (_x getOrDefault ["sourceNetId", ""]);
        private _b = objectFromNetId (_x getOrDefault ["targetNetId", ""]);
        if (!isNull _a && {!isNull _b}) then {
            private _ia = [_a] call _resolve;
            private _ib = [_b] call _resolve;
            if (_ia isNotEqualTo "" && {_ib isNotEqualTo ""} && {_ia isNotEqualTo _ib}) then {
                private _t = toUpper (_x getOrDefault ["relationType", "ASSOCIATE"]);
                private _conf = _x getOrDefault ["confidence", 0.8];
                [_ia, _ib, _rel getOrDefault [_t, toLower _t], [1, 2] select (_conf >= 0.7)] call _edge;
            };
        };
    } forEach comspec_sse_linkGraph;
};

// Objets rattachés à une organisation (attribut Eden, module, Zeus). La liste des objets rattachés est gardée en
// cache (60 s, vidé à l'ouverture du tableau) : allMissionObjects à chaque reconstruction (filtre, anaChanged)
// coûtait cher sur une grosse mission.
private _linkedCache = missionNamespace getVariable ["comspec_sse_orgLinkedCache", []];
if (_linkedCache isEqualTo [] || {time > ((_linkedCache select 0) + 60)}) then {
    _linkedCache = [time, (allMissionObjects "All") select { (_x getVariable ["COMSPEC_SSE_OrgLinked", ""]) isNotEqualTo "" }];
    missionNamespace setVariable ["comspec_sse_orgLinkedCache", _linkedCache];
};
{
    private _org = _x getVariable ["COMSPEC_SSE_OrgLinked", ""];
    if (_org isEqualType "" && {(trim _org) isNotEqualTo ""} && {!(_x isKindOf "CAManBase")}) then {
        private _oid = "O:ORG-" + (toUpper (((trim _org) splitString " '-_/.,:;") joinString ""));
        if (_oid in _nodes && {_zeus || {[_x] call _worked}}) then {
            private _id = [_x, "rattaché à " + _org] call _addObj;
            if (_id isNotEqualTo "") then { [_id, _oid, "appartient à", 2] call _edge; };
        };
    };
} forEach ((_linkedCache select 1) select { !isNull _x });

// 4. Téléphones, documents et lieux d'une personne ou d'un objet exploités.
{
    private _nid = _y get "netId";
    private _o = objectFromNetId _nid;
    if (_nid isNotEqualTo "" && {!isNull _o} && {_zeus || {[_o] call _worked}} && {(_y get "kind") in ["person", "object", "device", "doc", "vehicle"]}) then {
        private _from = _x;
        private _k = [1, 2] select ([_o] call _worked);
        private _devices = [_o, "digitalDevices"] call comspec_sse_fnc_getSection;
        if (!isNil "_devices" && {_devices isEqualType []}) then {
            {
                if (_x isEqualType createHashMap) then {
                    private _num = _x getOrDefault ["phoneNumber", ""];
                    private _lbl = [_num, _x getOrDefault ["model", "Appareil"]] select (_num isEqualTo "");
                    private _id = format ["D:%1:%2", _nid, _forEachIndex];
                    [_id, "device", _lbl, _x getOrDefault ["model", "téléphone"], _k, ""] call _addNode;
                    [_from, _id, "utilise", _k] call _edge;
                };
            } forEach (_devices select [0, 4]);
        };
        {
            private _sec = [_o, _x] call comspec_sse_fnc_getSection;
            if (!isNil "_sec" && {_sec isEqualType []}) then {
                {
                    if (_x isEqualType createHashMap) then {
                        private _g = _x getOrDefault ["grid", ""];
                        if (_g isEqualType "" && {_g isNotEqualTo ""}) then {
                            private _name = _x getOrDefault ["name", _x getOrDefault ["title", ""]];
                            if (!(_name isEqualType "")) then { _name = ""; };
                            private _id = "L:" + _g;
                            ["L:" + _g, "place", ["Lieu " + _g, format ["%1 (%2)", _name, _g]] select (_name isNotEqualTo ""), "Point mentionné", _k] call _addNode;
                            [_from, _id, "mentionne", _k] call _edge;
                        };
                    };
                } forEach (_sec select [0, 6]);
            };
        } forEach ["locations", "documents"];
    };
} forEach (+_nodes);

// Liens d'appartenance et de rattachement.
{
    private _p = _y get "parent";
    if (_p isNotEqualTo "" && {_p in _nodes}) then {
        [_x, _p, ["rattachée", _y get "sub"] select ((_y get "kind") isEqualTo "person"), _y get "known"] call _edge;
    } else {
        _y set ["parent", ""];
    };
} forEach _nodes;

[_nodes, _edges]
