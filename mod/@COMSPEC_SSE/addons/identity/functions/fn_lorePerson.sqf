/*
    Fiche d'une IA pour la remontée du lore de session (SERVEUR, kind « lore_push », ICD-SSE-LORE-001).
    [_unit] call comspec_sse_fnc_lorePerson -> HashMap (vide : pas une IA, ou sans identifiant)
    Réutilise ce que le SSE sait déjà, sans rien décider ni fabriquer de papier :
      identité vraie     fn_idGenerate (même tirage que le site et que les vrais papiers ; nom déclaré SSE compris),
                         papiers déjà décidés du registre (comspec_sse_idReg, pas d'« ensure »), profil Athena relié ;
      générateur         fiche « identity » (alias, nationalité, téléphone) et « biometrics » (taille, carrure) de
                         comspec_sse_data (comspec_sse_fnc_getSection) ;
      atelier IA         profil RP du serveur (comspec_sse_iarpProfile) : savoir, secrets, histoire, réponses écrites ;
      fichage            base biométrique (COMSPEC_SSE_BioDb), HVT et BOLO (fn_idHvt « of » / « faceFlag »).
    Champs vides : non envoyés (le site ne complète que ce qui est vide chez lui). Textes nettoyés et bornés.
    Pays : COMSPEC_ID_Country de l'unité, sinon celui de ses papiers, sinon le pays par défaut de la mission.
*/
params [["_u", objNull, [objNull]]];
if (!isServer || {isNull _u} || {!(_u isKindOf "CAManBase")} || {isPlayer _u}) exitWith { createHashMap };
private _uid = ["assign", _u] call comspec_sse_fnc_loreUid;
if (_uid isEqualTo "") exitWith { createHashMap };
private _nl = toString [10];
// Texte sûr pour le JSON : caractères de contrôle retirés (saut de ligne gardé, envoyé « \n »), longueur bornée.
private _txt = {
    params [["_s", ""], ["_max", 200]];
    if !(_s isEqualType "") then { _s = str _s; };
    private _a = (toArray _s) select { _x >= 32 || {_x isEqualTo 10} };
    private _o = trim (toString (_a select [0, _max]));
    [_o, _nl, endl] call CBA_fnc_replace
};
private _p = createHashMap;
private _put = { params ["_k", "_v"]; if (_v isEqualType "" && {_v isEqualTo ""}) exitWith {}; if (_v isEqualType [] && {_v isEqualTo []}) exitWith {}; _p set [_k, _v]; };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _key = [_u] call comspec_sse_fnc_bioPersonKey;

// --- Identité vraie (tirage reproductible) puis papiers déjà décidés.
private _own = [_u] call comspec_sse_fnc_idGenerate;
private _last = _own getOrDefault ["last", ""];
private _first = _own getOrDefault ["first", ""];
private _dob = _own getOrDefault ["dob", ""];
private _country = _u getVariable ["COMSPEC_ID_Country", "auto"];
if !(_country isEqualType "") then { _country = "auto"; };
private _cover = [];
private _docs = (((_reg get "units") getOrDefault [_key, []]) apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry }) select { (count _x) > 0 };
{
    private _kind = _x getOrDefault ["kind", "civil"];
    if (_kind in ["civil", "generated"]) then {
        if ((_x getOrDefault ["last", ""]) isNotEqualTo "") then { _last = _x get "last"; _first = _x getOrDefault ["first", _first]; };
        if ((_x getOrDefault ["dob", ""]) isNotEqualTo "") then { _dob = _x get "dob"; };
        if (_country isEqualTo "auto" && {(_x getOrDefault ["country", ""]) isNotEqualTo ""}) then { _country = _x get "country"; };
    } else {
        // Couverture, faux, alias : ce que disent les papiers, pas qui il est.
        _cover pushBackUnique format ["%1 %2 (%3)", _x getOrDefault ["first", ""], toUpper (_x getOrDefault ["last", ""]), _kind];
        if ((_x getOrDefault ["trueLast", ""]) isNotEqualTo "") then { _last = _x get "trueLast"; _first = _x getOrDefault ["trueFirst", _first]; };
    };
} forEach _docs;
// Profil Athena relié (papiers vrais du profil) : son nom est le sien.
private _pid = _u getVariable ["COMSPEC_ID_AiProfile", ""];
if (_pid isEqualType "" && {_pid isNotEqualTo ""} && {_cover isEqualTo []}) then {
    private _pn = ((_reg getOrDefault ["aiProfiles", createHashMap]) getOrDefault [_pid, createHashMap]) getOrDefault ["name", ""];
    if (_pn isEqualType "" && {(trim _pn) isNotEqualTo ""}) then {
        private _w = (trim _pn) splitString " ";
        _first = _w param [0, _first];
        if ((count _w) > 1) then { _last = (_w select [1, 9]) joinString " "; };
    };
};
if (_country isEqualTo "auto" || {_country isEqualTo ""}) then { _country = ["default"] call comspec_sse_fnc_idCountries; };

private _sec = [_u, "identity"] call comspec_sse_fnc_getSection;
if (isNil "_sec" || {!(_sec isEqualType createHashMap)}) then { _sec = createHashMap; };
private _bio = [_u, "biometrics"] call comspec_sse_fnc_getSection;
if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
private _ia = if (isNil "comspec_sse_fnc_iarpProfile") then { createHashMap } else { ["normalize", _u getVariable ["comspec_sse_iarpProfile", createHashMap]] call comspec_sse_fnc_iarpProfile };
private _facts = if ((count _ia) > 0) then { ["facts", _ia] call comspec_sse_fnc_iarpProfile } else { createHashMap };

