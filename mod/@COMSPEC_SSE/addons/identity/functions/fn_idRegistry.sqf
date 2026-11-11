/*
    Registre des documents (SERVEUR SEULEMENT, jamais diffusé) : missionNamespace comspec_sse_idReg.
      docs     docId -> document (données imprimées + vérité : trueLast/trueFirst, kind, quality, flaws, holderKey…)
      byClass  classe d'exemplaire -> docId          free   classe du catalogue -> numéros libres
      units    clé biométrique -> [docId…]           decided clé -> true (papiers automatiques déjà décidés)
      issued   "PAYS|type|numéro" -> [docId, nom, prénom, signalement] (base locale du pays, contrôle N3 hors ligne)
      watch    [[nom, prénom, alias, niveau, réf]…]  (avis locaux + paquet Athena)
      athena   "PAYS|type|numéro" -> document Athena (paquet)    countries  pays du paquet
      tokens   "poste|docId" -> fin de validité     flood  poste -> dernière demande     begin  "poste|op|cible" -> début
      controlled  netId -> [heure, contrôleur, libellé] (personnes contrôlées : interpellation au compte rendu AAR)
      Lot 4 et 5 : legends (clé -> légende, fn_idLegend) · covers (netId -> infiltration, fn_idCover) · forbidden (zones)
        questions (fn_idQuestion) · hvts (clé -> HVT) · bolos (réf. -> BOLO) · pidSeen (fn_idHvt) · briefs (uid -> brief
        RP du paquet) · population (identités préparées sur Athena, remises aux IA par « ensure »)
      aarSeen  constats déjà inscrits au compte rendu AAR (une ligne par document et par verdict)
    [_op, _args] call comspec_sse_fnc_idRegistry
      "init"                 crée le registre (XEH_postInit serveur)
      "alloc"   [catalogue]  -> classe d'exemplaire libre ("" si le stock est épuisé)
      "release" [classe]     rend la classe au stock (le document redevient virtuel)
      "doc"     [docId]      -> document (HashMap vide si inconnu)
      "ofClass" [classe]     -> document de l'exemplaire (HashMap vide : objet vierge pris à l'Arsenal)
      "unitDocs"[unité]      -> [docId…] (papiers automatiques décidés au premier appel : matérialisation paresseuse)
      "ensure"  [unité]      décide une fois des papiers de l'unité (attribut Eden, sinon règles des IA)
      "key"     [unité]      -> clé de la personne (clé biométrique)
*/
params [["_op", "", [""]], ["_args", []]];
if (!isServer) exitWith { nil };
if (_op isEqualTo "init") exitWith {
    if (!isNil "comspec_sse_idReg") exitWith { comspec_sse_idReg };
    private _free = createHashMap;
    // Stock par objet vierge : exemplaires <item>_001… déclarés (tools/gen_id_instances.py : 512 cartes, 256 passeports,
    // 256 permis, 256 cartes militaires ; extensions : leurs propres exemplaires), lus dans CfgComspecDocuments.
    {
        private _cls = ["item", _x] call comspec_sse_fnc_idDocTypes;
        private _n = ["stock", _x] call comspec_sse_fnc_idDocTypes;
        if (_cls isNotEqualTo "" && {_n > 0} && {!(_cls in _free)}) then { private _a = []; for "_i" from 1 to _n do { _a pushBack _i; }; _free set [_cls, _a]; };
    } forEach (keys ([] call comspec_sse_fnc_idDocTypes));
    comspec_sse_idReg = createHashMapFromArray [
        ["docs", createHashMap], ["byClass", createHashMap], ["free", _free], ["units", createHashMap],
        ["decided", createHashMap], ["issued", createHashMap], ["watch", []], ["athena", createHashMap],
        ["countries", createHashMap], ["tokens", createHashMap], ["flood", createHashMap], ["begin", createHashMap],
        ["requests", createHashMap], ["seq", 0], ["controlled", createHashMap], ["aarSeen", createHashMap]
    ];
    missionNamespace setVariable ["comspec_sse_idReg", comspec_sse_idReg];
    comspec_sse_idReg
};
private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
if ((count _reg) isEqualTo 0) then { _reg = ["init"] call comspec_sse_fnc_idRegistry; };
private _catOf = { private _c = ["item", _this] call comspec_sse_fnc_idDocTypes; [_c, "COMSPEC_ID_Card"] select (_c isEqualTo "") };

