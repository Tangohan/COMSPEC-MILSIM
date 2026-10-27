/*
    Chaîne de possession des pièces à conviction (serveur).

    Chaque pièce reçoit un numéro (P-<dossier>-0001) à sa saisie, un numéro de
    scellé (SC-000000) quand elle est mise sous scellé, puis un nouveau scellé
    chaque fois qu'on la rescelle après l'avoir ouverte. Chaque geste est inscrit
    au journal de la pièce : date et heure, geste, qui, où, remarque. Chaque ligne
    porte l'empreinte de la précédente (hashValue) : une ligne modifiée après
    coup casse la chaîne et la vérification la désigne.

    [_op, _args] call comspec_sse_fnc_custody
      "open"     [entité, acteur, remarque]          saisie (crée la pièce)
      "seal"     [entité, acteur, remarque]          mise sous scellé (crée la pièce au besoin)
      "event"    [entité, geste, acteur, remarque]   geste sur une pièce existante
                 (EXPLOITATION et ANALYSE ouvrent d'abord le scellé)
      "transfer" [de, à]                             remise de toutes les pièces détenues
      "tamper"   [entité | id]                       exercice : altère la dernière ligne
      "piece"    [entité] → id | ""                  (lecture locale)
      "held"     [unité] → [ids]                     (lecture locale)

    État diffusé : comspec_sse_custody (id → pièce).
*/
params [["_op", "", [""]], ["_args", [], [[]]]];
_op = toLower _op;

private _uidOf = {
    params ["_u"];
    if (isNull _u) exitWith { "" };
    private _p = getPlayerUID _u;
    if (_p isNotEqualTo "") then { _p } else { netId _u }
};

if (_op isEqualTo "piece") exitWith {
    _args params [["_e", objNull, [objNull]]];
    _e getVariable ["COMSPEC_SSE_PieceId", ""]
};
if (_op isEqualTo "held") exitWith {
    _args params [["_u", objNull, [objNull]]];
    private _uid = [_u] call _uidOf;
    private _all = missionNamespace getVariable ["comspec_sse_custody", createHashMap];
    (keys _all) select { private _p = _all get _x; (_p getOrDefault ["holderUid", ""]) isEqualTo _uid && {!(_p getOrDefault ["archived", false])} }
};
if (!isServer) exitWith { [_op, _args] remoteExecCall ["comspec_sse_fnc_custody", 2]; "" };
// Demande venue d'un client (appel distant) : demandeur retrouvé par remoteExecutedOwner, jamais fourni par le client.
// « tamper » (exercice) : Zeus ou administrateur ; « transfer » : seul le détenteur remet ses propres pièces.
if (isRemoteExecuted && {remoteExecutedOwner != 2} && {_op in ["tamper", "transfer"]}) then {
    private _ro = remoteExecutedOwner;
    private _req = (allPlayers select { owner _x == _ro }) param [0, objNull];
    private _ok = if (_op isEqualTo "tamper") then { [_req] call comspec_sse_fnc_isAuthority } else { !isNull _req && {_req isEqualTo (_args param [0, objNull])} };
    if (!_ok) then {
        diag_log text format ["[COMSPEC SSE] Scellés : « %1 » refusé au poste %2.", _op, _ro];
        _op = "refused";
    };
};

if (isNil "comspec_sse_custody") then { comspec_sse_custody = createHashMap; };
if (isNil "comspec_sse_custodyN") then { comspec_sse_custodyN = 0; };
if (isNil "comspec_sse_custodySealN") then { comspec_sse_custodySealN = 100000 + floor (random 800000); };

