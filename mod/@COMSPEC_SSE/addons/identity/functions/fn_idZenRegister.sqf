/*
    Modules Zeus (ZEN) « COMSPEC · Identité » : Donner des papiers, Signaler volé / révoquer, Voir les vrais papiers.
    Tout s'exécute sur le serveur (fn_idServer give / report / truth), qui vérifie que le demandeur est bien Zeus.
    « Voir les vrais papiers » : réservé au Zeus (vraie identité, nature, qualité, défauts), jamais aux joueurs.
    IA réalistes (Lot 3) : Checkpoint IA (zone, camp, équipement, sévérité), Retirer un checkpoint IA, Déclencher un
    contrôle (checkpoint qui couvre la personne, sinon l'IA la plus proche), Forcer un état IA, Couvre-feu
    (fn_idServer cpCreate / cpRemove / cpTrigger / fsmForce / curfew).
    Couvertures et HVT (Lots 4 et 5) : Couverture (infiltrer, brûler, lever), Zone interdite (couvertures), Désigner HVT,
    BOLO (liste à surveiller, visage joint si posé sur une personne), Lever un BOLO (fn_idServer cover / zone / hvt /
    bolo / boloRemove).
*/
if (!hasInterface) exitWith {};
[{ !isNil "zen_custom_modules_fnc_register" }, {
    private _cat = "COMSPEC_Cat_Identite";
    private _unitOf = {
        params ["_obj"];
        if (isNull _obj || {!(_obj isKindOf "CAManBase")}) exitWith { ["Placez le module sur une personne.", "warn"] call comspec_sse_fnc_zeusNotify; objNull };
        _obj
    };
    [_cat, "Donner des papiers", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        [
            format ["Papiers — %1", name _u],
            [
                ["LIST", ["Document", "Pièce délivrée à la personne (objet dans son inventaire)."], [["id_card", "passport", "driving_license", "military_id"], ["Carte d'identité", "Passeport", "Permis de conduire", "Carte militaire"], 0]],
                ["LIST", ["Pays", "Pays fictif (SIMULATION)."], [["auto", "ALT", "TNA", "LVN", "MLD"], ["Pays de la mission", "République d'Altis", "Fédération de Tanoa", "République de Livonie", "République de Malden"], 0]],
                ["LIST", ["Nature", "Civil : vrais papiers. Couverture : identité préparée qui passe tous les contrôles sauf la biométrie. Faux : défauts selon la qualité."], [["civil", "cover", "forged"], ["Vrais papiers", "Couverture préparée", "Faux papiers"], 0]],
                ["LIST", ["Procédé du faux", "Seulement pour de faux papiers."], [["", "counterfeit", "altered", "photo_swap", "stolen_blank"], ["Au hasard (selon la qualité)", "Contrefaçon complète", "Vrai document modifié", "Photo substituée", "Vierge volé"], 0]],
                ["SLIDER", ["Qualité du faux", "0 : grossier, vu au premier coup d'œil. 100 : excellent, seule la base ou la biométrie le trahit."], [0, 100, 50, 0]],
                ["EDIT", ["Nom imprimé", "Vide : nom de la personne, ou généré."], ""],
                ["EDIT", ["Prénom imprimé", "Vide : prénom de la personne, ou généré."], ""],
                ["EDIT", ["Numéro Athena", "Facultatif : numéro d'un document délivré sur Athena (paquet chargé) ; ses données remplacent les choix ci-dessus."], ""]
            ],
            {
                params ["_v", "_args"];
                _v params ["_type", "_country", "_kind", "_fk", "_q", "_last", "_first", "_ath"];
                _args params ["_u"];
                private _spec = createHashMapFromArray [["type", _type], ["country", _country], ["kind", _kind], ["forgeryKind", _fk], ["quality", round _q],
                    ["last", trim _last], ["first", trim _first], ["athena", trim _ath]];
                ["send", ["give", [player, _u, _spec]]] call comspec_sse_fnc_idClient;
            }, {}, [_u]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\identity\data\icon_card_ca.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Révoquer des papiers", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        [
            format ["Signalement — %1", name _u],
            [["LIST", ["Signalement", "S'applique à tous les documents de la personne : visible au contrôle N3 (base)."], [["vole", "perdu", "revoque", ""], ["Volé", "Perdu", "Révoqué", "Lever le signalement"], 0]]],
            { params ["_v", "_args"]; ["send", ["report", [player, _args select 0, _v select 0]]] call comspec_sse_fnc_idClient; }, {}, [_u]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_trash.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Voir les vrais papiers", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        ["send", ["truth", [player, _u]]] call comspec_sse_fnc_idClient;
    }, "\z\comspec_sse\addons\zeus\data\mod_evidence.paa"] call comspec_sse_fnc_zenModule;
    // IA réalistes et checkpoints IA (Lot 3) : tout s'exécute sur le serveur, qui vérifie que le demandeur est Zeus.
    private _cpRows = [
        ["SLIDER", ["Rayon (m)", "Zone contrôlée autour du module. 0 : réglage « Distance des checkpoints »."], [0, 100, 0, 0]],
        ["LIST", ["Camp du poste", "Camp des gardes. Automatique : celui des IA les plus proches du module."], [["auto", "west", "east", "guer", "civ"], ["Automatique", "BLUFOR", "OPFOR", "Indépendant", "Civil (police)"], 0]],
        ["LIST", ["Équipement du poste", "Visuel seul : examen à l'œil (N0). Scanner : bande MRZ (N1). Lecteur NFC : puce (N2). Liaison base : fichiers (N3)."], [[0, 1, 2, 3], ["Visuel seul", "Scanner MRZ", "Lecteur NFC", "Liaison base"], 1]],
        ["LIST", ["Sévérité", "Laxiste : laisse passer presque tout. Stricte : fouille et retient au moindre doute, ouvre le feu sur une menace armée."], [[0, 1, 2], ["Laxiste", "Normale", "Stricte"], 1]]
    ];
    [_cat, "Poser un checkpoint IA", {
        params ["_pos", "_obj"];
        [
            "Checkpoint IA",
            (missionNamespace getVariable ["comspec_sse_idZenCpRows", []]) + [["EDIT", ["Nom du poste", "Facultatif, affiché aux joueurs contrôlés."], ""]],
            {
                params ["_v", "_args"];
                _v params ["_r", "_side", "_eq", "_sev", "_name"];
                _args params ["_pos"];
                private _spec = createHashMapFromArray [["radius", [-1, _r] select (_r > 0)], ["side", _side], ["equip", _eq], ["severity", _sev], ["name", trim _name]];
                ["send", ["cpCreate", [player, ASLToATL _pos, _spec]]] call comspec_sse_fnc_idClient;
            }, {}, [_pos]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_check.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Retirer un checkpoint IA", {
        params ["_pos", "_obj"];
        ["send", ["cpRemove", [player, ASLToATL _pos]]] call comspec_sse_fnc_idClient;
    }, "\z\comspec_sse\addons\zeus\data\mod_trash.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Déclencher un contrôle", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        [
            format ["Contrôle — %1", name _u],
            [
                ["LIST", ["Équipement", "Sans checkpoint autour : celui de l'IA la plus proche qui fait le contrôle."], [[0, 1, 2, 3], ["Visuel seul", "Scanner MRZ", "Lecteur NFC", "Liaison base"], 1]],
                ["LIST", ["Sévérité", "Sans checkpoint autour : sévérité de ce contrôle."], [[0, 1, 2], ["Laxiste", "Normale", "Stricte"], 1]]
            ],
            {
                params ["_v", "_args"];
                ["send", ["cpTrigger", [player, _args select 0, createHashMapFromArray [["equip", _v select 0], ["severity", _v select 1]]]]] call comspec_sse_fnc_idClient;
            }, {}, [_u]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_evidence.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Forcer un état IA", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        [
            format ["État — %1", name _u],
            [["LIST", ["État", "Ordres réels donnés à l'IA (arrêt, fuite, reddition ACE, hostilité, renforts…). Elle réagit de nouveau d'elle-même 30 s plus tard."],
                [["interpelle", "coopere", "ment", "nerveux", "refuse", "fuit", "agressif", "rendu", "renforts", "calme"],
                 ["Interpellé", "Coopère", "Ment (présente sa couverture)", "Nerveux", "Refuse", "Fuit", "Agressif", "Se rend", "Appelle des renforts", "Calme (laisser partir)"], 0]]],
            { params ["_v", "_args"]; ["send", ["fsmForce", [player, _args select 0, _v select 0]]] call comspec_sse_fnc_idClient; }, {}, [_u]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_person.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Imposer un couvre-feu", {
        params ["_pos", "_obj"];
        [
            "Couvre-feu",
            [["LIST", ["Couvre-feu", "En vigueur : les civils contrôlés dehors sont plus nerveux."], [[true, false], ["En vigueur", "Levé"], [1, 0] select (missionNamespace getVariable ["comspec_sse_idCurfewZeus", missionNamespace getVariable ["comspec_sse_id_curfew", false]])]]],
            { params ["_v"]; ["send", ["curfew", [player, _v select 0]]] call comspec_sse_fnc_idClient; }, {}, []
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_flag.paa"] call comspec_sse_fnc_zenModule;
    // --- Couvertures et infiltrés (Lot 4) --------------------------------------------------------------------------
    [_cat, "Donner une couverture", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        [
            format ["Couverture — %1", name _u],
            [
                ["LIST", ["Action", "Infiltrer : la personne vit sous sa couverture (papiers « couverture » et légende). Brûler : la couverture saute, les ennemis proches sont alertés."], [["start", "burn", "stop"], ["Infiltrer", "Brûler la couverture", "Lever l'infiltration"], 0]],
                ["LIST", ["Captif tant que la couverture tient", "Les ennemis ne la prennent pas pour cible (setCaptive) tant qu'elle n'est pas démasquée."], [[-1, 1, 0], ["Selon le réglage de mission", "Oui", "Non"], 0]],
                ["CHECKBOX", ["Lui donner une carte de couverture", "Carte d'identité « couverture » (passe tous les contrôles sauf la biométrie), au nom ci-dessous ou généré."], false],
                ["EDIT", ["Nom de la couverture", "Facultatif."], ""],
                ["EDIT", ["Prénom de la couverture", "Facultatif."], ""]
            ],
            {
                params ["_v", "_args"];
                _v params ["_act", "_cap", "_card", "_l", "_f"];
                _args params ["_u"];
                if (_card) then {
                    ["send", ["give", [player, _u, createHashMapFromArray [["type", "id_card"], ["kind", "cover"], ["quality", 90], ["last", trim _l], ["first", trim _f]]]]] call comspec_sse_fnc_idClient;
                };
                private _spec = createHashMap;
                if (_cap >= 0) then { _spec set ["captive", _cap isEqualTo 1]; };
                // Carte remise d'abord : l'infiltration part un instant plus tard (légende tirée de la nouvelle carte).
                [{ ["send", ["cover", [player, _this select 0, _this select 1, _this select 2]]] call comspec_sse_fnc_idClient; }, [_u, _act, _spec], [0, 1.5] select _card] call CBA_fnc_waitAndExecute;
            }, {}, [_u]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\identity\data\icon_card_ca.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Poser une zone interdite", {
        params ["_pos", "_obj"];
        [
            "Zone interdite",
            [["SLIDER", ["Rayon (m)", "Un infiltré vu dans la zone par un ennemi éveille les soupçons. 0 : retire la zone la plus proche."], [0, 500, 100, 0]],
             ["EDIT", ["Nom", "Facultatif."], ""]],
            { params ["_v", "_args"]; ["send", ["zone", [player, _args select 0, round (_v select 0), trim (_v select 1)]]] call comspec_sse_fnc_idClient; }, {}, [ASLToATL _pos]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_trash.paa"] call comspec_sse_fnc_zenModule;

    // --- HVT et BOLO (Lot 5) ----------------------------------------------------------------------------------------
    [_cat, "Désigner une HVT", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        [
            format ["Désigner HVT — %1", name _u],
            [
                ["LIST", ["Niveau", "HVT : cible prioritaire (watchlist 1). Recherché : watchlist 2."], [["hvt", "recherche"], ["HVT (cible prioritaire)", "Recherché"], 0]],
                ["EDIT", ["Alias", "Surnom connu (« Le Renard »)."], ""],
                ["EDIT", ["Vrai nom", "Vide : celui de ses papiers."], ""],
                ["EDIT", ["Vrai prénom", "Vide : celui de ses papiers."], ""],
                ["EDIT", ["Papiers connus", "Numéros de documents connus, séparés par des virgules."], ""],
                ["EDIT", ["Notes", "Signalement, éléments connus (diffusé avec l'avis)."], ""],
                ["CHECKBOX", ["Lui donner une couverture", "Carte « couverture » à un faux nom : seule la biométrie (N4, SEEK) le trahit."], false],
                ["EDIT", ["Nom de la couverture", "Facultatif."], ""],
                ["EDIT", ["Prénom de la couverture", "Facultatif."], ""]
            ],
            {
                params ["_v", "_args"];
                _v params ["_lvl", "_al", "_l", "_f", "_docs", "_notes", "_cover", "_cl", "_cf"];
                private _spec = createHashMapFromArray [["level", _lvl], ["alias", trim _al], ["last", trim _l], ["first", trim _f], ["docs", trim _docs],
                    ["notes", trim _notes], ["cover", _cover], ["coverLast", trim _cl], ["coverFirst", trim _cf]];
                ["send", ["hvt", [player, _args select 0, _spec]]] call comspec_sse_fnc_idClient;
            }, {}, [_u]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_person.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Lancer un BOLO", {
        params ["_pos", "_obj"];
        private _u = [objNull, _obj] select (!isNull _obj && {_obj isKindOf "CAManBase"});
        [
            ["BOLO", format ["BOLO — %1 (visage joint)", name _u]] select (!isNull _u),
            [
                ["EDIT", ["Nom", "Nom à surveiller."], ""],
                ["EDIT", ["Prénom", ""], ""],
                ["EDIT", ["Alias", ""], ""],
                ["LIST", ["Niveau", "Surveillance : signalé. Recherché : à interpeller. HVT : cible prioritaire."], [["surveillance", "recherche", "hvt"], ["Sous surveillance", "Recherché", "HVT"], 1]],
                ["EDIT", ["Papiers connus", "Numéros de documents, séparés par des virgules."], ""],
                ["EDIT", ["Signalement", "Description, véhicule, consignes (diffusé avec l'avis)."], ""]
            ],
            {
                params ["_v", "_args"];
                _v params ["_l", "_f", "_al", "_lvl", "_docs", "_notes"];
                private _spec = createHashMapFromArray [["last", trim _l], ["first", trim _f], ["alias", trim _al], ["level", _lvl], ["docs", trim _docs], ["notes", trim _notes]];
                ["send", ["bolo", [player, _args select 0, _spec]]] call comspec_sse_fnc_idClient;
            }, {}, [_u]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_evidence.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Lever un BOLO", {
        params ["_pos", "_obj"];
        private _b = missionNamespace getVariable ["comspec_sse_idBolos", []];
        if (_b isEqualTo []) exitWith { ["Aucun BOLO en cours.", "warn"] call comspec_sse_fnc_zeusNotify; };
        [
            "Lever un BOLO",
            [["LIST", ["BOLO", "Retiré de la liste diffusée (et levé sur Athena)."], [_b apply { _x select 0 }, _b apply { format ["%1 · %2 %3", _x select 0, toUpper (_x select 1), _x select 2] }, 0]]],
            { params ["_v"]; ["send", ["boloRemove", [player, objNull, _v select 0]]] call comspec_sse_fnc_idClient; }, {}, []
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_trash.paa"] call comspec_sse_fnc_zenModule;

    missionNamespace setVariable ["comspec_sse_idZenCpRows", _cpRows];
    missionNamespace setVariable ["comspec_sse_idZenUnitOf", _unitOf];
}, [], 45] call CBA_fnc_waitUntilAndExecute;
