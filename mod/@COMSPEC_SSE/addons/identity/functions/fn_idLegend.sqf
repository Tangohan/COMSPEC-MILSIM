/*
    Légende d'une identité (SERVEUR SEULEMENT) : l'histoire qui accompagne les papiers d'une personne, couverture
    préparée d'un infiltré comme simple vie d'un civil. Tirage reproductible (clé biométrique), cohérent avec la pièce
    principale (nom, date et lieu de naissance, adresse, profession imprimés), complété par le brief RP d'Athena (paquet,
    bloc « briefs », Steam ID du joueur) qui l'emporte champ par champ, puis par le texte écrit dans l'éditeur SSE
    (« legendText » du papier : « Adresse : … », « Employeur : … », reste en tête de l'histoire), le choix le plus récent.
    Calculée une fois, gardée au registre.
    ["get", [unité]] -> légende (HashMap) :
      last, first, name, dob, pob, sex, country, address, city, years (ans à l'adresse), employer, profession, jobYears,
      phone HashMap (number, model, imei, sim : téléphone simulé, comspec_sse_fnc_generatePhone), contacts [[nom, lien,
      téléphone]…], extraDocs [textes], history [textes], biography, family, languages [textes], phrases [textes],
      sensitive [textes], secrets [textes], travel, brief (HashMap du paquet ou vide), instructions, title,
      cover (vrai : couverture préparée), quality (0-100 : préparation de la légende), docId (pièce principale)
    ["questions", [unité, nombre]] -> [[clé, question, bonne réponse, [leurres…]]…]   questions tirées de la légende
    ["aiAnswer", [unité, clé]] -> [phrase, cohérente]   un PNJ répond selon la qualité de sa couverture
    ["sheet", [unité, zeus]] -> fiche RP (HashMap) pour le seul joueur concerné ; zeus vrai : avec la vérité (nature,
                                vrai nom, cohérence). En plus de la légende : docs [[type, pays, numéro, fin de validité,
                                porté]…] (ce qui est imprimé, jamais la nature), source athena | local (brief ou kit du
                                paquet, sinon né en jeu), athenaAt (date), prepared (couverture ou texte préparé). Le brief
                                brut, qui porte le Steam ID et l'identifiant du compte, ne part pas.
    ["reset", [unité]]   oublie la légende (papiers changés, couverture donnée par le Zeus)
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith { nil };
_a params [["_u", objNull, [objNull]], ["_x1", 0]];
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _legends = _reg getOrDefault ["legends", createHashMap];
_reg set ["legends", _legends];
if (isNull _u) exitWith { [createHashMap, []] select (_op isEqualTo "questions") };
private _key = [_u] call comspec_sse_fnc_bioPersonKey;
if (_op isEqualTo "reset") exitWith { _legends deleteAt _key; true };

private _build = {
    private _docs = (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry) apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry };
    private _main = createHashMap;
    // Papier remis depuis l'éditeur avec son texte (le plus récent) : c'est lui, la légende voulue.
    { if ((_x getOrDefault ["legendText", ""]) isNotEqualTo "") then { _main = _x; }; } forEach _docs;
    if ((count _main) isEqualTo 0) then { { if ((_x getOrDefault ["kind", ""]) isEqualTo "cover") exitWith { _main = _x; }; } forEach _docs; };
    if ((count _main) isEqualTo 0) then { { if ((_x getOrDefault ["type", ""]) in ["id_card", "passport"]) exitWith { _main = _x; }; } forEach _docs; };
    if ((count _main) isEqualTo 0 && {_docs isNotEqualTo []}) then { _main = _docs select 0; };
    private _country = _main getOrDefault ["country", ["default"] call comspec_sse_fnc_idCountries];
    private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_country, createHashMap];
    private _printed = if ((count _main) > 0) then { _main } else { [_u, _key, "", _country] call comspec_sse_fnc_idGenerate };
    private _seed = [_key + "|legend"] call comspec_sse_fnc_bioHash;
    private _h = { [_seed, _this] call comspec_sse_fnc_hash };
    private _pick = { params ["_list", "_s"]; if (_list isEqualTo []) exitWith { "" }; _list select ((_s call _h) mod (count _list)) };
    private _cities = _c getOrDefault ["cities", ["-"]];
    private _lastPool = _c getOrDefault ["last", ["Doe"]];
    private _firstPool = (_c getOrDefault ["first", ["Alex"]]) + (_c getOrDefault ["firstF", []]);
    private _somebody = { format ["%1 %2", [_firstPool, _this + "f"] call _pick, toUpper ([_lastPool, _this + "l"] call _pick)] };
    private _last = _printed getOrDefault ["last", ""];
    private _first = _printed getOrDefault ["first", ""];
    private _dob = _printed getOrDefault ["dob", ""];
    private _pob = _printed getOrDefault ["pob", ""];
    // Adresse imprimée (« 12 RUE DU PORT, Kavala ») : la ville est la dernière partie.
    private _addr = _printed getOrDefault ["address", ""];
    private _city = trim (((_addr splitString ",") param [1, ""]));
    if (_city isEqualTo "") then { _city = [_cities, "city"] call _pick; };
    if (_addr isEqualTo "") then {
        _addr = format ["%1 %2, %3", 1 + (("no" call _h) mod 120), ["RUE DU PORT", "AV. CENTRALE", "RUE HAUTE", "PL. DU MARCHÉ", "CH. DES OLIVIERS"] select (("st" call _h) mod 5), _city];
    };
    private _other = { private _l = _cities select { _x isNotEqualTo _city }; [[_l, _this] call _pick, _city] select (_l isEqualTo []) };
    private _prof = _printed getOrDefault ["profession", ""];
    if (_prof isEqualTo "") then { _prof = [["Commerçant", "Chauffeur", "Mécanicien", "Ouvrier du bâtiment", "Fonctionnaire"], "job"] call _pick; };
    private _boss = toUpper ([_lastPool, "boss"] call _pick);
    private _employer = switch (true) do {
        case (_prof in ["Sans emploi"]): { "Sans employeur (petits travaux à la journée)" };
        case (_prof in ["Étudiant"]): { format ["Université de %1", _city] };
        case (_prof in ["Agriculteur", "Berger"]): { format ["Coopérative agricole de %1", _city] };
        case (_prof in ["Pêcheur"]): { format ["Pêcherie %1, port de %2", _boss, _city] };
        case (_prof in ["Enseignant"]): { format ["École de %1", _city] };
        case (_prof in ["Infirmier"]): { format ["Hôpital de %1", _city] };
        case (_prof in ["Fonctionnaire"]): { format ["Mairie de %1", _city] };
        default { format [["Transports %1", "Garage %1", "Épicerie %1", "Entreprise %1 et fils", "Hôtel %1", "Chantier %1"] select (("emp" call _h) mod 6), _boss] };
    };
    // Téléphone simulé (même générateur que les téléphones fouillés par le SSE) : indicatif fictif du pays.
    private _prefix = (createHashMapFromArray [["ALT", "+031"], ["TNA", "+067"], ["LVN", "+037"], ["MLD", "+035"]]) getOrDefault [_country, "+099"];
    private _num = { format ["%1 %2 %3", _prefix, [_seed, _this + "a", 4] call comspec_sse_fnc_idToken, [_seed, _this + "b", 4] call comspec_sse_fnc_idToken] };
    private _myNum = "me" call _num;
    private _rel = ["Frère", "Cousine", "Employeur", "Voisin", "Ami d'enfance", "Mère", "Collègue"];
    private _contacts = [];
    for "_i" from 0 to 2 do {
        private _r = _rel select ((format ["rel%1", _i] call _h) mod (count _rel));
        private _n = [format ["ct%1", _i] call _somebody, format ["%1 (%2)", _boss, _employer]] select (_r isEqualTo "Employeur");
        _contacts pushBack [_n, _r, format ["ct%1", _i] call _num];
    };
    private _phone = createHashMapFromArray [["number", _myNum], ["model", ""], ["imei", ""], ["sim", ""]];
    if (!isNil "comspec_sse_fnc_generatePhone") then {
        private _dev = [_seed, "CIVILIAN", "LIGHT", createHashMapFromArray [
            ["primaryName", format ["%1 %2", _first, toUpper _last]], ["primaryPhone", _myNum], ["networkContacts", _contacts apply { _x select 0 }]
        ]] call comspec_sse_fnc_generatePhone;
        // Seule l'identité de l'appareil est reprise : ses messages suivent la légende, pas un scénario insurgé.
        if (_dev isEqualType createHashMap) then { { _phone set [_x, _dev getOrDefault [_x, ""]]; } forEach ["model", "imei", "sim"]; };
    };
    private _years = 2 + (("yrs" call _h) mod 15);
    private _jobYears = 1 + (("jyrs" call _h) mod (_years max 2));
    private _family = [[
        "Marié(e), deux enfants", "Célibataire, vit chez ses parents", "Marié(e), sans enfant", "Divorcé(e), une fille",
        "Veuf(ve), un fils", "Célibataire, vit seul(e)"
    ], "fam"] call _pick;
    private _langCodes = _c getOrDefault ["languages", []];
    private _langName = createHashMapFromArray [["fr", "français"], ["en", "anglais"], ["de", "allemand"], ["es", "espagnol"], ["it", "italien"],
        ["pt", "portugais"], ["el", "grec"], ["pl", "polonais"], ["ru", "russe"], ["ar", "arabe"], ["fa", "persan"], ["zh", "chinois"]];
    private _languages = [];
    { _languages pushBackUnique (_langName getOrDefault [_x, _x]); } forEach (_langCodes select { _x isEqualType "" });
    if (_languages isEqualTo []) then { _languages pushBack format ["langue de %1 (maternelle)", _c getOrDefault ["name", _country]]; } else { _languages set [0, (_languages select 0) + " (maternelle)"]; };
    _languages pushBackUnique ([["anglais (notions)", "anglais (courant)", "français (notions)", "russe (notions)"], "lang2"] call _pick);
    private _travel = [[
        format ["Rendre visite à ma famille à %1", "tv1" call _other], "Une livraison pour mon employeur", format ["Un rendez-vous médical à %1", "tv2" call _other],
        "Je rentre chez moi après le travail", format ["Acheter des pièces au marché de %1", "tv3" call _other], format ["Chercher du travail à %1", "tv4" call _other]
    ], "travel"] call _pick;
    private _extra = [
        format ["Carte de travail (%1)", _employer], format ["Facture d'électricité au %1", _addr], format ["Ticket de bus %1 – %2", _city, "bus" call _other],
        "Ordonnance d'une pharmacie", "Photo de famille", "Carte de membre du club de football local", format ["Quittance de loyer, %1", _city]
    ];
    private _extraDocs = [];
    for "_i" from 0 to 1 do { _extraDocs pushBackUnique (_extra select ((format ["xd%1", _i] call _h) mod (count _extra))); };
    private _phrases = [
        "Je ne fais que passer, je rentre chez moi.", "Mon patron m'attend, je suis déjà en retard.", "Je n'ai rien à voir avec tout ça.",
        format ["Vous pouvez appeler %1, il vous le confirmera.", (_contacts select 0) select 0], format ["J'habite %1 depuis %2 ans.", _city, _years]
    ];
    private _isCover = ((_main getOrDefault ["kind", ""]) isEqualTo "cover") || {_u getVariable ["comspec_sse_idCoverOn", false]};
    private _kind = _main getOrDefault ["kind", "civil"];
    private _sensitive = [[
        "Son service militaire", "Ses voyages à l'étranger", format ["Ses liens avec %1", "sens" call _somebody], "L'origine de son argent", "Son ancien employeur"
    ], "sens1"] call _pick;
    private _sensitive2 = [["Ses opinions politiques", "Sa famille restée au pays", "Ses absences au travail"], "sens2"] call _pick;
    private _secrets = switch (true) do {
        case (_isCover): { ["Sa vraie identité et son unité", "Sa mission en cours et son point de rendez-vous"] };
        case (_kind isEqualTo "forged"): { ["Ses papiers sont faux", "Ses liens avec un groupe armé"] };
        default { [[[format ["Une dette auprès de %1", "debt" call _somebody], "Un petit trafic de cigarettes", "Rien de grave à cacher"], "sec"] call _pick] };
    };
    private _by = parseNumber (_dob select [0, 4]);
    private _age = [30, ((date select 0) - _by) max 18] select (_by > 1900);
    private _bio = format ["%1 %2, %3 ans, %4 à %5. %6.", _first, toUpper _last, _age, toLower _prof, _city, _family];
    private _quality = switch (_kind) do {
        case "cover": { _main getOrDefault ["quality", 90] };
        case "forged": { round ((_main getOrDefault ["quality", 50]) * 0.7) };
        case "alias"; case "generated": { 70 };
        default { 100 };
    };
    private _L = createHashMapFromArray [
        ["last", _last], ["first", _first], ["name", format ["%1 %2", toUpper _last, _first]], ["dob", _dob], ["pob", _pob],
        ["sex", _printed getOrDefault ["sex", "M"]], ["country", _country], ["address", _addr], ["city", _city], ["years", _years],
        ["employer", _employer], ["profession", _prof], ["jobYears", _jobYears], ["phone", _phone], ["contacts", _contacts],
        ["extraDocs", _extraDocs], ["family", _family], ["languages", _languages], ["phrases", _phrases],
        ["sensitive", [_sensitive, _sensitive2]], ["secrets", _secrets], ["travel", _travel], ["biography", _bio],
        ["history", [
            format ["Né(e) le %1 à %2.", _dob, _pob], format ["Habite au %1 depuis %2 ans.", _addr, _years],
            format ["%1 chez %2 depuis %3 ans.", _prof, _employer, _jobYears], _family
        ]],
        ["brief", createHashMap], ["instructions", ""], ["title", ""], ["cover", _isCover], ["quality", _quality],
        ["docId", _main getOrDefault ["id", ""]], ["kind", _kind],
        ["prepared", _isCover || {_kind in ["forged", "alias"]} || {(_main getOrDefault ["legendText", ""]) isNotEqualTo ""}]
    ];
    // Brief RP d'Athena (joueur : Steam ID) : biographie, légende, contacts, consignes, téléphone et adresse saisis.
    private _uid = getPlayerUID _u;
    private _brief = if (_uid isEqualTo "") then { createHashMap } else { (_reg getOrDefault ["briefs", createHashMap]) getOrDefault [_uid, createHashMap] };
    if ((count _brief) > 0) then {
        _L set ["brief", _brief];
        private _s = { private _v = _brief getOrDefault [_this, ""]; [trim _v, ""] select !(_v isEqualType "") };
        if (("address" call _s) isNotEqualTo "") then { _L set ["address", "address" call _s]; };
        if (("phone" call _s) isNotEqualTo "") then { _phone set ["number", "phone" call _s]; };
        if (("biography" call _s) isNotEqualTo "") then { _L set ["biography", "biography" call _s]; };
        // Rubriques du texte de légende (site récent) ; un paquet plus ancien n'a que le texte entier.
        { if ((_x call _s) isNotEqualTo "") then { _L set [_x, _x call _s]; }; } forEach ["employer", "travel", "family", "city", "profession"];
        private _legendText = [("legend" call _s), ("legend_free" call _s)] select ("legend_free" in _brief);
        if (_legendText isNotEqualTo "") then { (_L get "history") insert [0, [_legendText]]; };
        if (("contacts" call _s) isNotEqualTo "") then {
            { if ((trim _x) isNotEqualTo "") then { _contacts pushBack [trim _x, "", ""]; }; } forEach (("contacts" call _s) splitString (toString [10, 13] + ";"));
        };
        _L set ["instructions", "instructions" call _s];
        _L set ["title", "title" call _s];
        // Brief avec identité de couverture : légende préparée par le commandement.
        if ((_brief getOrDefault ["identity_id", ""]) isNotEqualTo "" || {("cover_name" call _s) isNotEqualTo ""}) then { _L set ["quality", (_L get "quality") max 95]; };
    };
    // Texte de l'éditeur SSE. Rubrique sans valeur (modèle pas rempli) : ignorée.
    private _written = _main getOrDefault ["legendText", ""];
    if (_written isNotEqualTo "") then {
        private _keys = createHashMapFromArray [["adresse", "address"], ["ville", "city"], ["employeur", "employer"], ["profession", "profession"], ["famille", "family"], ["raison du déplacement", "travel"]];
        private _free = [];
        {
            private _line = trim _x;
            private _sep = _line find ":";
            private _label = if (_sep > 0) then { toLower (trim (_line select [0, _sep])) } else { "" };
            private _value = if (_sep > 0) then { trim (_line select [_sep + 1]) } else { _line };
            if (_value isNotEqualTo "") then {
                switch (true) do {
                    case (_label in _keys): { _L set [_keys get _label, _value]; };
                    case (_label isEqualTo "téléphone"): { _phone set ["number", _value]; };
                    default { _free pushBack _line; };
                };
            };
        } forEach (_written splitString (toString [13, 10]));
        if (_free isNotEqualTo []) then { (_L get "history") insert [0, [_free joinString " "]]; };
        if (_kind isEqualTo "cover") then { _L set ["quality", (_L get "quality") max 95]; };
    };
    // Couverture importée d'un fichier généré sur Athena (app Ma couverture › Importer, fn_idCoverImport) : elle l'emporte
    // champ par champ, sans toucher aux papiers ni au registre des identités.
    private _imp = if (_uid isEqualTo "") then { createHashMap } else { (_reg getOrDefault ["imports", createHashMap]) getOrDefault [_uid, createHashMap] };
    if ((count _imp) > 0) then {
        private _iv = { _imp getOrDefault [_this, ""] };
        private _en = (_imp getOrDefault ["lang", "fr"]) isEqualTo "en";
        private _set = { params ["_k", "_to"]; private _v = _k call _iv; if (_v isEqualType "" && {_v isNotEqualTo ""}) then { _L set [_to, _v]; }; };
        { _x call _set } forEach [["last_name", "last"], ["first_name", "first"], ["dob", "dob"], ["pob", "pob"], ["address", "address"], ["city", "city"],
            ["employer", "employer"], ["profession", "profession"], ["travel", "travel"], ["family", "family"]];
        _L set ["name", format ["%1 %2", toUpper (_L get "last"), _L get "first"]];
        if (("phone" call _iv) isNotEqualTo "") then { _phone set ["number", "phone" call _iv]; };
        private _ct = (_imp getOrDefault ["contacts", []]) select { _x isEqualType [] && {(_x param [0, ""]) isNotEqualTo ""} };
        if (_ct isNotEqualTo []) then { _contacts resize 0; { _contacts pushBack _x; } forEach _ct; };
        private _kinds = if (_en) then { ["ID card", "Passport", "Driving licence", "Military ID"] } else { ["Carte d'identité", "Passeport", "Permis de conduire", "Carte militaire"] };
        private _paper = (_imp getOrDefault ["docs", []]) apply {
            _x params [["_t", ""], ["_c", ""], ["_n", ""], ["_i", ""], ["_e", ""]];
            format [["%1 n° %2 (%3, valable jusqu'au %4)", "%1 no. %2 (%3, valid until %4)"] select _en, _kinds select ((["id_card", "passport", "driving_license", "military_id"] find _t) max 0), _n, _c, _e]
        };
        if (_paper isNotEqualTo []) then { _L set ["extraDocs", _paper]; };
        private _hist = _imp getOrDefault ["history", []];
        if (_hist isNotEqualTo []) then { _L set ["history", +_hist]; };
        private _by = parseNumber ((_L get "dob") select [0, 4]);
        private _age = [30, ((date select 0) - _by) max 18] select (_by > 1900);
        _L set ["biography", format [["%1 %2, %3 ans, %4 à %5. %6", "%1 %2, %3 years old, %4 in %5. %6"] select _en, _L get "first", toUpper (_L get "last"), _age, toLower (_L get "profession"), _L get "city", _L get "family"]];
        _L set ["phrases", [
            ["Je ne fais que passer, je rentre chez moi.", "I'm only passing through, I'm on my way home."] select _en,
            [format ["Vous pouvez appeler %1, il vous le confirmera.", ((_L get "contacts") param [0, ["?"]]) select 0], format ["You can call %1, they will confirm it.", ((_L get "contacts") param [0, ["?"]]) select 0]] select _en,
            [format ["J'habite %1.", _L get "city"], format ["I live in %1.", _L get "city"]] select _en
        ]];
        _L set ["quality", (_L get "quality") max 95];
        _L set ["prepared", true];
        _L set ["imported", true];
        _L set ["importedAt", "generated_at" call _iv];
        _L set ["importedRef", "ref" call _iv];
    };
    _L
};

private _get = {
    private _L = _legends getOrDefault [_key, createHashMap];
    // Papiers changés depuis (couverture donnée, kit remis) : légende recalculée.
    private _now = str (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry);
    if ((count _L) > 0 && {(_L getOrDefault ["docsSig", ""]) isEqualTo _now}) exitWith { _L };
    _L = call _build;
    _L set ["docsSig", _now];
    _legends set [_key, _L];
    _L
};

switch (_op) do {
    case "get": { call _get };
    case "questions": {
        private _L = call _get;
        private _n = ((_x1 max 1) min 4);
        private _cities = (([] call comspec_sse_fnc_idCountries) getOrDefault [_L get "country", createHashMap]) getOrDefault ["cities", []];
        private _dec = {
            params ["_good", "_pool"];
            private _p = (_pool select { _x isNotEqualTo _good }) call BIS_fnc_arrayShuffle;
            _p select [0, 2]
        };
        private _bank = [
            ["origin", "D'où venez-vous ?", _L get "city", [_L get "city", _cities] call _dec],
            ["employer", "Pour qui travaillez-vous ?", _L get "employer", [_L get "employer", ["Transports KARRAS", "Mairie de la ville", "Coopérative agricole", "Garage du port", "Hôtel central", "Sans employeur (petits travaux à la journée)"]] call _dec],
            ["travel", "Raison du déplacement ?", _L get "travel", [_L get "travel", ["Je rentre chez moi après le travail", "Une livraison pour mon employeur", "Rendre visite à ma famille", "Un rendez-vous médical", "Chercher du travail"]] call _dec],
            ["job", "Quel est votre métier ?", _L get "profession", [_L get "profession", ["Commerçant", "Chauffeur", "Mécanicien", "Enseignant", "Pêcheur", "Électricien", "Fonctionnaire"]] call _dec],
            ["pob", "Où êtes-vous né ?", _L get "pob", [_L get "pob", _cities] call _dec],
            ["contact", "Qui peut confirmer ce que vous dites ?", ((_L get "contacts") param [0, ["?"]]) select 0, [((_L get "contacts") param [0, ["?"]]) select 0, ["Personne", "Mon cousin, mais il est parti", "Je ne sais pas", "Le chef du village"]] call _dec]
        ];
        // L'origine, l'employeur et le motif d'abord, puis au hasard.
        private _out = _bank select [0, 3];
        _out = (_out call BIS_fnc_arrayShuffle) + ((_bank select [3]) call BIS_fnc_arrayShuffle);
        (_out select [0, _n]) select { (_x select 2) isNotEqualTo "" && {(count (_x select 3)) >= 1} }
    };
    case "aiAnswer": {
        private _L = call _get;
        private _k = _x1;
        if !(_k isEqualType "") then { _k = "origin"; };
        // Cohérence : civil presque toujours ; couverture selon sa préparation ; faux papiers mal appris.
        private _p = ((_L get "quality") / 100) * 0.95 + 0.04;
        if ((_L get "kind") isEqualTo "civil") then { _p = 0.98; };
        private _ok = (random 1) < _p;
        private _cities = (([] call comspec_sse_fnc_idCountries) getOrDefault [_L get "country", createHashMap]) getOrDefault ["cities", ["-"]];
        private _wrong = { params ["_good", "_pool"]; private _l = _pool select { _x isNotEqualTo _good }; [selectRandom _l, _good] select (_l isEqualTo []) };
        private _txt = switch (_k) do {
            case "employer": { format ["Je travaille pour %1.", [[_L get "employer", ["une entreprise du port", "un cousin, au noir"]] call _wrong, _L get "employer"] select _ok] };
            case "travel": { format ["%1.", [[_L get "travel", ["Je vais voir un ami", "Je me promène", "Je ne sais plus trop"]] call _wrong, _L get "travel"] select _ok] };
            case "job": { format ["Je suis %1.", toLower ([[_L get "profession", ["chauffeur", "commerçant", "mécanicien"]] call _wrong, _L get "profession"] select _ok)] };
            case "pob": { format ["Je suis né à %1.", [[_L get "pob", _cities] call _wrong, _L get "pob"] select _ok] };
            case "contact": { ["Personne ne peut le confirmer, je viens d'arriver.", format ["Vous pouvez appeler %1.", ((_L get "contacts") param [0, ["?"]]) select 0]] select _ok };
            default { format ["Je viens de %1, j'y habite.", [[_L get "city", _cities] call _wrong, _L get "city"] select _ok] };
        };
        [_txt, _ok]
    };
    case "sheet": {
        private _L = +(call _get);
        _L deleteAt "docsSig";
        // D'où vient ce qu'il porte : paquet Athena (brief, kit du compte Steam) ou né en jeu. Rien sur les autres joueurs.
        private _uid = getPlayerUID _u;
        private _brief = if (_uid isEqualTo "") then { createHashMap } else { (_reg getOrDefault ["briefs", createHashMap]) getOrDefault [_uid, createHashMap] };
        private _kit = if (_uid isEqualTo "") then { [] } else { (_reg getOrDefault ["kits", createHashMap]) getOrDefault [_uid, []] };
        _L set ["source", ["local", "athena"] select ((count _brief) > 0 || {_kit isNotEqualTo []})];
        // Couverture importée d'un fichier (et rien d'envoyé par Athena au serveur) : source « import ».
        if ((_L getOrDefault ["imported", false]) && {(_L get "source") isEqualTo "local"}) then { _L set ["source", "import"]; };
        private _at = _brief getOrDefault ["updated_at", ""];
        if !(_at isEqualType "") then { _at = ""; };
        _L set ["athenaAt", [_at, _reg getOrDefault ["packAt", ""]] select (_at isEqualTo "")];
        _L deleteAt "brief";
        // Papiers : ce qui est imprimé et s'ils sont sur lui, jamais la nature ni le vrai nom.
        private _countries = [] call comspec_sse_fnc_idCountries;
        private _onHim = ([_u, 0] call comspec_sse_fnc_idDocsOf) apply { _x select 0 };
        _L set ["docs", ((["unitDocs", [_u]] call comspec_sse_fnc_idRegistry) apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry }) apply {
            [
                ["Carte d'identité", "Passeport", "Permis de conduire", "Carte militaire"] select ((["id_card", "passport", "driving_license", "military_id"] find (_x getOrDefault ["type", ""])) max 0),
                (_countries getOrDefault [_x getOrDefault ["country", ""], createHashMap]) getOrDefault ["name", _x getOrDefault ["country", ""]],
                _x getOrDefault ["number", ""], _x getOrDefault ["expires", ""],
                ((_x getOrDefault ["cls", ""]) isNotEqualTo "") && {(_x getOrDefault ["cls", ""]) in _onHim}
            ]
        }];
        // Zeus : la vérité en plus (nature des papiers, vrai nom, cohérence). Joueur : sa propre fiche seulement, sans la nature.
        if (_x1 isNotEqualTo true) then { _L deleteAt "kind"; _L deleteAt "quality"; };
        if (_x1 isEqualTo true) then {
            private _d = ["doc", [_L get "docId"]] call comspec_sse_fnc_idRegistry;
            _L set ["trueName", format ["%1 %2", toUpper (_d getOrDefault ["trueLast", ""]), _d getOrDefault ["trueFirst", ""]]];
        };
        _L
    };
    default { nil };
}