["uid", _uid] call _put;
["country", [_country, 3] call _txt] call _put;
["last", [_last, 80] call _txt] call _put;
["first", [_first, 80] call _txt] call _put;
["sex", _own getOrDefault ["sex", "M"]] call _put;
if (_dob regexMatch "[0-9]{4}-[0-9]{2}-[0-9]{2}") then { ["dob", _dob] call _put; };
["birth_place", [_facts getOrDefault ["pob", _own getOrDefault ["pob", ""]], 80] call _txt] call _put;
["nationality", [_sec getOrDefault ["nationality", _facts getOrDefault ["nation", _own getOrDefault ["nat", ""]]], 60] call _txt] call _put;
["profession", [_facts getOrDefault ["job", _own getOrDefault ["profession", ""]], 80] call _txt] call _put;
private _aliases = [];
{ if (_x isEqualType "" && {(trim _x) isNotEqualTo ""}) then { _aliases pushBackUnique ([_x, 60] call _txt); }; } forEach [
    _u getVariable ["COMSPEC_SSE_Alias", ""], _sec getOrDefault ["alias", ""],
    (["of", [_u]] call comspec_sse_fnc_idHvt) getOrDefault ["alias", ""]
];
["aliases", _aliases select [0, 10]] call _put;
private _h = _bio getOrDefault ["heightCm", 0];
if (_h isEqualType 0 && {_h >= 100} && {_h <= 230}) then { ["height_cm", round _h] call _put; };
["build", [_bio getOrDefault ["build", ""], 40] call _txt] call _put;
["addresses", [[_facts getOrDefault ["address", ""], 120] call _txt] select { _x isNotEqualTo "" }] call _put;
private _phones = [];
{ private _t = [_x, 40] call _txt; if (_t isNotEqualTo "") then { _phones pushBackUnique _t; }; } forEach [_sec getOrDefault ["phone", ""], _facts getOrDefault ["phone", ""]];
["phones", _phones] call _put;

// Liens : parent déclaré (attribut biométrique) ; objet du lien : HashMap (jamais une paire, voir fn_toJsonApprox).
private _links = [];
private _rel = _u getVariable ["COMSPEC_SSE_BioRelativeOf", ""];
if (_rel isEqualType "" && {_rel isNotEqualTo ""}) then {
    private _o = missionNamespace getVariable [_rel, objNull];
    if (_o isEqualType objNull && {!isNull _o}) then {
        private _ou = ["assign", _o] call comspec_sse_fnc_loreUid;
        if (_ou isNotEqualTo "") then { _links pushBack createHashMapFromArray [["uid", _ou], ["relation", [_u getVariable ["COMSPEC_SSE_BioRelation", "frere"], 30] call _txt]]; };
    };
};
["links", _links] call _put;

// Fichage : base biométrique, HVT, BOLO, arme.
private _db = toLower (_u getVariable ["COMSPEC_SSE_BioDb", "auto"]);
private _hvt = ["of", [_u]] call comspec_sse_fnc_idHvt;
private _bolo = ["faceFlag", [_u]] call comspec_sse_fnc_idHvt;
private _lvl = _hvt getOrDefault ["level", ""];
_p set ["status", createHashMapFromArray [
    ["wanted", _db isEqualTo "bewl2" || {_lvl isEqualTo "recherche"} || {(_bolo param [1, ""]) isEqualTo "recherche"}],
    ["bolo", _bolo isNotEqualTo []],
    ["hvt", _db isEqualTo "bewl1" || {_lvl isEqualTo "hvt"} || {(_bolo param [1, ""]) isEqualTo "hvt"}],
    ["watchlist", _db in ["bewl3", "bewl4"]],
    ["armed", (primaryWeapon _u) isNotEqualTo "" || {(handgunWeapon _u) isNotEqualTo ""} || {(secondaryWeapon _u) isNotEqualTo ""}]
]];

// Atelier IA : savoir (et secrets, pour le MJ), réponses écrites, histoire de couverture.
if ((count _ia) > 0) then {
    private _know = [_ia getOrDefault ["know", ""], 1500] call _txt;
    private _sec2 = (_ia getOrDefault ["secrets", []]) select { _x isEqualType "" };
    if (_sec2 isNotEqualTo []) then { _know = trim (_know + endl + "Secrets : " + ([_sec2 joinString " ; ", 400] call _txt)); };
    ["knowledge", _know] call _put;
    private _ans = [];
    {
        _x params [["_k", ""], ["_label", ""], ["_trig", []], "", ["_vars", []]];
        private _say = "";
        { if (_say isEqualTo "" && {(_x param [1, []]) isNotEqualTo []}) then { _say = (_x select 1) select 0; }; } forEach _vars;
        private _q = if (_trig isNotEqualTo []) then { _trig select 0 } else { [_label, _k] select (_label isEqualTo "") };
        if (_say isNotEqualTo "") then { _ans pushBack ([format ["%1 : %2", _q, _say], 300] call _txt); };
    } forEach ((_ia getOrDefault ["answers", []]) select [0, 30]);
    _p set ["answers", createHashMapFromArray [["answers", _ans], ["evoke", _ia getOrDefault ["evoke", "auto"]], ["banned", +(_ia getOrDefault ["banned", []])]]];
    private _story = [_ia getOrDefault ["story", ""], 800] call _txt;
    if (_story isNotEqualTo "") then { _cover pushBack _story; };
};
["cover", [_cover joinString endl, 1000] call _txt] call _put;
["side", ["east", "west", "guer", "civ"] select (([east, west, independent, civilian] find (side group _u)) max 0)] call _put;
["faction", [faction _u, 60] call _txt] call _put;
["group", [groupId (group _u), 60] call _txt] call _put;
["class", [typeOf _u, 80] call _txt] call _put;
_p
