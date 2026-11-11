/*
    Symbole militaire MIL-STD-2525D / APP-6D d'une unité, déduit des seules données du jeu et de l'ORBAT du site
    (règle : docs/ATAK_SYMBOLES_MILSTD.md). Sans donnée : symbole générique (cadre seul), jamais de type inventé.
    Descripteur : [affiliation, dimension, pictogramme, échelon, QG, prévu, état, civil]
      affiliation  "f" ami | "h" hostile | "n" neutre | "u" inconnu | "af" supposé ami | "s" suspect
      dimension    "l" terrestre | "a" air (en vol) | "s" surface (navire)
      pictogramme  clé de fn_milDraw (inf, mech, arm, rec, art, mor, eng, sig, med, c2, sup, log, avf, avr, uav, sni, at,
                   sof, jtac, mp, civ, mot, ad) ou "" (générique)
      échelon      "" | tm sq sc pl co bn rg bd dv (marques Ø ● ●● ●●● | || ||| X XX)
      QG           hampe de poste de commandement        prévu  cadre en tirets
      état         0 rien | 1 capacité réduite (véhicule endommagé, blessé) | 2 détruit, hors de combat
      civil        fond violet des civils (cadre neutre)
    Params : [opération, argument]
      "unit" entité (HashMap de la carte)          -> descripteur (gardé 2 s par unité)
      "manual" [affiliation, pictogramme, échelon, prévu, QG] -> descripteur d'un choix de l'utilisateur
      "self" pictogramme ("" : déduit)              -> mon descripteur (ami, échelon d'après les données)
      "orbat"                                       -> hashValue du joueur -> [échelon, fonction, QG, code, unité supérieure] (ORBAT du site)
      "sidc" descripteur                            -> code 2525D à 20 chiffres (site : Tacmap, /m/carte, milsymbol)
      "pictos" | "echelons" | "affs"                -> listes [[clé, libellé court, aide]...] du sélecteur
*/
params [["_op", ""], ["_a", []]];
switch (_op) do {
    case "unit": {
        private _e = _a;
        private _id = _e getOrDefault ["id", ""];
        private _cache = uiNamespace getVariable "COMSPEC_ATAK_MilSymCache";
        if (isNil "_cache" || {(count _cache) > 400}) then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MilSymCache", _cache]; };
        private _key = format ["%1|%2", _id, _e getOrDefault ["affiliation", ""]];
        private _hit = _cache getOrDefault [_key, []];
        if ((count _hit) > 0 && {diag_tickTime < (_hit select 0)}) exitWith { _hit select 1 };
        private _aff = switch (_e getOrDefault ["affiliation", ""]) do { case "friend": { "f" }; case "hostile": { "h" }; case "neutral": { "n" }; default { "u" }; };
        private _o = _e getOrDefault ["object", objNull];
        private _dim = "l"; private _pic = ""; private _ech = ""; private _hq = false; private _cond = 0; private _civ = false;
        if (isNull _o) then {
            // Piste Athena sans objet en jeu : seul son type est connu.
            _pic = switch (_e getOrDefault ["type", ""]) do {
                case "infantry": { "inf" }; case "armor": { "arm" }; case "vehicle": { "mot" }; case "medical": { "med" };
                case "air": { _dim = "a"; "" };
                default { "" };
            };
        } else {
            private _v = vehicle _o;
            if (side group _o isEqualTo civilian) then { _civ = true; _aff = "n"; };
            private _has = { params ["_t", "_w"]; private _l = toLower _t; (_w findIf { (_l find _x) >= 0 }) >= 0 };
            if (_v isNotEqualTo _o || {unitIsUAV _o}) then {
                private _cfg = configOf _v;
                if (_v isKindOf "Air" && {!isTouchingGround _v} && {((getPosATL _v) select 2) > 5}) then { _dim = "a"; };
                if (_v isKindOf "Ship") then { _dim = "s"; };
                _pic = switch (true) do {
                    case (unitIsUAV _v): { "uav" };
                    case (_v isKindOf "Plane"): { "avf" };
                    case (_v isKindOf "Air"): { "avr" };
                    case (_v isKindOf "Ship"): { "" };
                    case (_v isKindOf "StaticMortar"): { "mor" };
                    case (_v isKindOf "StaticAAWeapon"): { "ad" };
                    case (_v isKindOf "StaticATWeapon"): { "at" };
                    case (_v isKindOf "StaticWeapon"): { "" };
                    case ((getNumber (_cfg >> "artilleryScanner")) > 0): { "art" };
                    case ((getNumber (_cfg >> "attendant")) > 0): { "med" };
                    case ((getNumber (_cfg >> "transportRepair")) > 0): { "sup" };
                    case ((getNumber (_cfg >> "transportFuel")) > 0 || {(getNumber (_cfg >> "transportAmmo")) > 0}): { "log" };
                    case (_v isKindOf "Wheeled_APC_F"): { "mech" };
                    case (_v isKindOf "Tank"): { ["arm", "mech"] select ((getNumber (_cfg >> "transportSoldier")) >= 4) };
                    case (_v isKindOf "LandVehicle"): { "mot" };
                    default { "" };
                };
                // État : endommagé (capacité réduite), immobilisé ou détruit.
                _cond = switch (true) do { case (!alive _v || {!canMove _v && {!(_v isKindOf "StaticWeapon")}}): { 2 }; case ((damage _v) >= 0.25): { 1 }; default { 0 }; };
            } else {
                private _role = _o getVariable ["COMSPEC_FTRole", ""];
                private _desc = format ["%1 %2", roleDescription _o, _e getOrDefault ["callsign", ""]];
                private _cls = toLower typeOf _o;
                _pic = switch (true) do {
                    case _civ: { "civ" };
                    case ((_o getUnitTrait "medic") || {(_o getVariable ["ace_medical_medicClass", 0]) > 0} || {_role isEqualTo "MED"}): { "med" };
                    case ([_desc, ["jtac", "tacp", "forward air", "fac "]] call _has): { "jtac" };
                    case ([_desc, ["sniper", "marksman", "tireur d'élite", "tireur d'elite"]] call _has || {[_cls, ["_sniper", "_marksman", "_m_f", "_ghillie"]] call _has}): { "sni" };
                    case (_role isEqualTo "TE" || {[_desc, ["recon", "spotter", "éclaireur", "eclaireur", "scout"]] call _has} || {[_cls, ["_recon_", "_spotter"]] call _has}): { "rec" };
                    case ([_desc, ["mortar", "mortier"]] call _has): { "mor" };
                    case (_role isEqualTo "DRN" || {_o getUnitTrait "UAVHacker"}): { "uav" };
                    case (_role in ["ENG", "EOD"] || {_o getUnitTrait "engineer"} || {_o getUnitTrait "explosiveSpecialist"}): { "eng" };
                    case ([_desc, ["anti-tank", "antichar", "anti-char", "missile", "(at)", "(lat)"]] call _has || {[_cls, ["_at_", "_lat_", "_hat_"]] call _has}): { "at" };
                    case ([_desc, ["anti-air", "antiaérien", "(aa)"]] call _has || {[_cls, ["_aa_"]] call _has}): { "ad" };
                    case ([_desc, ["military police", "police militaire", "prévôt"]] call _has): { "mp" };
                    case ([_desc, ["radio", "rto", "transmission", "signal"]] call _has): { "sig" };
                    case ([_desc, ["helicopter pilot", "pilote d'hélico"]] call _has || {[_cls, ["helipilot", "helicrew"]] call _has}): { "avr" };
                    case ([_desc, ["pilot", "pilote"]] call _has || {[_cls, ["_pilot_"]] call _has}): { "avf" };
                    case ([_desc, ["special forces", "forces spéciales", "ctrg"]] call _has || {[_cls, ["_ctrg_", "_sf_"]] call _has}): { "sof" };
                    case ([_desc, ["logisti", "supply", "ravitaill"]] call _has): { "log" };
                    case ([_desc, ["repair", "réparat", "mécanicien"]] call _has): { "sup" };
                    default { "inf" };
                };
                _hq = [_desc, ["hq", "poste de commandement", "commandant", "company commander", "platoon commander", "officier", "officer", "actual"]] call _has;
                _cond = switch (true) do { case (!alive _o || {(lifeState _o) isEqualTo "INCAPACITATED"}): { 2 }; case ((damage _o) >= 0.25): { 1 }; default { 0 }; };
            };
            // ORBAT du site d'abord (titulaire d'un poste de chef : échelon, fonction et QG de son unité), sinon chef de
            // groupe Arma : échelon d'après l'effectif (même règle que les symboles OTAN, fn_natoSymbol).
            private _orb = (["orbat"] call comspec_atak_native_fnc_milSym) getOrDefault [hashValue _o, []];
            if ((count _orb) > 0) then {
                _orb params ["_oe", "_of", "_oh"];
                if (_oe isNotEqualTo "") then { _ech = _oe; };
                if (_of isNotEqualTo "" && {_v isEqualTo _o}) then { _pic = _of; };
                if (_oh) then { _hq = true; };
            } else {
                private _g = group _o;
                private _n = count units _g;
                if (leader _g isEqualTo _o && {_n > 1} && {_dim isEqualTo "l"}) then {
                    _ech = switch (true) do { case (_n <= 4): { "tm" }; case (_n <= 13): { "sq" }; default { "pl" }; };
                } else {
                    if ((_o getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE") then { _ech = "tm"; };
                };
            };
        };
        if (_dim isNotEqualTo "l") then { _ech = ""; _hq = false; if !(_pic in ["avf", "avr", "uav"]) then { _pic = ""; }; };
        private _d = [_aff, _dim, _pic, _ech, _hq, false, _cond, _civ];
        _cache set [_key, [diag_tickTime + 2, _d]];
        _d
    };
    case "manual": {
        _a params [["_aff", "f"], ["_pic", ""], ["_ech", ""], ["_plan", false], ["_hq", false]];
        if !(_aff in ["f", "h", "n", "u", "af", "s"]) then { _aff = "u"; };
        private _ok = (["pictos"] call comspec_atak_native_fnc_milSym) apply { _x select 0 };
        if !(_pic in _ok) then { _pic = ""; };
        if !(_ech in ["tm", "sq", "sc", "pl", "co", "bn", "rg", "bd", "dv"]) then { _ech = ""; };
        [_aff, "l", _pic, _ech, _hq isEqualTo true, _plan isEqualTo true, 0, _pic isEqualTo "civ"]
    };
    case "self": {
        private _e = ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]) getOrDefault [netId player, createHashMap];
        if ((count _e) isEqualTo 0) then { _e = createHashMapFromArray [["id", "self"], ["object", player], ["affiliation", "friend"]]; };
        private _d = +(["unit", _e] call comspec_atak_native_fnc_milSym);
        _d set [0, "f"];
        if (_a isNotEqualTo "") then { _d set [2, _a]; _d set [7, false]; };
        _d
    };
    case "orbat": {
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_MilOrbat", [-1e9, createHashMap]];
        if (diag_tickTime < (_c select 0)) exitWith { _c select 1 };
        // Lecture de l'ORBAT du site (fn_bftOrbat : au plus toutes les 5 min, en tâche de fond, rien si non relié).
        ["load"] call comspec_atak_native_fnc_bftOrbat;
        private _store = ["store"] call comspec_atak_native_fnc_bftOrbat;
        private _onKey = (["presence"] call comspec_atak_native_fnc_bftOrbat) select 1;
        private _ech = createHashMapFromArray [["equipe", "tm"], ["groupe", "sq"], ["section_otan", "sc"], ["section", "pl"], ["peloton", "pl"],
            ["compagnie", "co"], ["escadron", "co"], ["batterie", "co"], ["bataillon", "bn"], ["regiment", "rg"], ["brigade", "bd"], ["division", "dv"]];
        private _fn = createHashMapFromArray [["infantry", "inf"], ["infantry_light", "inf"], ["infantry_airborne", "inf"], ["infantry_air_assault", "inf"],
            ["infantry_mountain", "inf"], ["infantry_naval", "inf"], ["infantry_motorized", "mot"], ["infantry_mechanized", "mech"], ["armor", "arm"],
            ["recon", "rec"], ["artillery", "art"], ["mortar", "mor"], ["observer", "jtac"], ["anti_armor", "at"], ["air_defense", "ad"],
            ["engineer", "eng"], ["aviation", "avr"], ["aviation_rotary", "avr"], ["aviation_fixed", "avf"], ["uav", "uav"], ["signal", "sig"],
            ["military_police", "mp"], ["medical", "med"], ["supply", "log"], ["maintenance", "sup"], ["sof", "sof"], ["special_forces", "sof"],
            ["ranger", "sof"], ["sof_naval", "sof"], ["sof_aviation", "sof"]];
        private _names = createHashMap;
        { _names set [_x select 0, _x select 2]; } forEach (_store get "units");
        private _out = createHashMap;
        {
            _x params ["", "", "_uName", "_code", "_rows", "", ["_uEch", ""], ["_uFn", ""], ["_uHq", false], ["_uPar", 0]];
            private _f = _fn getOrDefault [_uFn, ""];
            {
                if ((_x select 0) isEqualTo "p") then {
                    private _o = _onKey getOrDefault [_x select 1, objNull];
                    if (isNull _o) then { continue };
                    private _chief = (_x param [3, ""]) in ["commander", "deputy"];
                    // Un poste de chef l'emporte sur une simple appartenance (unité la plus haute d'abord : parent avant enfants).
                    private _prev = _out getOrDefault [hashValue _o, []];
                    if ((count _prev) > 0 && {(_prev select 5) || {!_chief}}) then { continue };
                    _out set [hashValue _o, [["", _ech getOrDefault [_uEch, ""]] select _chief, _f, _chief && {_uHq}, _code, _names getOrDefault [_uPar, ""], _chief]];
                };
            } forEach _rows;
        } forEach (_store get "units");
        uiNamespace setVariable ["COMSPEC_ATAK_MilOrbat", [diag_tickTime + 5, _out]];
        _out
    };
    case "sidc": {
        _a params ["_aff", "_dim", "_pic", "_ech", "_hq", "_plan", "_cond", "_civ"];
        private _id = (createHashMapFromArray [["u", "1"], ["af", "2"], ["f", "3"], ["n", "4"], ["s", "5"], ["h", "6"]]) getOrDefault [_aff, "1"];
        private _set = switch (true) do { case _civ: { "11" }; case (_dim isEqualTo "a"): { "01" }; case (_dim isEqualTo "s"): { "30" }; default { "10" }; };
        private _ent = switch (true) do {
            case _civ: { "000000" };
            case (_dim isEqualTo "a"): { (createHashMapFromArray [["avf", "110100"], ["avr", "110200"], ["uav", "110300"]]) getOrDefault [_pic, "000000"] };
            case (_dim isEqualTo "s"): { "000000" };
            default {
                (createHashMapFromArray [["inf", "121100"], ["mech", "121102"], ["mot", "121104"], ["arm", "120500"], ["rec", "121300"], ["art", "130300"],
                    ["mor", "130800"], ["eng", "140700"], ["sig", "111000"], ["med", "161300"], ["c2", "110000"], ["sup", "161100"], ["log", "160200"],
                    ["avf", "120800"], ["avr", "120600"], ["uav", "121900"], ["sni", "121500"], ["at", "120400"], ["sof", "121700"], ["jtac", "130500"],
                    ["mp", "141200"], ["ad", "130100"]]) getOrDefault [_pic, "000000"]
            };
        };
        private _st = switch (true) do { case _plan: { "1" }; case (_cond isEqualTo 1): { "3" }; case (_cond isEqualTo 2): { "4" }; default { "0" }; };
        private _ec = (createHashMapFromArray [["tm", "11"], ["sq", "12"], ["sc", "13"], ["pl", "14"], ["co", "15"], ["bn", "16"], ["rg", "17"], ["bd", "18"], ["dv", "21"]]) getOrDefault [_ech, "00"];
        (format ["100%1%2%3%4%5%6", _id, _set, _st, ["0", "2"] select _hq, [_ec, "00"] select (_set isNotEqualTo "10"), _ent]) + "0000"
    };
    // Libellés courts (10 caractères au plus : jamais coupés dans les cases du sélecteur).
    case "pictos": { [
        ["inf", "INFANTERIE", "Infanterie (2525D 121100)"], ["mech", "MÉCANISÉE", "Infanterie mécanisée (121102)"], ["mot", "MOTORISÉE", "Infanterie motorisée, véhicule léger (121104)"],
        ["arm", "BLINDÉ", "Blindé (120500)"], ["rec", "RECO", "Reconnaissance (121300)"], ["art", "ARTILLERIE", "Artillerie (130300)"], ["mor", "MORTIER", "Mortier (130800)"],
        ["eng", "GÉNIE", "Génie (140700)"], ["sig", "TRANS.", "Transmissions (111000)"], ["med", "SANTÉ", "Santé (161300)"], ["c2", "COMMANDT", "Commandement (110000)"],
        ["sup", "SOUTIEN", "Soutien, maintenance (161100)"], ["log", "LOGISTIQUE", "Logistique, ravitaillement (160200)"], ["avf", "AVION", "Aviation à voilure fixe (120800)"],
        ["avr", "HÉLICO", "Aviation à voilure tournante (120600)"], ["uav", "DRONE", "Drones (121900)"], ["sni", "SNIPER", "Tireur d'élite (121500)"],
        ["at", "ANTICHAR", "Antichar (120400)"], ["sof", "FS", "Forces spéciales (121700)"], ["jtac", "JTAC", "Appui feu interarmées, JTAC (130500)"],
        ["mp", "PRÉVÔTÉ", "Police militaire (141200)"], ["ad", "SOL-AIR", "Défense sol-air (130100)"], ["civ", "CIVIL", "Civil"]
    ] };
    case "echelons": { [
        ["", "AUCUN", ""], ["tm", "ÉQUIPE", "Ø"], ["sq", "GROUPE", "●"], ["sc", "DEMI-SECT.", "●● (section OTAN)"], ["pl", "SECTION", "●●● (section, peloton)"],
        ["co", "COMPAGNIE", "| (compagnie, escadron, batterie)"], ["bn", "BATAILLON", "||"], ["rg", "RÉGIMENT", "|||"]
    ] };
    case "affs": { [
        ["f", "AMI", "Cadre rectangle bleu"], ["h", "HOSTILE", "Losange rouge"], ["n", "NEUTRE", "Carré vert"], ["u", "INCONNU", "Trèfle jaune"],
        ["af", "SUPP. AMI", "Supposé ami : cadre ami en tirets"], ["s", "SUSPECT", "Cadre hostile en tirets"]
    ] };
    default { nil };
};