private _dtg = {
    date params ["_y", "_m", "_d", "_h", "_mi"];
    format ["%1/%2/%3 %4:%5",
        [str _d, "0" + str _d] select (_d < 10), [str _m, "0" + str _m] select (_m < 10), _y,
        [str _h, "0" + str _h] select (_h < 10), [str _mi, "0" + str _mi] select (_mi < 10)]
};
private _note = {
    params ["_by", "_msg"];
    if (_by isEqualType objNull && {!isNull _by} && {isPlayer _by}) then {
        ["comspec_sse_anaNote", [_msg], _by] call CBA_fnc_targetEvent;
    };
};
private _publish = {
    params ["_p", ["_registry", true]];
    // _registry false : seule la fiche de l'entité est recopiée (remise groupée, registre diffusé une fois après).
    if (_registry) then {
        publicVariable "comspec_sse_custody";
        ["comspec_sse_anaChanged", []] call CBA_fnc_globalEvent;
    };
    private _e = objectFromNetId (_p getOrDefault ["netId", ""]);
    // Recopie sur l'entité : la fiche transmise au portail emporte sa chaîne.
    if (!isNull _e) then { [_e, "custody", +_p, true] call comspec_sse_fnc_setSection; };
};
private _append = {
    params ["_p", "_action", "_by", "_remark"];
    private _ev = _p get "events";
    private _prev = if (_ev isEqualTo []) then { "ORIGINE" } else { (_ev select -1) select 6 };
    private _who = if (isNull _by) then { "Système" } else { name _by };
    private _pos = if (isNull _by) then { getPosATL (objectFromNetId (_p get "netId")) } else { getPosATL _by };
    private _row = [call _dtg, _action, _who, [_by] call _uidOf, mapGridPosition _pos, _remark];
    _row pushBack (hashValue ([_prev] + _row));
    _ev pushBack _row;
};
private _create = {
    params ["_e", "_by"];
    private _have = _e getVariable ["COMSPEC_SSE_PieceId", ""];
    if (_have isNotEqualTo "" && {_have in comspec_sse_custody}) exitWith { comspec_sse_custody get _have };
    comspec_sse_custodyN = comspec_sse_custodyN + 1;
    private _case = "";
    if (!isNil "comspec_sse_fnc_getCaseReference") then { _case = [] call comspec_sse_fnc_getCaseReference; };
    if (!(_case isEqualType "") || {_case isEqualTo ""}) then { _case = missionNamespace getVariable ["comspec_sse_missionId", "OP"]; };
    private _n = str comspec_sse_custodyN;
    while { count _n < 4 } do { _n = "0" + _n; };
    private _id = format ["%1-%2-%3", missionNamespace getVariable ["comspec_sse_ana_piecePrefix", "P"], _case, _n];
    private _label = "";
    if (!isNil "comspec_sse_fnc_makeEvidenceLabel") then { _label = [_e] call comspec_sse_fnc_makeEvidenceLabel; };
    private _data = [_e] call comspec_sse_fnc_getData;
    private _desc = getText (configOf _e >> "displayName");
    if (_e isKindOf "CAManBase") then { _desc = format ["Effets personnels de %1", ([_e] call comspec_sse_fnc_anaLabel) select 0]; };
    private _p = createHashMapFromArray [
        ["id", _id], ["seal", ""], ["seals", []], ["label", _label], ["netId", netId _e],
        ["uid", if (isNil "_data") then { "" } else { [_data, "uid", ""] call BIS_fnc_getFromPairs }],
        ["cls", typeOf _e], ["desc", _desc], ["state", "SAISIE"],
        ["holder", if (isNull _by) then { "" } else { name _by }], ["holderUid", [_by] call _uidOf],
        ["events", []], ["tampered", false]
    ];
    comspec_sse_custody set [_id, _p];
    _e setVariable ["COMSPEC_SSE_PieceId", _id, true];
    [_p, "SAISIE", _by, format ["%1, %2", _desc, _label]] call _append;
    _p
};

