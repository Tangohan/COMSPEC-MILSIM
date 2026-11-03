/*
    Pays FICTIFS des documents (codes hors ISO, mention SIMULATION sur tous les gabarits). Pays réels STYLISÉS
    (fn_idRealCountries : Royaume-Uni, Grèce, États-Unis, Allemagne…) seulement dans un univers « réaliste —
    simulation » : réglage comspec_sse_id_realSim, ou pays importé par la communauté sur Athena (paquet).
    [] call comspec_sse_fnc_idCountries            -> HashMap code -> HashMap pays
    ["default"] call comspec_sse_fnc_idCountries   -> code du pays de la mission (réglage, sinon selon la carte ; pays
                                                      archivé sur Athena : premier pays actif)
    ["usable", [code, type]] call …                -> Boolean : le pays délivre encore ce type (actif, doc_types)
    Pays : code, name (FR), nameEn, mrz (code MRZ), number_format (# chiffre, @ lettre), authority, validity, chip,
    first / firstF / last / cities (réservoirs de noms) ; du paquet : theme, languages, docTypes, colors, active
    (false : pays archivé, plus de papiers générés, ceux déjà émis restent lisibles). Mêmes valeurs que le site (SseIdCountryRepository,
    SseIdentityGeneratorService, régions ALTIS / TANOA / LIVONIA / MALDEN) : une graine donne la même identité et le
    même numéro en jeu et sur Athena. Le paquet Athena (fn_idAthenaPack) peut en ajouter ou en corriger.
*/
params [["_mode", ""], ["_args", []]];
private _base = missionNamespace getVariable "comspec_sse_idCountryBase";
if (isNil "_base") then {
    _base = createHashMap;
    {
        _x params ["_code", "_name", "_nameEn", "_fmt", "_auth", "_cities", "_first", "_firstF", "_last"];
        _base set [_code, createHashMapFromArray [
            ["code", _code], ["name", _name], ["nameEn", _nameEn], ["mrz", _code], ["number_format", _fmt],
            ["authority", _auth], ["validity", 10], ["cities", _cities], ["chip", true], ["fictional", true],
            ["first", _first], ["firstF", _firstF], ["last", _last]
        ]];
    } forEach [
        ["ALT", "République d'Altis", "Republic of Altis", "AL@######", "Préfecture de Kavala",
            ["Kavala", "Pyrgos", "Athira", "Sofia", "Zaros", "Neochori", "Agios Dionysios", "Paros", "Charkia", "Telos", "Kore", "Molos"],
            ["Nikos", "Giorgos", "Dimitris", "Kostas", "Yannis", "Christos", "Panagiotis", "Vasilis", "Stavros", "Andreas", "Michalis", "Petros", "Spyros", "Thanos", "Alexandros"],
            ["Maria", "Eleni", "Katerina", "Sofia", "Anna", "Georgia", "Despina", "Ioanna", "Dimitra", "Vasiliki", "Christina", "Angeliki"],
            ["Papadopoulos", "Georgiou", "Nikolaidis", "Konstantinou", "Dimitriou", "Papadakis", "Vlachos", "Karagiannis", "Oikonomou", "Makris", "Antoniou", "Stavrou", "Pappas", "Kyriakou", "Theodorou", "Mavridis"]],
        ["TNA", "Fédération de Tanoa", "Federation of Tanoa", "T########", "Bureau de l'état civil de Georgetown",
            ["Georgetown", "Lijnhaven", "Katkoula", "Tuvanaka", "La Rochelle", "Balavu", "Ouméré", "Savaka", "Moddergat", "Nicolet"],
            ["Josefa", "Viliame", "Semi", "Tevita", "Jone", "Peni", "Etonia", "Malakai", "Isikeli", "Ratu", "Samuela", "Waisea"],
            ["Mere", "Ana", "Litia", "Salote", "Losana", "Vika", "Asenaca", "Unaisi", "Mereani", "Sereana"],
            ["Naivalu", "Tuilagi", "Rabuka", "Vunipola", "Koroi", "Tabua", "Delai", "Nakarawa", "Ravouvou", "Lomani", "Waqa", "Seru", "Baleiwai", "Matanitobua"]],
        ["LVN", "République de Livonie", "Republic of Livonia", "LV#######", "Mairie de Lubjansk",
            ["Lubjansk", "Gieraltów", "Topolin", "Nadbór", "Sobótka", "Dolnik", "Grabin", "Lipina", "Huta", "Wrzeszcz"],
            ["Piotr", "Tomasz", "Krzysztof", "Paweł", "Michał", "Marek", "Jakub", "Andrzej", "Grzegorz", "Łukasz", "Wojciech", "Kamil"],
            ["Anna", "Katarzyna", "Magdalena", "Agnieszka", "Joanna", "Monika", "Ewa", "Zofia", "Małgorzata", "Natalia"],
            ["Nowak", "Kowalski", "Wiśniewski", "Wójcik", "Kamiński", "Lewandowski", "Zieliński", "Szymański", "Woźniak", "Dąbrowski", "Kozłowski", "Jankowski", "Mazur", "Krawczyk"]],
        ["MLD", "République de Malden", "Republic of Malden", "M@@######", "Préfecture du Port",
            ["Le Port", "La Pessagne", "Lolisse", "Goisse", "Arudy", "Chapoi", "Larche", "Houdan", "Vigny", "Saint-Louis"],
            ["Jean", "Pierre", "Louis", "Antoine", "Mathieu", "Nicolas", "Julien", "Olivier", "Thomas", "Laurent", "Étienne", "Rémi"],
            ["Marie", "Claire", "Camille", "Julie", "Élodie", "Sophie", "Anne", "Céline", "Manon", "Hélène"],
            ["Martin", "Bernard", "Moreau", "Laurent", "Lefèvre", "Girard", "Rousseau", "Fournier", "Morel", "Guérin", "Mercier", "Blanc", "Faure", "André", "Chevalier", "Leroy"]]
    ];
    missionNamespace setVariable ["comspec_sse_idCountryBase", _base];
};
// Univers réaliste-simulation (réglage de mission) : les pays réels stylisés s'ajoutent aux pays fictifs.
private _real = [] call comspec_sse_fnc_idRealCountries;
if (missionNamespace getVariable ["comspec_sse_id_realSim", false]) then {
    _base = +_base;
    { _base set [_x, +_y]; } forEach _real;
};

