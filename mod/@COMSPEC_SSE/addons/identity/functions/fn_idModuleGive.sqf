/*
    Module Zeus « Donner des papiers » (SERVEUR, appelé par fn_idServer « give » après contrôle du curateur).
    [_unit, _spec] call comspec_sse_fnc_idModuleGive -> docId
      _spec : comme fn_idIssue ; « athena » = numéro d'un document du paquet Athena (ses données sont reprises)
    ["athenaSpec", clé "PAYS|type|numéro"] call comspec_sse_fnc_idModuleGive -> _spec d'un document Athena (athenaId : id du
      papier, athenaIdentity : id de l'identité Athena qui le porte)
    Les papiers donnés par le Zeus sont matérialisés tout de suite (objet dans l'inventaire de la personne).
*/
params [["_u", objNull, [objNull, ""]], ["_spec", createHashMap, [createHashMap, ""]]];
if (!isServer) exitWith { "" };
if (_u isEqualType "") exitWith {
    private _d = ((missionNamespace getVariable ["comspec_sse_idReg", createHashMap]) getOrDefault ["athena", createHashMap]) getOrDefault [_spec, createHashMap];
    if ((count _d) isEqualTo 0) exitWith { createHashMap };
    private _i = _d getOrDefault ["identity", createHashMap];
    // Nature : origine du papier (official | cover | forged | generated), sinon genre de l'identité.
    private _kind = ["civil", "cover", "forged", "generated"] select ((["official", "cover", "forged", "generated"] find (_d getOrDefault ["origin", ""])) max 0);
    if ((_d getOrDefault ["origin", ""]) isEqualTo "" && {(_i getOrDefault ["kind", ""]) in ["cover", "forged", "generated", "alias"]}) then {
        _kind = [_i get "kind", "cover"] select ((_i get "kind") isEqualTo "alias");
    };
    private _q = _d getOrDefault ["forgery_quality", 60];
    if (_q isEqualType "") then { _q = [parseNumber _q, 60] select (_q isEqualTo ""); };
    private _fk = ["counterfeit", "photo_swap", "altered", "stolen_blank"] select ((["counterfeit", "photo_swap", "data_alteration", "stolen_blank"] find (_d getOrDefault ["forgery_kind", ""])) max 0);
    private _rep = ["", "vole", "perdu"] select ((["stolen", "lost"] find (_d getOrDefault ["report", ""])) + 1);
    if ((_d getOrDefault ["validity", ""]) in ["revoked", "suspended"]) then { _rep = "revoque"; };
    private _sx = toUpper (_i getOrDefault ["sex", "M"]);
    createHashMapFromArray [
        ["type", _d getOrDefault ["type", "id_card"]], ["country", _d getOrDefault ["country", ""]], ["number", _d getOrDefault ["number", ""]],
        ["last", _i getOrDefault ["last_name", ""]], ["first", _i getOrDefault ["first_name", ""]], ["sex", ["M", "F"] select (_sx isEqualTo "F")],
        ["dob", _i getOrDefault ["birth_date", ""]], ["pob", _i getOrDefault ["birth_place", ""]], ["issued", _d getOrDefault ["issued_on", ""]],
        ["expires", _d getOrDefault ["expires_on", ""]], ["chip", _d getOrDefault ["chip", ""]], ["kind", _kind], ["quality", _q],
        ["forgeryKind", ["", _fk] select (_kind isEqualTo "forged" && {(_d getOrDefault ["forgery_kind", ""]) isNotEqualTo ""})],
        ["report", _rep],
        ["athenaId", str (_d getOrDefault ["id", ""])],
        ["athenaIdentity", call {
            private _iid = _d getOrDefault ["identity_id", ""];
            if (_iid isEqualType 0) exitWith { ["", str _iid] select (_iid > 0) };
            [_iid, ""] select !(_iid isEqualType "")
        }]
    ]
};
if (isNull _u || {!(_u isKindOf "CAManBase")}) exitWith { "" };
private _num = trim (_spec getOrDefault ["athena", ""]);
if (_num isNotEqualTo "") then {
    private _ath = (missionNamespace getVariable ["comspec_sse_idReg", createHashMap]) getOrDefault ["athena", createHashMap];
    private _k = (keys _ath) select { (toUpper ((_x splitString "|") param [2, ""])) isEqualTo (toUpper _num) };
    if (_k isNotEqualTo []) then { _spec = ["athenaSpec", _k select 0] call comspec_sse_fnc_idModuleGive; };
};
_spec set ["materialize", true];
// Papiers donnés à la main : l'automatique ne s'ajoute plus par-dessus.
(comspec_sse_idReg get "decided") set [[_u] call comspec_sse_fnc_bioPersonKey, true];
[_u, _spec] call comspec_sse_fnc_idIssue
