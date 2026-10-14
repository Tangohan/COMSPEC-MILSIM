/*
    Caméras IP fixes (item COMSPEC_ATAK_Camera) : posées au mur, au sol, sur un objet ou un véhicule,
    regardées depuis l'app Vidéo live du téléphone (section « Caméras fixes », fn_camView).

    L'objet posé (COMSPEC_ATAK_CameraObj, caméra durcie IPC-2 sur rotule magnétique, tools/model_items.py) est global et porte, en variables publiques :
      COMSPEC_ATAK_CamSide  camp du poseur (str side), seul ce camp la voit dans le téléphone
      COMSPEC_ATAK_CamName  nom (CAM-1, CAM-2... ou renommée)
      COMSPEC_ATAK_CamOwner nom du poseur ; COMSPEC_ATAK_CamGroup indicatif de son groupe
    Registre partagé : missionNamespace COMSPEC_ATAK_Cams (objets, publié à chaque pose / récupération).
    Détruite (dégâts, explosion, véhicule porteur détruit) : hors service, se retire sans rendre l'item.

    Modes :
      ["aceInit"]                 actions ACE (auto-interaction « Téléphone ATAK > Caméras » et actions sur la caméra)
      ["place"]                   pose devant soi (lancer de rayon à 3 m depuis les yeux), barre de progression ACE
      ["pickup", obj]             récupère la caméra (rend l'item si elle est intacte)
      ["rotate", obj, degrés]     tourne la caméra sur son axe vertical (+ à droite)
      ["rename", obj]             ouvre le téléphone sur la caméra, champ de nom affiché
      ["setName", obj, nom]       renomme (16 caractères au plus)
      ["list"]                    caméras du camp du joueur
      ["dead", obj]               bool : détruite
      ["status", obj]             [code, libellé, couleur RGBA] ; code OK, DEAD, JAM, NONET, LINK
                                  Relais dans la mission (fn_videoLink) : la caméra doit aussi joindre le téléphone, en
                                  direct (Wi-Fi, comspec_atak_native_camera_range) ou par les relais, sinon LINK.
      ["video", obj]              chemin du flux (HashMap de fn_videoLink "path", clé "CAM:<hashValue>")
*/
params [["_mode", ""], ["_a", objNull], ["_b", 0]];
#define CAM_ITEM "COMSPEC_ATAK_Camera"
#define CAM_CLASS "COMSPEC_ATAK_CameraObj"
private _fn = comspec_atak_native_fnc_camAction;
// Repère orthonormé d'un regard (haut vertical autant que possible).
private _upOf = {
    params ["_d"];
    private _r = _d vectorCrossProduct [0, 0, 1];
    if ((vectorMagnitude _r) < 0.01) then { _r = [1, 0, 0]; };
    vectorNormalized (_r vectorCrossProduct _d)
};

