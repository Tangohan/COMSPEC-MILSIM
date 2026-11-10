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
