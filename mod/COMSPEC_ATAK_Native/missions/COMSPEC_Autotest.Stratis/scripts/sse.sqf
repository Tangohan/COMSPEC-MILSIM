// SSE : contrôle d'identité d'une IA de jour et de nuit, bulle, document et photo ; éditeur ; écran numérique d'une preuve.
if !("sse" call AT_fnc_groupe) exitWith {};
// Comme l'action ACE du joueur (fn_initAceIdentity) : « begin » au serveur, barre de 2 s, puis la demande.
AT_fnc_demande = {
    params ["_u"];
    missionNamespace setVariable ["comspec_sse_idLastAnswer", nil];
    ["send", ["begin", [player, "ask", _u]]] call comspec_sse_fnc_idClient;
    [2, "Papiers, s'il vous plaît…", { params ["_t"]; ["send", ["ask", [player, _t]]] call comspec_sse_fnc_idClient; }, [_u], _u] call comspec_sse_fnc_progressAction;
};
AT_fnc_controle = {
    params ["_u", "_doc", "_tag"];
    [_u] call AT_fnc_demande;
    private _rep = { ((missionNamespace getVariable ["comspec_sse_idLastAnswer", [objNull]]) select 0) isEqualTo _u };
    [[_rep, 15] call AT_fnc_wait, "pas de réponse à « Papiers s'il vous plaît »"] call AT_fnc_check;
    (format ["réponse : %1", (missionNamespace getVariable ["comspec_sse_idLastAnswer", []]) select [1, 2]]) call AT_fnc_info;
    [format ["papiers_reponse_%1", _tag]] call AT_fnc_shot;
    // Réponse naturelle tirée au sort (profil de l'IA) : si elle ne présente pas, on la rend pour voir le document.
    if (((missionNamespace getVariable ["comspec_sse_idLastAnswer", []]) param [1, ""]) isNotEqualTo "present") then {
        _u setVariable ["ace_captives_isSurrendering", true, true];
        uiSleep 1;
        [_u] call AT_fnc_demande;
        [_rep, 15] call AT_fnc_wait;
    };
    uiSleep 1;
    // Pièce réellement présentée : celle du jeton, sinon celle qu'on lui a délivrée.
    private _jetons = missionNamespace getVariable ["comspec_sse_idTokens", createHashMap];
    private _tendus = (keys _jetons) select { ((_jetons get _x) select 0) isEqualTo _u };
    private _h = _tendus param [0, _doc];
    ["send", ["begin", [player, "view", _h]]] call comspec_sse_fnc_idClient;
    [2, "Examen du document…", { params ["_h"]; ["send", ["view", [player, _h]]] call comspec_sse_fnc_idClient; }, [_h], _u] call comspec_sse_fnc_progressAction;
    private _v = { !isNull (uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull]) };
    [[_v, 8] call AT_fnc_wait, format ["visionneuse du document %1 non ouverte", _h]] call AT_fnc_check;
    uiSleep 2; // photo en direct (PiP)
    [format ["document_%1", _tag]] call AT_fnc_shot;
    if (call _v) then {
        ["shoot"] call comspec_sse_fnc_idPhoto;
        uiSleep 2;
        [format ["document_photo_%1", _tag]] call AT_fnc_shot;
    };
    closeDialog 0;
    uiSleep 1;
};
AT_fnc_ia = {
    private _g = createGroup civilian;
    private _u = _g createUnit ["C_man_1", player getPos [2.5, getDir player], [], 0, "CAN_COLLIDE"];
    _u setDir ((getDir player) + 180);
    _u disableAI "PATH";
    _u allowDamage false;
    _u
};

