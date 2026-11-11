/*
    PC portable de TOC : poste de commandement local, sans Athena ni site web.
    L'action « PC du poste TOC » ouvre l'écran du PC lui-même (fn_tocLaptop), sans téléphone : carte en grand, nœuds
    ATAK, groupes, flux, ordres et messages au camp. Un joueur à pied à moins de 2,5 m d'un PC de TOC garde aussi, s'il
    a un ATAK, l'app Poste TOC dans son téléphone (fn_vehicleTerminal "toc", fn_pageToc).
    PC de TOC : objet COMSPEC_ATAK_TocLaptop (Eden, Zeus), tout objet avec this setVariable ["COMSPEC_ATAK_Toc", true, true],
    ou n'importe quel ordinateur portable posé (réglage « Tous les ordinateurs portables servent de PC de TOC »).
    Camp réservé (attribut Eden ou variable COMSPEC_ATAK_TocSide "WEST" | "EAST" | "GUER" | "CIV") : vide = tous les camps.
    Params : [action, argument]
      "tick"          (1 s, client) cherche le PC de TOC le plus proche -> missionNamespace COMSPEC_ATAK_TocNear ;
                      publie COMSPEC_ATAK_TocOn (indicatif du poste) sur mon unité tant que j'y suis (réponses des joueurs)
      "near"          -> PC de TOC à portée (objNull sinon)
      "isToc" objet   -> true si l'objet sert de PC de TOC pour moi
      "open" objet    ouvre le PC du poste (fn_tocLaptop), indépendant du téléphone
      "callsign"      -> indicatif du poste (config.json, toc.callsign)
      "menu"          actions ACE / molette (appelé une fois par XEH_postInitClient) ; "action" : action de la molette
    Action de la molette « Ouvrir le PC du poste » (demande de TTRD, 11/11) : proposée devant tout poste qui n'a pas
    l'action dans un menu ACE (ACE absent, écran d'une classe sans menu ACE, écran d'un autre mod désigné en jeu,
    joueur arrivé en cours de partie), sur le poste le plus proche (COMSPEC_ATAK_TocActObj, cherché chaque seconde,
    distance au centre de l'écran : l'origine des modèles est au sol ou au plafond).
*/
params [["_act", "near"], ["_arg", objNull]];
private _laptops = ["Land_Laptop_unfolded_F", "Land_Laptop_device_F", "Land_Laptop_F", "Land_Laptop_02_unfolded_F", "Land_Laptop_03_black_F", "Land_Laptop_03_olive_F", "Land_Laptop_03_sand_F", "Land_MultiScreenComputer_01_black_F", "Land_MultiScreenComputer_01_olive_F", "Land_MultiScreenComputer_01_sand_F", "Land_PCSet_01_screen_F"];
private _isToc = {
    params ["_o"];
    if (isNull _o || {!alive _o}) exitWith { false };
    private _ok = _o isKindOf "COMSPEC_ATAK_TocLaptop" || {_o getVariable ["COMSPEC_ATAK_Toc", false]}
        || {(missionNamespace getVariable ["comspec_atak_native_toc_anylaptop", false]) && {(typeOf _o) in _laptops}};
    if !(_ok) exitWith { false };
    private _side = toUpper trim (_o getVariable ["COMSPEC_ATAK_TocSide", ""]);
    // Camp écrit à la main dans Eden : noms courants acceptés (BLUFOR, OPFOR, INDEP…), sinon le PC restait muet.
    _side = (createHashMapFromArray [["BLUFOR", "WEST"], ["BLU", "WEST"], ["OPFOR", "EAST"], ["RED", "EAST"], ["INDEP", "GUER"], ["INDEPENDENT", "GUER"], ["RESISTANCE", "GUER"], ["IND", "GUER"], ["CIVILIAN", "CIV"]]) getOrDefault [_side, _side];
    _side isEqualTo "" || {_side isEqualTo str side group player}
};
switch (_act) do {
    case "isToc": { [_arg] call _isToc };
    case "near": {
        private _o = missionNamespace getVariable ["COMSPEC_ATAK_TocNear", objNull];
        // Écran : distance à son centre (mur d'images, écran suspendu), comme « tick ».
        private _scr = !isNull _o && {!isNil { _o getVariable "COMSPEC_ATAK_TocScr" }};
        [objNull, _o] select (!isNull _o && {alive player} && {isNull objectParent player}
            && {(if (_scr) then { (eyePos player) distance (["center", _o] call comspec_atak_native_fnc_tocScreen) } else { player distance _o }) < ([3.5, 5.5] select _scr)})
    };
    case "callsign": { ((["get"] call comspec_atak_native_fnc_localCfg) get "toc") get "callsign" };
    case "tick": {
        if (!hasInterface || {isNull player}) exitWith {};
        private _near = objNull;
        if (alive player && {isNull objectParent player} && {missionNamespace getVariable ["comspec_atak_native_toc_enabled", true]}) then {
            // Écran TOC (fn_tocScreen) : grand objet, poste à 4,5 m de son centre ; PC portable à 2,5 m.
            // Mur d'images, écran suspendu : l'origine du modèle est loin de l'image, la distance se prend à son centre.
            private _cands = (nearestObjects [player, [], 7, true]) select {
                private _scr = !isNil { _x getVariable "COMSPEC_ATAK_TocScr" };
                (if (_scr) then { (eyePos player) distance (["center", _x] call comspec_atak_native_fnc_tocScreen) } else { player distance _x }) < ([2.5, 4.5] select _scr) && {[_x] call _isToc}
            };
            if (_cands isNotEqualTo []) then { _near = _cands select 0; };
        };
        // Poste pour l'action de la molette : aucun quand ACE porte déjà l'action sur cet objet (classe au menu ACE des
        // écrans ou des PC, objet qui a reçu le menu), pour ne pas la montrer deux fois.
        private _act = _near;
        if (!isNull _act && {!isNil "ace_interact_menu_fnc_createAction"}) then {
            private _cls = typeOf _act;
            if (_act isKindOf "COMSPEC_ATAK_TocLaptop" || {_cls in (missionNamespace getVariable ["COMSPEC_ATAK_TocLaptops", []])}
                || {_act getVariable ["COMSPEC_ATAK_WallObj", false]}
                || {_cls in (missionNamespace getVariable ["COMSPEC_ATAK_WallClasses", []]) && {isClass (configOf _act >> "ACE_Actions" >> "ACE_MainActions")}}) then { _act = objNull; };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocActObj", _act];
        private _was = missionNamespace getVariable ["COMSPEC_ATAK_TocNear", objNull];
        missionNamespace setVariable ["COMSPEC_ATAK_TocNear", _near];
        private _cs = ["", ["callsign"] call comspec_atak_native_fnc_tocPost] select !isNull _near;
        if ((player getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo _cs) then { player setVariable ["COMSPEC_ATAK_TocOn", _cs, true]; };
        if (isNull _near && {!isNull _was}) then {
            // Parti du poste : l'app Poste TOC se ferme.
            if ((((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "TOC") && {!isNull ([] call comspec_atak_native_fnc_display)}) then {
                ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
            };
        };
    };
    case "open": {
        if !([_arg] call _isToc) exitWith { ["WARNING", "Ce PC n'est pas un poste TOC de votre camp", 3, 30] call comspec_atak_native_fnc_notify; };
        missionNamespace setVariable ["COMSPEC_ATAK_TocNear", _arg];
        player setVariable ["COMSPEC_ATAK_TocOn", ["callsign"] call comspec_atak_native_fnc_tocPost, true];
        // Téléphone ouvert en grand : rangé, le PC prend l'écran.
        if (!isNull ([] call comspec_atak_native_fnc_display) && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then {
            [] call comspec_atak_native_fnc_interactToggle;
        };
        ["open", _arg] call comspec_atak_native_fnc_tocLaptop;
    };
    case "menu": {
        if (!hasInterface) exitWith {};
        private _icon = "\z\comspec_atak_native\addons\main\data\app_toc.paa";
        if (!isNil "ace_interact_menu_fnc_createAction") then {
            private _a = ["COMSPEC_ATAK_TocOpen", "PC du poste TOC", _icon, { ["open", _target] call comspec_atak_native_fnc_tocPost; },
                { ["isToc", _target] call comspec_atak_native_fnc_tocPost && {isNull objectParent _player} }] call comspec_atak_native_fnc_aceAction;
            // Classe exacte, sans héritage : l'action héritée (objet Static) dépend d'un événement d'init qui ne vient pas
            // toujours, et l'action de la molette est retirée pour cet objet quand ACE est là : plus aucun moyen d'ouvrir le PC.
            ["COMSPEC_ATAK_TocLaptop", 0, ["ACE_MainActions"], _a] call ace_interact_menu_fnc_addActionToClass;
            // Ordinateurs portables du jeu (réglage) et objets désignés par la mission : même action.
            { [_x, 0, ["ACE_MainActions"], _a] call ace_interact_menu_fnc_addActionToClass; } forEach _laptops;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocLaptops", _laptops];
        // Sans ACE (ou objet désigné par la mission, sans menu ACE) : action de la molette, remise à chaque réapparition.
        ["action"] call comspec_atak_native_fnc_tocPost;
        player addEventHandler ["Respawn", { [{ ["action"] call comspec_atak_native_fnc_tocPost; }, [], 1] call comspec_atak_native_fnc_waitAndExec; }];
    };
    case "action": {
        // Poste trouvé par « tick » (une variable lue par image, aucun calcul dans la condition). Avant : objet sous le
        // viseur seulement, à 4,5 m de son origine (jamais vrai devant un mur d'images ou un écran suspendu).
        player addAction [format ["<t color='#7fd0b0'>%1</t>", ("Ouvrir le PC du poste") call comspec_atak_native_fnc_tr], { ["open", missionNamespace getVariable ["COMSPEC_ATAK_TocActObj", objNull]] call comspec_atak_native_fnc_tocPost; }, nil, 1.3, false, true, "",
            "isNull objectParent _this && {!isNull (missionNamespace getVariable ['COMSPEC_ATAK_TocActObj', objNull])}"];
    };
};
