/*
    Registre des panneaux du TOC (4.10.0, préalable posé par la feuille de route). Avant, chaque tableau lisait lui-même
    l'état du téléphone ou les variables du serveur, dans fn_tocLaptop, fn_tocBoard et fn_tocScreen à la fois. Ici une
    seule entrée : le PC du poste et les écrans demandent un panneau par son nom et reçoivent ses lignes, le texte à
    montrer quand il est vide et la note de plafond. Aucun panneau ne lit le téléphone. Un panneau d'un lot suivant
    (ou d'une mission) s'ajoute par « register », sans toucher aux gros fichiers. Mode d'emploi : docs/TOC-DATA-API.md.

    Modes (client) :
      ["register", [id, titre, fournisseur { params ["_side", "_args"]; [lignes, note] }, texte si vide]]   -> true
      ["list"]                         -> [[id, titre]...]
      ["has", id]                      -> vrai si le panneau existe
      ["data", [id, camp, args]]       -> [titre, lignes, état "OK" | "EMPTY" | "WAIT" | "LATE", texte d'état, note]
                                          WAIT : le serveur n'a encore rien publié ; LATE : dernier envoi de plus de 15 s
      ["feed", camp]                   -> flux du camp, nœuds complétés aux 16 champs (serveur plus ancien compris)
      ["orbat", camp]                  -> [groupes, ordres] publiés par le serveur, sinon lus dans le jeu sur ce poste
    Panneaux fournis :
      TRACKS    nœuds (fn_tocTracks)                          STRENGTH  [[libellé, valeur]...] effectifs et réseau
      ORBAT     groupes du jeu + [état du meilleur nœud, [x, y] ou []]    FEED      flux, le plus récent d'abord
      ORDERS    ordres du camp et accusés (fn_tocInbox)       INBOX     ma boîte (messages, réponses, retours)
      MYORDERS  mes ordres et mon statut                      MYREQ     mes demandes au TOC et leur réponse
      LOG       main courante, la plus récente d'abord        REQ       demandes des éléments
      KILLS     pertes et résultats                           EXCHECK   liste d'exécution
      TIMERS    chronos                                       POSTS     postes tenus
      CAMS      caméras du camp (fn_tocCam « sources »)       FEEDBACK  dernier retour d'action [[texte, type, âge]]
    Exemple : (["data", ["ORBAT", "WEST"]] call comspec_atak_native_fnc_tocPanels) params ["_titre", "_lignes", "_etat", "_texte", "_note"];
*/
params [["_mode", "list"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocPanels;
private _reg = missionNamespace getVariable ["COMSPEC_ATAK_TocPanelReg", createHashMap];
if ((count _reg) isEqualTo 0 && {_mode isNotEqualTo "init"}) then {
    ["init"] call _fn;
    _reg = missionNamespace getVariable ["COMSPEC_ATAK_TocPanelReg", createHashMap];
};
switch (_mode) do {
    case "init": {
        _reg = createHashMap;
        missionNamespace setVariable ["COMSPEC_ATAK_TocPanelReg", _reg];
        private _room = {
            // Tranche de l'état de la salle (fn_tocRoom), la plus récente d'abord si demandé.
            params ["_side", "_i", ["_rev", false]];
            private _l = +((["state", _side] call comspec_atak_native_fnc_tocRoom) select _i);
            if (_rev) then { reverse _l; };
            _l
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocPanelRoom", _room];
        {
            _x params ["_id", "_title", "_code", "_empty"];
            _reg set [_id, [_title, _code, _empty]];
        } forEach [
            ["TRACKS", "PISTES", {
                params ["_side"];
                private _feed = ["feed", _side] call comspec_atak_native_fnc_tocPanels;
                private _ext = ((_feed param [3, []]) param [4, []]);
                private _shown = _ext param [2, 0];
                private _total = _ext param [3, 0];
                [_feed param [1, []], ["", format ["%1 pistes affichées sur %2", _shown, _total]] select (_total > _shown)]
            }, "Aucune unité du camp à montrer"],
            ["STRENGTH", "EFFECTIFS", {
                params ["_side"];
                private _st = (["feed", _side] call comspec_atak_native_fnc_tocPanels) param [3, []];
                if ((count _st) < 4) exitWith { [[], ""] };
                private _ext = _st param [4, []];
                private _src = (createHashMapFromArray [["MIX", "jeu et réseau ATAK"], ["GAME", "jeu"], ["ATAK", "réseau ATAK"]]) getOrDefault [_ext param [4, "ATAK"], "réseau ATAK"];
                [[["Joueurs", _st select 2], ["IA", _st select 3], ["Joueurs sur le réseau ATAK", _ext param [0, _st select 2]], ["IA sur le réseau ATAK", _ext param [1, _st select 3]],
                    ["Pistes affichées", _ext param [2, 0]], ["Pistes en tout", _ext param [3, 0]], ["Source des positions", _src], ["Brouilleurs", _st select 0], ["Relais", _st select 1]], ""]
            }, "En attente des effectifs du camp"],
            ["ORBAT", "ORBAT", {
                params ["_side"];
                private _nodes = (["feed", _side] call comspec_atak_native_fnc_tocPanels) param [1, []];
                private _rows = ((["orbat", _side] call comspec_atak_native_fnc_tocPanels) select 0) apply {
                    private _r = +_x;
                    private _gn = _r select 0;
                    private _mine = _nodes select { (_x param [7, ""]) isEqualTo _gn };
                    _r resize 13;
                    _r pushBack (["best", _mine apply { _x param [4, 4] }] call comspec_atak_native_fnc_tocNodeStatus);
                    _r pushBack ((_mine param [0, []]) param [2, []]);
                    _r
                };
                [_rows, ""]
            }, "Aucun groupe du camp en jeu"],
            ["FEED", "FLUX", {
                params ["_side"];
                private _l = +((["feed", _side] call comspec_atak_native_fnc_tocPanels) param [2, []]);
                reverse _l;
                [_l, ""]
            }, "Aucun compte rendu ni message pour l'instant : ils s'inscrivent ici dès qu'un élément en envoie ou que le poste en saisit un"],
            ["ORDERS", "ORDRES", {
                params ["_side"];
                private _l = +((["orbat", _side] call comspec_atak_native_fnc_tocPanels) select 1);
                reverse _l;
                [_l, ""]
            }, "Aucun ordre émis"],
            ["INBOX", "MESSAGES DU TOC", { [["list", []] call comspec_atak_native_fnc_tocInbox, ""] }, "Aucun message du poste"],
            ["MYORDERS", "MES ORDRES", { [["orders"] call comspec_atak_native_fnc_tocInbox, ""] }, "Aucun ordre reçu"],
            ["MYREQ", "MES DEMANDES AU TOC", { [["requests"] call comspec_atak_native_fnc_tocInbox, ""] }, "Aucune demande envoyée au poste"],
            ["LOG", "MAIN COURANTE", { params ["_side"]; [[_side, 3, true] call COMSPEC_ATAK_TocPanelRoom, ""] }, "Main courante vide"],
            ["REQ", "DEMANDES DES ÉLÉMENTS", { params ["_side"]; [[_side, 5, true] call COMSPEC_ATAK_TocPanelRoom, ""] }, "Aucune demande en cours"],
            ["KILLS", "PERTES ET RÉSULTATS", { params ["_side"]; [[_side, 8] call COMSPEC_ATAK_TocPanelRoom, ""] }, "Aucune perte déclarée"],
            ["EXCHECK", "LISTE D'EXÉCUTION", { params ["_side"]; [[_side, 4] call COMSPEC_ATAK_TocPanelRoom, ""] }, "Aucune ligne d'exécution"],
            ["TIMERS", "CHRONOS", { params ["_side"]; [[_side, 1] call COMSPEC_ATAK_TocPanelRoom, ""] }, "AUCUN CHRONO"],
            ["POSTS", "POSTES", { params ["_side"]; [[_side, 7] call COMSPEC_ATAK_TocPanelRoom, ""] }, "Aucun poste tenu"],
            ["CAMS", "CAMÉRAS", { [["sources"] call comspec_atak_native_fnc_tocCam, ""] }, "Aucune caméra du camp à afficher (drone, caméra fixe, caméra de casque ou caméra partagée)"],
            ["FEEDBACK", "RETOUR", {
                private _l = ["lastFeedback"] call comspec_atak_native_fnc_tocInbox;
                [[[], [_l]] select (_l isNotEqualTo []), ""]
            }, ""]
        ];
        true
    };
    case "register": {
        _a params [["_id", ""], ["_title", ""], ["_code", {}], ["_empty", ""]];
        if (!(_id isEqualType "") || {_id isEqualTo ""} || {!(_code isEqualType {})} || {!(_title isEqualType "")} || {!(_empty isEqualType "")}) exitWith { false };
        _reg set [toUpper _id, [_title, _code, _empty]];
        true
    };
    case "list": {
        private _l = (keys _reg) apply { [_x, (_reg get _x) select 0] };
        _l sort true;
        _l
    };
    case "has": { _a isEqualType "" && {(toUpper _a) in _reg} };
    case "feed": {
        private _feed = missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _a], []];
        if (!(_feed isEqualType []) || {(count _feed) < 4}) exitWith { [] };
        private _nodes = _feed select 1;
        // Serveur plus ancien (nœuds à 9 champs) : complétés ici, source « réseau ATAK ».
        if (_nodes isNotEqualTo [] && {(count (_nodes select 0)) < 16}) then {
            _feed = +_feed;
            _feed set [1, (["merge", [_nodes, [], "ATAK", 400]] call comspec_atak_native_fnc_tocTracks) select 0];
        };
        _feed
    };
    case "orbat": {
        private _pub = missionNamespace getVariable [format ["COMSPEC_ATAK_TocOrbat_%1", _a], []];
        if (_pub isEqualType [] && {(count _pub) >= 3}) exitWith { [_pub select 1, _pub select 2] };
        // Rien de publié (serveur plus ancien, ou pas encore lecteur) : groupes lus dans le jeu sur ce poste.
        [["orbat", _a] call comspec_atak_native_fnc_tocTracks, []]
    };
    case "data": {
        _a params [["_id", ""], ["_side", ""], ["_args", []]];
        if (!(_id isEqualType "") || {!(_side isEqualType "")}) exitWith { ["", [], "EMPTY", "Panneau inconnu", ""] };
        private _p = _reg getOrDefault [toUpper _id, []];
        if (_p isEqualTo []) exitWith { ["", [], "EMPTY", "Panneau inconnu", ""] };
        _p params ["_title", "_code", "_empty"];
        private _res = [_side, _args] call _code;
        if (isNil "_res" || {!(_res isEqualType [])}) then { _res = [[], ""]; };
        _res params [["_rows", []], ["_note", ""]];
        private _state = "OK";
        private _txt = "";
        if (_rows isEqualTo []) then { _state = "EMPTY"; _txt = _empty; };
        // Panneaux tirés du flux du serveur : dit si rien n'est encore arrivé ou si le dernier envoi date.
        if ((toUpper _id) in ["TRACKS", "STRENGTH", "FEED"]) then {
            private _feed = missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _side], []];
            if (!(_feed isEqualType []) || {(count _feed) < 4}) then {
                _state = "WAIT"; _txt = "En attente des données du serveur";
            } else {
                if ((serverTime - (_feed select 0)) > 15) then { _state = "LATE"; _txt = "Données du serveur en retard"; };
            };
        };
        [_title, _rows, _state, _txt, _note]
    };
    default { false };
};