if (_mode isEqualTo "default") exitWith {
    private _set = missionNamespace getVariable ["comspec_sse_id_country", "auto"];
    private _code = if (_set isNotEqualTo "auto" && {_set in _base}) then { _set } else {
        switch (toLower worldName) do {
            case "tanoa": { "TNA" };
            case "enoch": { "LVN" };
            case "malden": { "MLD" };
            default { "ALT" };
        }
    };
    // Pays archivé sur Athena : premier pays encore actif (intégrés d'abord, puis ceux du paquet).
    private _all = [] call comspec_sse_fnc_idCountries;
    if ((_all getOrDefault [_code, createHashMap]) getOrDefault ["active", true]) exitWith { _code };
    private _codes = ["ALT", "TNA", "LVN", "MLD"] + ((keys _all) - ["ALT", "TNA", "LVN", "MLD"]);
    private _i = _codes findIf { (_all getOrDefault [_x, createHashMap]) getOrDefault ["active", true] };
    [_code, _codes select _i] select (_i >= 0)
};
if (_mode isEqualTo "usable") exitWith {
    _args params [["_code", ""], ["_type", ""]];
    private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_code, createHashMap];
    if ((count _c) isEqualTo 0 || {!(_c getOrDefault ["active", true])}) exitWith { false };
    private _t = _c getOrDefault ["docTypes", []];
    _t isEqualTo [] || {_type isEqualTo ""} || {_type in _t}
};

// Pays du paquet Athena (serveur) par-dessus les pays intégrés (le nom d'affichage intégré est gardé s'il existe).
private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
private _extra = _reg getOrDefault ["countries", createHashMap];
if ((count _extra) isEqualTo 0) exitWith { _base };
private _all = +_base;
{
    private _code = _x;
    private _known = _code in _all;
    // Pays réel stylisé importé par la communauté : réservoirs de noms, villes, durées du jeu stylisé.
    private _c = _all getOrDefault [_code, +(_real getOrDefault [_code, createHashMapFromArray [["code", _code], ["mrz", _code], ["number_format", "@@#######"], ["cities", ["-"]], ["chip", true], ["nameEn", _code], ["validity", 10], ["authority", ""]]])];
    {
        if !(_known && {_x isEqualTo "name"}) then { if (_y isNotEqualTo "") then { _c set [_x, _y]; }; };
    } forEach _y;
    _all set [_code, _c];
} forEach _extra;
_all
