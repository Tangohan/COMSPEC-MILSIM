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
    Modèles d'Arma, aucun nouveau modèle ; rien ne dépend d'un autre mod. Sans ACE : objets présents, aucune action.
      Joueur  : ["aceInit"], ["deploy", [kit]], ["deployNow", [kit]], ["fold", [antenne]], ["result", [genre, kit, texte]]
      Serveur : ["init"], ["srvDeploy", [unité, kit, position ASL, cap]], ["srvFold", [unité, antenne]]
*/
params [["_act", ""], ["_a", []]];
#define KIT_FN comspec_atak_native_fnc_antennaKit
// [id, kit, objet posé, type de relais, nom sur le réseau, places, portée, gain dB, hauteur m, Mbit/s, durée s, textes ACE et barre]
// Hauteurs par défaut : celles des modèles (verticalOffset de leur classe d'Arma x 2).
private _defs = [
    ["sat", "COMSPEC_ATAK_SatAntennaKit", "COMSPEC_ATAK_DeployedAntenna_Sat", "satellite", "Antenne satellite", 16, 1000, 3, 1.8, 10, 20,
        "Déployer l'antenne satellite", "Déploiement de l'antenne satellite…", "Repli de l'antenne satellite…"],
    ["mast", "COMSPEC_ATAK_MastAntennaKit", "COMSPEC_ATAK_DeployedAntenna_Omni", "mat", "Antenne omnidirectionnelle", 24, 1500, 6, 4, 8, 45,
        "Déployer l'antenne omnidirectionnelle", "Déploiement de l'antenne omnidirectionnelle…", "Repli de l'antenne omnidirectionnelle…"]
];
private _defOf = { params ["_kit"]; _defs param [_defs findIf { (_x select 1) isEqualTo _kit }, []] };
private _set = { params ["_id", "_key", "_def"]; missionNamespace getVariable [format ["comspec_atak_native_antkit_%1_%2", _id, _key], _def] };
private _say = {
    params ["_type", "_msg"];
    if !([_msg, 5] call comspec_atak_native_fnc_toast) then { [_type, _msg, 5, 30] call comspec_atak_native_fnc_notify; };
};
private _answer = { params ["_unit", "_what", "_kit", "_msg"]; ["comspec_atak_native_antKitResult", [_what, _kit, _msg], _unit] call CBA_fnc_targetEvent; };
switch (_act) do {
    case "aceInit": {
        if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"}) exitWith { false };
        ["comspec_atak_native_antKitResult", { ["result", _this] call KIT_FN; }] call CBA_fnc_addEventHandler;
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
    case "deploy": {
        _a params ["_kit"];
        private _d = [_kit] call _defOf;
        if (_d isEqualTo []) exitWith { false };
        [[_d select 0, "time", _d select 10] call _set, [_kit], { ["deployNow", _this select 0] call KIT_FN; }, {},
            ((_d select 12) call comspec_atak_native_fnc_tr), { alive player && {(((_this select 0) select 0) in (items player))} && {isNull objectParent player} },
            ["isNotInside", "isNotSitting"]] call ace_common_fnc_progressBar;
        true
    };
    case "deployNow": {
        _a params ["_kit"];
        if (!alive player || {!(_kit in (items player))} || {!isNull objectParent player}) exitWith { false };
        // 2,5 m devant le joueur, sur le premier sol rencontré en descendant (toit, dalle, sinon le terrain).
        private _dir = getDir player;
        private _top = (getPosASL player) vectorAdd [2.5 * sin _dir, 2.5 * cos _dir, 2];
        private _hit = lineIntersectsSurfaces [_top, _top vectorAdd [0, 0, -8], player, objNull, true, 1, "GEOM", "NONE"];
        private _pos = if (_hit isEqualTo []) then { [_top select 0, _top select 1, getTerrainHeightASL _top] } else { (_hit select 0) select 0 };
        if (surfaceIsWater _pos && {(_pos select 2) < 0.5}) exitWith { ["WARNING", "Pas de déploiement dans l'eau"] call _say; false };
        // Objet retiré d'abord, rendu si le serveur refuse : jamais de doublon.
        player removeItem _kit;
        ["comspec_atak_native_antKitDeploy", [player, _kit, _pos, _dir]] call CBA_fnc_serverEvent;
        true
    };
    case "fold": {
        _a params ["_o"];
        private _d = [_o getVariable ["COMSPEC_AtakRelayKit", ""]] call _defOf;
        if (_d isEqualTo []) exitWith { false };
        // Replier prend moitié moins de temps que déployer.
        [(([_d select 0, "time", _d select 10] call _set) / 2) max 3, [_o], { ["comspec_atak_native_antKitFold", [player, (_this select 0) select 0]] call CBA_fnc_serverEvent; }, {},
            ((_d select 13) call comspec_atak_native_fnc_tr), { alive player && {alive ((_this select 0) select 0)} }, ["isNotInside", "isNotSitting"]] call ace_common_fnc_progressBar;
        true
    };
    case "result": {
        _a params ["_what", "_kit", "_msg"];
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
        _a params [["_unit", objNull, [objNull]], ["_kit", "", [""]], ["_pos", [], [[]], 3], ["_dir", 0, [0]]];
        private _d = [_kit] call _defOf;
        if (!isServer || {isNull _unit} || {_d isEqualTo []} || {_pos isEqualTo []}) exitWith {};
        _d params ["_id", "", "_cls", "_kind", "_name", "_slots", "_r0", "_g0", "_h0", "_m0"];
        if (!alive _unit || {((getPosASL _unit) distance _pos) > 15}) exitWith { [_unit, "refund", _kit, "Déploiement impossible ici"] call _answer; };
        private _side = str side group _unit;
        private _max = missionNamespace getVariable ["comspec_atak_native_antkit_max", 12];
        private _mine = { (_x getVariable ["COMSPEC_AtakRelayKit", ""]) isNotEqualTo "" && {(_x getVariable ["COMSPEC_AtakRelaySide", ""]) isEqualTo _side} } count ((missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x });
        if (_mine >= _max) exitWith { [_unit, "refund", _kit, format ["Déjà %1 antennes déployées dans votre camp : repliez-en une", _mine]] call _answer; };
        private _o = createVehicle [_cls, [0, 0, 0], [], 0, "CAN_COLLIDE"];
        _o setDir _dir;
        _o setPosASL _pos;
        _o setVectorUp [0, 0, 1];
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
    case "srvFold": {
        _a params [["_unit", objNull, [objNull]], ["_o", objNull, [objNull]]];
        // Deux joueurs en même temps : le premier supprime l'objet, le second tombe sur un objet nul et ne reçoit rien.
        if (!isServer || {isNull _unit} || {isNull _o} || {!alive _o} || {!alive _unit} || {(_unit distance _o) > 15}) exitWith {};
        private _kit = _o getVariable ["COMSPEC_AtakRelayKit", ""];
        if (_kit isEqualTo "") exitWith {};
        ["remove", [_o]] call comspec_atak_native_fnc_relayConvert;
        deleteVehicle _o;
        [_unit, "give", _kit, "Antenne repliée"] call _answer;
    };
};
