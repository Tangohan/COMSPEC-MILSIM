/*
    Organisations, appartenances et ce que les joueurs en savent (serveur).
    Appelée sur un poste, la demande part au serveur.

    [_op, _args] call comspec_sse_fnc_orgOp
      "ensure"   [nom, nature, nomParent, menace, description] → id (crée ou complète)
      "set"      [id, champ, valeur]
      "delete"   [id]               (les membres passent à l'organisation parente, sinon sortent)
      "member"   [unité, nomOrg, rôle, alias, nomCellule, connu (-1 = inchangé)] → id
      "unmember" [unité]
      "reveal"   [unité | clé, niveau 1-2, comment, acteur]
      "revealOrg"[id, niveau 1-2, comment, acteur]
      "seen"     [unité]            (personne rencontrée : elle entre au tableau des liens)
      "exploit"  [entité, acteur]   (une pièce exploitée trahit ses liens)
      "orgOf"    [unité] → [id, rôle] | []   (lecture locale)
      "publish"  []                 (diffuse l'état, après une génération groupée)

    État diffusé à tous :
      comspec_sse_orgs       id → {id, name, kind, parent, threat, desc, known, how}
      comspec_sse_orgMembers clé → {key, org, role, alias, known, how, when, netId, name}
      comspec_sse_anaSeen    clé → netId (personne rencontrée)
    Connaissance : 0 non identifié · 1 soupçonné · 2 confirmé.
*/
params [["_op", "", [""]], ["_args", [], [[]]]];
_op = toLower _op;

if (_op isEqualTo "orgof") exitWith {
    _args params [["_u", objNull, [objNull]]];
    _u getVariable ["COMSPEC_SSE_Org", []]
};
if (!isServer) exitWith { ["comspec_sse_orgOp", [_op, _args]] call CBA_fnc_serverEvent; "" };

if (isNil "comspec_sse_orgs") then { comspec_sse_orgs = createHashMap; };
if (isNil "comspec_sse_orgMembers") then { comspec_sse_orgMembers = createHashMap; };
if (isNil "comspec_sse_anaSeen") then { comspec_sse_anaSeen = createHashMap; };
private _orgs = comspec_sse_orgs;
private _mem = comspec_sse_orgMembers;

private _publish = {
    // Génération d'un réseau : une seule diffusion à la fin (op « publish »).
    if (missionNamespace getVariable ["comspec_sse_orgBatch", false]) exitWith {};
    publicVariable "comspec_sse_orgs";
    publicVariable "comspec_sse_orgMembers";
    publicVariable "comspec_sse_anaSeen";
    ["comspec_sse_anaChanged", []] call CBA_fnc_globalEvent;
};
private _slug = {
    params ["_s"];
    "ORG-" + (toUpper (((trim _s) splitString " '-_/.,:;") joinString ""))
};
private _now = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
private _note = {
    params ["_by", "_msg"];
    if (_by isEqualType objNull && {!isNull _by} && {isPlayer _by}) then {
        ["comspec_sse_anaNote", [_msg], _by] call CBA_fnc_targetEvent;
    };
};