switch (_op) do {
    case "open": {
        _args params [["_e", objNull, [objNull]], ["_by", objNull, [objNull]], ["_remark", "", [""]]];
        if (isNull _e) exitWith { "" };
        private _had = (_e getVariable ["COMSPEC_SSE_PieceId", ""]) in comspec_sse_custody;
        private _p = [_e, _by] call _create;
        if (_had && {_remark isNotEqualTo ""}) then { [_p, "COLLECTE", _by, _remark] call _append; };
        [_p] call _publish;
        if (!_had) then { [_by, format ["Pièce %1 ouverte : %2.", _p get "id", _p get "desc"]] call _note; };
        _p get "id"
    };
    case "seal": {
        _args params [["_e", objNull, [objNull]], ["_by", objNull, [objNull]], ["_remark", "", [""]]];
        if (isNull _e) exitWith { "" };
        private _p = [_e, _by] call _create;
        if ((_p get "state") isEqualTo "SCELLÉE") exitWith { [_by, format ["Pièce %1 déjà sous scellé n° %2.", _p get "id", _p get "seal"]] call _note; _p get "id" };
        comspec_sse_custodySealN = comspec_sse_custodySealN + 1;
        private _seal = format ["%1-%2", missionNamespace getVariable ["comspec_sse_ana_sealPrefix", "SC"], comspec_sse_custodySealN];
        private _again = (_p get "seals") isNotEqualTo [];
        (_p get "seals") pushBack _seal;
        _p set ["seal", _seal];
        _p set ["state", "SCELLÉE"];
        if (!isNull _by) then { _p set ["holder", name _by]; _p set ["holderUid", [_by] call _uidOf]; };
        [_p, ["MISE SOUS SCELLÉ", "RESCELLÉE"] select _again, _by, ([format ["scellé n° %1", _seal], _remark] select { _x isNotEqualTo "" }) joinString ", "] call _append;
        [_p] call _publish;
        [_by, format ["Scellé n° %1 posé sur la pièce %2.", _seal, _p get "id"]] call _note;
        _p get "id"
    };
    case "event": {
        _args params [["_e", objNull, [objNull]], ["_action", "", [""]], ["_by", objNull, [objNull]], ["_remark", "", [""]]];
        private _id = _e getVariable ["COMSPEC_SSE_PieceId", ""];
        if (!(_id in comspec_sse_custody)) exitWith { "" };
        private _p = comspec_sse_custody get _id;
        _action = toUpper _action;
        if (_action in ["EXPLOITATION", "ANALYSE"] && {(_p get "state") isEqualTo "SCELLÉE"}) then {
            [_p, "OUVERTURE DU SCELLÉ", _by, format ["scellé n° %1 brisé pour %2", _p get "seal", toLower _action]] call _append;
            _p set ["state", "OUVERTE"];
            [_by, format ["Scellé n° %1 brisé. Pensez à resceller la pièce %2.", _p get "seal", _id]] call _note;
        };
        [_p, _action, _by, _remark] call _append;
        if (_action isEqualTo "ANALYSE") then { _p set ["state", "ANALYSÉE"]; };
        [_p] call _publish;
        _id
    };
    case "transfer": {
        _args params [["_from", objNull, [objNull]], ["_to", objNull, [objNull]]];
        if (isNull _from || {isNull _to}) exitWith { 0 };
        private _ids = ["held", [_from]] call comspec_sse_fnc_custody;
        {
            private _p = comspec_sse_custody get _x;
            [_p, "REMISE", _from, format ["de %1 à %2, scellé %3", name _from, name _to, ["non posé", "intact"] select ((_p get "state") isEqualTo "SCELLÉE")]] call _append;
            _p set ["holder", name _to];
            _p set ["holderUid", [_to] call _uidOf];
            [_p, false] call _publish;
        } forEach _ids;
        // Registre et événement une seule fois pour toute la remise.
        if (_ids isNotEqualTo []) then {
            publicVariable "comspec_sse_custody";
            ["comspec_sse_anaChanged", []] call CBA_fnc_globalEvent;
        };
        // Les sachets scellés changent de mains, une fois pour toutes les pièces. removeItem / addItem n'agissent que
        // là où l'unité est locale (le joueur, pas le serveur) : l'inventaire est modifié chez chacun.
        private _bags = ({ _x isEqualTo "COMSPEC_SSE_EvidenceBag" } count (items _from)) min (count _ids);
        if (_bags > 0) then {
            ["comspec_sse_custodyBags", [_from, -_bags], _from] call CBA_fnc_targetEvent;
            ["comspec_sse_custodyBags", [_to, _bags], _to] call CBA_fnc_targetEvent;
        };
        if (_ids isNotEqualTo []) then {
            [_to, format ["%1 vous remet %2 pièce(s) : %3.", name _from, count _ids, _ids joinString ", "]] call _note;
            [_from, format ["%1 pièce(s) remise(s) à %2, inscrit au registre.", count _ids, name _to]] call _note;
        };
        count _ids
    };
    case "tamper": {
        _args params [["_what", objNull, [objNull, ""]]];
        private _id = if (_what isEqualType "") then { _what } else { _what getVariable ["COMSPEC_SSE_PieceId", ""] };
        if (!(_id in comspec_sse_custody)) exitWith { false };
        private _p = comspec_sse_custody get _id;
        private _ev = _p get "events";
        if (_ev isEqualTo []) exitWith { false };
        private _row = _ev select -1;
        _row set [5, (_row select 5) + " (corrigé)"];
        _row set [0, call _dtg];
        _p set ["tampered", true];
        [_p] call _publish;
        true
    };
    default { "" };
}