switch (_mode) do {
    case "list": {
        private _side = str side group player;
        (missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]) select { !isNull _x && {(_x getVariable ["COMSPEC_ATAK_CamSide", ""]) isEqualTo _side} }
    };
    case "dead": {
        if (isNull _a) exitWith { true };
        private _host = attachedTo _a;
        !alive _a || {damage _a > 0.5} || {_a getVariable ["COMSPEC_ATAK_CamDead", false]} || {!isNull _host && {!alive _host}}
    };
    case "status": {
        if (isNull _a) exitWith { ["GONE", "Retirée", [0.54, 0.6, 0.58, 1]] };
        if (["dead", _a] call _fn) exitWith { ["DEAD", "Détruite", [0.9, 0.28, 0.23, 1]] };
        private _now = [time, serverTime] select isMultiplayer;
        // Brouilleurs de mission (mêmes entrées que fn_linkQuality) : au cœur de la zone, plus d'image.
        private _jam = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) findIf {
            _x isEqualType [] && {
                _x params [["_p", [0, 0, 0]], ["_r", 500], "", "", ["_until", 1e9]];
                if (_p isEqualType objNull) then { _p = getPosATL _p; };
                _until > _now && {(_a distance2D _p) < _r * 0.6}
            }
        };
        if (_jam >= 0) exitWith { ["JAM", "Brouillée", [0.88, 0.64, 0.23, 1]] };
        // Même règle que le reste du téléphone : sans réseau, pas de flux.
        if ((([] call comspec_atak_native_fnc_linkQuality) get "bars") isEqualTo 0) exitWith { ["NONET", "Pas de réseau", [0.88, 0.64, 0.23, 1]] };
        // Liaison Wi-Fi de la caméra : un ATAK du camp (le sien compris) à portée.
        private _range = missionNamespace getVariable ["comspec_atak_native_camera_range", 600];
        if !(_range isEqualType 0) then { _range = 600; };
        private _side = _a getVariable ["COMSPEC_ATAK_CamSide", ""];
        private _near = (allPlayers findIf { alive _x && {(_x distance _a) < _range} && {str side group _x isEqualTo _side} && {[_x] call comspec_atak_native_fnc_hasDevice} }) >= 0;
        if (!_near) exitWith { ["LINK", "Hors liaison", [0.88, 0.64, 0.23, 1]] };
        private _v = ["video", _a] call _fn;
        if ((_v getOrDefault ["active", false]) && {!(_v getOrDefault ["ok", true])}) exitWith { ["LINK", "Hors réseau des relais", [0.88, 0.64, 0.23, 1]] };
        ["OK", "En ligne", [0.36, 0.78, 0.42, 1]]
    };
    case "video": {
        if (isNull _a) exitWith { createHashMap };
        private _range = missionNamespace getVariable ["comspec_atak_native_camera_range", 600];
        if !(_range isEqualType 0) then { _range = 600; };
        private _dd = player distance _a;
        ["path", [format ["CAM:%1", hashValue _a], (getPosASL _a) vectorAdd [0, 0, 0.5], [0, 1 - (_dd / _range) ^ 2] select (_dd < _range), 8]] call comspec_atak_native_fnc_videoLink
    };
    case "place": {
        if !(CAM_ITEM in (items player)) exitWith { ["WARNING", "Aucune caméra IP dans l'inventaire", 3, 20] call comspec_atak_native_fnc_notify; false };
        if (!isNull objectParent player) exitWith { ["WARNING", "Descendez du véhicule pour poser la caméra", 3, 20] call comspec_atak_native_fnc_notify; false };
        private _from = eyePos player;
        private _to = _from vectorAdd ((getCameraViewDirection player) vectorMultiply 3);
        private _res = lineIntersectsSurfaces [_from, _to, player, objNull, true, 1, "GEOM", "FIRE"];
        if (_res isEqualTo []) then { _res = lineIntersectsSurfaces [_from, _to, player, objNull, true, 1, "VIEW", "FIRE"]; };
        if (_res isEqualTo []) exitWith { ["WARNING", "Aucune surface à moins de 3 m : visez un mur, le sol ou un véhicule", 4, 20] call comspec_atak_native_fnc_notify; false };
        (_res select 0) params ["_pos", "_n", ["_obj", objNull], ["_parent", objNull]];
        private _hit = [_pos, _n, [_parent, _obj] select (isNull _parent)];
        if (isNil "ace_common_fnc_progressBar") exitWith { ["placeNow", _hit] call _fn };
        [3, [_hit], { ["placeNow", (_this select 0) select 0] call comspec_atak_native_fnc_camAction; }, {}, "Pose de la caméra…"] call ace_common_fnc_progressBar;
        true
    };
    case "placeNow": {
        _a params ["_pos", "_n", "_o"];
        if !(CAM_ITEM in (items player)) exitWith { false };
        _n = vectorNormalized _n;
        private _look = vectorDir player;
        _look set [2, 0];
        _look = vectorNormalized _look;
        // Mur : regard dans l'axe de la normale ; sol : devant le poseur ; plafond : devant, plongeant.
        private _d = switch (true) do {
            case ((_n select 2) > 0.7): { _look };
            case ((_n select 2) < -0.7): { vectorNormalized (_look vectorAdd [0, 0, -0.6]) };
            default { vectorNormalized [_n select 0, _n select 1, 0] };
        };
        private _u = [_d] call _upOf;
        // Modèle camera_ip : base magnétique 9,2 cm sous le centre du boîtier (posée au sol), dos à 3,5 cm (mur, plafond).
        private _p = _pos vectorAdd (_n vectorMultiply ([0.045, 0.092] select ((_n select 2) > 0.7)));
        private _cam = createVehicle [CAM_CLASS, ASLToATL _p, [], 0, "CAN_COLLIDE"];
        if (isNull _cam) exitWith { false };
        _cam setPosASL _p;
        private _onVeh = !isNull _o && {_o isKindOf "AllVehicles"} && {!(_o isKindOf "CAManBase")};
        if (_onVeh) then {
            _cam attachTo [_o, _o worldToModel (ASLToAGL _p)];
            _cam setVectorDirAndUp [_o vectorWorldToModel _d, _o vectorWorldToModel _u];
        } else {
            _cam setVectorDirAndUp [_d, _u];
        };
        player removeItem CAM_ITEM;
        private _names = (["list"] call _fn) apply { _x getVariable ["COMSPEC_ATAK_CamName", ""] };
        private _i = 1;
        while { (format ["CAM-%1", _i]) in _names } do { _i = _i + 1; };
        private _name = format ["CAM-%1", _i];
        _cam setVariable ["COMSPEC_ATAK_CamSide", str side group player, true];
        _cam setVariable ["COMSPEC_ATAK_CamName", _name, true];
        _cam setVariable ["COMSPEC_ATAK_CamOwner", name player, true];
        _cam setVariable ["COMSPEC_ATAK_CamGroup", groupId group player, true];
        // Explosion proche : hors service, même si l'objet tient encore debout.
        _cam addEventHandler ["Explosion", { params ["_c", "_dmg"]; if (_dmg > 0.05) then { _c setVariable ["COMSPEC_ATAK_CamDead", true, true]; }; }];
        private _all = (missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]) select { !isNull _x };
        _all pushBackUnique _cam;
        missionNamespace setVariable ["COMSPEC_ATAK_Cams", _all, true];
        { [_x, [[_cam], true]] remoteExec ["addCuratorEditableObjects", 2]; } forEach allCurators;
        playSound "ClickSoft";
        ["SUCCESS", format ["%1 posée%2 : app Vidéo live > Caméras fixes", _name, ["", format [" sur %1", getText (configOf _o >> "displayName")]] select _onVeh], 4, 30] call comspec_atak_native_fnc_notify;
        _cam
    };
    case "pickup": {
        if (isNull _a) exitWith { false };
        if (isNil "ace_common_fnc_progressBar") exitWith { ["pickupNow", _a] call _fn };
        [2, [_a], { ["pickupNow", (_this select 0) select 0] call comspec_atak_native_fnc_camAction; }, {}, "Récupération de la caméra…"] call ace_common_fnc_progressBar;
        true
    };
    case "pickupNow": {
        if (isNull _a) exitWith { false };
        private _dead = ["dead", _a] call _fn;
        private _name = _a getVariable ["COMSPEC_ATAK_CamName", "Caméra"];
        missionNamespace setVariable ["COMSPEC_ATAK_Cams", (missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]) select { !isNull _x && {_x isNotEqualTo _a} }, true];
        deleteVehicle _a;
        if (_dead) exitWith { ["INFO", format ["%1 détruite retirée", _name], 3, 20] call comspec_atak_native_fnc_notify; true };
        if (player canAdd CAM_ITEM) then {
            player addItem CAM_ITEM;
        } else {
            private _gh = createVehicle ["GroundWeaponHolder", getPosATL player, [], 0, "CAN_COLLIDE"];
            _gh addItemCargoGlobal [CAM_ITEM, 1];
        };
        ["INFO", format ["%1 récupérée%2", _name, ["", " (inventaire plein : posée au sol)"] select !(CAM_ITEM in (items player))], 3, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "rotate": {
        if (isNull _a || {["dead", _a] call _fn}) exitWith { false };
        private _d = vectorDir _a;
        private _nd = [(_d select 0) * cos _b + (_d select 1) * sin _b, (_d select 1) * cos _b - (_d select 0) * sin _b, _d select 2];
        private _nu = [_nd] call _upOf;
        private _host = attachedTo _a;
        if (!isNull _host) then { _nd = _host vectorWorldToModel _nd; _nu = _host vectorWorldToModel _nu; };
        if (local _a) then { _a setVectorDirAndUp [_nd, _nu]; } else { [_a, [_nd, _nu]] remoteExecCall ["setVectorDirAndUp", _a]; };
        playSound "ClickSoft";
        true
    };
    case "setName": {
        if (isNull _a || {!(_b isEqualType "")}) exitWith { false };
        private _t = _b trim [" ", 0];
        if (_t isEqualTo "") exitWith { false };
        if ((count _t) > 16) then { _t = _t select [0, 16]; };
        _a setVariable ["COMSPEC_ATAK_CamName", _t, true];
        true
    };
    case "rename": {
        if (isNull _a) exitWith { false };
        if (isNull ([] call comspec_atak_native_fnc_display) || {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then {
            [true] call comspec_atak_native_fnc_open;
        };
        [{
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            _s set ["livecamTarget", netId _this];
            _s set ["livecamPan", 0];
            _s set ["livecamRename", true];
            ["LIVECAM"] call comspec_atak_native_fnc_navigate;
        }, _a] call CBA_fnc_execNextFrame;
        true
    };
    case "aceInit": {
        if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"}) exitWith { false };
        private _icon = "\z\comspec_atak_native\addons\main\data\item_camera.paa";
        private _root = ["ACE_SelfActions", "COMSPEC_ATAK_Phone"];
        private _self = ["COMSPEC_ATAK_Cams", "Caméras", _icon, {}, { CAM_ITEM in (items _player) && {isNull objectParent _player} }] call comspec_atak_native_fnc_aceAction;
        ["CAManBase", 1, _root, _self, true] call ace_interact_menu_fnc_addActionToClass;
        private _put = ["COMSPEC_ATAK_CamPlace", "Poser une caméra", _icon, { ["place"] call comspec_atak_native_fnc_camAction; }, { true }] call comspec_atak_native_fnc_aceAction;
        ["CAManBase", 1, _root + ["COMSPEC_ATAK_Cams"], _put, true] call ace_interact_menu_fnc_addActionToClass;

        // Sur la caméra posée.
        private _mine = { (_target getVariable ["COMSPEC_ATAK_CamSide", ""]) isEqualTo str side group _player && {!(["dead", _target] call comspec_atak_native_fnc_camAction)} };
        missionNamespace setVariable ["COMSPEC_ATAK_CamMine", _mine];
        private _main = ["COMSPEC_ATAK_CamMain", "Caméra IP", _icon, {}, { true }, {}, [], [0, 0, 0], 2.5] call comspec_atak_native_fnc_aceAction;
        [CAM_CLASS, 0, [], _main] call ace_interact_menu_fnc_addActionToClass;
        {
            _x params ["_id", "_txt", "_code", "_cond"];
            [CAM_CLASS, 0, ["COMSPEC_ATAK_CamMain"], [_id, _txt, "", _code, _cond] call comspec_atak_native_fnc_aceAction] call ace_interact_menu_fnc_addActionToClass;
        } forEach [
            ["COMSPEC_ATAK_CamTake", "Récupérer la caméra", { ["pickup", _target] call comspec_atak_native_fnc_camAction; }, { !(["dead", _target] call comspec_atak_native_fnc_camAction) }],
            ["COMSPEC_ATAK_CamWreck", "Retirer la caméra détruite", { ["pickupNow", _target] call comspec_atak_native_fnc_camAction; }, { ["dead", _target] call comspec_atak_native_fnc_camAction }],
            ["COMSPEC_ATAK_CamRename", "Renommer", { ["rename", _target] call comspec_atak_native_fnc_camAction; }, { call (missionNamespace getVariable "COMSPEC_ATAK_CamMine") && {[_player] call comspec_atak_native_fnc_hasDevice} }],
            ["COMSPEC_ATAK_CamRotL", "Orienter à gauche (-45°)", { ["rotate", _target, -45] call comspec_atak_native_fnc_camAction; }, { call (missionNamespace getVariable "COMSPEC_ATAK_CamMine") }],
            ["COMSPEC_ATAK_CamRotR", "Orienter à droite (+45°)", { ["rotate", _target, 45] call comspec_atak_native_fnc_camAction; }, { call (missionNamespace getVariable "COMSPEC_ATAK_CamMine") }]
        ];
        true
    };
    default { false };
};
