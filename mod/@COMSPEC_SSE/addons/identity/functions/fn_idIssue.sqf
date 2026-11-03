/*
    Délivre un document (SERVEUR) : il entre au registre, virtuel tant qu'il n'est pas matérialisé (fn_idMaterialize).
    [_unit, _spec] call comspec_sse_fnc_idIssue -> docId ("" si refusé)
    _spec (HashMap, tout facultatif) :
      type          id_card (défaut) | passport | driving_license
      country       code du pays (défaut : pays de la mission)
      kind          civil (défaut) | cover (couverture préparée, passe tous les contrôles sauf la biométrie)
                    | forged (faux) | generated | alias
      quality       0-100 (faux : plus c'est bas, plus les défauts sont nombreux et visibles)
      forgeryKind   counterfeit (contrefaçon) | altered (vrai document modifié) | photo_swap (photo substituée)
                    | stolen_blank (vierge volé) ; défaut tiré selon la qualité
      last, first, sex, dob, pob, number, issued, expires, chip    imposés (Zeus, paquet Athena)
      report        "" | vole | perdu | revoque      athenaId  identifiant Athena      materialize  true : objet tout de suite
    Vérité gardée sur le document (jamais envoyée aux joueurs) : trueLast, trueFirst, kind, quality, forgeryKind, flaws.
*/
params [["_u", objNull, [objNull]], ["_spec", createHashMap, [createHashMap]]];
if (!isServer) exitWith { "" };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _key = if (isNull _u) then { _spec getOrDefault ["holderKey", ""] } else { [_u] call comspec_sse_fnc_bioPersonKey };
if (_key isEqualTo "") then { _key = format ["X%1", floor random 999999]; };
private _type = _spec getOrDefault ["type", "id_card"];
if !(_type in ["id_card", "passport", "driving_license"]) then { _type = "id_card"; };
private _country = _spec getOrDefault ["country", ""];
if (_country isEqualTo "" || {_country isEqualTo "auto"}) then { _country = ["default"] call comspec_sse_fnc_idCountries; };
private _countries = [] call comspec_sse_fnc_idCountries;
if !(_country in _countries) then { _country = ["default"] call comspec_sse_fnc_idCountries; };
// Papier généré en mission : seulement par un pays actif qui délivre ce type (pays archivé sur Athena : ses papiers
// déjà émis, repris du paquet avec athenaId, restent valables et lisibles).
if ((_spec getOrDefault ["athenaId", ""]) isEqualTo "" && {!(["usable", [_country, _type]] call comspec_sse_fnc_idCountries)}) then {
    private _def = ["default"] call comspec_sse_fnc_idCountries;
    if (_def in _countries) then { _country = _def; };
};
private _c = _countries get _country;
private _kind = _spec getOrDefault ["kind", "civil"];
if !(_kind in ["civil", "cover", "forged", "generated", "alias"]) then { _kind = "civil"; };
private _q = ((_spec getOrDefault ["quality", 60]) max 0) min 100;
private _seq = (_reg get "seq") + 1;
_reg set ["seq", _seq];
private _id = str _seq;
while { count _id < 4 } do { _id = "0" + _id; };
_id = "ID" + _id;
private _r = { [_key, format ["iss|%1|%2|%3", _type, _kind, _this]] call comspec_sse_fnc_bioRand };

// --- Identité imprimée et vraie identité -----------------------------------------------------------------------
private _own = [_u, _key, "", _country] call comspec_sse_fnc_idGenerate;
private _printed = _own;
private _trueLast = _own get "last";
private _trueFirst = _own get "first";
if (_kind in ["cover", "forged", "alias"]) then {
    private _bioTrue = if (isNull _u) then { "" } else { trim (_u getVariable ["COMSPEC_SSE_BioTrueName", ""]) };
    if (_bioTrue isNotEqualTo "") then {
        // Vraie identité saisie par la mission : le nom déclaré (papiers) est le faux.
        private _p = _bioTrue splitString " ";
        _trueFirst = _p param [0, _trueFirst];
        _trueLast = [(_p select [1, 9]) joinString " ", _trueLast] select ((count _p) < 2);
    } else {
        // Même fausse identité pour tous les faux papiers de la personne.
        _printed = [_u, _key, "alt", _country] call comspec_sse_fnc_idGenerate;
    };
};
{ if ((_spec getOrDefault [_x, ""]) isNotEqualTo "") then { _printed set [_x, _spec get _x]; }; } forEach ["last", "first", "sex", "dob", "pob"];