switch (_op) do {
    case "publish": { call _publish; true };
    case "ensure": {
        _args params [["_name", "", [""]], ["_kind", "", [""]], ["_parent", "", [""]], ["_threat", "", [""]], ["_desc", "", [""]]];
        _name = trim _name;
        if (_name isEqualTo "") exitWith { "" };
        private _id = [_name] call _slug;
        if !(_id in _orgs) then {
            _orgs set [_id, createHashMapFromArray [
                ["id", _id], ["name", _name], ["kind", "reseau"], ["parent", ""], ["threat", "moyenne"],
                ["desc", ""], ["known", 0], ["how", ""], ["created", call _now]
            ]];
        };
        private _o = _orgs get _id;
        if (_kind isNotEqualTo "") then { _o set ["kind", toLower _kind]; };
        if (_threat isNotEqualTo "") then { _o set ["threat", toLower _threat]; };
        if (_desc isNotEqualTo "") then { _o set ["desc", _desc]; };
        if ((trim _parent) isNotEqualTo "") then {
            private _pid = ["ensure", [_parent]] call comspec_sse_fnc_orgOp;
            // Pas de boucle : le parent ne peut pas descendre de cette organisation.
            private _walk = _pid;
            private _loop = false;
            for "_i" from 0 to 20 do {
                if (_walk isEqualTo "") exitWith {};
                if (_walk isEqualTo _id) exitWith { _loop = true; };
                _walk = (_orgs getOrDefault [_walk, createHashMap]) getOrDefault ["parent", ""];
            };
            if (!_loop) then { _o set ["parent", _pid]; };
        };
        call _publish;
        _id
    };
    case "set": {
        _args params [["_id", "", [""]], ["_k", "", [""]], ["_v", ""]];
        private _o = _orgs getOrDefault [_id, createHashMap];
        if (count _o == 0 || {_k in ["id", "parent"]}) exitWith { false };
        _o set [_k, _v];
        call _publish;
        true
    };
    case "delete": {
        _args params [["_id", "", [""]]];
        private _o = _orgs getOrDefault [_id, createHashMap];
        if (count _o == 0) exitWith { false };
        private _up = _o getOrDefault ["parent", ""];
        { if ((_y getOrDefault ["parent", ""]) isEqualTo _id) then { _y set ["parent", _up]; }; } forEach _orgs;
        {
            private _v = _mem get _x;
            if ((_v getOrDefault ["org", ""]) isEqualTo _id) then {
                if (_up isEqualTo "") then { _mem deleteAt _x; } else { _v set ["org", _up]; };
            };
        } forEach (keys _mem);
        _orgs deleteAt _id;
        call _publish;
        true
    };
    case "member": {
        _args params [["_u", objNull, [objNull]], ["_org", "", [""]], ["_role", "membre", [""]], ["_alias", "", [""]], ["_cell", "", [""]], ["_known", -1, [0]]];
        if (isNull _u || {(trim _org) isEqualTo ""}) exitWith { "" };
        private _target = ["ensure", [_org]] call comspec_sse_fnc_orgOp;
        if ((trim _cell) isNotEqualTo "") then {
            _target = ["ensure", [_cell, "cellule", _org]] call comspec_sse_fnc_orgOp;
        };
        private _key = [_u] call comspec_sse_fnc_bioPersonKey;
        private _prev = _mem getOrDefault [_key, createHashMap];
        private _lbl = [_u] call comspec_sse_fnc_anaLabel;
        if ((trim _alias) isEqualTo "") then { _alias = _lbl select 1; };
        if ((trim _role) isEqualTo "") then { _role = "membre"; };
        _mem set [_key, createHashMapFromArray [
            ["key", _key], ["org", _target], ["role", toLower _role], ["alias", trim _alias],
            ["known", [_prev getOrDefault ["known", 0], (_known max 0) min 2] select (_known >= 0)],
            ["how", _prev getOrDefault ["how", ["", "renseignement initial"] select (_known > 0)]],
            ["when", _prev getOrDefault ["when", ""]],
            ["netId", netId _u], ["name", _lbl select 0]
        ]];
        _u setVariable ["COMSPEC_SSE_Org", [_target, toLower _role], true];
        call _publish;
        _target
    };
    case "unmember": {
        _args params [["_u", objNull, [objNull]]];
        if (isNull _u) exitWith { false };
        _mem deleteAt ([_u] call comspec_sse_fnc_bioPersonKey);
        _u setVariable ["COMSPEC_SSE_Org", nil, true];
        call _publish;
        true
    };
    case "seen": {
        _args params [["_u", objNull, [objNull]]];
        if (isNull _u || {!(_u isKindOf "CAManBase")}) exitWith { false };
        private _key = [_u] call comspec_sse_fnc_bioPersonKey;
        if ((comspec_sse_anaSeen getOrDefault [_key, ""]) isEqualTo (netId _u)) exitWith { false };
        comspec_sse_anaSeen set [_key, netId _u];
        call _publish;
        true
    };
    case "reveal": {
        _args params [["_who", objNull, [objNull, ""]], ["_lvl", 1, [0]], ["_how", "", [""]], ["_by", objNull, [objNull]]];
        private _key = if (_who isEqualType "") then { _who } else { [_who] call comspec_sse_fnc_bioPersonKey };
        if (_who isEqualType objNull && {!isNull _who}) then { comspec_sse_anaSeen set [_key, netId _who]; };
        private _m = _mem getOrDefault [_key, createHashMap];
        if (count _m == 0 || {(_m getOrDefault ["known", 0]) >= _lvl}) exitWith { call _publish; false };
        _m set ["known", (_lvl max 1) min 2];
        _m set ["how", _how];
        _m set ["when", call _now];
        private _o = _orgs getOrDefault [_m get "org", createHashMap];
        if ((_o getOrDefault ["known", 0]) < 1) then { _o set ["known", 1]; _o set ["how", _how]; };
        call _publish;
        [_by, format ["%1 : %2, %3 de %4 (%5).",
            ["Lien soupçonné", "Appartenance confirmée"] select (_lvl >= 2),
            _m get "name",
            toLower (["roleLabel", _m get "role"] call comspec_sse_fnc_orgTables),
            _o getOrDefault ["name", "?"], _how]] call _note;
        true
    };
    case "revealorg": {
        _args params [["_id", "", [""]], ["_lvl", 1, [0]], ["_how", "", [""]], ["_by", objNull, [objNull]]];
        if (!(_id in _orgs)) then { _id = [_id] call _slug; };
        private _o = _orgs getOrDefault [_id, createHashMap];
        if (count _o == 0 || {(_o getOrDefault ["known", 0]) >= _lvl}) exitWith { false };
        _o set ["known", (_lvl max 1) min 2];
        _o set ["how", _how];
        call _publish;
        [_by, format ["Organisation repérée : %1 (%2).", _o get "name", _how]] call _note;
        true
    };
    case "exploit": {
        _args params [["_e", objNull, [objNull]], ["_by", objNull, [objNull]]];
        if (isNull _e) exitWith { false };
        // Une pièce rattachée à une organisation (attribut Eden, module Zeus) la révèle.
        private _linked = _e getVariable ["COMSPEC_SSE_OrgLinked", ""];
        if (_linked isEqualType "" && {(trim _linked) isNotEqualTo ""}) then {
            ["revealOrg", [[_linked] call _slug, 1, format ["pièce exploitée : %1", getText (configOf _e >> "displayName")], _by]] call comspec_sse_fnc_orgOp;
        };
        // Une personne exploitée (téléphone, carnet, documents) trahit deux de ses contacts.
        if (_e isKindOf "CAManBase") then {
            private _key = [_e] call comspec_sse_fnc_bioPersonKey;
            private _m = _mem getOrDefault [_key, createHashMap];
            if (count _m == 0) exitWith {};
            ["reveal", [_e, 1, "documents et téléphone saisis sur lui", _by]] call comspec_sse_fnc_orgOp;
            private _org = _m get "org";
            private _parent = (_orgs getOrDefault [_org, createHashMap]) getOrDefault ["parent", ""];
            private _pool = [];
            {
                private _o2 = _y getOrDefault ["org", ""];
                if (_x isNotEqualTo _key && {(_y getOrDefault ["known", 0]) < 1} && {_o2 isEqualTo _org || {_o2 isEqualTo _parent && {_parent isNotEqualTo ""}}}) then {
                    _pool pushBack _x;
                };
            } forEach _mem;
            {
                ["reveal", [_x, 1, format ["contact de %1", _m get "name"], _by]] call comspec_sse_fnc_orgOp;
            } forEach ((_pool call BIS_fnc_arrayShuffle) select [0, 2]);
        };
        // Un objet lié à une personne dans le graphe la fait soupçonner.
        if (!(_e isKindOf "CAManBase") && {!isNil "comspec_sse_linkGraph"}) then {
            {
                private _other = objectFromNetId ([_x getOrDefault ["sourceNetId", ""], _x getOrDefault ["targetNetId", ""]] select ((_x getOrDefault ["sourceNetId", ""]) isEqualTo (netId _e)));
                if (!isNull _other && {_other isKindOf "CAManBase"}) then {
                    ["reveal", [_other, 1, format ["lié à %1", getText (configOf _e >> "displayName")], _by]] call comspec_sse_fnc_orgOp;
                };
            } forEach ([_e] call comspec_sse_fnc_getLinks);
        };
        true
    };
    default { false };
}
