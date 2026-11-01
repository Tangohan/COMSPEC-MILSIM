/*
    Empreintes sha256 calculées par COMSPEC Link (HashText, 2.0.88) pour l'état de synchro des tenues.
      [texte, true]   -> empreinte du texte tel quel : payload_sha256 du site, clés de cache (adresse d'un portrait…).
      [tenue]         -> empreinte du CONTENU de la tenue (tableau sous n'importe quel format ACE, ou texte « [ … ] »).
      [texte]         -> contenu si le texte est une tenue (commence par « [ »), sinon texte tel quel.
    Retour : sha256 hexadécimal minuscule, ou "" (DLL plus ancienne, tenue illisible). Cache pour la session.

    Contenu : deux tenues avec les mêmes objets ont la même empreinte, quel que soit leur format (ACE étendue,
    emballée, texte d'export ACE du site, tenue seule), la casse des classes, l'ordre des objets dans la tenue, le
    gilet et le sac, ou les infos étendues (visage, voix, insigne). Avant, le texte brut était comparé : une tenue
    importée dans ACE depuis le texte d'export du site restait « pas à jour » alors que tout était identique.
    Forme normalisée « cv1 » (à reproduire à l'identique côté site si le site calcule un jour cette empreinte) :
      C(x) = x en minuscules si c'est un texte, sinon ""          N(x) = entier arrondi (« 30 »), « 0 » si absent
      arme W = "" si pas de classe, sinon [C classe, C bouche, C rail, C optique, M chargeur 1, M chargeur 2, C bipied]
               joints par « , » ; M = "" ou C classe + « / » + N munitions
      contenant K = "" si pas de classe, sinon C classe puis jetons triés (ordre ASCII) joints par « ; » :
               objet [classe, n] -> « i:classe » ; chargeur [classe, n, munitions] -> « m:classe/munitions » ;
               arme [[arme], n] -> « w: » + W ; sac/gilet rangé [classe, vrai/faux] -> « c:classe » (n = 1) ;
               jetons identiques fusionnés (quantités additionnées), puis « *quantité »
      texte = « cv1 » | W principale | W lanceur | W pistolet | K tenue | K gilet | K sac | C casque | C lunettes
              | W jumelles | objets assignés (C, vides retirés, triés, joints par « , »)   (séparateur « | »)
      empreinte = sha256 hexadécimal minuscule du texte (UTF-8).
*/
params [["_in", ""], ["_raw", false, [false]]];

private _fnSha = {
    params ["_t"];
    if (_t isEqualTo "") exitWith { "" };
    if (uiNamespace getVariable ["COMSPEC_ArsenalHashOff", false]) exitWith { "" };
    private _cache = uiNamespace getVariable ["COMSPEC_ArsenalHashCache", nil];
    if (isNil "_cache") then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ArsenalHashCache", _cache]; };
    private _hit = _cache getOrDefault [_t, ""];
    if (_hit isNotEqualTo "") exitWith { _hit };
    private _r = [(["COMSPECExtension", "HashText", [_t]] call comspec_overwatch_connect_fnc_extDirect)] call comspec_overwatch_connect_fnc_extResult;
    if (!(_r isEqualType "") || {(_r find "OK|") != 0} || {(count _r) != 67}) exitWith {
        // Commande absente (DLL < 2.0.88) : ne plus essayer de la session.
        uiNamespace setVariable ["COMSPEC_ArsenalHashOff", true];
        ["INFO", "ARSENAL", format ["HashText indisponible (%1) : état de synchro des tenues par nom seulement", _r]] call comspec_overwatch_connect_fnc_log;
        ""
    };
    private _sha = _r select [3];
    if ((count _cache) > 2000) then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ArsenalHashCache", _cache]; };
    _cache set [_t, _sha];
    _sha
};

if ((_in isEqualType "") && {_raw || {(_in select [0, 1]) isNotEqualTo "["}}) exitWith { [_in] call _fnSha };
if !((_in isEqualType "") || {_in isEqualType []}) exitWith { "" };

private _lo = [_in] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
if (_lo isEqualTo []) exitWith { "" };

private _cc = uiNamespace getVariable ["COMSPEC_ArsenalContentCache", nil];
if (isNil "_cc" || {(count _cc) > 1000}) then { _cc = createHashMap; uiNamespace setVariable ["COMSPEC_ArsenalContentCache", _cc]; };
private _ck = str _lo;
private _hit = _cc getOrDefault [_ck, ""];
if (_hit isNotEqualTo "") exitWith { _hit };

private _fnC = { params ["_v"]; if (_v isEqualType "") then { toLower _v } else { "" } };
private _fnN = { params ["_v"]; if (_v isEqualType 0) then { (round _v) toFixed 0 } else { "0" } };
private _fnHasClass = { params ["_a"]; (_a isEqualType []) && {(count _a) > 0} && {(_a select 0) isEqualType ""} && {(_a select 0) isNotEqualTo ""} };
private _fnM = { params ["_m"]; if ([_m] call _fnHasClass) then { ([_m select 0] call _fnC) + "/" + ([_m param [1, 0]] call _fnN) } else { "" } };
private _fnW = {
    params ["_a"];
    if !([_a] call _fnHasClass) exitWith { "" };
    [
        [_a select 0] call _fnC, [_a param [1, ""]] call _fnC, [_a param [2, ""]] call _fnC, [_a param [3, ""]] call _fnC,
        [_a param [4, []]] call _fnM, [_a param [5, []]] call _fnM, [_a param [6, ""]] call _fnC
    ] joinString ","
};
private _fnK = {
    params ["_a"];
    if !([_a] call _fnHasClass) exitWith { "" };
    private _items = _a param [1, []];
    if !(_items isEqualType []) then { _items = []; };
    private _counts = createHashMap;
    {
        if !((_x isEqualType []) && {(count _x) >= 2}) then { continue };
        private _it = _x select 0;
        private _n = _x select 1;
        private _t = switch (true) do {
            case (_it isEqualType []): { private _w = [_it] call _fnW; if (_w isEqualTo "") then { "" } else { "w:" + _w } };
            case (!(_it isEqualType "") || {_it isEqualTo ""}): { "" };
            case (_n isEqualType true): { "c:" + toLower _it };
            case ((count _x) >= 3): { "m:" + (toLower _it) + "/" + ([_x select 2] call _fnN) };
            default { "i:" + toLower _it };
        };
        if (_t isEqualTo "") then { continue };
        private _q = if (_n isEqualType 0) then { round _n } else { 1 };
        _counts set [_t, (_counts getOrDefault [_t, 0]) + _q];
    } forEach _items;
    private _toks = (keys _counts) apply { _x + "*" + ((_counts get _x) toFixed 0) };
    _toks sort true;
    ([[_a select 0] call _fnC] + _toks) joinString ";"
};

private _assigned = ((_lo select 9) select { (_x isEqualType "") && {_x isNotEqualTo ""} }) apply { toLower _x };
_assigned sort true;
private _text = [
    "cv1",
    [_lo select 0] call _fnW, [_lo select 1] call _fnW, [_lo select 2] call _fnW,
    [_lo select 3] call _fnK, [_lo select 4] call _fnK, [_lo select 5] call _fnK,
    [_lo select 6] call _fnC, [_lo select 7] call _fnC,
    [_lo select 8] call _fnW,
    _assigned joinString ","
] joinString "|";

private _sha = [_text] call _fnSha;
if (_sha isNotEqualTo "") then { _cc set [_ck, _sha]; };
_sha