// --- Dates, autorité, numéro -----------------------------------------------------------------------------------
date params ["_yy", "_mm", "_dd"];
private _two = { if (_this < 10) then { "0" + str _this } else { str _this } };
private _ymd = { params ["_y", "_m", "_d"]; format ["%1-%2-%3", _y, _m call _two, _d call _two] };
private _valid = _c getOrDefault ["validity", 10];
// Durées par type (pays du paquet Athena, pays réels stylisés) ; sinon 10 ans, permis 15 ans.
_valid = if (_valid isEqualType createHashMap) then { _valid getOrDefault [_type, [10, 15] select (_type isEqualTo "driving_license")] } else { [_valid, 15] select (_type isEqualTo "driving_license") };
if !(_valid isEqualType 0) then { _valid = 10; };
_valid = _valid max 1;
private _back = floor (("age" call _r) * (_valid - 1));
private _im = 1 + ((floor (("im" call _r) * 12)) min 11);
private _id_ = 1 + ((floor (("id" call _r) * 28)) min 27);
private _iy = _yy - _back;
if (_iy isEqualTo _yy && {_im > _mm}) then { _im = _mm; };
private _issued = [_iy, _im, _id_] call _ymd;
private _expires = [_iy + _valid, _im, _id_] call _ymd;
// Quelques civils ont une pièce périmée (1 à 24 mois) : de quoi douter sans que ce soit un faux.
if (_kind isEqualTo "civil" && {("expired" call _r) < 0.04}) then {
    private _late = 1 + floor (("late" call _r) * 24);
    private _em = _mm - (_late mod 12);
    private _ey = _yy - floor (_late / 12);
    if (_em < 1) then { _em = _em + 12; _ey = _ey - 1; };
    _expires = [_ey, _em, 1] call _ymd;
};
if ((_spec getOrDefault ["expires", ""]) isNotEqualTo "") then { _expires = _spec get "expires"; };
private _cities = _c getOrDefault ["cities", ["-"]];
private _city = _cities select (floor (("city" call _r) * (count _cities)) min ((count _cities) - 1));
private _streets = ["RUE DU PORT", "AV. CENTRALE", "RUE DE L'ÉGLISE", "CH. DES OLIVIERS", "PL. DU MARCHÉ", "RUE HAUTE", "BD DE LA MER"];
private _addr = format ["%1 %2, %3", 1 + floor (("no" call _r) * 120), _streets select (floor (("st" call _r) * (count _streets)) min 6), _city];
private _number = _spec getOrDefault ["number", ""];
if (_number isEqualTo "") then { _number = [_country, _type, format ["%1|%2", _key, _kind]] call comspec_sse_fnc_idDocNumber; };

private _doc = createHashMapFromArray [
    ["id", _id], ["cls", ""], ["type", _type], ["country", _country], ["mrzIssuer", _c getOrDefault ["mrz", _country]], ["number", _number],
    ["last", _printed get "last"], ["first", _printed get "first"], ["sex", _printed get "sex"], ["dob", _printed get "dob"],
    ["pob", _printed get "pob"], ["nat", _c getOrDefault ["mrz", _country]], ["profession", _printed getOrDefault ["profession", ""]],
    ["issued", _issued], ["expires", _expires], ["authority", [_c getOrDefault ["authority", ""], format ["Préfecture de %1", _city]] select ((_c getOrDefault ["authority", ""]) isEqualTo "")], ["address", _addr],
    ["chip", ["absent", "ok"] select (_type in ["id_card", "passport"])], ["chipExpected", _type in ["id_card", "passport"]],
    ["kind", _kind], ["quality", _q], ["forgeryKind", ""], ["flaws", []], ["report", _spec getOrDefault ["report", ""]],
    ["holderKey", _key], ["trueLast", _trueLast], ["trueFirst", _trueFirst], ["athenaId", _spec getOrDefault ["athenaId", ""]],
    ["chipNote", ""], ["issuedAt", time]
];

