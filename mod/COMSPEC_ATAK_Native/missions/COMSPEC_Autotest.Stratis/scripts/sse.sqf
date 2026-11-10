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
    if !(isClass (configFile >> "CfgPatches" >> "comspec_sse_ext_example_documents")) exitWith { "extension d'exemple non chargée (mod @COMSPEC_SSE_ExampleDocuments absent)" call AT_fnc_skip; };
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
    } else { "fn_docExtServer absente de ce build" call AT_fnc_info; };
    if (!isNil "comspec_sse_fnc_docExtOpen") then {
        [] call comspec_sse_fnc_docExtOpen;
        if ([[{ dialog }, 6] call AT_fnc_wait, "fenêtre Extensions non ouverte"] call AT_fnc_check) then { uiSleep 1; ["extensions_fenetre"] call AT_fnc_shot; closeDialog 0; };
    } else { "fn_docExtOpen absente de ce build" call AT_fnc_info; };
    deleteVehicle _u;
    call AT_fnc_done;
}, 120] call AT_fnc_run;