switch (_op) do {
    case "key": {
        _args params [["_u", objNull]];
        if (isNull _u) exitWith { "" };
        [_u] call comspec_sse_fnc_bioPersonKey
    };
    case "alloc": {
        _args params [["_type", "id_card"]];
        private _cat = _type call _catOf;
        private _list = (_reg get "free") getOrDefault [_cat, []];
        if (_list isEqualTo []) exitWith {
            diag_log text format ["[COMSPEC SSE] Identité : stock de %1 épuisé, document laissé virtuel.", _cat];
            ""
        };
        private _s = str (_list deleteAt 0);
        while { count _s < 3 } do { _s = "0" + _s; };
        format ["%1_%2", _cat, _s]
    };
    case "release": {
        _args params [["_cls", ""]];
        private _id = (_reg get "byClass") getOrDefault [_cls, ""];
        if (_id isEqualTo "") exitWith { false };
        (_reg get "byClass") deleteAt _cls;
        private _d = (_reg get "docs") getOrDefault [_id, createHashMap];
        _d set ["cls", ""];
        private _p = _cls splitString "_";
        private _n = parseNumber (_p select -1);
        private _cat = (_p select [0, (count _p) - 1]) joinString "_";
        ((_reg get "free") getOrDefault [_cat, []]) pushBack _n;
        true
    };
    case "kit": {
        // Apparition (InitPost) : attribut Eden imposé, ou documents vierges du kit délivrés au nom de la personne.
        _args params [["_u", objNull]];
        if (isNull _u || {!alive _u} || {!(missionNamespace getVariable ["comspec_sse_id_enabled", true])}) exitWith { false };
        if ((_u getVariable ["COMSPEC_ID_Carry", "auto"]) isNotEqualTo "auto") then { ["ensure", [_u]] call comspec_sse_fnc_idRegistry; };
        // Attribut Eden de groupe : le groupe porte le réglage, chaque unité le reprend à son apparition.
        { _x params ["_gv", "_uv"]; private _gval = (group _u) getVariable [_gv, ""]; if (_gval isNotEqualTo "" && {(_u getVariable [_uv, ""]) in ["", "auto"]}) then { _u setVariable [_uv, _gval]; }; } forEach [["COMSPEC_ID_GroupProfile", "COMSPEC_ID_AiProfile"], ["COMSPEC_ID_GroupBehaviour", "COMSPEC_ID_Behaviour"], ["COMSPEC_ID_GroupCarry", "COMSPEC_ID_Carry"]];
        // Attribut Eden « Infiltré (couverture) » : infiltration ouverte une fois les papiers posés.
        if (_u getVariable ["COMSPEC_ID_Undercover", false]) then { [{ ["start", [_this, createHashMap]] call comspec_sse_fnc_idCover; }, _u, 2] call CBA_fnc_waitAndExecute; };
        {
            private _cls = _x select 0;
            // Objet vierge du catalogue (classe de l'objet, pas un exemplaire) : délivré au nom de la personne.
            private _tk = ["ofItem", _cls] call comspec_sse_fnc_idDocTypes;
            if (_tk isNotEqualTo "" && {(toLower (["item", _tk] call comspec_sse_fnc_idDocTypes)) isEqualTo (toLower _cls)}) then {
                ["comspec_sse_idLocalRemove", [_u, _cls], _u] call CBA_fnc_targetEvent;
                [_u, createHashMapFromArray [["type", _tk], ["kind", "civil"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
            };
        } forEach ([_u, 0] call comspec_sse_fnc_idDocsOf);
        true
    };
    case "doc": { (_reg get "docs") getOrDefault [_args param [0, ""], createHashMap] };
    case "ofClass": {
        private _id = (_reg get "byClass") getOrDefault [_args param [0, ""], ""];
        (_reg get "docs") getOrDefault [_id, createHashMap]
    };
    case "unitDocs": {
        _args params [["_u", objNull]];
        if (isNull _u) exitWith { [] };
        ["ensure", [_u]] call comspec_sse_fnc_idRegistry;
        +((_reg get "units") getOrDefault [[_u] call comspec_sse_fnc_bioPersonKey, []])
    };
    case "ensure": {
        _args params [["_u", objNull]];
        if (isNull _u || {!(_u isKindOf "CAManBase")}) exitWith { false };
        private _key = [_u] call comspec_sse_fnc_bioPersonKey;
        if (_key isEqualTo "" || {(_reg get "decided") getOrDefault [_key, false]}) exitWith { false };
        (_reg get "decided") set [_key, true];
        // Papiers délivrés sur Athena à cette personne (clé biométrique du paquet) : ce sont les siens.
        private _ath = (_reg getOrDefault ["athenaByKey", createHashMap]) getOrDefault [_key, []];
        if (_ath isNotEqualTo []) exitWith {
            {
                private _spec = ["athenaSpec", _x] call comspec_sse_fnc_idModuleGive;
                if ((count _spec) > 0) then { _spec set ["materialize", isPlayer _u]; [_u, _spec] call comspec_sse_fnc_idIssue; };
            } forEach _ath;
            true
        };
        // Profil Athena relié à cette IA (Zeus, Eden, élément gardé) : ses papiers. Absent du paquet : suite normale, générée.
        private _aiPid = _u getVariable ["COMSPEC_ID_AiProfile", ""];
        if (_aiPid isEqualType "" && {_aiPid isNotEqualTo ""}) then {
            private _aiN = ["issue", [_u, _aiPid]] call comspec_sse_fnc_idAiConfig;
            if (_aiN isNotEqualTo 0) exitWith { _aiPid = "done"; };
        };
        if (_aiPid isEqualTo "done") exitWith { true };
        // Population préparée sur Athena (paquet, bloc « population ») : une identité du même camp et de la même catégorie
        // (civil, militaire, suspect, HVT), avec ses papiers émis, remise une seule fois.
        private _pop = _reg getOrDefault ["population", []];
        private _given = 0;
        if (_pop isNotEqualTo [] && {!isPlayer _u} && {(_u getVariable ["COMSPEC_ID_Carry", "auto"]) isEqualTo "auto"} && {missionNamespace getVariable ["comspec_sse_id_aiPapers", true]}) then {
            private _sk = ["east", "west", "guer", "civ"] select (([east, west, independent, civilian] find (side group _u)) max 0);
            private _bdb = toLower (_u getVariable ["COMSPEC_SSE_BioDb", "auto"]);
            private _pc = call {
                if (_bdb isEqualTo "bewl1") exitWith { "hvt" };
                if (_bdb in ["bewl2", "bewl3"]) exitWith { "suspect" };
                if (_sk isEqualTo "civ") exitWith { "civilian" };
                "military"
            };
            // Zone du RENS (nom de marqueur) : d'abord une entrée de la zone où se trouve l'IA, sinon une entrée sans zone.
            private _inZone = { private _z = _this getOrDefault ["zone", ""]; _z isEqualType "" && {_z in allMapMarkers} && {_u inArea _z} };
            private _noZone = { private _z = _this getOrDefault ["zone", ""]; !(_z isEqualType "") || {_z isEqualTo ""} || {!(_z in allMapMarkers)} };
            private _fits = { (_this getOrDefault ["side", "civ"]) isEqualTo _sk && {(_this getOrDefault ["category", "civilian"]) isEqualTo _pc} };
            private _i = _pop findIf { (_x call _fits) && {_x call _inZone} };
            if (_i < 0) then { _i = _pop findIf { (_x call _fits) && {_x call _noZone} }; };
            if (_i < 0 && {_pc in ["suspect", "hvt"]}) then { _i = _pop findIf { (_x getOrDefault ["side", "civ"]) isEqualTo _sk && {(_x call _inZone) || {_x call _noZone}} }; };
            if (_i >= 0) then {
                private _p = _pop deleteAt _i;
                private _byId = _reg getOrDefault ["athenaById", createHashMap];
                {
                    private _k = _byId getOrDefault [_x, ""];
                    if (_k isNotEqualTo "") then {
                        private _spec = ["athenaSpec", _k] call comspec_sse_fnc_idModuleGive;
                        if ((count _spec) > 0) then { _spec set ["materialize", false]; [_u, _spec] call comspec_sse_fnc_idIssue; _given = _given + 1; };
                    };
                } forEach (_p getOrDefault ["documents", []]);
            };
        };
        if (_given > 0) exitWith { true };
        private _carry = _u getVariable ["COMSPEC_ID_Carry", "auto"];
        private _kind = _u getVariable ["COMSPEC_ID_Kind", "civil"];
        private _quality = _u getVariable ["COMSPEC_ID_Quality", 60];
        private _country = _u getVariable ["COMSPEC_ID_Country", "auto"];
        if (_country isEqualTo "auto") then { _country = ""; };
        private _db = toLower (_u getVariable ["COMSPEC_SSE_BioDb", "auto"]);
        private _types = [];
        private _r = { [_key, "idcarry|" + _this] call comspec_sse_fnc_bioRand };
        private _wanted = "";
        if (_carry isEqualTo "auto") then {
            // Joueurs (et unités jouables) : aucun papier par défaut.
            if (isPlayer _u || {_u in playableUnits} || {_u in switchableUnits}) exitWith {};
            if !(missionNamespace getVariable ["comspec_sse_id_aiPapers", true]) exitWith {};
            private _civ = (side group _u) isEqualTo civilian;
            // Personnes fichées (biométrie) : HVT = couverture préparée, recherché = vrais papiers à son nom.
            if (_db isEqualTo "bewl1") exitWith { _types = ["id_card"]; _kind = "cover"; _quality = 90; _wanted = "hvt"; };
            if (_db isEqualTo "bewl2") exitWith { _types = ["id_card"]; _kind = "civil"; _wanted = "recherche"; };
            if (_db in ["bewl3", "bewl4"]) then { _wanted = "surveillance"; };
            if (_civ) exitWith {
                if (("has" call _r) < 0.95) then { _types pushBack "id_card"; };
                if (("lic" call _r) < 0.3) then { _types pushBack "driving_license"; };
                _kind = "civil";
            };
            // Combattant ennemi d'un joueur : faux papiers (réglage), sinon rien.
            private _enemy = (allPlayers findIf { [side group _x, side group _u] call BIS_fnc_sideIsEnemy }) >= 0;
            if (_enemy && {("forged" call _r) * 100 < (missionNamespace getVariable ["comspec_sse_id_forgedRate", 60])}) then {
                _types = ["id_card"]; _kind = "forged"; _quality = 25 + floor (("q" call _r) * 60);
            };
            // Militaire régulier sans faux papiers : sa carte militaire, vraie (sans uniforme, aucune).
            if (_types isEqualTo [] && {("mil" call _r) < 0.7} && {(uniform _u) isNotEqualTo ""}) then {
                private _uc = getText (configFile >> "CfgWeapons" >> uniform _u >> "ItemInfo" >> "uniformClass");
                if (_uc isNotEqualTo "" && {(getNumber (configFile >> "CfgVehicles" >> _uc >> "side")) in [0, 1, 2]}) then { _types = ["military_id"]; _kind = "civil"; };
            };
        } else {
            _types = switch (_carry) do {
                case "card": { ["id_card"] };
                case "passport": { ["passport"] };
                case "license": { ["driving_license"] };
                case "card_license": { ["id_card", "driving_license"] };
                case "card_passport": { ["id_card", "passport"] };
                case "military": { ["military_id"] };
                // Clé(s) de CfgComspecDocuments (sélecteur Eden : « press_card », « id_card,press_card »…).
                default { ((_carry splitString ",; ") apply { toLower (trim _x) }) select { (count (["get", _x] call comspec_sse_fnc_idDocTypes)) > 0 } };
            };
        };
        // IA sans papiers à l'issue des règles (uniforme civil d'un militaire, tirage) : une identité générée par défaut,
        // pour que « Papiers, s'il vous plaît » réponde. Seuls le réglage « IA sans papiers (%) » et l'attribut Eden « Aucun »
        // laissent quelqu'un sans papiers.
        if (_types isEqualTo [] && {_carry isEqualTo "auto"} && {!isPlayer _u} && {!(_u in playableUnits)} && {!(_u in switchableUnits)}
            && {missionNamespace getVariable ["comspec_sse_id_aiPapers", true]}
            && {("nopapers" call _r) * 100 >= (missionNamespace getVariable ["comspec_sse_id_noPapers", 0])}) then {
            _types = ["id_card"];
            _kind = "generated";
        };
        // Faux papiers décidés par la biométrie (fn_bioProfile, faux papiers « oui » ou tirés) : les papiers sont faux.
        if (_types isNotEqualTo [] && {_kind isEqualTo "civil"} && {_carry isEqualTo "auto"} && {(_u getVariable ["COMSPEC_SSE_BioFalsePapers", "auto"]) isNotEqualTo "non"}) then {
            private _prof = [_u, _key] call comspec_sse_fnc_bioProfile;
            if (_prof getOrDefault ["falsePapers", false]) then { _kind = "forged"; _quality = 70; };
        };
        // IA nommée par la mission (Zeus, Eden, atelier IA, profil, élément gardé) : ses papiers portent ce nom. Un faux tiré
        // par les règles automatiques (taux d'insurgés faussaires, biométrie « auto ») ne le remplace pas ; seuls un choix
        // explicite (attribut « Faux papiers », Zeus « Le rendre faux », biométrie « faux papiers : oui ») le rendent faux.
        if (_kind isEqualTo "forged" && {_carry isEqualTo "auto"} && {_u getVariable ["COMSPEC_SSE_NameAuthored", false]}
            && {(_u getVariable ["COMSPEC_ID_Kind", "civil"]) isNotEqualTo "forged"} && {(_u getVariable ["COMSPEC_SSE_BioFalsePapers", "auto"]) isNotEqualTo "oui"}) then {
            _kind = "civil";
        };
        // Faux tiré par les règles automatiques (pas un choix de la mission) : noté sur le papier ; si l'IA est nommée plus
        // tard, ses papiers redeviennent vrais à son nom (fn_idAiConfig « sync »).
        private _autoKind = _kind isEqualTo "forged" && {_carry isEqualTo "auto"} && {(_u getVariable ["COMSPEC_ID_Kind", "civil"]) isNotEqualTo "forged"}
            && {(_u getVariable ["COMSPEC_SSE_BioFalsePapers", "auto"]) isNotEqualTo "oui"};
        {
            private _spec = createHashMapFromArray [["type", _x], ["kind", _kind], ["quality", _quality], ["country", _country], ["autoKind", _autoKind]];
            // Le permis suit la carte : vrais papiers (le faux ne porte que sur la pièce d'identité).
            if (_x isEqualTo "driving_license" && {_forEachIndex > 0}) then { _spec set ["kind", "civil"]; };
            // Matérialisation tout de suite pour les joueurs (objet dans l'inventaire), paresseuse pour les IA.
            _spec set ["materialize", isPlayer _u || {_u in playableUnits}];
            [_u, _spec] call comspec_sse_fnc_idIssue;
        } forEach _types;
        // Avis local : la personne recherchée est fichée sous son vrai nom (base N3 hors ligne).
        if (_wanted isNotEqualTo "") then {
            private _own = ["get", [_u, _key]] call comspec_sse_fnc_idIdentityOf;
            private _true = trim (_u getVariable ["COMSPEC_SSE_BioTrueName", ""]);
            private _tl = _own get "last";
            private _tf = _own get "first";
            if (_true isNotEqualTo "") then { private _p = _true splitString " "; _tf = _p param [0, _tf]; _tl = (_p select [1, 9]) joinString " "; };
            (_reg get "watch") pushBack [_tl, _tf, _u getVariable ["COMSPEC_SSE_Alias", ""], _wanted, format ["LOC-%1", _key]];
        };
        true
    };
    default { nil };
}
