/*
    Infrastructure réseau intérieure (baies, serveurs, coffrets, onduleurs, climatiseur, armoire électrique :
    classes COMSPEC_ATAK_Infra_*, modèles tools/model_net_infra.py). Décor par défaut ; chaque objet qui a un rôle
    (config comspec_atak_infraRole : "server" | "network" | "power" | "cooling") est un nœud d'infrastructure :
    allumé / éteint / en panne / endommagé / détruit, voyants du modèle liés à cet état, actions ACE (ou molette).

    Variable publique de l'objet : COMSPEC_ATAK_Infra [allumé, en panne] (tenue par le serveur).
    État lu partout : ["state", objet] -> "on" | "off" | "fault" | "damaged" | "destroyed"
      (détruit : mort ou dégâts >= 0,95 ; endommagé : dégâts >= 0,5, l'objet tourne encore).
    Registre : missionNamespace COMSPEC_ATAK_InfraNodes (objets), diffusé par le serveur quand il change.

    Réglage serveur « Le TOC dépend d'un serveur » (comspec_atak_native_infra_toc, décoché par défaut) : coché, un
    écran TOC qui a au moins une baie serveur / un serveur tour à moins de 80 m s'éteint (COMSPEC_ATAK_TocOff) dès
    qu'aucun de ces serveurs ne tourne, et se rallume avec eux. Un écran sans serveur à portée n'est jamais touché
    (les missions sans baie restent comme avant). Seuls les écrans coupés ici sont rallumés (COMSPEC_ATAK_InfraCut).

    Extraction de données (câble DL-7) : une baie est un objet comme un autre pour le module « Données à extraire »
    (fn_dataSetup, COMSPEC_ATAK_Data) ; rien à faire ici. Exemple de mission :
      [baie, [["name", "Baie serveur B2"], ["kind", "serveur"], ["port", "rj45"], ["theme", "linux"]]] call comspec_atak_native_fnc_dataSetup;

    Modes :
      ["settings"]                      preInit : réglage CBA
      ["postInit"]                      boucles serveur (3 s) et client (0,6 s, objets à moins de 60 m seulement)
      ["init", objet]                   XEH init de la classe de base (toutes les machines)
      ["state", objet]                  état
      ["set", [objet, allumé, panne]]   serveur (nil = inchangé) ; un client passe par ["ask", ...]
      ["ask", [objet, allumé, panne]]   client : demande au serveur
      ["status", objet]                 client : état sur le téléphone (notification du mod, pas de message Arma)
      ["powered", [position, rayon]]    vrai si aucun serveur dans le rayon, ou si l'un d'eux tourne
      ["room", [position ATL, cap]]     serveur : pose une salle serveur cohérente (3 baies, clim, armoire, coffret, chemins de câbles)
    Exemples de mission : ["set", [baie1, false]] call comspec_atak_native_fnc_infra;   // coupe la baie
                          ["set", [baie1, nil, true]] call comspec_atak_native_fnc_infra; // la met en panne
*/
params [["_mode", "", [""]], ["_a", []]];

private _stateOf = {
    params [["_o", objNull, [objNull]]];
    if (isNull _o || {!alive _o} || {(damage _o) >= 0.95}) exitWith { "destroyed" };
    (_o getVariable ["COMSPEC_ATAK_Infra", [true, false]]) params [["_on", true], ["_fault", false]];
    if (!_on) exitWith { "off" };
    if (_fault) exitWith { "fault" };
    if ((damage _o) >= 0.5) exitWith { "damaged" };
    "on"
};
private _roleOf = { getText ((configOf (_this select 0)) >> "comspec_atak_infraRole") };

