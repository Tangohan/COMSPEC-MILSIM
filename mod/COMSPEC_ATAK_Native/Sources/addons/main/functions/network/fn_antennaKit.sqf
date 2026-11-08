/*
    Antennes transportables : un objet d'inventaire (kit) qu'on déploie et replie par ACE, avec une barre de progression.
    À ne pas confondre avec fn_antenna (antennes vissées sur le téléphone) ni avec le mât de la MPU5 (fn_mpuLink "mast").
    Le serveur décide et crée l'objet : il reste en place et visible pour tous, arrivées en cours de partie comprises
    (objet global, variables publiques, liste COMSPEC_AtakRelays). Aucune boucle : tout passe par des événements.
    Une fois posée, c'est un relais ordinaire (fn_relayConvert "register") : fn_relayNet le liste, le bilan fn_radioLink
    s'applique depuis la hauteur de son antenne, la carte réseau et le maillage le voient. Réservé au camp qui l'a posée.
    Détruite, supprimée (Zeus) ou repliée : retirée du réseau tout de suite (fn_relayConvert "remove").
    Portée du relais = portée de base x 10^(gain / 20) : fn_radioLink donne 0 dB de marge à la portée, donc 6 dB de gain
    la doublent à peu près. Valeurs de jeu plausibles, pas une étude : tout se règle dans les réglages CBA
    comspec_atak_native_antkit_<sat | mast>_<range | gain | height | mbps | time>.
      Antenne satellite : raccordée (porte le réseau seule), courte portée locale, comme le terminal satellite du module.
      Antenne omnidirectionnelle : répéteur, doit voir un relais raccordé (réglage antkit_mast_root pour la raccorder).
      Mini-antenne de bâtiment : petit répéteur posé sur un mur ou un toit visé, mode de visée avec aperçu (touche Espace,
      Échap pour annuler, 45 s au plus). L'antenne reste à la position réelle : le bilan radio part de là, sa hauteur
      n'est que le débord au-dessus du support. Bâtiment détruit ou supprimé : retirée sous 15 s (fn_relayConvert "check").
    Modèles d'Arma, aucun nouveau modèle ; rien ne dépend d'un autre mod. Sans ACE : objets présents, déploiement refusé avec un message.
    Deux voies pour déployer : ACE (Équipement > Déployer) et clic droit sur l'objet dans l'inventaire (menu d'objet de CBA).
    Jamais d'échec muet : chaque refus s'affiche à l'écran (le téléphone fermé ne montre rien) et chaque étape écrit une ligne
    [COMSPEC ATAK NATIVE][INFO][ANTENNE] dans le RPT, côté joueur et côté serveur.
      Joueur  : ["clientInit"], ["aceInit"], ["deploy", [kit]], ["deployNow", [kit, pose]], ["fold", [antenne]], ["result", [genre, kit, texte]],
                ["aim", [kit]], ["aimDraw"], ["aimEnd", [poser]]
      Serveur : ["init"], ["srvDeploy", [unité, kit, position ASL, cap, [dir, haut], support]], ["srvFold", [unité, antenne]], ["hosts"]
*/
params [["_act", ""], ["_a", []]];
#define KIT_FN comspec_atak_native_fnc_antennaKit
// [id, kit, objet posé, type de relais, nom sur le réseau, places, portée, gain dB, hauteur m, Mbit/s, durée s, textes ACE et barre]
// Hauteurs par défaut : celles des modèles (verticalOffset de leur classe d'Arma x 2).
private _defs = [
    ["sat", "COMSPEC_ATAK_SatAntennaKit", "COMSPEC_ATAK_DeployedAntenna_Sat", "satellite", "Antenne satellite", 16, 1000, 3, 1.8, 10, 20,
        "Déployer l'antenne satellite", "Déploiement de l'antenne satellite…", "Repli de l'antenne satellite…"],
    ["mast", "COMSPEC_ATAK_MastAntennaKit", "COMSPEC_ATAK_DeployedAntenna_Omni", "mat", "Antenne omnidirectionnelle", 24, 1500, 6, 4, 8, 45,
        "Déployer l'antenne omnidirectionnelle", "Déploiement de l'antenne omnidirectionnelle…", "Repli de l'antenne omnidirectionnelle…"],
    ["mini", "COMSPEC_ATAK_BuildingAntennaKit", "COMSPEC_ATAK_DeployedAntenna_Mini", "portable", "Mini-antenne", 6, 500, 3, 0.3, 4, 8,
        "Poser la mini-antenne sur un bâtiment", "Pose de la mini-antenne…", "Retrait de la mini-antenne…"]
];
// Surface visée à moins de 4 m : [valable, position ASL, normale, bâtiment, raison du refus].
private _aimAt = {
    private _from = eyePos player;
    private _hit = lineIntersectsSurfaces [_from, _from vectorAdd ((getCameraViewDirection player) vectorMultiply 4), player, objNull, true, 1, "GEOM", "FIRE"];
    if (_hit isEqualTo []) exitWith { [false, [], [], objNull, "Visez un mur ou un toit à moins de 4 m"] };
    (_hit select 0) params ["_pos", "_n", ["_obj", objNull], ["_parent", objNull]];
    private _host = [_parent, _obj] select (isNull _parent);
    if (isNull _host || {!(_host isKindOf "Building")} || {!alive _host}) exitWith { [false, _pos, _n, objNull, "Ceci n'est pas un bâtiment"] };
    // Ni plafond ni dessous de balcon : l'antenne doit rester droite.
    if ((_n select 2) < -0.2) exitWith { [false, _pos, _n, _host, "Pas sous un plafond : visez un mur ou un toit"] };
    [true, _pos, _n, _host, ""]
};
private _defOf = { params ["_kit"]; _defs param [_defs findIf { (_x select 1) isEqualTo _kit }, []] };
private _set = { params ["_id", "_key", "_def"]; missionNamespace getVariable [format ["comspec_atak_native_antkit_%1_%2", _id, _key], _def] };
private _log = { params ["_txt"]; ["INFO", "ANTENNE", _txt] call comspec_atak_native_fnc_log; };
// À l'écran de jeu : une notification du téléphone ne se voit que téléphone ouvert. Gardée aussi au centre de notifications.
private _say = {
    params ["_type", "_msg"];
    private _shown = _msg call comspec_atak_native_fnc_tr;
    if (isNil "ace_common_fnc_displayTextStructured") then { hintSilent _shown; } else { [_shown, 1.5, player, 14] call ace_common_fnc_displayTextStructured; };
    [_type, _msg, 5, 30] call comspec_atak_native_fnc_notify;
};
private _answer = { params ["_unit", "_what", "_kit", "_msg"]; ["comspec_atak_native_antKitResult", [_what, _kit, _msg], _unit] call CBA_fnc_targetEvent; };
switch (_act) do {
    case "clientInit": {
        if (!hasInterface) exitWith { false };
        ["comspec_atak_native_antKitResult", { ["result", _this] call KIT_FN; }] call CBA_fnc_addEventHandler;
        // Clic droit sur l'objet, dans l'inventaire (CBA) : l'objet n'a sinon aucune action à cet endroit.
        if (isNil "CBA_fnc_addItemContextMenuOption") exitWith { ["menu d'inventaire de CBA absent"] call _log; false };
        {
            _x params ["_id", "_kit", "", "", "", "", "", "", "", "", "", "_label"];
            [_kit, ["CONTAINER"], _label call comspec_atak_native_fnc_tr, [], format ["\z\comspec_atak_native\addons\main\data\item_antkit_%1.paa", _id],
                { isNull objectParent (_this select 0) }, {
                    [{ ["deploy", _this] call KIT_FN; }, [_this select 2]] call comspec_atak_native_fnc_nextFrame;
                    false
                }] call CBA_fnc_addItemContextMenuOption;
        } forEach _defs;
        true
    };
    case "aceInit": {
        if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"}) exitWith { false };
        {
            _x params ["_id", "_kit", "_cls", "", "", "", "", "", "", "", "", "_label"];
            private _icon = format ["\z\comspec_atak_native\addons\main\data\item_antkit_%1.paa", _id];
            private _dep = [format ["COMSPEC_ATAK_AntKitDeploy_%1", _id], _label, _icon, { ["deploy", [_this select 2]] call KIT_FN; },
                { ((_this select 2) in (items _player)) && {isNull objectParent _player} }, {}, _kit] call comspec_atak_native_fnc_aceAction;
            ["CAManBase", 1, ["ACE_SelfActions", "ACE_Equipment"], _dep, true] call ace_interact_menu_fnc_addActionToClass;
            private _fold = [format ["COMSPEC_ATAK_AntKitFold_%1", _id], "Replier l'antenne", _icon, { ["fold", [_target]] call KIT_FN; },
                { alive _target && {(_target getVariable ["COMSPEC_AtakRelayKit", ""]) isNotEqualTo ""} && {isNull objectParent _player} }, {}, [], [0, 0, 0], 4] call comspec_atak_native_fnc_aceAction;
            [_cls, 0, [], _fold] call ace_interact_menu_fnc_addActionToClass;
        } forEach _defs;
        true
    };
    case "aim": {
        _a params ["_kit"];
        if (!isNil {uiNamespace getVariable "COMSPEC_ATAK_AntKitAim"}) exitWith { false };
        private _disp = findDisplay 46;
        private _keyEh = _disp displayAddEventHandler ["KeyDown", {
            params ["", "_key"];
            if (_key isEqualTo 57) exitWith { ["aimEnd", [true]] call KIT_FN; true };
            if (_key isEqualTo 1) exitWith { ["aimEnd", [false]] call KIT_FN; true };
            false
        }];
        uiNamespace setVariable ["COMSPEC_ATAK_AntKitAim", [_kit, diag_tickTime + 45, addMissionEventHandler ["Draw3D", { ["aimDraw"] call KIT_FN; }], _keyEh]];
        ["mode de visée de la mini-antenne"] call _log;
        ["INFO", "Visez un mur ou un toit. Espace : poser. Échap : annuler."] call _say;
        true
    };
    case "aimDraw": {
        (uiNamespace getVariable ["COMSPEC_ATAK_AntKitAim", []]) params [["_kit", ""], ["_end", 0]];
        if (!alive player || {!isNull objectParent player} || {!(_kit in (items player))} || {diag_tickTime > _end}) exitWith {
            ["aimEnd", [false]] call KIT_FN;
            ["WARNING", "Pose annulée"] call _say;
        };
        (call _aimAt) params ["_ok", "_pos", "", "", "_why"];
        // Rien à montrer sans surface : consigne seule, au centre de la vue.
        private _at = if (_pos isEqualTo []) then { (positionCameraToWorld [0, 0, 3]) } else { ASLToAGL _pos };
        drawIcon3D ["\z\comspec_atak_native\addons\main\data\item_antkit_mini.paa", [[0.9, 0.2, 0.15, 1], [0.3, 0.9, 0.35, 1]] select _ok, _at, 1.1, 1.1, 0,
            ([_why, "Espace : poser"] select _ok) call comspec_atak_native_fnc_tr, 2, 0.035, "PuristaMedium", "center"];
    };
    case "aimEnd": {
        _a params ["_place"];
        (uiNamespace getVariable ["COMSPEC_ATAK_AntKitAim", []]) params [["_kit", ""], "", ["_draw", -1], ["_keyEh", -1]];
        if (_kit isEqualTo "") exitWith { false };
        private _spot = if (_place) then { call _aimAt } else { [] };
        if (_place && {!(_spot select 0)}) exitWith { ["WARNING", _spot select 4] call _say; false };
        removeMissionEventHandler ["Draw3D", _draw];
        (findDisplay 46) displayRemoveEventHandler ["KeyDown", _keyEh];
        uiNamespace setVariable ["COMSPEC_ATAK_AntKitAim", nil];
        if (!_place) exitWith { ["visée annulée"] call _log; false };
        _spot params ["", "_pos", "_n", "_host"];
        // À plat contre un mur, debout sur un toit. Le haut de l'antenne reste la verticale.
        private _away = [_n select 0, _n select 1, 0];
        private _mount = [_pos vectorAdd (_n vectorMultiply 0.05), if ((vectorMagnitude _away) > 0.2) then { vectorNormalized _away } else { vectorDir player }, _host];
        [{ ["deploy", _this] call KIT_FN; }, [_kit, _mount]] call comspec_atak_native_fnc_nextFrame;
        true
    };
    case "deploy": {
        _a params ["_kit", ["_mount", []]];
        private _d = [_kit] call _defOf;
        [format ["déployer demandé : %1, dans l'inventaire : %2", _kit, _kit in (items player)]] call _log;
        if (_d isEqualTo []) exitWith { ["WARNING", "Déploiement refusé : antenne inconnue"] call _say; false };
        if !(_kit in (items player)) exitWith { ["WARNING", "Déploiement refusé : l'antenne n'est plus dans l'inventaire"] call _say; false };
        if (!isNull objectParent player) exitWith { ["WARNING", "Déploiement refusé : descendez du véhicule"] call _say; false };
        if (isNil "ace_common_fnc_progressBar") exitWith { ["WARNING", "Déploiement refusé : ACE est nécessaire"] call _say; false };
        if ((_d select 0) isEqualTo "mini" && {_mount isEqualTo []}) exitWith { ["aim", [_kit]] call KIT_FN };
        [[_d select 0, "time", _d select 10] call _set, [_kit, _mount], { ["deployNow", _this select 0] call KIT_FN; }, { ["interrupted", [(_this select 0) select 0, _this select 3]] call KIT_FN; },
            ((_d select 12) call comspec_atak_native_fnc_tr), { alive player && {(((_this select 0) select 0) in (items player))} && {isNull objectParent player} },
            ["isNotInside", "isNotSitting"]] call ace_common_fnc_progressBar;
        true
    };
    case "deployNow": {
        _a params ["_kit", ["_mount", []]];
        if (!alive player || {!(_kit in (items player))} || {!isNull objectParent player}) exitWith { ["WARNING", "Déploiement refusé : antenne absente ou joueur en véhicule"] call _say; ["fin de barre refusée"] call _log; false };
        if (_mount isNotEqualTo []) exitWith {
            _mount params ["_pos", "_dirV", "_host"];
            player removeItem _kit;
            [format ["envoi au serveur : %1 en %2 sur %3", _kit, _pos, _host]] call _log;
            uiNamespace setVariable ["COMSPEC_ATAK_AntKitPending", _kit];
            ["comspec_atak_native_antKitDeploy", [player, _kit, _pos, getDir player, [_dirV, [0, 0, 1]], _host]] call CBA_fnc_serverEvent;
            [{
                params ["_kit"];
                if ((uiNamespace getVariable ["COMSPEC_ATAK_AntKitPending", ""]) isNotEqualTo _kit) exitWith {};
                ["late", ["refund", _kit, "Aucune réponse du serveur : vérifiez qu'il a la même version du mod"]] call KIT_FN;
            }, [_kit], 10] call comspec_atak_native_fnc_waitAndExec;
            true
        };
        // 2,5 m devant le joueur, sur le premier sol rencontré en descendant (toit, dalle, sinon le terrain).
        private _dir = getDir player;
        private _top = (getPosASL player) vectorAdd [2.5 * sin _dir, 2.5 * cos _dir, 2];
        private _hit = lineIntersectsSurfaces [_top, _top vectorAdd [0, 0, -8], player, objNull, true, 1, "GEOM", "NONE"];
        private _pos = if (_hit isEqualTo []) then { [_top select 0, _top select 1, getTerrainHeightASL _top] } else { (_hit select 0) select 0 };
        if (surfaceIsWater _pos && {(_pos select 2) < 0.5}) exitWith { ["WARNING", "Pas de déploiement dans l'eau"] call _say; false };
        // Objet retiré d'abord, rendu si le serveur refuse : jamais de doublon.
        player removeItem _kit;
        [format ["envoi au serveur : %1 en %2", _kit, _pos]] call _log;
        uiNamespace setVariable ["COMSPEC_ATAK_AntKitPending", _kit];
        ["comspec_atak_native_antKitDeploy", [player, _kit, _pos, _dir]] call CBA_fnc_serverEvent;
        // Sans réponse (serveur sans le mod ou d'une autre version) : objet rendu, plutôt qu'une antenne perdue sans un mot.
        [{
            params ["_kit"];
            if ((uiNamespace getVariable ["COMSPEC_ATAK_AntKitPending", ""]) isNotEqualTo _kit) exitWith {};
            ["late", ["refund", _kit, "Aucune réponse du serveur : vérifiez qu'il a la même version du mod"]] call KIT_FN;
        }, [_kit], 10] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "interrupted": {
        _a params ["_kit", "_code"];
        [format ["barre interrompue : %1, code ACE %2 (1 annulé, 2 mort ou changement d'unité, 3 condition, 4 interaction impossible)", _kit, _code]] call _log;
        ["WARNING", "Déploiement interrompu : l'antenne reste dans l'inventaire"] call _say;
    };
    case "late": {
        _a params ["_what", "_kit", "_msg"];
        uiNamespace setVariable ["COMSPEC_ATAK_AntKitPending", ""];
        [format ["sans réponse du serveur : %1 rendu", _kit]] call _log;
        ["result", [_what, _kit, _msg, true]] call KIT_FN;
    };
    case "fold": {
        _a params ["_o"];
        private _d = [_o getVariable ["COMSPEC_AtakRelayKit", ""]] call _defOf;
        if (_d isEqualTo []) exitWith { ["WARNING", "Repli impossible : cet objet n'est pas une antenne transportable"] call _say; false };
        // Replier prend moitié moins de temps que déployer.
        [(([_d select 0, "time", _d select 10] call _set) / 2) max 3, [_o], { ["comspec_atak_native_antKitFold", [player, (_this select 0) select 0]] call CBA_fnc_serverEvent; }, {},
            ((_d select 13) call comspec_atak_native_fnc_tr), { alive player && {alive ((_this select 0) select 0)} }, ["isNotInside", "isNotSitting"]] call ace_common_fnc_progressBar;
        [format ["repli demandé : %1", typeOf _o]] call _log;
        true
    };
    case "result": {
        _a params ["_what", "_kit", "_msg", ["_local", false]];
        [format ["réponse : %1, %2, %3", _what, _kit, _msg]] call _log;
        // Réponse après le délai d'attente : l'objet a déjà été rendu (refus : rien à faire ; posée quand même : on le reprend).
        if (!_local && {_what in ["refund", "deployed"]}) then {
            private _late = (uiNamespace getVariable ["COMSPEC_ATAK_AntKitPending", ""]) isNotEqualTo _kit;
            uiNamespace setVariable ["COMSPEC_ATAK_AntKitPending", ""];
            if (_late) then {
                if (_what isEqualTo "deployed") then { player removeItem _kit; };
                if (_what isEqualTo "refund") then { _what = "none"; _msg = ""; };
            };
        };
        if (_what in ["refund", "give"]) then {
            if (player canAdd _kit) then {
                player addItem _kit;
            } else {
                private _h = createVehicle ["GroundWeaponHolder", getPosATL player, [], 0, "CAN_COLLIDE"];
                _h addItemCargoGlobal [_kit, 1];
                ["WARNING", "Inventaire plein : posé à vos pieds"] call _say;
            };
        };
        if (_msg isNotEqualTo "") then {
            [["SUCCESS", "WARNING"] select (_what isEqualTo "refund"), _msg] call _say;
            if (_what isEqualTo "refund") then { playSound "addItemFailed"; };
        };
    };
    case "init": {
        if (!isServer) exitWith {};
        ["comspec_atak_native_antKitDeploy", { ["srvDeploy", _this] call KIT_FN; }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_antKitFold", { ["srvFold", _this] call KIT_FN; }] call CBA_fnc_addEventHandler;
    };
    case "srvDeploy": {
        _a params [["_unit", objNull, [objNull]], ["_kit", "", [""]], ["_pos", [], [[]], 3], ["_dir", 0, [0]], ["_vecs", [], [[]]], ["_host", objNull, [objNull]]];
        private _d = [_kit] call _defOf;
        if (!isServer) exitWith {};
        [format ["serveur, demande : %1, %2 en %3", _unit, _kit, _pos]] call _log;
        if (isNull _unit) exitWith { ["serveur, refus : unité nulle"] call _log; };
        if (_d isEqualTo [] || {_pos isEqualTo []}) exitWith { ["serveur, refus : données invalides"] call _log; [_unit, "refund", _kit, "Refusé par le serveur : demande invalide"] call _answer; };
        _d params ["_id", "", "_cls", "_kind", "_name", "_slots", "_r0", "_g0", "_h0", "_m0"];
        if (!alive _unit || {((getPosASL _unit) distance _pos) > 15}) exitWith { ["serveur, refus : trop loin"] call _log; [_unit, "refund", _kit, "Refusé par le serveur : position trop éloignée"] call _answer; };
        if (_id isEqualTo "mini" && {isNull _host || {!alive _host}}) exitWith { ["serveur, refus : bâtiment absent"] call _log; [_unit, "refund", _kit, "Refusé par le serveur : bâtiment introuvable ou détruit"] call _answer; };
        if !(isClass (configFile >> "CfgVehicles" >> _cls)) exitWith { ["serveur, refus : classe absente, mod du serveur à une autre version"] call _log; [_unit, "refund", _kit, "Refusé par le serveur : son mod est d'une autre version"] call _answer; };
        private _side = str side group _unit;
        private _max = missionNamespace getVariable ["comspec_atak_native_antkit_max", 12];
        private _mine = { (_x getVariable ["COMSPEC_AtakRelayKit", ""]) isNotEqualTo "" && {(_x getVariable ["COMSPEC_AtakRelaySide", ""]) isEqualTo _side} } count ((missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x });
        if (_mine >= _max) exitWith { ["serveur, refus : plafond du camp"] call _log; [_unit, "refund", _kit, format ["Déjà %1 antennes déployées dans votre camp : repliez-en une", _mine]] call _answer; };
        private _o = createVehicle [_cls, [0, 0, 0], [], 0, "CAN_COLLIDE"];
        _o setDir _dir;
        _o setPosASL _pos;
        if (_vecs isEqualTo []) then { _o setVectorUp [0, 0, 1]; } else { _o setVectorDirAndUp _vecs; };
        // Support de la mini-antenne, copie locale du serveur (["hosts"]).
        if (_id isEqualTo "mini") then { _o setVariable ["COMSPEC_AtakRelayHost", _host]; };
        if (isNull _o) exitWith { ["serveur, refus : objet non créé"] call _log; [_unit, "refund", _kit, "Refusé par le serveur : objet non créé"] call _answer; };
        [format ["serveur, créé : %1, modèle %2, en %3", typeOf _o, (getModelInfo _o) select 0, getPosASL _o]] call _log;
        // La portée du bilan radio (0 dB de marge) porte le gain de l'antenne.
        private _range = round ((([_id, "range", _r0] call _set) * 10 ^ (([_id, "gain", _g0] call _set) / 20)) min 30000);
        private _mb = [_id, "mbps", _m0] call _set;
        private _root = (_id isEqualTo "sat") || {missionNamespace getVariable ["comspec_atak_native_antkit_mast_root", false]};
        _o setVariable ["COMSPEC_AtakRelayKit", _kit, true];
        ["register", [_o, format ["%1 %2", _name, mapGridPosition _o], _kind, _range, _mb, [_id, "height", _h0] call _set, _slots, _side, _root, _cls]] call comspec_atak_native_fnc_relayConvert;
        _o addEventHandler ["Deleted", { ["remove", [_this select 0]] call comspec_atak_native_fnc_relayConvert; }];
        _o addEventHandler ["Killed", {
            params ["_o"];
            ["remove", [_o]] call comspec_atak_native_fnc_relayConvert;
            // Pas de ruine pour ces modèles : l'épave s'efface peu après.
            [{ params ["_o"]; if (!isNull _o) then { deleteVehicle _o; }; }, [_o], 20] call comspec_atak_native_fnc_waitAndExec;
        }];
        { [_x, [[_o], false]] remoteExec ["addCuratorEditableObjects", 2]; } forEach allCurators;
        private _msg = format ["%1 déployée : portée %2 m, %3 Mbit/s", _name, _range, _mb];
        if (!_root) then { _msg = _msg + " · Répéteur : il lui faut un relais raccordé à portée"; };
        [_unit, "deployed", _kit, _msg] call _answer;
    };
    case "hosts": {
        // Serveur, avec fn_relayConvert "check" (15 s) : mini-antenne dont le bâtiment est détruit, remplacé ou supprimé.
        if (!isServer) exitWith {};
        {
            if ((_x getVariable ["COMSPEC_AtakRelayKit", ""]) isNotEqualTo "COMSPEC_ATAK_BuildingAntennaKit") then { continue };
            private _h = _x getVariable ["COMSPEC_AtakRelayHost", objNull];
            if (isNull _h || {!alive _h} || {isObjectHidden _h}) then {
                ["serveur, bâtiment perdu : mini-antenne retirée"] call _log;
                ["remove", [_x]] call comspec_atak_native_fnc_relayConvert;
                deleteVehicle _x;
            };
        } forEach ((missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x });
    };
    case "srvFold": {
        _a params [["_unit", objNull, [objNull]], ["_o", objNull, [objNull]]];
        // Deux joueurs en même temps : le premier supprime l'objet, le second tombe sur un objet nul et ne reçoit rien.
        if (!isServer || {isNull _unit} || {isNull _o} || {!alive _o} || {!alive _unit} || {(_unit distance _o) > 15}) exitWith {};
        private _kit = _o getVariable ["COMSPEC_AtakRelayKit", ""];
        if (_kit isEqualTo "") exitWith {};
        ["remove", [_o]] call comspec_atak_native_fnc_relayConvert;
        deleteVehicle _o;
        ["serveur, antenne repliée"] call _log;
        [_unit, "give", _kit, "Antenne repliée"] call _answer;
    };
};
