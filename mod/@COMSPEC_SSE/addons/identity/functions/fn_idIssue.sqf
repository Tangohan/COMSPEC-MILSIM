/*
    Délivre un document (SERVEUR) : il entre au registre, virtuel tant qu'il n'est pas matérialisé (fn_idMaterialize).
    [_unit, _spec] call comspec_sse_fnc_idIssue -> docId ("" si refusé)
    _spec (HashMap, tout facultatif) :
      type          clé de CfgComspecDocuments : id_card (défaut), passport, driving_license, military_id, ou un type
                    d'extension active (ex. press_card) ; ses champs « input » (ex. media) sont lus dans _spec
      groundPos     [x, y] : sans personne, le document est posé au sol à cet endroit (sélecteur Zeus)
      rank, force   carte militaire : grade et force imprimés (défaut : ceux de l'unité)
      country       code du pays (défaut : pays de la mission)
      kind          civil (défaut) | cover (couverture préparée, passe tous les contrôles sauf la biométrie)
                    | forged (faux) | generated | alias
      quality       0-100 (faux : plus c'est bas, plus les défauts sont nombreux et visibles)
      forgeryKind   counterfeit (contrefaçon) | altered (vrai document modifié) | photo_swap (photo substituée)
                    | stolen_blank (vierge volé) ; défaut tiré selon la qualité
      last, first, sex, dob, pob, number, issued, expires, chip    imposés (Zeus, paquet Athena)
      report        "" | vole | perdu | revoque      athenaId  identifiant Athena      materialize  true : objet tout de suite
      athenaIdentity  identifiant de l'identité Athena (IDT-…)     ownerUid, ownerName  propriétaire (défaut : la personne)
    Vérité gardée sur le document (jamais envoyée aux joueurs) : trueLast, trueFirst, kind, quality, forgeryKind, flaws.
*/
params [["_u", objNull, [objNull]], ["_spec", createHashMap, [createHashMap]]];
if (!isServer) exitWith { "" };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _key = if (isNull _u) then { _spec getOrDefault ["holderKey", ""] } else { [_u] call comspec_sse_fnc_bioPersonKey };
if (_key isEqualTo "") then { _key = format ["X%1", floor random 999999]; };
private _type = _spec getOrDefault ["type", "id_card"];
// Types du registre (CfgComspecDocuments : papiers intégrés et extensions actives) ; inconnu ou extension coupée : carte.
private _tdef = ["get", _type] call comspec_sse_fnc_idDocTypes;
if ((count _tdef) isEqualTo 0 || {!(["enabled", _tdef get "extension"] call comspec_sse_fnc_idDocTypes)}) then { _type = "id_card"; _tdef = ["get", "id_card"] call comspec_sse_fnc_idDocTypes; };
// Pays : code (« FRA »), alpha-2 (« FR ») ou ancien nom gardé par une mission ; « auto » ou inconnu : pays de la mission.
private _country = ["resolve", _spec getOrDefault ["country", ""]] call comspec_sse_fnc_idCountries;
private _countries = [] call comspec_sse_fnc_idCountries;
if !(_country in _countries) then { _country = ["default"] call comspec_sse_fnc_idCountries; };
// Pays réel qui ne délivre pas ce type (Royaume-Uni, États-Unis : pas de carte nationale) : sa pièce courante
// (permis de conduire) ; une extension (press_card…) passe toujours.
private _cdef = _countries getOrDefault [_country, createHashMap];
// Pays fictif : seulement pour les papiers des pays réels (titre de séjour, carte grise), comme avant pour le reste.
if (((_tdef getOrDefault ["extension", ""]) isEqualTo "") && {(_spec getOrDefault ["athenaId", ""]) isEqualTo ""}
    && {!(_cdef getOrDefault ["fictional", true]) || {_type in ["residence_permit", "vehicle_registration"]}}
    && {(_cdef getOrDefault ["docTypes", []]) isNotEqualTo []} && {!(_type in (_cdef get "docTypes"))}) then {
    _type = _cdef getOrDefault ["primary", "id_card"];
    _tdef = ["get", _type] call comspec_sse_fnc_idDocTypes;
};
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
// Pays réel : 0 = sans date d'expiration (carte grise, permis suisse) ; le reste comme avant.
private _noExpiry = _valid <= 0 && {!(_c getOrDefault ["fictional", true])};
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
if (_noExpiry) then { _expires = ""; };
if ((_spec getOrDefault ["expires", ""]) isNotEqualTo "") then { _expires = _spec get "expires"; };
private _cities = _c getOrDefault ["cities", ["-"]];
private _ci = floor (("city" call _r) * (count _cities)) min ((count _cities) - 1);
private _city = _cities select _ci;
private _row = (_c getOrDefault ["cityRows", []]) param [_ci, [_city, "", ""]];
_row params [["_cityName", _city], ["_postal", ""], ["_rcode", ""]];
private _real = !(_c getOrDefault ["fictional", true]);
private _region = if (_real) then { ["region", [_country, _rcode]] call comspec_sse_fnc_idCountries } else { createHashMap };
private _streets = ["RUE DU PORT", "AV. CENTRALE", "RUE DE L'ÉGLISE", "CH. DES OLIVIERS", "PL. DU MARCHÉ", "RUE HAUTE", "BD DE LA MER"];
private _addr = format ["%1 %2, %3", 1 + floor (("no" call _r) * 120), _streets select (floor (("st" call _r) * (count _streets)) min 6), _city];
// Pays réel : adresse au format du pays (« {no} {street}, {postal} {city} », « {street} {no}, {postal} {city} »…).
private _fill = {
    private _t = _this;
    { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [
        ["{city}", _cityName], ["{postal}", _postal], ["{R}", _rcode], ["{region}", _region getOrDefault ["name", _rcode]]
    ];
    _t
};
if (_real && {(_c getOrDefault ["streets", []]) isNotEqualTo []}) then {
    private _st = _c get "streets";
    _addr = [_c getOrDefault ["address", "{no} {street}, {postal} {city}"], "{no}", str (1 + floor (("no" call _r) * 120))] call CBA_fnc_replace;
    _addr = [_addr, "{street}", _st select ((floor (("st" call _r) * (count _st))) min ((count _st) - 1))] call CBA_fnc_replace;
    _addr = _addr call _fill;
    _addr = trim ([_addr, " ,", ","] call CBA_fnc_replace);
};
// Contexte des numéros à clé (NIR, PESEL, codice fiscale, permis britannique…).
private _nctx = createHashMapFromArray [["sex", _printed get "sex"], ["dob", _printed get "dob"], ["last", _printed get "last"], ["first", _printed get "first"],
    ["pob", _printed get "pob"], ["region", _rcode], ["area", _region getOrDefault ["area", "555"]]];
private _number = _spec getOrDefault ["number", ""];
if (_number isEqualTo "") then { _number = [_country, _type, format ["%1|%2", _key, _kind], 0, _nctx] call comspec_sse_fnc_idDocNumber; };
// Autorité : celle du type (« Préfecture — {city} », « Stadt {city} » ; États-Unis « state » : l'agence de l'État).
private _auth = [_c getOrDefault ["authority", ""], format ["Préfecture de %1", _city]] select ((_c getOrDefault ["authority", ""]) isEqualTo "");
if (_real) then {
    private _a = (_c getOrDefault ["authorities", createHashMap]) getOrDefault [_type, _c getOrDefault ["authority", ""]];
    if (_a isEqualTo "state") then { _a = _region getOrDefault ["authority", _region getOrDefault ["name", _rcode]]; };
    if (_a isNotEqualTo "") then { _auth = _a call _fill; };
};

// Puce : documents intégrés à puce, ou champ « chip = 1 » du type (extensions).
private _chipType = _type in ["id_card", "passport", "military_id"] || {(_tdef getOrDefault ["chip", false]) isEqualTo true};
private _doc = createHashMapFromArray [
    ["id", _id], ["cls", ""], ["type", _type], ["country", _country], ["mrzIssuer", _c getOrDefault ["mrz", _country]], ["number", _number],
    ["last", _printed get "last"], ["first", _printed get "first"], ["sex", _printed get "sex"], ["dob", _printed get "dob"],
    ["pob", _printed get "pob"], ["nat", _c getOrDefault ["mrz", _country]], ["profession", _printed getOrDefault ["profession", ""]],
    ["issued", _issued], ["expires", _expires], ["authority", _auth], ["address", _addr],
    ["city", _cityName], ["postal", _postal], ["region", _rcode],
    ["chip", ["absent", "ok"] select _chipType], ["chipExpected", _chipType],
    ["kind", _kind], ["quality", _q], ["forgeryKind", ""], ["flaws", []], ["report", _spec getOrDefault ["report", ""]],
    ["holderKey", _key], ["trueLast", _trueLast], ["trueFirst", _trueFirst], ["athenaId", _spec getOrDefault ["athenaId", ""]],
    ["chipNote", ""], ["issuedAt", time],
    // Lien avec Athena et propriétaire (API fn_idPaperOf / fn_idPaperOwner) : identité Athena du papier, joueur à qui il
    // a été remis (Steam ID, nom), chaîne de possession (fn_idPaperTrack).
    ["athenaIdentity", _spec getOrDefault ["athenaIdentity", ""]],
    ["ownerUid", _spec getOrDefault ["ownerUid", if (isNull _u) then { "" } else { getPlayerUID _u }]],
    ["ownerName", _spec getOrDefault ["ownerName", if (isNull _u) then { "" } else { name _u }]],
    ["custody", []]
];

// Carte militaire : grade, force et camp de la force, lus par fn_idEvaluate (N3).
if (_type isEqualTo "military_id") then {
    private _rankTxt = _spec getOrDefault ["rank", ""];
    if (_rankTxt isEqualTo "" && {!isNull _u}) then {
        _rankTxt = (["Soldat", "Caporal", "Sergent", "Lieutenant", "Capitaine", "Commandant", "Colonel"] select ((["PRIVATE", "CORPORAL", "SERGEANT", "LIEUTENANT", "CAPTAIN", "MAJOR", "COLONEL"] find (rank _u)) max 0));
    };
    private _force = _spec getOrDefault ["force", ""];
    if (_force isEqualTo "" && {!isNull _u}) then { _force = getText (configFile >> "CfgFactionClasses" >> faction _u >> "displayName"); };
    _doc set ["rank", [_rankTxt, "Soldat"] select (_rankTxt isEqualTo "")];
    _doc set ["force", [_force, "Forces armées"] select (_force isEqualTo "")];
    _doc set ["cardSide", if (isNull _u) then { "" } else { ["east", "west", "guer", "civ"] select (([east, west, independent, civilian] find (side group _u)) max 0) }];
    if ((_doc get "profession") isEqualTo "") then { _doc set ["profession", "Militaire"]; };
};

// --- Pays réel : nationalité imprimée, taille, numéro personnel, MRZ du pays, État (États-Unis), carte grise --------
if (_real) then {
    if ((_c getOrDefault ["natText", ""]) isNotEqualTo "") then { _doc set ["natText", _c get "natText"]; };
    // Taille : tirée de la personne (graine), imprimée en cm ou en pieds-pouces (États-Unis).
    ([[163, 32], [152, 28]] select ((_printed get "sex") isEqualTo "F")) params ["_lo", "_span"];
    private _cm = round (_lo + ([_key, "height"] call comspec_sse_fnc_bioRand) * _span);
    _doc set ["heightCm", _cm];
    _doc set ["height", if ((_c getOrDefault ["height", "cm"]) isEqualTo "ftin") then {
        private _in = round (_cm / 2.54);
        format ["%1'-%2""", floor (_in / 12), _in mod 12]
    } else { format ["%1 cm", _cm] }];
    // Numéro personnel (NIR, numéro de registre national, PESEL, BSN, SSN…) : même personne, même numéro sur toutes ses
    // pièces ; imprimé seulement là où la vraie pièce le porte (printPersonal), sinon gardé pour les fichiers.
    private _ps = (_c getOrDefault ["number_format", createHashMap]) getOrDefault ["personal", ""];
    if (_ps isNotEqualTo "") then {
        private _pn = ["make", [_ps, [_key + "|personal|" + _country] call comspec_sse_fnc_bioHash, "personal", _nctx]] call comspec_sse_fnc_idNumbers;
        // Espagne : le DNI est le numéro de la carte elle-même.
        if (_country isEqualTo "ESP" && {_type isEqualTo "id_card"}) then { _pn = _number; };
        _doc set ["personal", _pn];
        _doc set ["personalPrint", ((_c getOrDefault ["printPersonal", 0]) isEqualTo 1) && {_type in ["id_card", "residence_permit"]}];
    };
    // Code du document dans la MRZ (« ID », « IR », « AR »… : deuxième lettre au choix de l'État émetteur).
    private _mc = (_c getOrDefault ["mrzCodes", createHashMap]) getOrDefault [_type, ""];
    if (_mc isNotEqualTo "") then { _doc set ["mrzCode", _mc]; };
    // Belgique, Espagne : numéro personnel dans les données facultatives de la ligne 1 (TD1).
    if (_country in ["BEL", "ESP"] && {(_doc getOrDefault ["personal", ""]) isNotEqualTo ""}) then { _doc set ["mrzOptional", _doc get "personal"]; };
    // États-Unis (et pays à régions) : État de délivrance, imprimé en tête des permis et cartes d'État.
    if ((_region getOrDefault ["name", ""]) isNotEqualTo "") then { _doc set ["state", toUpper (_region get "name")]; };
    if (_type isEqualTo "residence_permit") then {
        _doc set ["permit", (["Résident · Resident", "Séjour temporaire · Temporary", "Travailleur · Worker", "Étudiant · Student"] select (floor (("permit" call _r) * 4) min 3))];
        // Titre de séjour : la nationalité est celle d'un autre pays (ressortissant étranger).
        private _others = (["ordered", false] call comspec_sse_fnc_idCountries) - [_country];
        if (_others isNotEqualTo []) then {
            private _o = _others select ((floor (("foreign" call _r) * (count _others))) min ((count _others) - 1));
            _doc set ["nat", (_countries getOrDefault [_o, createHashMap]) getOrDefault ["mrz", _o]];
            _doc set ["natText", _o];
            // Nom et lieu de naissance du pays d'origine (sauf nom imposé par le Zeus ou le paquet).
            if ((_spec getOrDefault ["last", ""]) isEqualTo "" && {_kind in ["civil", "generated"]}) then {
                private _fo = [_u, _key, "", _o] call comspec_sse_fnc_idGenerate;
                { _doc set [_x, _fo get _x]; } forEach ["last", "first", "pob"];
                _doc set ["trueLast", _fo get "last"];
                _doc set ["trueFirst", _fo get "first"];
            };
        };
    };
    if (_type isEqualTo "vehicle_registration") then {
        private _plate = _c getOrDefault ["plate", ""];
        if ((_region getOrDefault ["plate", ""]) isNotEqualTo "") then { _plate = _region get "plate"; };
        _doc set ["plate", ["make", [_plate, [_key + "|plate"] call comspec_sse_fnc_bioHash, "plate", _nctx]] call comspec_sse_fnc_idNumbers];
        private _cars = switch (_c getOrDefault ["zone", "europe"]) do {
            case "americas": { ["FORD F-150", "TOYOTA CAMRY", "CHEVROLET SILVERADO", "HONDA CIVIC", "JEEP WRANGLER", "NISSAN ALTIMA", "DODGE RAM 1500", "TOYOTA COROLLA"] };
            case "other": { ["TOYOTA HILUX", "TOYOTA COROLLA", "HYUNDAI ELANTRA", "KIA RIO", "NISSAN SUNNY", "MITSUBISHI L200", "LADA NIVA", "DACIA LOGAN"] };
            default { ["RENAULT CLIO", "PEUGEOT 208", "VOLKSWAGEN GOLF", "OPEL CORSA", "SKODA OCTAVIA", "FIAT PANDA", "TOYOTA YARIS", "FORD FOCUS", "DACIA SANDERO", "BMW 320D"] };
        };
        _doc set ["vehicle", _cars select ((floor (("car" call _r) * (count _cars))) min ((count _cars) - 1))];
        _doc set ["vin", ["vin", [[_key + "|vin"] call comspec_sse_fnc_bioHash, "vin", ["SSE", "1SE"] select ((_c getOrDefault ["zone", ""]) isEqualTo "americas")]] call comspec_sse_fnc_idNumbers];
        _doc set ["holder", format ["%1 %2", toUpper (_doc get "last"), _doc get "first"]];
        _doc set ["firstReg", [(_iy - floor (("reg" call _r) * 8)) max 1990, _im, _id_] call _ymd];
        _doc set ["chip", "absent"];
        _doc set ["chipExpected", false];
    };
};

// Champs propres au type (input = 1 : saisis par le Zeus, sinon valeur par défaut du type) : imprimés tels quels.
{
    _x params ["_ik", "", "_idef"];
    private _iv = _spec getOrDefault [_ik, ""];
    if !(_iv isEqualType "") then { _iv = str _iv; };
    _doc set [_ik, [trim _iv, _idef] select ((trim _iv) isEqualTo "")];
} forEach (_tdef getOrDefault ["inputs", []]);

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
// Document posé au sol (sélecteur Zeus « Poser au sol ») : porte-objets à l'endroit choisi.
private _ground = _spec getOrDefault ["groundPos", []];
if (isNull _u && {_ground isEqualType []} && {(count _ground) >= 2}) then {
    private _h = createVehicle ["GroundWeaponHolder", [_ground select 0, _ground select 1, 0], [], 0, "CAN_COLLIDE"];
    _h setPosATL [_ground select 0, _ground select 1, 0];
    [_id, _h] call comspec_sse_fnc_idMaterialize;
};
_id
