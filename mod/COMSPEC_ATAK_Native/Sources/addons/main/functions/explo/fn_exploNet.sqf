/*
    Déclencheur réseau : objet d'inventaire (COMSPEC_ATAK_NetDetonator) lié à une charge ACE, commandée depuis l'app Explosifs.
    Voie retenue : pas de nouvelle entrée ACE_Triggers. Celle-ci exigerait de réécrire SupportedTriggers de chaque chargeur
    (ceux d'ACE et ceux des autres mods) et casserait à la moindre mise à jour. On pose la charge par l'API publique
    ace_explosives_fnc_placeExplosive avec un déclencheur télécommandé que le chargeur accepte déjà (Command, MK16_Transmitter),
    on retire l'éventuelle entrée de clacker, et le serveur note la liaison sur la charge. Toute charge qu'ACE sait
    télécommander fonctionne donc, y compris celles des autres mods.
    Le serveur décide de tout : droit sur la charge (UID de celui qui l'a posée), état armé, liaison, détonation.
      Charge : variable publique COMSPEC_ATAK_NetCharge = [UID, nom, chargeur, déclencheur ACE, armée]
      Liste  : variable publique COMSPEC_ATAK_NetCharges = [charges...] (reçue par ceux qui rejoignent, UID retrouvé à la reconnexion)
    Liaison (["path", [charge, unité]], client et serveur) : la charge est sur le réseau si un relais du camp, ou le téléphone
    lui-même sauf réseau exclusivement par relais, la joint à la portée du réglage CBA (fn_radioLink : relief, météo) sans
    brouilleur plus fort que le signal. Simulation réseau coupée : distance seule, pas de brouillage.
      Joueur  : ["clientInit"], ["aceInit"], ["phone"], ["path", [charge, unité]], ["triggerOf", chargeur], ["place", [support]],
                ["linkPlaced", [charge, chargeur, déclencheur]], ["nearby", [unité]], ["cmd", [verbe, [charges]]], ["result", [...]]
      Serveur : ["init"], ["srvLink", [unité, charge, chargeur, déclencheur, essai]], ["srvCmd", [verbe, unité, [charges]]]
                verbes : "arm", "safe", "fire", "release"
*/
params [["_act", ""], ["_a", []]];
#define NET_FN comspec_atak_native_fnc_exploNet
#define NET_ITEM "COMSPEC_ATAK_NetDetonator"
#define NET_VAR "COMSPEC_ATAK_NetCharge"
#define NET_LIST "COMSPEC_ATAK_NetCharges"
#define NET_ICON "\z\comspec_atak_native\addons\main\data\item_netdetonator.paa"
private _maxRange = { (missionNamespace getVariable ["comspec_atak_native_explo_net_range", 1500]) max 50 };
private _consume = { missionNamespace getVariable ["comspec_atak_native_explo_net_consume", true] };
// Brouilleur plus fort que le signal à cette position : même rapport brouilleur / signal que fn_linkQuality.
private _jamAt = {
    params ["_p", "_side", "_margin"];
    private _now = [time, serverTime] select isMultiplayer;
    private _real = ["realism"] call comspec_atak_native_fnc_netCond;
    if !(_real isEqualType 0) then { _real = 1; };
    private _hit = false;
    {
        _x params [["_pos", [0, 0, 0]], ["_rad", 500], ["_uid", ""], ["_s", ""], ["_until", 1e9], ["_ex", false], ["_str", 1]];
        if (_until < _now || {_ex && {_s isEqualTo _side}}) then { continue };
        if (_pos isEqualType objNull) then {
            if (isNull _pos) then { continue };
            _pos = getPosATL _pos;
        };
        private _d = _p distance2D _pos;
        if (_d >= _rad) then { continue };
        private _m = 1 - ((_str max 0) min 1) * (1 - (_d / _rad) ^ 2);
        if (_real > 0) then {
            private _j = 20 * log (_rad / (_d max 1));
            _m = 1 - ((_str max 0) min 1) * ((((_j - (_margin max 0) + 12) / 24) max 0) min 1);
        };
        if (_m < 0.5) exitWith { _hit = true; };
    } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { _x isEqualType [] && {(_x param [7, 0]) isEqualTo 0} && {(_x param [8, ""]) isNotEqualTo "gps"} });
    _hit
};
switch (_act) do {
    // [ok, code ("ok" | "jam" | "range" | "relay"), motif, marge dB, passe par]
    case "path": {
        _a params ["_e", "_unit"];
        if (isNull _e || {isNull _unit}) exitWith { [false, "range", "Charge introuvable", -99, ""] };
        private _side = str side group _unit;
        private _target = (getPosASL _e) vectorAdd [0, 0, 0.5];
        private _r = call _maxRange;
        private _sim = ["sim"] call comspec_atak_native_fnc_netCond;
        private _only = ["only"] call comspec_atak_native_fnc_relayNet;
        private _margin = { params ["_from"]; if (_sim) then { ([_from, _target, _r, "fast"] call comspec_atak_native_fnc_radioLink) select 0 } else { [-1, 1] select ((_from distance _target) <= _r) } };
        private _best = -99;
        private _via = "";
        if (!_only) then {
            _best = [eyePos _unit] call _margin;
            _via = "téléphone";
        };
        // Au plus trois relais, les plus proches : le bilan radio n'est calculé que pour eux.
        private _nodes = (["list", [_side]] call comspec_atak_native_fnc_relayNet) select { (_x select 6) && {((_x select 1) distance2D _target) < 2 * _r} };
        private _near = [];
        { _near pushBack [(_x select 1) distance2D _target, _forEachIndex]; } forEach _nodes;
        _near sort true;
        {
            private _n = _nodes select (_x select 1);
            private _m = [_n select 1] call _margin;
            if (_m > _best) then { _best = _m; _via = _n select 2; };
        } forEach (_near select [0, 3]);
        if (_only && {_best < 0}) exitWith { [false, "relay", "Charge hors réseau : aucun relais ne la couvre", _best, ""] };
        if (_best < 0) exitWith { [false, "range", format ["Charge hors réseau : ni relais ni téléphone à moins de %1 m en liaison dégagée", round _r], _best, ""] };
        if (_sim && {[_target, _side, _best] call _jamAt}) exitWith { [false, "jam", "Charge brouillée", _best, _via] };
        [true, "ok", "", _best, _via]
    };
    // Téléphone du joueur : [ok, motif]
    case "phone": {
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { [false, "Téléphone ATAK absent"] };
        if (missionNamespace getVariable ["COMSPEC_ATAK_Airplane", false]) exitWith { [false, "Mode avion : aucun réseau"] };
        if ((profileNamespace getVariable ["COMSPEC_ATAK_BatterySim", true]) && {["battery"] call comspec_atak_native_fnc_netCond} && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0}) exitWith { [false, "Batterie du téléphone vide"] };
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        if ((_q getOrDefault ["sim", false]) && {(_q getOrDefault ["bars", 4]) isEqualTo 0}) exitWith { [false, format ["Téléphone sans signal : %1", _q getOrDefault ["label", "aucun réseau"]]] };
        if !(_q getOrDefault ["tx", true]) exitWith { [false, "Téléphone en réception seule : hors de portée d'émission"] };
        [true, format ["%1 barre(s) sur 4", _q getOrDefault ["bars", 4]]]
    };
    // Déclencheur télécommandé accepté par ce chargeur, "" sinon (minuterie, plaque de pression, fil : pas de liaison).
    case "triggerOf": {
        private _sup = (getArray (configFile >> "CfgMagazines" >> _a >> "ACE_Triggers" >> "SupportedTriggers")) apply { toLower _x };
        ["", "Command", "MK16_Transmitter"] select ((["command", "mk16_transmitter"] findIf { _x in _sup }) + 1)
    };
    case "clientInit": {
        if (!hasInterface) exitWith {};
        ["comspec_atak_native_exploResult", { ["result", _this] call NET_FN; }] call CBA_fnc_addEventHandler;
    };
    case "aceInit": {
        if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"} || {!isClass (configFile >> "CfgPatches" >> "ace_explosives")}) exitWith { false };
        // À la pose : sous « Déclencheur » du support ACE, à côté du clacker et de la minuterie. Classes filles comprises.
        private _pose = ["COMSPEC_ATAK_NetPlace", "Déclencheur réseau", NET_ICON, { params ["_target"]; ["place", [_target]] call NET_FN; },
            { params ["_target", "_player"]; (NET_ITEM in (items _player)) && {(["triggerOf", _target getVariable ["ace_explosives_class", ""]] call NET_FN) isNotEqualTo ""} }] call comspec_atak_native_fnc_aceAction;
        ["ACE_Explosives_Place", 0, ["ACE_MainActions", "ACE_SetTrigger"], _pose, true] call ace_interact_menu_fnc_addActionToClass;
        // Sur une charge déjà posée au clacker, à moins de 4 m : Explosifs > Lier au réseau.
        private _link = ["COMSPEC_ATAK_NetLink", "Lier au réseau", NET_ICON, {},
            { params ["", "_player"]; (NET_ITEM in (items _player)) && {(["nearby", [_player]] call NET_FN) isNotEqualTo []} },
            {
                params ["_target", "_player"];
                (["nearby", [_player]] call NET_FN) apply {
                    _x params ["_e", "_mag", "_trig"];
                    [[format ["COMSPEC_ATAK_NetLink_%1", netId _e], format ["Lier : %1", getText (configFile >> "CfgMagazines" >> _mag >> "displayName")], NET_ICON,
                        { ["linkPlaced", _this select 2] call NET_FN; }, { true }, {}, [_e, _mag, _trig]] call comspec_atak_native_fnc_aceAction, [], _target]
                }
            }] call comspec_atak_native_fnc_aceAction;
        ["CAManBase", 1, ["ACE_SelfActions", "ACE_Explosives"], _link, true] call ace_interact_menu_fnc_addActionToClass;
        true
    };
    // Mes charges au clacker à moins de 4 m, pas encore liées : [[charge, chargeur, déclencheur]...]
    case "nearby": {
        _a params ["_u"];
        private _out = [];
        {
            if (!(_x isEqualType []) || {(count _x) < 5}) then { continue };
            _x params ["_e", "", "", "_mag", "_trig"];
            if (isNull _e || {(_u distance _e) > 4} || {(_e getVariable [NET_VAR, []]) isNotEqualTo []}) then { continue };
            if ((toLower _trig) in ["command", "mk16_transmitter"]) then { _out pushBack [_e, _mag, _trig]; };
        } forEach (_u getVariable ["ace_explosives_clackers", []]);
        _out
    };
    case "place": {
        _a params ["_t"];
        if (isNull _t || {isNil "ace_explosives_fnc_placeExplosive"}) exitWith { false };
        private _mag = _t getVariable ["ace_explosives_class", ""];
        private _trig = ["triggerOf", _mag] call NET_FN;
        if (_trig isEqualTo "" || {!(NET_ITEM in (items player))}) exitWith { false };
        private _dir = _t getVariable ["ace_explosives_direction", getDir _t];
        private _e = [player, getPosATL _t, _dir, _mag, _trig, [], _t] call ace_explosives_fnc_placeExplosive;
        if (isNull _e) exitWith { ["WARNING", "Pose impossible", 4, 30] call comspec_atak_native_fnc_notify; false };
        ["linkPlaced", [_e, _mag, _trig]] call NET_FN;
        true
    };
    case "linkPlaced": {
        _a params ["_e", "_mag", "_trig"];
        // Objet retiré d'abord, rendu si le serveur refuse : jamais de doublon (comme fn_antennaKit).
        if (call _consume) then { player removeItem NET_ITEM; };
        ["srvLink", [player, _e, _mag, _trig, 0]] remoteExecCall ["comspec_atak_native_fnc_exploNet", 2];
        true
    };
    case "cmd": {
        _a params ["_verb", "_list"];
        ["srvCmd", [_verb, player, _list]] remoteExecCall ["comspec_atak_native_fnc_exploNet", 2];
        true
    };
    case "result": {
        _a params [["_what", ""], ["_msg", ""], ["_k", ""], ["_type", "WARNING"]];
        private _e = if (_k isEqualTo "") then { objNull } else { objectFromNetId _k };
        switch (_what) do {
            case "refund": {
                if (call _consume) then {
                    if (player canAdd NET_ITEM) then {
                        player addItem NET_ITEM;
                    } else {
                        private _h = createVehicle ["GroundWeaponHolder", getPosATL player, [], 0, "CAN_COLLIDE"];
                        _h addItemCargoGlobal [NET_ITEM, 1];
                    };
                };
            };
            case "linked": {
                // Plus de clacker sur cette charge : seul le réseau la commande.
                private _list = (player getVariable ["ace_explosives_clackers", []]) select { !(_x isEqualType []) || {(_x param [0, objNull]) isNotEqualTo _e} };
                player setVariable ["ace_explosives_clackers", _list, true];
            };
            case "fired": {
                // Poste de commandement (Overwatch) prévenu comme pour une minuterie, si la charge lui est connue.
                private _cid = if (isNull _e) then { "" } else { _e getVariable ["COMSPEC_chargeId", ""] };
                if (_cid isEqualType "" && {_cid isNotEqualTo ""} && {!isNil "comspec_overwatch_connect_fnc_reportExplosiveTimer"}) then {
                    [objNull, 0, objNull, "detonated", _cid] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
                };
            };
        };
        if (_msg isNotEqualTo "") then { [_type, _msg, 4, 40] call comspec_atak_native_fnc_notify; };
        ["changed"] call comspec_atak_native_fnc_exploAction;
    };
    case "init": {
        if (!isServer) exitWith {};
        // Qui a posé quoi, côté serveur : le droit de lier une charge posée sans clacker ne dépend pas d'un dire du client.
        ["ace_explosives_place", {
            params ["_e", "", "", "_unit"];
            if (!isNull _e && {!isNull _unit}) then { _e setVariable ["COMSPEC_ATAK_PlacerUid", getPlayerUID _unit]; };
        }] call CBA_fnc_addEventHandler;
        private _forget = {
            params ["_e"];
            if (isNull _e) exitWith {};
            if ((_e getVariable [NET_VAR, []]) isNotEqualTo []) then {
                _e setVariable [NET_VAR, nil, true];
                missionNamespace setVariable [NET_LIST, (missionNamespace getVariable [NET_LIST, []]) - [_e], true];
            };
        };
        ["ace_explosives_defuse", _forget] call CBA_fnc_addEventHandler;
        ["ace_explosives_explodeOnDefuse", _forget] call CBA_fnc_addEventHandler;
        // Charges sautées ou supprimées par un autre moyen (tir, Zeus) : liste publique tenue à jour, publiée seulement si elle change.
        [{
            private _l = missionNamespace getVariable [NET_LIST, []];
            if (_l isEqualTo []) exitWith {};
            private _k = _l select { !isNull _x && {alive _x} };
            if ((count _k) isNotEqualTo (count _l)) then { missionNamespace setVariable [NET_LIST, _k, true]; };
        }, 5] call CBA_fnc_addPerFrameHandler;
    };
    case "srvLink": {
        _a params [["_unit", objNull, [objNull]], ["_e", objNull, [objNull]], ["_mag", "", [""]], ["_trig", "", [""]], ["_try", 0, [0]]];
        if (!isServer || {isNull _unit}) exitWith {};
        if (isRemoteExecuted && {!(remoteExecutedOwner in [0, 2, clientOwner])} && {(owner _unit) isNotEqualTo remoteExecutedOwner}) exitWith {
            ["WARNING", "EXPLO", format ["Liaison refusée : client %1 pour %2", remoteExecutedOwner, name _unit]] call comspec_atak_native_fnc_log;
        };
        private _uid = getPlayerUID _unit;
        private _refuse = { params ["_msg"]; ["comspec_atak_native_exploResult", ["refund", _msg, "", "WARNING"], _unit] call CBA_fnc_targetEvent; };
        if (isNil "ace_explosives_fnc_detonateExplosive") exitWith { ["ACE Explosifs n'est pas chargé"] call _refuse; };
        if (isNull _e || {!alive _e}) exitWith { ["Charge introuvable (déjà sautée ou désamorcée)"] call _refuse; };
        if (!alive _unit || {(_unit distance _e) > 15}) exitWith { ["Trop loin de la charge pour la lier"] call _refuse; };
        if ((_e getVariable [NET_VAR, []]) isNotEqualTo []) exitWith { ["Cette charge est déjà liée au réseau"] call _refuse; };
        if ((["triggerOf", _mag] call NET_FN) isEqualTo "") exitWith { ["Cette charge ne peut pas recevoir de déclencheur réseau"] call _refuse; };
        if (!(call _consume) && {!(NET_ITEM in (items _unit))}) exitWith { ["Il faut un déclencheur réseau dans l'inventaire"] call _refuse; };
        // Droit d'ACE : celui qui l'a posée (événement de pose reçu ici, ou clacker porté par l'unité).
        private _placer = _e getVariable ["COMSPEC_ATAK_PlacerUid", ""];
        private _clack = ((_unit getVariable ["ace_explosives_clackers", []]) findIf { (_x isEqualType []) && {(_x param [0, objNull]) isEqualTo _e} }) >= 0;
        if (_placer isEqualTo "" && {!_clack} && {_try < 2}) exitWith {
            // L'événement de pose peut arriver juste après cette demande.
            [{ ["srvLink", _this] call NET_FN; }, [_unit, _e, _mag, _trig, _try + 1], 1] call comspec_atak_native_fnc_waitAndExec;
        };
        if (_uid isEqualTo "" || {(_placer isNotEqualTo _uid) && {!_clack}}) exitWith { ["Cette charge n'est pas à vous"] call _refuse; };
        _e setVariable [NET_VAR, [_uid, name _unit, _mag, _trig, false], true];
        missionNamespace setVariable [NET_LIST, ((missionNamespace getVariable [NET_LIST, []]) select { !isNull _x }) + [_e], true];
        ["comspec_atak_native_exploResult", ["linked", "Charge liée au réseau, désarmée : armez-la depuis l'app Explosifs", netId _e, "SUCCESS"], _unit] call CBA_fnc_targetEvent;
    };
    case "srvCmd": {
        _a params [["_verb", ""], ["_unit", objNull, [objNull]], ["_list", [], [[]]]];
        if (!isServer || {isNull _unit}) exitWith {};
        if (isRemoteExecuted && {!(remoteExecutedOwner in [0, 2, clientOwner])} && {(owner _unit) isNotEqualTo remoteExecutedOwner}) exitWith {
            ["WARNING", "EXPLO", format ["Ordre refusé : client %1 pour %2", remoteExecutedOwner, name _unit]] call comspec_atak_native_fnc_log;
        };
        private _uid = getPlayerUID _unit;
        private _say = { params ["_what", "_msg", "_k", "_type"]; ["comspec_atak_native_exploResult", [_what, _msg, _k, _type], _unit] call CBA_fnc_targetEvent; };
        // Respawn : comme ACE retire les clackers, le joueur perd ses liaisons ; les charges restent en place, à désamorcer.
        if (_verb isEqualTo "release") exitWith {
            private _all = missionNamespace getVariable [NET_LIST, []];
            private _mine = _all select { !isNull _x && {((_x getVariable [NET_VAR, []]) param [0, ""]) isEqualTo _uid} };
            { _x setVariable [NET_VAR, nil, true]; } forEach _mine;
            if (_mine isNotEqualTo []) then { missionNamespace setVariable [NET_LIST, _all - _mine, true]; };
        };
        if !(_verb in ["arm", "safe", "fire"]) exitWith {};
        private _phoneJam = [getPosASL _unit, str side group _unit, 10] call _jamAt;
        private _sim = ["sim"] call comspec_atak_native_fnc_netCond;
        {
            private _e = _x;
            private _k = if (isNull _e) then { "" } else { netId _e };
            private _rec = if (isNull _e) then { [] } else { _e getVariable [NET_VAR, []] };
            private _why = switch (true) do {
                case (!alive _unit): { "Vous êtes hors de combat" };
                case !([_unit] call comspec_atak_native_fnc_hasDevice): { "Téléphone ATAK absent" };
                case (isNull _e || {!alive _e}): { "Charge introuvable (déjà sautée ou désamorcée)" };
                case (_rec isEqualTo []): { "Cette charge n'est pas liée au réseau" };
                case (_uid isEqualTo "" || {(_rec select 0) isNotEqualTo _uid}): { "Cette charge n'est pas à vous" };
                case (_e getVariable ["COMSPEC_detonateFired", false]): { "Mise à feu déjà ordonnée" };
                case (_verb isEqualTo "fire" && {!(_rec select 4)}): { "Charge désarmée : armez-la d'abord" };
                case (_sim && {_phoneJam}): { "Téléphone brouillé" };
                default {
                    (["path", [_e, _unit]] call NET_FN) params ["_ok", "", "_txt"];
                    [_txt, ""] select _ok
                };
            };
            if (_why isNotEqualTo "") then {
                ["refused", _why, _k, "WARNING"] call _say;
                continue;
            };
            private _label = getText (configFile >> "CfgMagazines" >> (_rec select 2) >> "displayName");
            if (_verb in ["arm", "safe"]) then {
                private _new = +_rec;
                _new set [4, _verb isEqualTo "arm"];
                _e setVariable [NET_VAR, _new, true];
                ["state", "", _k, "INFO"] call _say;
            } else {
                _e setVariable ["COMSPEC_detonateFired", true, true];
                private _fuse = getNumber (configFile >> "CfgMagazines" >> (_rec select 2) >> "ACE_Triggers" >> (_rec select 3) >> "FuseTime");
                ["fired", format ["Mise à feu : %1", _label], _k, "SUCCESS"] call _say;
                // Délai du réseau, réglable. Détonation par l'API d'ACE, côté serveur : ses gestionnaires de détonation restent appliqués.
                [{
                    params ["_e", "_unit", "_fuse"];
                    if (isNull _e) exitWith {};
                    if !([_unit, -1, [_e, _fuse], NET_ITEM] call ace_explosives_fnc_detonateExplosive) then {
                        _e setVariable ["COMSPEC_detonateFired", false, true];
                        ["comspec_atak_native_exploResult", ["refused", "Mise à feu bloquée par la mission", netId _e, "WARNING"], _unit] call CBA_fnc_targetEvent;
                    };
                }, [_e, _unit, _fuse], (missionNamespace getVariable ["comspec_atak_native_explo_net_delay", 0.5]) max 0] call comspec_atak_native_fnc_waitAndExec;
            };
        } forEach (_list select [0, 40]);
    };
};