["sse_controle_jour_nuit", {
    if (["comspec_sse_fnc_idClient", "comspec_sse_fnc_idIssue", "comspec_sse_fnc_idPhoto"] call AT_fnc_nil) exitWith {};
    private _date0 = date;
    private _u = call AT_fnc_ia;
    uiSleep 2;
    private _doc = [_u, createHashMapFromArray [["type", "id_card"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    if !([_doc isNotEqualTo "", "document de l'IA non délivré (fn_idIssue)"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    // Bulle au-dessus de la tête (fn_aiBubble) : vue si l'IA est en ligne de vue.
    if (isNil "comspec_atak_native_fnc_aiBubble") then {
        "fn_aiBubble absente de ce build" call AT_fnc_info;
    } else {
        [["say", [_u, "Papiers : voici ma carte.", name _u, "INFO"]] call comspec_atak_native_fnc_aiBubble, "bulle non affichée, IA en ligne de vue à 2,5 m"] call AT_fnc_check;
        uiSleep 0.5;
        ["bulle_tete"] call AT_fnc_shot;
    };
    setDate [_date0 select 0, _date0 select 1, _date0 select 2, 12, 0];
    uiSleep 1;
    [_u, _doc, "jour"] call AT_fnc_controle;
    [["IDCHECK", 2] call AT_fnc_page, "app Contrôle ID non ouverte"] call AT_fnc_check;
    ["controle_id_app"] call AT_fnc_shot;
    call AT_fnc_nettoie;
    uiSleep 1;
    setDate [_date0 select 0, _date0 select 1, _date0 select 2, 23, 30];
    uiSleep 1;
    ["nuit_scene"] call AT_fnc_shot;
    [_u, _doc, "nuit"] call AT_fnc_controle;
    setDate _date0;
    "document et photo lisibles de nuit (visage non noir)" call AT_fnc_visuel;
    deleteVehicle _u;
    call AT_fnc_done;
}, 150] call AT_fnc_run;

["sse_editeur_5_types", {
    if (["comspec_sse_fnc_editor"] call AT_fnc_nil) exitWith {};
    ["open", [getPosATL player, objNull]] call comspec_sse_fnc_editor;
    private _ouvert = { !isNull (uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull]) };
    if !([[_ouvert, 6] call AT_fnc_wait, "éditeur non ouvert"] call AT_fnc_check) exitWith { call AT_fnc_done; };
    uiSleep 1;
    ["editeur_guide"] call AT_fnc_shot;
    for "_i" from 1 to 6 do { ["guideNext"] call comspec_sse_fnc_editor; uiSleep 0.2; };
    {
        _x params ["_i", "_lib"];
        ["type", _i] call comspec_sse_fnc_editor;
        uiSleep 0.8;
        [format ["editeur_type_%1", _lib]] call AT_fnc_shot;
    } forEach [[0, "renseignement"], [1, "identite"], [2, "papier"], [3, "dossier"], [4, "kit"]];
    ["toggleLibrary"] call comspec_sse_fnc_editor;
    uiSleep 1;
    ["editeur_bibliotheque"] call AT_fnc_shot;
    ["close"] call comspec_sse_fnc_editor;
    // La variable de l'éditeur n'est remise à zéro qu'à l'image suivante (onUnload) : attendre au lieu de tester tout de suite.
    [[{ !(call _ouvert) }, 4] call AT_fnc_wait, "éditeur non fermé"] call AT_fnc_check;
    if (call _ouvert) then { closeDialog 0; };
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["sse_preuve_numerique", {
    if (["comspec_sse_fnc_generateData", "comspec_sse_fnc_revealDigitalFog", "comspec_sse_fnc_openDigitalPanel"] call AT_fnc_nil) exitWith {};
    private _o = createVehicle ["Land_MobilePhone_smart_F", player getPos [1.5, getDir player], [], 0, "CAN_COLLIDE"];
    _o setVariable ["comspec_sse_forcedType", "PHONE", true];
    [_o, "INSURGENT", "DETAILED", "AUTOTEST"] call comspec_sse_fnc_generateData;
    uiSleep 1;
    private _fog = [_o, "full", 78] call comspec_sse_fnc_revealDigitalFog;
    [_fog isEqualType createHashMap && {(count _fog) > 0} && {_fog getOrDefault ["ok", true]}, "aucune donnée numérique révélée"] call AT_fnc_check;
    [_fog, _o] call comspec_sse_fnc_openDigitalPanel;
    [[{ dialog }, 6] call AT_fnc_wait, "écran numérique de la preuve non ouvert"] call AT_fnc_check;
    uiSleep 1.5;
    ["preuve_numerique"] call AT_fnc_shot;
    closeDialog 0;
    deleteVehicle _o;
    call AT_fnc_done;
}, 45] call AT_fnc_run;

// Papier relié à une identité Athena (API fn_idPaperLink / idPaperOf / idPaperOwner, évènements CBA) : une IA reçoit sa
// carte, elle est reliée à l'identité IDT-000001 d'un propriétaire, une autre unité puis le joueur la ramassent ; l'identité
// renvoyée reste celle du papier (jamais celle du porteur). Noms d'objets sans « simulation » (la mention est sur le papier).
["sse_papier_lie", {
    if (["comspec_sse_fnc_idIssue", "comspec_sse_fnc_idPaperOf", "comspec_sse_fnc_idPaperOwner", "comspec_sse_fnc_idPaperLink", "comspec_sse_fnc_idPaperTrack"] call AT_fnc_nil) exitWith {};
    {
        private _n = getText (configFile >> "CfgWeapons" >> _x >> "displayName");
        [_n isNotEqualTo "" && {((toLower _n) find "simulation") < 0}, format ["nom d'objet avec « simulation » : %1 (%2)", _x, _n]] call AT_fnc_check;
    } forEach ["COMSPEC_ID_Card", "COMSPEC_ID_Passport", "COMSPEC_ID_DrivingLicense", "COMSPEC_ID_MilitaryCard", "COMSPEC_ID_Card_001", "COMSPEC_ID_MilitaryCard_001"];
    private _a = call AT_fnc_ia;
    private _b = call AT_fnc_ia;
    _b setPosATL (player getPos [3.5, getDir player]);
    uiSleep 1;
    private _doc = [_a, createHashMapFromArray [["type", "id_card"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    if !([_doc isNotEqualTo "", "papier de l'IA non délivré (fn_idIssue)"] call AT_fnc_check) exitWith { deleteVehicle _a; deleteVehicle _b; call AT_fnc_done; };
    private _p0 = [_doc] call comspec_sse_fnc_idPaperOf;
    private _cls = _p0 getOrDefault ["cls", ""];
    [_cls isNotEqualTo "" && {(_p0 get "holder") isEqualTo _a}, format ["exemplaire non posé chez l'IA (%1)", _cls]] call AT_fnc_check;
    [[_cls, createHashMapFromArray [["athenaIdentity", "IDT-000001"], ["ownerUid", "AUTOTEST-OWNER"], ["ownerName", "Propriétaire"]]] call comspec_sse_fnc_idPaperLink, "lien papier ↔ identité Athena refusé"] call AT_fnc_check;
    private _p1 = [_cls] call comspec_sse_fnc_idPaperOf;
    [(_p1 get "athenaRef") isEqualTo "IDT-000001" && {(_p1 get "athenaIdentityId") isEqualTo "1"}, format ["identité Athena du papier : %1", _p1 get "athenaRef"]] call AT_fnc_check;
    // Ramassé par une autre unité, puis par le joueur : évènement CBA, porteur suivi, identité inchangée.
    missionNamespace setVariable ["AT_paperPicked", []];
    private _eh = ["comspec_sse_identity_paperPicked", { missionNamespace setVariable ["AT_paperPicked", _this]; }] call CBA_fnc_addEventHandler;
    _a removeItem _cls;
    _b addItem _cls;
    ["take", [_b, _cls, _a]] call comspec_sse_fnc_idPaperTrack;
    [[{ ((missionNamespace getVariable ["AT_paperPicked", []]) param [1, ""]) isEqualTo _cls }, 3] call AT_fnc_wait, "évènement comspec_sse_identity_paperPicked non reçu"] call AT_fnc_check;
    private _p2 = [_cls] call comspec_sse_fnc_idPaperOf;
    [(_p2 get "holder") isEqualTo _b, "porteur non suivi après ramassage"] call AT_fnc_check;
    [(_p2 get "last") isEqualTo (_p0 get "last") && {(_p2 get "first") isEqualTo (_p0 get "first")}, format ["identité du papier changée : %1 %2", _p2 get "last", _p2 get "first"]] call AT_fnc_check;
    _b removeItem _cls;
    player addItem _cls;
    ["take", [player, _cls, _b]] call comspec_sse_fnc_idPaperTrack;
    private _own = [_cls] call comspec_sse_fnc_idPaperOwner;
    [(_own select 0) isEqualTo "AUTOTEST-OWNER" && {(_own select 2) isEqualTo player}, format ["propriétaire / porteur : %1", _own]] call AT_fnc_check;
    [(_own select 4) isEqualTo ((getPlayerUID player) isNotEqualTo ""), "papier d'un autre non signalé « foreign »"] call AT_fnc_check;
    [((([_cls] call comspec_sse_fnc_idPaperOf) get "custody") apply { _x select 1 }) isEqualTo ["issued", "link", "take", "take"], "chaîne de possession incomplète"] call AT_fnc_check;
    (format ["papier %1 : %2 %3 (%4), porté par %5", _cls, _p2 get "last", _p2 get "first", _p2 get "athenaRef", name player]) call AT_fnc_info;
    ["comspec_sse_identity_paperPicked", _eh] call CBA_fnc_removeEventHandler;
    player removeItem _cls;
    deleteVehicle _a;
    deleteVehicle _b;
    call AT_fnc_done;
}, 45] call AT_fnc_run;

// Paquet Athena relayé par le téléphone du joueur (serveur sans COMSPEC Link, sans Zeus) : message d'échec précis avant,
// puis seuls ses papiers repris (kit d'un autre Steam ID ignoré), papier Athena remis et fiche « Identité reçue d'Athena ».
["sse_paquet_relais", {
    if (["comspec_sse_fnc_idAthenaPack", "comspec_sse_fnc_idLegend", "comspec_sse_fnc_idDocsOf"] call AT_fnc_nil) exitWith {};
    if (!isServer) exitWith { "poste non serveur : étape sautée" call AT_fnc_info; call AT_fnc_done; };
    private _uid = getPlayerUID player;
    if (_uid isEqualTo "") exitWith { "pas de Steam ID : étape sautée" call AT_fnc_info; call AT_fnc_done; };
    private _reg = ["init"] call comspec_sse_fnc_idRegistry;
    private _keep = createHashMap;
    { _keep set [_x, +(_reg getOrDefault [_x, createHashMap])]; } forEach ["packState", "selfPacks", "selfFails", "kits", "briefs"];
    private _packAt = _reg getOrDefault ["packAt", ""];
    _reg set ["packAt", ""];
    _reg set ["selfPacks", createHashMap];
    // 1. Échec du relais (liaison Athena coupée sur le poste) : la fiche dit l'étape et quoi faire.
    ["send", ["packFail", [player, "self", "uplink_off"]]] call comspec_sse_fnc_idClient;
    uiSleep 0.6;
    if (_uid isNotEqualTo "_SP_PLAYER_") then {
        private _d = (["sheet", [player, false]] call comspec_sse_fnc_idLegend) getOrDefault ["diag", []];
        private _ko = _d select { (_x select 1) isEqualTo "Paquet Athena non chargé" };
        [_ko isNotEqualTo [] && {((_ko select 0) select 2) find "service Athena sur Non" >= 0}, format ["raison du relais manquante : %1", _ko]] call AT_fnc_check;
        [(_d findIf { (_x select 1) isEqualTo "En attendant" }) >= 0, "conseil « Télécharger ma couverture » absent"] call AT_fnc_check;
    } else { "partie solo : diagnostic du relais non vérifié" call AT_fnc_info; };
    // 2. Paquet « self » : le kit d'un autre Steam ID, un avis et un pays déjà connu ne doivent pas être repris.
    private _cc = ["default"] call comspec_sse_fnc_idCountries;
    private _cName = (([] call comspec_sse_fnc_idCountries) getOrDefault [_cc, createHashMap]) getOrDefault ["name", ""];
    private _num = format ["%1990101", _cc];
    private _raw = str [
        ["version", 1], ["generated_at", "2026-11-10T19:00:00Z"], ["map", 1], ["op", "~self"],
        ["countries", [[["code", _cc], ["name", "PAYS REMPLACÉ"], ["mrz_code", _cc], ["number_format", ""], ["fictional", true]]]],
        ["identities", [[["id", 990001], ["person_id", ""], ["kind", "primary"], ["last_name", "AUTOTEST"], ["first_name", "Relais"], ["sex", "M"], ["birth_date", "1990-01-01"], ["birth_place", "Agia Marina"], ["nationality", _cc], ["arma_key", ""], ["profession", "Testeur"]]]],
        ["documents", [
            [["id", 990101], ["identity_id", 990001], ["type", "id_card"], ["country", _cc], ["number", _num], ["issued_on", "2025-01-01"], ["expires_on", "2035-01-01"], ["origin", "official"], ["validity", "valid"], ["report", ""]],
            [["id", 990102], ["identity_id", 990001], ["type", "passport"], ["country", _cc], ["number", _num + "9"], ["issued_on", "2025-01-01"], ["expires_on", "2035-01-01"], ["origin", "official"], ["validity", "valid"], ["report", ""]]
        ]],
        ["kits", [[["uid", _uid], ["user_id", 1], ["scope", "default"], ["documents", [990101]]], [["uid", "AUTOTEST-AUTRE"], ["user_id", 2], ["scope", "default"], ["documents", [990102]]]]],
        ["briefs", []], ["watch", [[["ref", "WL-AUTOTEST"], ["last_name", "NEPASREPRENDRE"]]]]
    ];
    ["applySelf", [player, _raw]] call comspec_sse_fnc_idAthenaPack;
    [((_reg get "kits") getOrDefault [_uid, []]) isEqualTo ["990101"], format ["kit relayé : %1", (_reg get "kits") getOrDefault [_uid, []]]] call AT_fnc_check;
    [!("AUTOTEST-AUTRE" in (_reg get "kits")), "kit d'un autre Steam ID repris depuis un paquet « self »"] call AT_fnc_check;
    [((_reg get "watch") findIf { (_x param [4, ""]) isEqualTo "WL-AUTOTEST" }) < 0, "avis de recherche repris depuis un paquet « self »"] call AT_fnc_check;
    [((([] call comspec_sse_fnc_idCountries) getOrDefault [_cc, createHashMap]) getOrDefault ["name", ""]) isEqualTo _cName, "pays connu remplacé par un paquet « self »"] call AT_fnc_check;
    [_uid in (_reg getOrDefault ["selfPacks", createHashMap]), "paquet « self » non noté"] call AT_fnc_check;
    private _mine = (["unitDocs", [player]] call comspec_sse_fnc_idRegistry) select { ((["doc", [_x]] call comspec_sse_fnc_idRegistry) getOrDefault ["athenaId", ""]) isEqualTo "990101" };
    [_mine isNotEqualTo [], "papier Athena du kit non remis au joueur"] call AT_fnc_check;
    if (_uid isNotEqualTo "_SP_PLAYER_") then {
        private _s = ["sheet", [player, false]] call comspec_sse_fnc_idLegend;
        [(_s getOrDefault ["origin", ""]) isEqualTo "athena", format ["fiche : origine %1 au lieu d'athena", _s getOrDefault ["origin", ""]]] call AT_fnc_check;
        [(_s getOrDefault ["packStep", ""]) isEqualTo "ok" && {(_s getOrDefault ["packVia", ""]) isEqualTo "self"}, format ["état remonté à Athena : %1 / %2", _s getOrDefault ["packStep", ""], _s getOrDefault ["packVia", ""]]] call AT_fnc_check;
    };
    (format ["paquet relayé : %1 papier(s) remis, %2", count _mine, _num]) call AT_fnc_info;
    // Nettoyage : papiers retirés, registre remis comme avant.
    { private _c = (["doc", [_x]] call comspec_sse_fnc_idRegistry) getOrDefault ["cls", ""]; if (_c isNotEqualTo "") then { player removeItem _c; }; } forEach _mine;
    { _reg set [_x, _keep get _x]; } forEach (keys _keep);
    _reg set ["packAt", _packAt];
    ["reset", [player]] call comspec_sse_fnc_idLegend;
    call AT_fnc_done;
}, 45] call AT_fnc_run;

// Papiers de la visionneuse : une pièce par papier (jour), capture de chacune ; textures de nuit présentes ; long texte non coupé.
["sse_papiers", {
    if (["comspec_sse_fnc_paperSkin", "comspec_sse_fnc_showResult"] call AT_fnc_nil) exitWith {};
    private _long = ((["Ligne de contrôle du défilement, texte long."] call { private _l = []; for "_i" from 1 to 40 do { _l pushBack (_this select 0); }; _l }) joinString " ");
    {
        _x params ["_code", "_lib"];
        [fileExists (["texture", [_code, true]] call comspec_sse_fnc_paperSkin), format ["texture de nuit absente : %1", _code]] call AT_fnc_check;
        private _doc = createHashMapFromArray [["uid", "AUTOTEST-" + _code], ["title", _lib], ["summary", ["Note : rendez-vous au pont à 22 h.", _long] select (_forEachIndex isEqualTo 0)], ["paper", _code]];
        private _fog = createHashMapFromArray [["title", "Dossier documentaire"], ["kind", "documents"], ["level", "documents"], ["docs", [_doc]], ["lines", []], ["quality", 90], ["uid", "AUTOTEST"], ["type", "DOCUMENTS"]];
        [_fog, objNull] call comspec_sse_fnc_showResult;
        if !([[{ !isNull findDisplay 93010 }, 6] call AT_fnc_wait, format ["visionneuse non ouverte : %1", _code]] call AT_fnc_check) exitWith {};
        uiSleep 0.6;
        private _d = findDisplay 93010;
        private _tex = _d displayCtrl 93027;
        // ctrlText d'une image de style 48 revient vide après ctrlSetText : la présence du papier se prouve par le contrôle affiché.
        [ctrlShown _tex, format ["papier non posé : %1", _code]] call AT_fnc_check;
        if (_forEachIndex isEqualTo 0) then {
            private _b = _d displayCtrl 93012;
            [((ctrlPosition _b) select 3) >= (ctrlTextHeight _b) - 0.001, "texte long coupé dans la visionneuse"] call AT_fnc_check;
        };
        [format ["papier_%1", _code]] call AT_fnc_shot;
        closeDialog 0;
        uiSleep 0.3;
    } forEach (["list"] call comspec_sse_fnc_paperSkin);
    call AT_fnc_done;
}, 120] call AT_fnc_run;

// Fenêtre « IA de la zone » (centre Zeus de l'ATAK, fn_aiConfigOpen) : trois IA civiles, profils Athena simulés (ingérés comme le
// paquet d'identité), une IA en fausse identité (vrai papier à un autre nom) : la liste donne le nom donné et l'icône « ment »,
// le vrai nom est dans la ligne Zeus du milieu ; aucun bouton, titre ni en-tête coupé ; le vrai papier est dessiné dans
// « Ce que verra le joueur » ; application d'un profil ; mémoire de la carte (garder puis supprimer) ; captures.
["sse_ia_zone", {
    if (["comspec_sse_fnc_aiConfigOpen", "comspec_sse_fnc_editorAi", "comspec_sse_fnc_editorAiServer", "comspec_sse_fnc_editorAiCard", "comspec_sse_fnc_aiWho", "comspec_sse_fnc_idAiConfig", "comspec_sse_fnc_idRegistry", "comspec_sse_fnc_idIssue"] call AT_fnc_nil) exitWith {};
    if (!isServer) exitWith { "serveur distant : profils Athena non simulables depuis ce poste" call AT_fnc_skip; };
    private _a = call AT_fnc_ia;
    private _b = call AT_fnc_ia;
    private _c = call AT_fnc_ia;
    _b setPosATL (player getPos [3.5, (getDir player) + 35]);
    _c setPosATL (player getPos [3.5, (getDir player) - 35]);
    uiSleep 1;
    private _menage = {
        ["ingest", [[], []]] call comspec_sse_fnc_idAiConfig;
        (["init"] call comspec_sse_fnc_idRegistry) set ["packAt", ""];
        { deleteVehicle _x; } forEach [_a, _b, _c];
    };
    // Profils simulés : même forme que « settings.ai_profiles » du paquet (fn_idAthenaPack -> fn_idAiConfig « ingest »).
    private _profils = [
        [["id", 9001], ["ref", "PRF-9001"], ["label", "Vendeur du marché"], ["name", "Stavros Vlachos"], ["side", "civ"], ["category", "civilian"], ["papers", "identity"], ["behaviour", "coopere"], ["documents", []]],
        [["id", 9002], ["ref", "PRF-9002"], ["label", "Chauffeur de taxi"], ["name", "Nikos Petrou"], ["side", "civ"], ["category", "suspect"], ["papers", "identity"], ["behaviour", "nerveux"], ["documents", []]],
        [["id", 9003], ["ref", "PRF-9003"], ["label", "Sergent de la milice"], ["name", "Ilias Markou"], ["side", "guer"], ["category", "military"], ["papers", "identity"], ["behaviour", "auto"], ["documents", []]]
    ];
    ["ingest", [_profils, []]] call comspec_sse_fnc_idAiConfig;
    (["init"] call comspec_sse_fnc_idRegistry) set ["packAt", "2026-11-10T20:30:00"];
    // _b : vrai papier à un autre nom que le sien (fausse identité : il ment).
    private _doc = [_b, createHashMapFromArray [["type", "id_card"], ["kind", "civil"], ["last", "Vlachos"], ["first", "Stavros"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    [_doc isNotEqualTo "", "papier de l'IA non délivré (fn_idIssue)"] call AT_fnc_check;
    private _qui = [_b] call comspec_sse_fnc_aiWho;
    [(_qui getOrDefault ["truth", ""]) isEqualTo "lies", format ["vérité attendue « lies », reçue « %1 » (donné %2, vrai %3)", _qui getOrDefault ["truth", ""], _qui getOrDefault ["given", ""], _qui getOrDefault ["true", ""]]] call AT_fnc_check;
    [[_a, _b, _c], getPosATL player] call comspec_sse_fnc_aiConfigOpen;
    private _ouvert = { !isNull (uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull]) };
    if !([[_ouvert, 6] call AT_fnc_wait, "fenêtre IA de la zone non ouverte"] call AT_fnc_check) exitWith { call _menage; call AT_fnc_done; };
    ["stop"] call comspec_sse_fnc_editorCoach;
    private _etat = { uiNamespace getVariable ["comspec_sse_editorAi", createHashMap] };
    [[{ (count ((call _etat) getOrDefault ["rows", []])) isEqualTo 3 }, 8] call AT_fnc_wait, "liste des IA non reçue (3 attendues)"] call AT_fnc_check;
    uiSleep 1;
    private _d = uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull];
    // Profils : 3 reçus, liste « Tirée au sort » + 3 profils, volet « Importer depuis Athena (3 profils) ».
    [(count ((call _etat) getOrDefault ["profiles", []])) isEqualTo 3, "profils Athena non reçus (3 attendus)"] call AT_fnc_check;
    [(lbSize (_d displayCtrl 95101)) isEqualTo 4, format ["liste des profils : %1 entrées (4 attendues)", lbSize (_d displayCtrl 95101)]] call AT_fnc_check;
    [((ctrlText (_d displayCtrl 95109)) find "3") >= 0, format ["volet Athena : « %1 »", ctrlText (_d displayCtrl 95109)]] call AT_fnc_check;
    // Ligne de _b : nom donné (papiers), jamais le nom d'unité Arma ; vérité « lies » et vrai nom différent dans la ligne reçue.
    private _l = _d displayCtrl 95017;
    private _rb = -1;
    for "_i" from 0 to (((lnbSize _l) select 0) - 1) do { if ((_l lnbData [_i, 0]) isEqualTo (netId _b)) exitWith { _rb = _i; }; };
    if ([_rb >= 0, "IA en fausse identité absente de la liste"] call AT_fnc_check) then {
        private _donne = _l lnbText [_rb, 1];
        [((toLower _donne) find "vlachos") >= 0, format ["nom donné : « %1 » (attendu Stavros VLACHOS)", _donne]] call AT_fnc_check;
        [_donne isNotEqualTo (name _b), "nom d'unité Arma présenté comme nom de l'IA"] call AT_fnc_check;
        private _ligne = ((call _etat) getOrDefault ["rows", []]) select { (_x select 0) isEqualTo (netId _b) };
        [((_ligne param [0, []]) param [3, ""]) isEqualTo "lies" && {((_ligne select 0) select 2) isNotEqualTo ((_ligne select 0) select 1)}, "vérité ou vrai nom de la ligne faux"] call AT_fnc_check;
    };
    // Fiche de _b (cochée seule) : papier reçu, titre au nom donné, vrai nom dans la ligne Zeus, papier dessiné à droite.
    ["checkNone"] call comspec_sse_fnc_editorAi;
    (call _etat) set ["checked", [netId _b]];
    (call _etat) set ["cur", netId _b];
    ["sheet"] call comspec_sse_fnc_editorAi;
    [[{ (count ((call _etat) getOrDefault ["docs", []])) isEqualTo 1 }, 6] call AT_fnc_wait, "papiers de l'IA non reçus (1 attendu)"] call AT_fnc_check;
    [[{ (count ((_d displayCtrl 95503) getVariable ["comspec_sse_cardCtrls", []])) > 3 }, 6] call AT_fnc_wait, "papier non dessiné dans « Ce que verra le joueur »"] call AT_fnc_check;
    uiSleep 0.5;
    [((toLower (ctrlText (_d displayCtrl 95030))) find "vlachos") >= 0, format ["titre de la fiche : « %1 »", ctrlText (_d displayCtrl 95030)]] call AT_fnc_check;
    [((toLower (ctrlText (_d displayCtrl 95031))) find (toLower (_qui getOrDefault ["true", "?"]))) >= 0, format ["vrai nom absent de la ligne Zeus : « %1 »", ctrlText (_d displayCtrl 95031)]] call AT_fnc_check;
    [!ctrlShown (_d displayCtrl 95504), "phrase d'attente affichée par-dessus le papier dessiné"] call AT_fnc_check;
    [_d displayCtrl 95508, "aperçu « au contrôle »"] call AT_fnc_tient;
    ["tab", "docs"] call comspec_sse_fnc_editorAi;
    uiSleep 0.3;
    [((lnbSize (_d displayCtrl 95208)) select 0) isEqualTo 1, "liste des papiers vide"] call AT_fnc_check;
    ["ia_zone_fiche"] call AT_fnc_shot;
    ["tab", "id"] call comspec_sse_fnc_editorAi;
    // Profil Athena appliqué à _b : la ligne passe sur PRF-9001 ; la fenêtre garde la sélection.
    private _pc = _d displayCtrl 95101;
    for "_i" from 0 to ((lbSize _pc) - 1) do { if ((_pc lbData _i) isEqualTo "9001") exitWith { _pc lbSetCurSel _i; }; };
    [(_pc lbData (lbCurSel _pc)) isEqualTo "9001", "profil PRF-9001 non sélectionnable"] call AT_fnc_check;
    [(call _etat) getOrDefault ["dirty", false], "réglage changé sans « Modifié, pas encore appliqué »"] call AT_fnc_check;
    ["applyChecked"] call comspec_sse_fnc_editorAi;
    private _applique = { (((call _etat) getOrDefault ["rows", []]) findIf { (_x select 0) isEqualTo (netId _b) && {(_x select 6) isEqualTo "PRF-9001"} }) >= 0 };
    [[_applique, 10] call AT_fnc_wait, "profil non appliqué (ligne sans PRF-9001 après Appliquer)"] call AT_fnc_check;
    uiSleep 0.5;
    [(netId _b) in ((call _etat) getOrDefault ["checked", []]), "sélection perdue après « Appliquer »"] call AT_fnc_check;
    [!((call _etat) getOrDefault ["dirty", true]), "« Modifié » encore affiché après « Appliquer »"] call AT_fnc_check;
    // Mémoire de la carte : gardée sur ce serveur (liste du volet), puis supprimée.
    ["memory"] call comspec_sse_fnc_editorAi;
    ["keepChecked"] call comspec_sse_fnc_editorAi;
    private _kc = _d displayCtrl 95806;
    private _garde = { (lbSize _kc) > 0 && {(_kc lbData 0) isNotEqualTo ""} };
    if ([[_garde, 10] call AT_fnc_wait, "IA gardée absente de la mémoire de la carte"] call AT_fnc_check) then {
        (format ["mémoire : %1", _kc lbText 0]) call AT_fnc_info;
        ["ia_zone_memoire"] call AT_fnc_shot;
        ["unkeep", [player, _kc lbData 0]] call comspec_sse_fnc_editorAiServer;
        uiSleep 1;
        [(lbSize _kc) > 0 && {(_kc lbData 0) isEqualTo ""}, "enregistrement non supprimé"] call AT_fnc_check;
    };
    ["close"] call comspec_sse_fnc_editor;
    [[{ !(call _ouvert) }, 4] call AT_fnc_wait, "fenêtre non fermée"] call AT_fnc_check;
    call _menage;
    call AT_fnc_done;
}, 120] call AT_fnc_run;

// Fenêtre « IA de la zone », chaque état (liste vide, liste remplie, chaque onglet, volets Athena, Filtres et Mémoire, visite
// guidée) : aucun texte coupé (AT_fnc_tient), aucune police sous la taille minimale (0.78 case de la grille), chaque liste
// déroulante assez large pour son plus long libellé, aucun contrôle visible qui en chevauche un autre (rectangles à l'écran).
["sse_ia_zone_etats", {
    if (["comspec_sse_fnc_aiConfigOpen", "comspec_sse_fnc_editorAi", "comspec_sse_fnc_editorCoach", "comspec_sse_fnc_uiGrid"] call AT_fnc_nil) exitWith {};
    ([] call comspec_sse_fnc_uiGrid) params ["", "", "_gw", "_gh"];
    private _min = _gh * 0.78 * 0.99;
    private _ouvert = { !isNull (uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull]) };
    private _etat = { uiNamespace getVariable ["comspec_sse_editorAi", createHashMap] };
    // Chevauchements et contrôles hors de leur propriétaire : audit de la fenêtre (fn_editorAi « audit », même registre et
    // mêmes contrôles posés dessous à dessein que tests/Unit/SseAiZoneWindowAssetTest.php).
    private _verifie = {
        params ["_nom"];
        private _d = uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull];
        private _vus = ((["idcs"] call comspec_sse_fnc_editorAi) apply { _d displayCtrl _x }) select { !isNull _x && {ctrlShown _x} };
        private _m = _d ctrlCreate ["RscText", -1];
        _m ctrlShow false;
        {
            private _c = _x;
            private _t = ctrlType _c;
            // Texte qui tient (une image n'a pas de texte : son chemin n'est pas mesuré).
            if (_t in [0, 1, 13] && {!((ctrlStyle _c) in [48, 2096])}) then { [_c, format ["%1 · %2", _nom, ctrlIDC _c]] call AT_fnc_tient; };
            if (_t in [0, 1, 2]) then { [(ctrlFontHeight _c) >= _min, format ["%1 · %2 : police %3 sous le minimum %4", _nom, ctrlIDC _c, ctrlFontHeight _c, _min]] call AT_fnc_check; };
            // Liste déroulante : chaque libellé tient dans la case (flèche déduite, carrée).
            if (_t isEqualTo 4) then {
                (ctrlPosition _c) params ["", "", "_w", "_h"];
                _m ctrlSetFont "RobotoCondensed";
                _m ctrlSetFontHeight (ctrlFontHeight _c);
                for "_i" from 0 to ((lbSize _c) - 1) do {
                    _m ctrlSetText (_c lbText _i);
                    [(ctrlTextWidth _m) <= (_w - _h * 0.75) * 1.02, format ["%1 · %2 : « %3 » coupé dans la liste", _nom, ctrlIDC _c, _c lbText _i]] call AT_fnc_check;
                };
            };
        } forEach _vus;
        ctrlDelete _m;
        // Registre : rien d'affiché hors des propriétaires visibles, aucun chevauchement dans une couche (page, volet ouvert),
        // un volet au plus.
        private _au = ["audit"] call comspec_sse_fnc_editorAi;
        [(_au getOrDefault ["orphans", [0]]) isEqualTo [], format ["%1 : contrôles affichés hors de leur propriétaire %2", _nom, _au getOrDefault ["orphans", []]]] call AT_fnc_check;
        [(_au getOrDefault ["overlaps", [0]]) isEqualTo [], format ["%1 : chevauchements %2", _nom, _au getOrDefault ["overlaps", []]]] call AT_fnc_check;
        [(count (_au getOrDefault ["volets", []])) <= 1, format ["%1 : plusieurs volets ouverts %2", _nom, _au getOrDefault ["volets", []]]] call AT_fnc_check;
    };
    // 1. Liste vide (aucune unité donnée, point loin de tout) : panneau d'aide (il renvoie aux boutons du haut).
    uiNamespace setVariable ["comspec_sse_editorAiUnits", []];
    ["open", [[0, 0, 0], objNull, "ai"]] call comspec_sse_fnc_editor;
    if !([[_ouvert, 6] call AT_fnc_wait, "fenêtre IA de la zone non ouverte"] call AT_fnc_check) exitWith { call AT_fnc_done; };
    ["stop"] call comspec_sse_fnc_editorCoach;
    // À l'ouverture : aucun volet ouvert, rien d'affiché hors du registre (« Bug » de la 1.9.0).
    "ouverture" call _verifie;
    [((call _etat) getOrDefault ["volet", "?"]) isEqualTo "", "un volet est ouvert à l'ouverture de la fenêtre"] call AT_fnc_check;
    [[{ (call _etat) getOrDefault ["scanned", false] }, 8] call AT_fnc_wait, "réponse du serveur non reçue"] call AT_fnc_check;
    uiSleep 0.5;
    private _d = uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull];
    if ((count ((call _etat) getOrDefault ["rows", []])) isEqualTo 0) then {
        [ctrlShown (_d displayCtrl 95019) && {ctrlShown (_d displayCtrl 95020)} && {!ctrlShown (_d displayCtrl 95017)}, "état vide sans son panneau d'aide"] call AT_fnc_check;
        "liste vide" call _verifie;
        ["ia_zone_vide"] call AT_fnc_shot;
    } else { "IA autour de [0,0] : état vide non vérifié" call AT_fnc_info; };
    // 2. Liste remplie, chaque onglet, puis les volets et la visite guidée.
    private _a = call AT_fnc_ia;
    private _b = call AT_fnc_ia;
    _b setPosATL (player getPos [3.5, (getDir player) + 35]);
    uiSleep 0.5;
    ["scanUnits", [_a, _b]] call comspec_sse_fnc_editorAi;
    [[{ (count ((call _etat) getOrDefault ["rows", []])) isEqualTo 2 }, 8] call AT_fnc_wait, "liste des IA non reçue (2 attendues)"] call AT_fnc_check;
    ["checkAll"] call comspec_sse_fnc_editorAi;
    uiSleep 0.5;
    "liste remplie" call _verifie;
    {
        ["tab", _x] call comspec_sse_fnc_editorAi;
        uiSleep 0.4;
        (format ["onglet %1", _x]) call _verifie;
        [format ["ia_zone_onglet_%1", _x]] call AT_fnc_shot;
    } forEach ["id", "docs", "beh", "side"];
    ["tab", "id"] call comspec_sse_fnc_editorAi;
    ["fold"] call comspec_sse_fnc_editorAi;
    uiSleep 0.3;
    "volet Athena" call _verifie;
    ["fold"] call comspec_sse_fnc_editorAi;
    ["filters"] call comspec_sse_fnc_editorAi;
    uiSleep 0.3;
    [!ctrlEnabled (_d displayCtrl 95017), "liste cliquable sous le volet Filtres"] call AT_fnc_check;
    "volet Filtres" call _verifie;
    ["ia_zone_filtres"] call AT_fnc_shot;
    ["filters"] call comspec_sse_fnc_editorAi;
    ["memory"] call comspec_sse_fnc_editorAi;
    uiSleep 0.3;
    "volet Mémoire de la carte" call _verifie;
    // Un seul volet : en ouvrir un autre ferme le premier ; Échap le ferme sans fermer la fenêtre.
    ["filters"] call comspec_sse_fnc_editorAi;
    [((call _etat) getOrDefault ["volet", ""]) isEqualTo "filt", "volet Filtres non ouvert à la place de la Mémoire"] call AT_fnc_check;
    "deux volets demandés" call _verifie;
    [["escape"] call comspec_sse_fnc_editorAi, "Échap n'a pas fermé le volet"] call AT_fnc_check;
    [((call _etat) getOrDefault ["volet", "?"]) isEqualTo "" && {call _ouvert}, "Échap : volet resté ouvert ou fenêtre fermée"] call AT_fnc_check;
    // Visite guidée : bulle à côté de sa cible (jamais dessus), texte et boutons entiers, aucun voile.
    ["coach"] call comspec_sse_fnc_editorAi;
    for "_s" from 1 to 3 do {
        uiSleep 0.4;
        [["open"] call comspec_sse_fnc_editorCoach, format ["visite guidée fermée avant l'étape %1", _s]] call AT_fnc_check;
        // Cible = l'intérieur du cadre vert (haut 95900, droite 95903).
        (ctrlPosition (_d displayCtrl 95900)) params ["_ox", "_oy"];
        (ctrlPosition (_d displayCtrl 95903)) params ["_rx", "", "", "_rh"];
        private _tx = _ox + _gw * 0.15;
        private _ty = _oy + _gh * 0.15;
        private _tb = _oy + _rh - _gh * 0.15;
        (ctrlPosition (_d displayCtrl 95905)) params ["_bx", "_by", "_bw", "_bh"];
        [!((_bx + _bw) > _tx && {_rx > _bx} && {(_by + _bh) > _ty} && {_tb > _by}), format ["étape %1 : la bulle couvre sa cible", _s]] call AT_fnc_check;
        { [_d displayCtrl _x, format ["visite %1 · %2", _s, _x]] call AT_fnc_tient; } forEach [95907, 95908, 95909, 95910];
        [format ["ia_zone_visite_%1", _s]] call AT_fnc_shot;
        ["next"] call comspec_sse_fnc_editorCoach;
    };
    [!(["open"] call comspec_sse_fnc_editorCoach), "visite guidée encore ouverte après « Terminer »"] call AT_fnc_check;
    [profileNamespace getVariable ["comspec_sse_aiZoneCoach", false], "visite terminée non notée dans le profil"] call AT_fnc_check;
    ["close"] call comspec_sse_fnc_editor;
    [[{ !(call _ouvert) }, 4] call AT_fnc_wait, "fenêtre non fermée"] call AT_fnc_check;
    { deleteVehicle _x; } forEach [_a, _b];
    call AT_fnc_done;
}, 150] call AT_fnc_run;

// App Ma couverture : fiche en cache. Réouverture dans la minute = aucune nouvelle demande au serveur ; Actualiser et
// Demander la mise à jour en envoient une ; fiche inchangée = réponse courte (coverSame, le cache est gardé et complété).
["sse_couverture_cache", {
    if (["comspec_sse_fnc_idCoverPage", "comspec_sse_fnc_idClient"] call AT_fnc_nil) exitWith {};
    private _n = { missionNamespace getVariable ["comspec_sse_idCoverAsks", 0] };
    private _fiche = { (missionNamespace getVariable ["comspec_sse_idCoverSheets", createHashMap]) getOrDefault [netId player, createHashMap] };
    private _at = { (missionNamespace getVariable ["comspec_sse_idCoverAt", createHashMap]) getOrDefault [netId player, -1] };
    if !([["IDCOVER", 2] call AT_fnc_page, "app Ma couverture non ouverte"] call AT_fnc_check) exitWith { call AT_fnc_done; };
    [[{ (count (call _fiche)) > 0 }, 10] call AT_fnc_wait, "fiche de couverture jamais reçue"] call AT_fnc_check;
    [((call _fiche) getOrDefault ["rev", ""]) isNotEqualTo "", "fiche sans empreinte « rev » (serveur antérieur ?)"] call AT_fnc_check;
    ["couverture_fiche"] call AT_fnc_shot;
    // Réouverture : rien de changé (équipement compris, le drapeau d'évènement est remis à zéro pour isoler le cache).
    missionNamespace setVariable ["comspec_sse_idCoverDirty", false];
    private _n1 = call _n;
    ["LAUNCHER", 1] call AT_fnc_page;
    ["IDCOVER", 2] call AT_fnc_page;
    [(call _n) isEqualTo _n1, format ["réouverture dans la minute : %1 demande(s) de fiche en plus (attendu 0)", (call _n) - _n1]] call AT_fnc_check;
    // Fiche inchangée : une demande non forcée revient en coverSame, le cache (et ce qu'on y a posé) est gardé.
    (call _fiche) set ["autotestMark", true];
    private _t0 = call _at;
    uiSleep 0.6;
    ["act", "ask", [player, false]] call comspec_sse_fnc_idCoverPage;
    [[{ (call _at) > _t0 }, 8] call AT_fnc_wait, "pas de réponse à la demande de fiche"] call AT_fnc_check;
    [(call _fiche) getOrDefault ["autotestMark", false], "fiche inchangée renvoyée en entier (attendu : coverSame)"] call AT_fnc_check;
    // Actualiser : relecture complète, toujours.
    private _n2 = call _n;
    uiSleep 0.6;
    ["act", "refresh"] call comspec_sse_fnc_idCoverPage;
    [(call _n) isEqualTo (_n2 + 1), "Actualiser n'a pas redemandé la fiche"] call AT_fnc_check;
    [[{ !((call _fiche) getOrDefault ["autotestMark", false]) }, 8] call AT_fnc_wait, "Actualiser : fiche complète non reçue"] call AT_fnc_check;
    // Demander la mise à jour : relecture aussi (le paquet Athena est rechargé à part, au plus toutes les 2 min).
    private _n3 = call _n;
    uiSleep 0.6;
    ["act", "pull"] call comspec_sse_fnc_idCoverPage;
    [(call _n) isEqualTo (_n3 + 1), "Demander la mise à jour n'a pas redemandé la fiche"] call AT_fnc_check;
    // Évènement (équipement changé) : la fiche est relue à l'ouverture suivante.
    uiSleep 5.5;
    private _n4 = call _n;
    missionNamespace setVariable ["comspec_sse_idCoverDirty", true];
    ["LAUNCHER", 1] call AT_fnc_page;
    ["IDCOVER", 2] call AT_fnc_page;
    [(call _n) isEqualTo (_n4 + 1), "fiche périmée par un évènement non relue à l'ouverture"] call AT_fnc_check;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// ---------- Documents : types du registre (jour et nuit, recto et verso), sans alerte Arma ; Relever ; sélecteur ; extension ----------
// Ouvre la visionneuse sur un document délivré (même chemin que le menu ACE « Examiner » : begin puis view au serveur).
AT_fnc_ouvreDoc = {
    params ["_id", "_u"];
    ["send", ["begin", [player, "view", _id]]] call comspec_sse_fnc_idClient;
    [2, "Examen du document…", { params ["_h"]; ["send", ["view", [player, _h]]] call comspec_sse_fnc_idClient; }, [_id], _u] call comspec_sse_fnc_progressAction;
    private _v = { !isNull (uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull]) };
    private _ok = [_v, 10] call AT_fnc_wait;
    uiSleep 2.5; // studio photo (PiP) en place
    _ok
};
// Compteur d'alertes Arma : BIS_fnc_showNotification remplacée le temps du test (restaurée à la fin).
AT_fnc_notifGarde = {
    params ["_on"];
    if (_on) then {
        AT_NotifOrig = BIS_fnc_showNotification;
        AT_NotifN = 0;
        BIS_fnc_showNotification = { AT_NotifN = AT_NotifN + 1; diag_log format ["AUTOTEST|INFO|alerte Arma interceptée : %1", _this]; false };
    } else {
        if (!isNil "AT_NotifOrig") then { BIS_fnc_showNotification = AT_NotifOrig; };
    };
};

["sse_documents_types", {
    if (["comspec_sse_fnc_idDocTypes", "comspec_sse_fnc_idIssue", "comspec_sse_fnc_idClient", "comspec_sse_fnc_idViewerOnLoad"] call AT_fnc_nil) exitWith {};
    private _date0 = date;
    private _types = ["list", [""]] call comspec_sse_fnc_idDocTypes;
    (format ["types de documents servis : %1", _types joinString ", "]) call AT_fnc_info;
    [(count _types) >= 4, format ["moins de 4 types dans le registre : %1", _types]] call AT_fnc_check;
    { [_x in _types, format ["type intégré absent : %1", _x]] call AT_fnc_check; } forEach ["id_card", "passport", "driving_license", "military_id"];
    private _u = call AT_fnc_ia;
    uiSleep 1;
    [true] call AT_fnc_notifGarde;
    {
        private _type = _x;
        private _t = ["get", _type] call comspec_sse_fnc_idDocTypes;
        private _spec = createHashMapFromArray [["type", _type], ["materialize", true]];
        { _spec set [_x select 0, _x select 2]; } forEach (_t getOrDefault ["inputs", []]);
        private _id = [_u, _spec] call comspec_sse_fnc_idIssue;
        if !([_id isNotEqualTo "", format ["document %1 non délivré", _type]] call AT_fnc_check) then { continue; };
        {
            _x params ["_h", "_tag"];
            setDate [_date0 select 0, _date0 select 1, _date0 select 2, _h, 0];
            uiSleep 0.8;
            if !([[_id, _u] call AT_fnc_ouvreDoc, format ["visionneuse non ouverte : %1 (%2)", _type, _tag]] call AT_fnc_check) then { continue; };
            private _d = uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull];
            private _tpl = ctrlText (_d displayCtrl 93720);
            [fileExists _tpl, format ["gabarit absent : %1 (%2)", _tpl, _type]] call AT_fnc_check;
            [format ["doc_%1_recto_%2", _type, _tag]] call AT_fnc_shot;
            if ((_t get "sides") > 1) then {
                ["side"] call comspec_sse_fnc_idViewerOnLoad;
                uiSleep 0.8;
                [format ["doc_%1_verso_%2", _type, _tag]] call AT_fnc_shot;
            };
            if (_tag isEqualTo "jour") then {
                ["zoom"] call comspec_sse_fnc_idViewerOnLoad;
                uiSleep 0.8;
                [format ["doc_%1_zoom", _type]] call AT_fnc_shot;
            };
            closeDialog 0;
            uiSleep 0.6;
        } forEach [[12, "jour"], [23, "nuit"]];
    } forEach _types;
    setDate _date0;
    [AT_NotifN isEqualTo 0, format ["%1 alerte(s) Arma (BIS_fnc_showNotification) pendant l'ouverture des documents", AT_NotifN]] call AT_fnc_check;
    [false] call AT_fnc_notifGarde;
    "photo d'identité : tête et épaules remplissant le cadre, fond clair uni, même rendu de jour et de nuit ; documents nets et crédibles" call AT_fnc_visuel;
    deleteVehicle _u;
    call AT_fnc_done;
}, 420] call AT_fnc_run;

["sse_relever", {
    if (["comspec_sse_fnc_idPhoto", "comspec_sse_fnc_idIssue", "comspec_sse_fnc_idHistory"] call AT_fnc_nil) exitWith {};
    if !("COMSPEC_ATAK_Phone" in (items player)) then { player addItem "COMSPEC_ATAK_Phone"; };
    AT_Releve = [];
    private _eh = ["comspec_sse_controlRecord", { AT_Releve = _this; }] call CBA_fnc_addEventHandler;
    private _u = call AT_fnc_ia;
    uiSleep 1;
    private _id = [_u, createHashMapFromArray [["type", "id_card"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    [true] call AT_fnc_notifGarde;
    if !([[_id, _u] call AT_fnc_ouvreDoc, "visionneuse non ouverte"] call AT_fnc_check) exitWith { [false] call AT_fnc_notifGarde; deleteVehicle _u; call AT_fnc_done; };
    private _d = uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull];
    private _b = _d displayCtrl 93714;
    [ctrlEnabled _b, format ["bouton Relever désactivé avant le relevé (%1)", ctrlText _b]] call AT_fnc_check;
    ["shoot"] call comspec_sse_fnc_idPhoto;
    uiSleep 1.5;
    private _rec = ["current"] call comspec_sse_fnc_idHistory;
    [(_rec getOrDefault ["photo", ""]) isEqualTo "releve", "relevé non noté sur le contrôle en cours"] call AT_fnc_check;
    [(count (_rec getOrDefault ["releve", []])) >= 2, "lignes du relevé absentes"] call AT_fnc_check;
    [AT_Releve isNotEqualTo [], "évènement comspec_sse_controlRecord non reçu"] call AT_fnc_check;
    private _pairs = AT_Releve param [0, []];
    [(_pairs findIf { (_x select 0) isEqualTo "number" && {(_x select 1) isNotEqualTo ""} }) >= 0, "paire « number » absente du relevé"] call AT_fnc_check;
    [ctrlShown (_d displayCtrl 93706), "ligne d'état de la visionneuse non affichée après le relevé"] call AT_fnc_check;
    [!(ctrlEnabled _b) && {((toUpper (ctrlText _b)) find "RELEV") >= 0}, format ["bouton Relever non mis à jour après le relevé (%1)", ctrlText _b]] call AT_fnc_check;
    (format ["bouton après relevé : « %1 »", ctrlText _b]) call AT_fnc_info;
    ["document_releve"] call AT_fnc_shot;
    [AT_NotifN isEqualTo 0, format ["%1 alerte(s) Arma pendant le relevé", AT_NotifN]] call AT_fnc_check;
    [false] call AT_fnc_notifGarde;
    closeDialog 0;
    uiSleep 0.5;
    [["IDCHECK", 2] call AT_fnc_page, "app Contrôle ID non ouverte"] call AT_fnc_check;
    ["controle_id_releve"] call AT_fnc_shot;
    ["comspec_sse_controlRecord", _eh] call CBA_fnc_removeEventHandler;
    deleteVehicle _u;
    call AT_fnc_done;
}, 90] call AT_fnc_run;

["sse_selecteur_document", {
    if (["comspec_sse_fnc_idDocPicker", "comspec_sse_fnc_idDocsOf", "comspec_sse_fnc_idDocTypes"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_ia;
    uiSleep 1;
    ["open", [_u, getPos _u]] call comspec_sse_fnc_idDocPicker;
    if !([[{ !isNull findDisplay 93800 }, 6] call AT_fnc_wait, "sélecteur de document non ouvert"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    uiSleep 1;
    private _d = findDisplay 93800;
    private _l = _d displayCtrl 93811;
    [(lbSize _l) >= 5, format ["liste des types trop courte : %1 lignes", lbSize _l]] call AT_fnc_check;
    private _i = -1;
    for "_k" from 0 to ((lbSize _l) - 1) do { if ((_l lbData _k) isEqualTo "passport") exitWith { _i = _k; }; };
    [_i >= 0, "passeport absent de la liste du sélecteur"] call AT_fnc_check;
    if (_i >= 0) then { _l lbSetCurSel _i; uiSleep 0.8; };
    [(ctrlText (_d displayCtrl 93820)) isNotEqualTo "", "aperçu vide"] call AT_fnc_check;
    ["selecteur_document"] call AT_fnc_shot;
    ["give"] call comspec_sse_fnc_idDocPicker;
    private _has = { (([_u, 0] call comspec_sse_fnc_idDocsOf) findIf { (["ofItem", _x select 0] call comspec_sse_fnc_idDocTypes) isEqualTo "passport" }) >= 0 };
    [[_has, 8] call AT_fnc_wait, "passeport non délivré à l'IA par le sélecteur"] call AT_fnc_check;
    closeDialog 0;
    uiSleep 0.5;
    // Poser au sol : porte-objets à l'endroit choisi.
    private _pos = player getPos [3, (getDir player) + 90];
    ["open", [objNull, _pos]] call comspec_sse_fnc_idDocPicker;
    if ([[{ !isNull findDisplay 93800 }, 6] call AT_fnc_wait, "sélecteur (au sol) non ouvert"] call AT_fnc_check) then {
        uiSleep 0.8;
        private _d2 = findDisplay 93800;
        (_d2 displayCtrl 93851) cbSetChecked true;
        ["give"] call comspec_sse_fnc_idDocPicker;
        private _sol = { ((nearestObjects [_pos, ["GroundWeaponHolder"], 3]) findIf { ((itemCargo _x) findIf { (["ofItem", _x] call comspec_sse_fnc_idDocTypes) isNotEqualTo "" }) >= 0 }) >= 0 };
        [[_sol, 8] call AT_fnc_wait, "document non posé au sol"] call AT_fnc_check;
        ["selecteur_au_sol"] call AT_fnc_shot;
        closeDialog 0;
    };
    { deleteVehicle _x; } forEach (nearestObjects [_pos, ["GroundWeaponHolder"], 3]);
    deleteVehicle _u;
    call AT_fnc_done;
}, 90] call AT_fnc_run;

["sse_extension_exemple", {
    if (["comspec_sse_fnc_idDocTypes", "comspec_sse_fnc_idIssue"] call AT_fnc_nil) exitWith {};
    if !(isClass (configFile >> "CfgPatches" >> "comspec_sse_ext_example_documents")) exitWith { "extension d'exemple absente (comspec_sse_ext_example_documents.pbo pas dans le pack SSE)" call AT_fnc_skip; };
    // Livrée dans le pack SSE, coupée par défaut (defaultOff = 1) : active pour l'étape, recoupée à la fin.
    [(["defaultOff"] call comspec_sse_fnc_idDocTypes) isEqualTo ["example_documents"], "extension d'exemple pas coupée par défaut (defaultOff)"] call AT_fnc_check;
    if (!isNil "comspec_sse_fnc_docExtServer") then { ["set", ["example_documents", true]] call comspec_sse_fnc_docExtServer; uiSleep 1; };
    private _exts = ["extensions"] call comspec_sse_fnc_idDocTypes;
    (format ["extensions détectées : %1", _exts apply { format ["%1 %2 (%3 modèles, %4)", _x select 1, _x select 3, count (_x select 5), ["coupée", "active"] select (_x select 7)] }]) call AT_fnc_info;
    [(_exts findIf { (_x select 0) isEqualTo "example_documents" }) >= 0, "extension example_documents non détectée"] call AT_fnc_check;
    ["press_card" in (["list", [""]] call comspec_sse_fnc_idDocTypes), "carte de presse absente des types servis"] call AT_fnc_check;
    private _u = call AT_fnc_ia;
    uiSleep 1;
    [true] call AT_fnc_notifGarde;
    private _id = [_u, createHashMapFromArray [["type", "press_card"], ["media", "Radio Kavala"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    if ([_id isNotEqualTo "", "carte de presse non délivrée"] call AT_fnc_check) then {
        private _doc = ["doc", [_id]] call comspec_sse_fnc_idRegistry;
        [(_doc getOrDefault ["media", ""]) isEqualTo "Radio Kavala", format ["champ propre « media » non imprimé : %1", _doc getOrDefault ["media", ""]]] call AT_fnc_check;
        if ([[_id, _u] call AT_fnc_ouvreDoc, "visionneuse (carte de presse) non ouverte"] call AT_fnc_check) then {
            ["extension_carte_presse"] call AT_fnc_shot;
            closeDialog 0;
        };
    };
    [false] call AT_fnc_notifGarde;
    // Désactivation (serveur) puis réactivation : la liste servie suit.
    if (!isNil "comspec_sse_fnc_docExtServer") then {
        ["set", ["example_documents", false]] call comspec_sse_fnc_docExtServer;
        uiSleep 1;
        [!("press_card" in (["list", [""]] call comspec_sse_fnc_idDocTypes)), "extension coupée : la carte de presse est encore servie"] call AT_fnc_check;
        ["set", ["example_documents", true]] call comspec_sse_fnc_docExtServer;
        uiSleep 1;
        ["press_card" in (["list", [""]] call comspec_sse_fnc_idDocTypes), "extension rallumée : la carte de presse n'est pas servie"] call AT_fnc_check;
        ["set", ["example_documents", false]] call comspec_sse_fnc_docExtServer;
    } else { "fn_docExtServer absente de ce build" call AT_fnc_info; };
    if (!isNil "comspec_sse_fnc_docExtOpen") then {
        [] call comspec_sse_fnc_docExtOpen;
        if ([[{ dialog }, 6] call AT_fnc_wait, "fenêtre Extensions non ouverte"] call AT_fnc_check) then { uiSleep 1; ["extensions_fenetre"] call AT_fnc_shot; closeDialog 0; };
    } else { "fn_docExtOpen absente de ce build" call AT_fnc_info; };
    deleteVehicle _u;
    call AT_fnc_done;
}, 120] call AT_fnc_run;

// Fenêtre « SSE ROLEPLAY · Extensions » : aucun texte coupé, aucun contrôle sur un autre, dans les trois vues (liste,
// aucune extension, tuto et ses 10 étapes) ; « Copier l'exemple » remplit le presse-papiers (solo) et le dit dans la fenêtre.
AT_fnc_extVerif = {
    params ["_etat"];
    private _d = findDisplay 93900;
    if (isNull _d) exitWith { [false, format ["%1 : fenêtre fermée", _etat]] call AT_fnc_check };
    private _fond = ["Dim", "Back", "Head", "Line", "RightPanel", "ReadBack", "Probe"];
    private _vis = (allControls _d) select {
        private _g = ctrlParentControlsGroup _x;
        ctrlShown _x && {!((ctrlClassName _x) in _fond)} && {isNull _g || {ctrlShown _g}} && {((ctrlPosition _x) select 2) > 0}
    };
    // Texte d'un groupe défilant : sa hauteur suit le texte, seul le groupe compte.
    { if ((ctrlType _x) in [0, 1, 13, 16] && {isNull (ctrlParentControlsGroup _x)}) then { [_x, format ["%1 : %2", _etat, ctrlClassName _x]] call AT_fnc_tient; }; } forEach _vis;
    private _top = _vis select { isNull (ctrlParentControlsGroup _x) && {(ctrlClassName _x) isNotEqualTo "ctrlButton"} };
    private _n = count _top;
    for "_i" from 0 to (_n - 2) do {
        (ctrlPosition (_top select _i)) params ["_ax", "_ay", "_aw", "_ah"];
        for "_j" from (_i + 1) to (_n - 1) do {
            (ctrlPosition (_top select _j)) params ["_bx", "_by", "_bw", "_bh"];
            private _ix = ((_ax + _aw) min (_bx + _bw)) - (_ax max _bx);
            private _iy = ((_ay + _ah) min (_by + _bh)) - (_ay max _by);
            [_ix < 0.002 || {_iy < 0.002}, format ["%1 : %2 sur %3", _etat, ctrlClassName (_top select _i), ctrlClassName (_top select _j)]] call AT_fnc_check;
        };
    };
};
["sse_extensions_fenetre", {
    if (["comspec_sse_fnc_docExtWindow", "comspec_sse_fnc_docExtOpen"] call AT_fnc_nil) exitWith {};
    (format ["résolution %1", getResolution]) call AT_fnc_info;
    uiNamespace setVariable ["comspec_sse_docExtTestEmpty", false];
    [] call comspec_sse_fnc_docExtOpen;
    if !([[{ !isNull findDisplay 93900 }, 6] call AT_fnc_wait, "fenêtre Extensions non ouverte"] call AT_fnc_check) exitWith { call AT_fnc_done; };
    uiSleep 0.5;
    private _d = findDisplay 93900;
    [(["view"] call comspec_sse_fnc_docExtWindow) isEqualTo "list", "vue liste attendue (extension d'exemple livrée)"] call AT_fnc_check;
    ["liste"] call AT_fnc_extVerif;
    ["extensions_liste"] call AT_fnc_shot;
    // Aucune extension : état vide à la place des volets, bouton « Ouvrir le tuto ».
    uiNamespace setVariable ["comspec_sse_docExtTestEmpty", true];
    ["refresh"] call comspec_sse_fnc_docExtWindow;
    uiSleep 0.3;
    [(["view"] call comspec_sse_fnc_docExtWindow) isEqualTo "empty", "vue vide attendue"] call AT_fnc_check;
    [ctrlShown (_d displayCtrl 93952) && {!ctrlShown (_d displayCtrl 93910)}, "état vide : bouton Ouvrir le tuto absent ou liste encore affichée"] call AT_fnc_check;
    ["vide"] call AT_fnc_extVerif;
    ["extensions_vide"] call AT_fnc_shot;
    ["help", "tuto"] call comspec_sse_fnc_docExtWindow;
    uiNamespace setVariable ["comspec_sse_docExtTestEmpty", false];
    uiSleep 0.3;
    [(["view"] call comspec_sse_fnc_docExtWindow) isEqualTo "tuto", "Ouvrir le tuto : vue tuto attendue"] call AT_fnc_check;
    private _steps = _d displayCtrl 93960;
    [(lbSize _steps) isEqualTo 10, format ["tuto : %1 étapes au lieu de 10", lbSize _steps]] call AT_fnc_check;
    for "_i" from 0 to ((lbSize _steps) - 1) do {
        _steps lbSetCurSel _i;
        uiSleep 0.2;
        [format ["tuto %1", _i + 1]] call AT_fnc_extVerif;
        private _txt = (_d displayCtrl 93961) controlsGroupCtrl 93962;
        [(ctrlTextHeight _txt) <= ((ctrlPosition _txt) select 3) * 1.01, format ["tuto %1 : texte plus haut que sa zone défilante", _i + 1]] call AT_fnc_check;
        [(ctrlShown (_d displayCtrl 93963)) isEqualTo (_i isEqualTo 4), format ["tuto %1 : bouton Copier l'exemple mal affiché", _i + 1]] call AT_fnc_check;
        if (_i isEqualTo 4) then { ["extensions_tuto_config"] call AT_fnc_shot; };
    };
    _steps lbSetCurSel 4;
    uiSleep 0.2;
    ["copy"] call comspec_sse_fnc_docExtWindow;
    if (!isMultiplayer) then {
        [copyFromClipboard isEqualTo (["skeleton"] call comspec_sse_fnc_docExtWindow), "Copier l'exemple : presse-papiers différent du config.cpp affiché"] call AT_fnc_check;
    };
    [(ctrlText (_d displayCtrl 93924)) isNotEqualTo "", "Copier l'exemple : rien sur la ligne d'état"] call AT_fnc_check;
    ["copie"] call AT_fnc_extVerif;
    ["help"] call comspec_sse_fnc_docExtWindow;
    uiSleep 0.3;
    [(["view"] call comspec_sse_fnc_docExtWindow) isEqualTo "list", "bouton Extensions : retour à la liste attendu"] call AT_fnc_check;
    closeDialog 0;
    call AT_fnc_done;
}, 120] call AT_fnc_run;

// Pays réels des papiers (registre CfgComspecCountries) : registre, clés des numéros, MRZ OACI (fn_idCountrySelfTest).
["sse_pays_reels", {
    if (["comspec_sse_fnc_idCountrySelfTest"] call AT_fnc_nil) exitWith {};
    ([] call comspec_sse_fnc_idCountrySelfTest) params ["_ko", "_info"];
    { _x call AT_fnc_info; } forEach _info;
    { [false, _x] call AT_fnc_check; } forEach _ko;
    call AT_fnc_done;
}, 30] call AT_fnc_run;