// --- Faux : défauts selon le procédé et la qualité ------------------------------------------------------------
private _flaws = _doc get "flaws";
private _baseName = [_doc get "last", _doc get "first"];
private _inBase = true;
private _baseReport = _doc get "report";
if (_kind isEqualTo "forged") then {
    private _fk = _spec getOrDefault ["forgeryKind", ""];
    if !(_fk in ["counterfeit", "altered", "photo_swap", "stolen_blank"]) then {
        private _t = "fk" call _r;
        _fk = if (_q < 40) then { ["counterfeit", "altered"] select (_t < 0.4) } else { ["altered", "photo_swap", "stolen_blank", "counterfeit"] select (floor (_t * 4) min 3) };
    };
    _doc set ["forgeryKind", _fk];
    switch (_fk) do {
        case "counterfeit": {
            if (_q < 70) then { _flaws pushBack "holo"; };
            if (_q < 60) then { _flaws pushBack "font"; };
            if (_q < 45) then { _doc set ["mrzFlaw", "check"]; _flaws pushBack "mrz"; };
            if (_doc get "chipExpected") then { _doc set ["chip", ["mismatch", "absent"] select (_q < 80)]; };
            _inBase = false;
        };
        case "altered": {
            if (_q < 75) then { _flaws pushBack "font"; };
            private _orig = [_u, _key, "orig", _country] call comspec_sse_fnc_idGenerate;
            if (_q < 60) then { _doc set ["mrzLast", _orig get "last"]; _flaws pushBack "mrzdata"; };
            if (_doc get "chipExpected") then { _doc set ["chip", ["mismatch", "defect"] select (_q >= 90)]; };
            _baseName = [_orig get "last", _orig get "first"];
        };
        case "photo_swap": {
            if (_q < 85) then { _flaws pushBack "photo"; };
            if (_doc get "chipExpected") then { _doc set ["chip", "mismatch"]; _doc set ["chipNote", "photo"]; };
            if (("rep" call _r) < 0.5) then { _baseReport = "vole"; };
        };
        case "stolen_blank": {
            if (_q < 50) then { _flaws pushBack "font"; };
            if (_doc get "chipExpected") then { _doc set ["chip", ["defect", "absent"] select (("chip" call _r) < 0.5)]; };
            private _orig = [_u, _key, "blank", _country] call comspec_sse_fnc_idGenerate;
            _baseName = [_orig get "last", _orig get "first"];
            _baseReport = "vole";
        };
    };
};

// Faux enregistré sur Athena : inconnu des fichiers, comme sur le site.
if (_kind isEqualTo "forged" && {(_spec getOrDefault ["athenaId", ""]) isNotEqualTo ""}) then { _inBase = false; };
// Valeurs imposées (paquet Athena, Zeus) : puce et date de délivrance.
if ((_spec getOrDefault ["chip", ""]) in ["ok", "absent", "defect", "mismatch"] && {_doc get "chipExpected"}) then { _doc set ["chip", _spec get "chip"]; };
if ((_spec getOrDefault ["issued", ""]) isNotEqualTo "") then { _doc set ["issued", _spec get "issued"]; };

// --- Registre ---------------------------------------------------------------------------------------------------
(_reg get "docs") set [_id, _doc];
private _units = _reg get "units";
private _mine = _units getOrDefault [_key, []];
_mine pushBack _id;
_units set [_key, _mine];
if (_inBase) then {
    (_reg get "issued") set [format ["%1|%2|%3", _country, _type, _number], [_id, _baseName select 0, _baseName select 1, _baseReport]];
};
if (!isNull _u && {_spec getOrDefault ["materialize", false]}) then { [_id, _u] call comspec_sse_fnc_idMaterialize; };
_id