switch (_mode) do {
    case "settings": {
        private _args = ["comspec_atak_native_infra_toc", "CHECKBOX",
            ["Le TOC dépend d'un serveur", "Décoché par défaut : les baies et serveurs COMSPEC sont un décor. Coché : un écran TOC qui a une baie serveur ou un serveur tour à moins de 80 m s'éteint quand plus aucun de ces serveurs ne tourne (éteint, en panne ou détruit), et se rallume avec eux."],
            ["COMSPEC", "ATAK · Infrastructure réseau"], false, 1];
        if (isNil "comspec_atak_native_fnc_addSetting") then { _args call CBA_fnc_addSetting; } else { _args call comspec_atak_native_fnc_addSetting; };
        true
    };
    case "postInit": {
        if (isServer && {!(missionNamespace getVariable ["COMSPEC_ATAK_InfraSrv", false])}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_InfraSrv", true];
            ["comspec_atak_native_infraSet", { ["set", _this] call comspec_atak_native_fnc_infra; }] call CBA_fnc_addEventHandler;
            [{ ["serverTick"] call comspec_atak_native_fnc_infra; }, 3] call CBA_fnc_addPerFrameHandler;
        };
        if (hasInterface && {!(missionNamespace getVariable ["COMSPEC_ATAK_InfraCli", false])}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_InfraCli", true];
            [{ ["tick"] call comspec_atak_native_fnc_infra; }, 0.6] call CBA_fnc_addPerFrameHandler;
            ["aceInit"] call comspec_atak_native_fnc_infra;
        };
        true
    };
    case "init": {
        private _o = _a;
        if !(_o isEqualType objNull) exitWith { false };
        if (isNull _o || {([_o] call _roleOf) isEqualTo ""}) exitWith { false };
        if (isServer) then {
            // Attributs Eden (COMSPEC_InfraOn) posés après l'init : lus une seconde plus tard.
            [{
                params ["_o"];
                if (isNull _o) exitWith {};
                if (isNil { _o getVariable "COMSPEC_ATAK_Infra" }) then {
                    _o setVariable ["COMSPEC_ATAK_Infra", [_o getVariable ["COMSPEC_InfraOn", true], false], true];
                };
                private _reg = missionNamespace getVariable ["COMSPEC_ATAK_InfraNodes", []];
                _reg pushBackUnique _o;
                missionNamespace setVariable ["COMSPEC_ATAK_InfraNodes", _reg];
                missionNamespace setVariable ["COMSPEC_ATAK_InfraDirty", true];
            }, [_o], 1] call comspec_atak_native_fnc_waitAndExec;
        };
        // Sans ACE : deux actions à la molette.
        if (hasInterface && {isNil "ace_interact_menu_fnc_createAction"} && {!(_o getVariable ["COMSPEC_ATAK_InfraWheel", false])}) then {
            _o setVariable ["COMSPEC_ATAK_InfraWheel", true];
            _o addAction [format ["<t color='#7fd0b0'>%1</t>", "Infrastructure : allumer / éteindre" call comspec_atak_native_fnc_tr], {
                private _t = _this select 0;
                ["ask", [_t, !((_t getVariable ["COMSPEC_ATAK_Infra", [true, false]]) param [0, true])]] call comspec_atak_native_fnc_infra;
            }, nil, 1.2, false, true, "", "alive _target", 3];
            _o addAction [format ["<t color='#7fd0b0'>%1</t>", "Infrastructure : état" call comspec_atak_native_fnc_tr], {
                ["status", _this select 0] call comspec_atak_native_fnc_infra;
            }, nil, 1.19, false, true, "", "true", 3];
        };
        true
    };
    case "state": { [_a] call _stateOf };
    case "set": {
        if (!isServer) exitWith { false };
        _a params [["_o", objNull, [objNull]], "_on", "_fault"];
        if (isNull _o || {([_o] call _roleOf) isEqualTo ""}) exitWith { false };
        private _cur = +(_o getVariable ["COMSPEC_ATAK_Infra", [true, false]]);
        if (!isNil "_on" && {_on isEqualType true}) then { _cur set [0, _on]; };
        if (!isNil "_fault" && {_fault isEqualType true}) then { _cur set [1, _fault]; };
        if (_cur isNotEqualTo (_o getVariable ["COMSPEC_ATAK_Infra", [true, false]])) then { _o setVariable ["COMSPEC_ATAK_Infra", _cur, true]; };
        true
    };
    case "ask": {
        _a params [["_o", objNull, [objNull]], "_on", "_fault"];
        if (isNull _o) exitWith { false };
        private _msg = [_o];
        _msg pushBack (if (isNil "_on") then { 0 } else { _on });
        _msg pushBack (if (isNil "_fault") then { 0 } else { _fault });
        ["comspec_atak_native_infraSet", _msg] call CBA_fnc_serverEvent;
        true
    };
    case "status": {
        if (!hasInterface) exitWith { false };
        private _o = _a;
        if !(_o isEqualType objNull) exitWith { false };
        if (isNull _o) exitWith { false };
        private _st = [_o] call _stateOf;
        private _txt = switch (_st) do {
            case "on": { "en service" };
            case "off": { "éteint" };
            case "fault": { "en panne" };
            case "damaged": { "endommagé, tourne encore" };
            default { "détruit" };
        };
        private _role = switch ([_o] call _roleOf) do {
            case "server": { "serveur" };
            case "network": { "réseau" };
            case "power": { "énergie" };
            case "cooling": { "climatisation" };
            default { "" };
        };
        private _name = _o getVariable ["COMSPEC_InfraName", ""];
        if (_name isEqualTo "") then { _name = getText ((configOf _o) >> "displayName"); };
        [["INFO", "WARNING"] select (_st in ["fault", "damaged", "destroyed"]),
            format ["%1 (%2) : %3", _name, _role call comspec_atak_native_fnc_tr, _txt call comspec_atak_native_fnc_tr], 5, 10] call comspec_atak_native_fnc_notify;
        true
    };
    case "powered": {
        _a params [["_pos", [0, 0, 0], [[], objNull]], ["_radius", 80, [0]]];
        private _srv = (missionNamespace getVariable ["COMSPEC_ATAK_InfraNodes", []]) select {
            !isNull _x && {([_x] call _roleOf) isEqualTo "server"} && {(_x distance _pos) < _radius}
        };
        if (_srv isEqualTo []) exitWith { true };
        (_srv findIf { ([_x] call _stateOf) in ["on", "damaged"] }) >= 0
    };
    case "serverTick": {
        if (!isServer) exitWith { false };
        if (missionNamespace getVariable ["COMSPEC_ATAK_InfraDirty", false]) then {
            missionNamespace setVariable ["COMSPEC_ATAK_InfraDirty", false];
            private _reg = (missionNamespace getVariable ["COMSPEC_ATAK_InfraNodes", []]) select { !isNull _x };
            missionNamespace setVariable ["COMSPEC_ATAK_InfraNodes", _reg, true];
        };
        // Écrans TOC : seulement si le réglage est coché (ou s'il reste des écrans coupés à rallumer).
        private _dep = missionNamespace getVariable ["comspec_atak_native_infra_toc", false];
        private _cut = missionNamespace getVariable ["COMSPEC_ATAK_InfraCutAny", false];
        if (!_dep && {!_cut}) exitWith { true };
        private _any = false;
        {
            if (!isNull _x) then {
                private _was = _x getVariable ["COMSPEC_ATAK_InfraCut", false];
                private _ok = !_dep || {["powered", [_x, 80]] call comspec_atak_native_fnc_infra};
                if (!_ok && {!_was} && {!(_x getVariable ["COMSPEC_ATAK_TocOff", false])}) then {
                    _x setVariable ["COMSPEC_ATAK_TocOff", true, true];
                    _x setVariable ["COMSPEC_ATAK_InfraCut", true, true];
                    _was = true;
                };
                if (_ok && {_was}) then {
                    _x setVariable ["COMSPEC_ATAK_TocOff", false, true];
                    _x setVariable ["COMSPEC_ATAK_InfraCut", false, true];
                    _was = false;
                };
                if (_was) then { _any = true; };
            };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]);
        missionNamespace setVariable ["COMSPEC_ATAK_InfraCutAny", _any];
        true
    };
    case "tick": {
        // Client, toutes les 0,6 s : voyants des nœuds à moins de 60 m. Liste des proches refaite toutes les 3 s ;
        // une texture n'est reposée que si l'état (ou la phase du clignotement) a changé.
        if (!hasInterface) exitWith { false };
        private _now = diag_tickTime;
        private _near = missionNamespace getVariable ["COMSPEC_ATAK_InfraNear", [-1e9, []]];
        if ((_now - (_near select 0)) > 3) then {
            private _eye = positionCameraToWorld [0, 0, 0];
            _near = [_now, (missionNamespace getVariable ["COMSPEC_ATAK_InfraNodes", []]) select { !isNull _x && {(_x distance _eye) < 60} }];
            missionNamespace setVariable ["COMSPEC_ATAK_InfraNear", _near];
        };
        private _list = _near select 1;
        if (_list isEqualTo []) exitWith { true };
        private _g = "#(argb,8,8,3)color(0.1,1,0.25,1,co)";
        private _am = "#(argb,8,8,3)color(1,0.55,0.05,1,co)";
        private _b = "#(argb,8,8,3)color(0.15,0.45,1,1,co)";
        private _r = "#(argb,8,8,3)color(1,0.06,0.03,1,co)";
        private _k = "#(argb,8,8,3)color(0,0,0,1,co)";
        private _phase = (floor (_now / 0.6)) % 2;
        {
            if (!isNull _x) then {
                private _st = [_x] call _stateOf;
                private _ph = (_phase + _forEachIndex) % 2;
                private _tex = switch (_st) do {
                    case "on": { [_g, [_am, _k] select _ph, _b] };
                    case "fault": { [_k, _am, _r] };
                    case "damaged": { [[_g, _k] select ((random 1) < 0.3), _k, [_r, _k] select _ph] };
                    default { [_k, _k, _k] };
                };
                if (_tex isNotEqualTo (_x getVariable ["COMSPEC_ATAK_InfraLed", []])) then {
                    _x setVariable ["COMSPEC_ATAK_InfraLed", _tex];
                    _x setObjectTexture [0, _tex select 0];
                    _x setObjectTexture [1, _tex select 1];
                    _x setObjectTexture [2, _tex select 2];
                };
            };
        } forEach _list;
        true
    };
    case "aceInit": {
        if (!hasInterface) exitWith { false };
        if (missionNamespace getVariable ["COMSPEC_ATAK_InfraAce", false]) exitWith { true };
        missionNamespace setVariable ["COMSPEC_ATAK_InfraAce", true];
        if (isNil "ace_interact_menu_fnc_createAction") exitWith { false };
        private _hasRole = { (getText ((configOf _target) >> "comspec_atak_infraRole")) isNotEqualTo "" };
        private _on = ["COMSPEC_ATAK_InfraOn", "Allumer", "", {
            ["ask", [_target, true]] call comspec_atak_native_fnc_infra;
        }, { alive _target && {!((_target getVariable ["COMSPEC_ATAK_Infra", [true, false]]) param [0, true])} }] call comspec_atak_native_fnc_aceAction;
        private _off = ["COMSPEC_ATAK_InfraOff", "Éteindre", "", {
            ["ask", [_target, false]] call comspec_atak_native_fnc_infra;
        }, { alive _target && {(_target getVariable ["COMSPEC_ATAK_Infra", [true, false]]) param [0, true]} }] call comspec_atak_native_fnc_aceAction;
        private _st = ["COMSPEC_ATAK_InfraState", "État", "", {
            ["status", _target] call comspec_atak_native_fnc_infra;
        }, { true }] call comspec_atak_native_fnc_aceAction;
        private _root = ["COMSPEC_ATAK_InfraRoot", "Infrastructure réseau", "", {}, _hasRole] call comspec_atak_native_fnc_aceAction;
        ["COMSPEC_ATAK_Infra_Base", 0, ["ACE_MainActions"], _root, true] call ace_interact_menu_fnc_addActionToClass;
        { ["COMSPEC_ATAK_Infra_Base", 0, ["ACE_MainActions", "COMSPEC_ATAK_InfraRoot"], _x, true] call ace_interact_menu_fnc_addActionToClass; } forEach [_on, _off, _st];
        true
    };
    case "room": {
        // Salle serveur type (repère : mur du fond en Y = 0, la salle vers +Y), posée à la position et au cap donnés.
        if (!isServer) exitWith { [] };
        _a params [["_pos", [0, 0, 0], [[]]], ["_dir", 0, [0]]];
        private _made = [];
        {
            _x params ["_cls", "_off", "_rot"];
            if (isClass (configFile >> "CfgVehicles" >> _cls)) then {
                _off params ["_ox", "_oy", "_oz"];
                private _p = [(_pos select 0) + _ox * (cos _dir) + _oy * (sin _dir), (_pos select 1) - _ox * (sin _dir) + _oy * (cos _dir), ((_pos param [2, 0])) + _oz];
                private _o = createVehicle [_cls, _p, [], 0, "CAN_COLLIDE"];
                _o setDir (_dir + _rot);
                _o setPosATL _p;
                _made pushBack _o;
            };
        } forEach [
            ["COMSPEC_ATAK_Infra_RackPatch", [-0.6, 1.0, 0], 180], ["COMSPEC_ATAK_Infra_RackServer", [0, 1.0, 0], 180], ["COMSPEC_ATAK_Infra_RackServer", [0.6, 1.0, 0], 180],
            ["COMSPEC_ATAK_Infra_CRAC", [1.75, 0.5, 0], 180], ["COMSPEC_ATAK_Infra_ElecCabinet", [-1.75, 0.17, 0], 180], ["COMSPEC_ATAK_Infra_Wall9U", [3.0, 0, 1.55], 0],
            ["COMSPEC_ATAK_Infra_TrayStraight", [-0.9, 1.0, 2.0], 0], ["COMSPEC_ATAK_Infra_TrayStraight", [1.1, 1.0, 2.0], 0], ["COMSPEC_ATAK_Infra_UPS", [-2.6, 0.35, 0], 180]
        ];
        { [_x, [_made, true]] remoteExec ["addCuratorEditableObjects", 2]; } forEach allCurators;
        _made
    };
    default { false };
};
