/*
    Vidéoprojecteurs et toiles de projection COMSPEC (demande de TTRD, 02/11) : un projecteur (COMSPEC_ATAK_Projector,
    de table ou de plafond) associé à une toile (COMSPEC_ATAK_ProjScreen, sur trépied ou murale) à moins de 15 m.
    Allumé, il projette sur la toile ce qu'on y diffuse, avec les mêmes sources que les écrans COMSPEC : diapositives
    du briefing ou leçon Academy (fn_screenCast), affichage TOC, carte, caméra en direct (fn_tocScreen, fn_tocCam).
    La toile est un écran comme les autres pour ces fonctions ; elle ne montre l'image que si un projecteur allumé et
    intact la vise, sans obstacle entre l'objectif et la toile (personne, mur, véhicule) : sinon toile blanche.
    Enroulée, la toile disparaît (carter seul) et ne reçoit plus rien.

    Variables publiques (posées par le joueur qui agit, gardées pour ceux qui rejoignent) :
      projecteur COMSPEC_ATAK_Proj        [allumé, toile associée (objNull : aucune)]
      toile      COMSPEC_ATAK_ProjRolled  toile enroulée
    Variables locales (chaque poste, contrôle toutes les 2 s, seulement à moins de 160 m) :
      toile      COMSPEC_ATAK_ProjLit     éclairée (lue par fn_tocScreen, fn_screenCast, fn_tocCam)
      projecteur COMSPEC_ATAK_ProjFx      [lueur de l'objectif, faisceau, toile du faisceau] (lumières locales, à moins de 40 m)
    Rien par image : tout se décide dans « tick » (2 s) et à chaque action.

    Modes :
      ["power", [projecteur, allumé]]   allume / éteint (sans toile valable : la plus proche devant l'objectif)
      ["link", [projecteur, toile]]     associe une toile
      ["roll", [toile, enroulée]]       enroule / déroule la toile
      ["candidates", projecteur]        [[toile, distance, alignement]...] à moins de 15 m en ligne de vue, devant d'abord
      ["lit", [projecteur, toile]]      la toile reçoit l'image de ce projecteur (contrôle local)
      ["tick"]                          client, toutes les 2 s (XEH_postInitClient)
      ["fx", [projecteur, allumé, toile éclairée, œil]]   lumières locales
      ["aceInit"]                       menus ACE (ou molette sans ACE) des projecteurs et des toiles
    Exemple de mission : ["power", [projo1, true]] call comspec_atak_native_fnc_projector;
*/
params [["_mode", "tick"], ["_a", objNull]];
private _fn = comspec_atak_native_fnc_projector;
private _range = 15;
// Objectif : position ASL et axe optique (points mémoire objectif / objectif_dir du modèle).
private _lens = {
    params ["_p"];
    private _m0 = _p selectionPosition ["objectif", "Memory"];
    private _m1 = _p selectionPosition ["objectif_dir", "Memory"];
    if (_m0 isEqualTo [0, 0, 0]) then { _m0 = [0, 0.16, 0.06]; _m1 = [0, 1.16, 0.06]; };
    private _o = _p modelToWorldWorld _m0;
    [_o, vectorNormalized ((_p modelToWorldWorld _m1) vectorDiff _o), _m0]
};
// Centre de l'image sur la toile (point mémoire screen_c).
private _center = {
    params ["_t"];
    private _m = _t selectionPosition ["screen_c", "Memory"];
    if (_m isEqualTo [0, 0, 0]) exitWith { (getPosASL _t) vectorAdd [0, 0, 1.4] };
    _t modelToWorldWorld _m
};
private _canvasTex = {
    params ["_t"];
    (getArray (configOf _t >> "hiddenSelectionsTextures")) param [0, "#(argb,8,8,3)color(0.93,0.93,0.91,1,co)"]
};
private _say = {
    params ["_txt", ["_t", 3]];
    if (hasInterface) then { ["INFO", _txt, _t, 20] call comspec_atak_native_fnc_notify; };
};

switch (_mode) do {
    case "candidates": {
        private _p = _a;
        if (isNull _p) exitWith { [] };
        ([_p] call _lens) params ["_o", "_dir"];
        private _out = [];
        {
            private _c = [_x] call _center;
            private _d = _o distance _c;
            if (_d <= _range && {alive _x} && {(lineIntersectsSurfaces [_o, _c, _p, _x, true, 1, "VIEW", "FIRE"]) isEqualTo []}) then {
                _out pushBack [_x, _d, _dir vectorDotProduct (vectorNormalized (_c vectorDiff _o))];
            };
        } forEach (nearestObjects [_p, ["COMSPEC_ATAK_ProjScreen_Base"], _range + 3]);
        // Devant l'objectif d'abord (cône de ± 53°), puis les plus proches.
        [_out, [], { (_x select 1) + ([0, 100] select ((_x select 2) < 0.6)) }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "lit": {
        _a params [["_p", objNull], ["_t", objNull]];
        if (isNull _p || {isNull _t} || {!alive _p} || {(damage _p) > 0.9} || {!alive _t}) exitWith { false };
        (_p getVariable ["COMSPEC_ATAK_Proj", []]) params [["_on", false], ["_lt", objNull]];
        if (!_on || {_lt isNotEqualTo _t} || {_t getVariable ["COMSPEC_ATAK_ProjRolled", false]}) exitWith { false };
        ([_p] call _lens) params ["_o", "_dir"];
        private _c = [_t] call _center;
        if ((_o distance _c) > _range) exitWith { false };
        // Toile devant l'objectif (projecteur tourné ailleurs : rien), puis ligne de vue libre.
        if ((_dir vectorDotProduct (vectorNormalized (_c vectorDiff _o))) < 0.6) exitWith { false };
        (lineIntersectsSurfaces [_o, _c, _p, _t, true, 1, "VIEW", "FIRE"]) isEqualTo []
    };
    case "power": {
        _a params [["_p", objNull], ["_on", true]];
        if (isNull _p) exitWith { false };
        if (_on && {!alive _p || {(damage _p) > 0.9}}) exitWith { ["Projecteur hors d'usage"] call _say; false };
        (_p getVariable ["COMSPEC_ATAK_Proj", []]) params [["_was", false], ["_t", objNull]];
        if (_on && {isNull _t || {!alive _t} || {(_t distance _p) > _range + 3}}) then {
            // Association automatique : la toile la plus proche devant l'objectif, en ligne de vue.
            private _c = (["candidates", _p] call _fn) select { (_x select 2) >= 0.6 };
            _t = (_c param [0, []]) param [0, objNull];
        };
        _p setVariable ["COMSPEC_ATAK_Proj", [_on, _t], true];
        if (!_on) then { ["Projecteur éteint", 2] call _say; } else {
            if (isNull _t) then {
                ["Projecteur allumé : aucune toile en vue à moins de 15 m (Projeter sur…)", 4] call _say;
            } else {
                [format ["Projecteur allumé : image sur la toile à %1 m", round (_p distance _t)], 3] call _say;
            };
        };
        if (hasInterface) then { ["tick"] call _fn; };
        true
    };
    case "link": {
        _a params [["_p", objNull], ["_t", objNull]];
        if (isNull _p) exitWith { false };
        (_p getVariable ["COMSPEC_ATAK_Proj", []]) params [["_on", false]];
        _p setVariable ["COMSPEC_ATAK_Proj", [_on, _t], true];
        if (!isNull _t) then {
            [[format ["Projecteur associé à la toile à %1 m", round (_p distance _t)], format ["Projecteur associé à la toile à %1 m (éteint : Allumer)", round (_p distance _t)]] select !_on, 3] call _say;
        };
        if (hasInterface) then { ["tick"] call _fn; };
        true
    };
    case "roll": {
        _a params [["_t", objNull], ["_r", true]];
        if (isNull _t) exitWith { false };
        _t setVariable ["COMSPEC_ATAK_ProjRolled", _r, true];
        [["Toile déroulée", "Toile enroulée"] select _r, 2] call _say;
        if (hasInterface) then { ["tick"] call _fn; };
        true
    };
    case "tick": {
        if (!hasInterface) exitWith { false };
        private _eye = positionCameraToWorld [0, 0, 0];
        private _projs = nearestObjects [_eye, ["COMSPEC_ATAK_Projector_Base"], 160];
        private _litList = [];
        {
            private _p = _x;
            (_p getVariable ["COMSPEC_ATAK_Proj", []]) params [["_on", false], ["_t", objNull]];
            private _ok = ["lit", [_p, _t]] call _fn;
            if (_ok) then { _litList pushBackUnique _t; };
            // Objectif lumineux dès que le projecteur est allumé et intact (même sans toile).
            private _glow = _on && {alive _p} && {(damage _p) <= 0.9};
            if ((_p getVariable ["COMSPEC_ATAK_ProjGlow", false]) isNotEqualTo _glow) then {
                _p setVariable ["COMSPEC_ATAK_ProjGlow", _glow];
                _p setObjectTexture [0, ["#(argb,8,8,3)color(0.05,0.06,0.08,1,co)", "#(argb,8,8,3)color(1,0.93,0.78,1,co)"] select _glow];
                _p setObjectMaterial [0, "\z\comspec_atak_native\addons\items\data\" + (["comspec_lens.rvmat", "comspec_lens_on.rvmat"] select _glow)];
            };
            ["fx", [_p, _glow, [objNull, _t] select _ok, _eye]] call _fn;
            if (isNil "ace_interact_menu_fnc_createAction") then { ["wheel", _p] call _fn; };
        } forEach _projs;
        // Lumières des projecteurs sortis du rayon ou supprimés : retirées.
        private _fxOn = (missionNamespace getVariable ["COMSPEC_ATAK_ProjFxOn", []]) select {
            _x params ["_p", "_lights"];
            private _keep = !isNull _p && {_p in _projs} && {(_p getVariable ["COMSPEC_ATAK_ProjFx", []]) isNotEqualTo []};
            if (!_keep) then { { deleteVehicle _x; } forEach _lights; if (!isNull _p) then { _p setVariable ["COMSPEC_ATAK_ProjFx", []]; }; };
            _keep
        };
        missionNamespace setVariable ["COMSPEC_ATAK_ProjFxOn", _fxOn];
        // Toiles : éclairée ou non (lu par les écrans), texture de base (blanche, vide si enroulée).
        {
            private _t = _x;
            private _on = _t in _litList;
            if ((_t getVariable ["COMSPEC_ATAK_ProjLit", false]) isNotEqualTo _on) then {
                _t setVariable ["COMSPEC_ATAK_ProjLit", _on];
                // Écran TOC : image tout de suite (sans attendre les 2 s de rafraîchissement).
                _t setVariable ["COMSPEC_ATAK_TocAt", -10];
            };
            private _base = [[_t] call _canvasTex, ""] select (_t getVariable ["COMSPEC_ATAK_ProjRolled", false]);
            // Texture d'origine remise par fn_tocScreen / fn_screenCast / fn_tocCam quand l'image s'en va.
            _t setVariable ["COMSPEC_ATAK_TocOrig", _base];
            _t setVariable ["COMSPEC_ATAK_ScreenOrig", _base];
            if (!_on && {(_t getVariable ["COMSPEC_ATAK_ProjBase", "?"]) isNotEqualTo _base}) then {
                _t setVariable ["COMSPEC_ATAK_ProjBase", _base];
                _t setObjectTexture [0, _base];
            };
            if (_on) then { _t setVariable ["COMSPEC_ATAK_ProjBase", "?"]; };
            if (isNil "ace_interact_menu_fnc_createAction") then { ["wheel", _t] call _fn; };
        } forEach (nearestObjects [_eye, ["COMSPEC_ATAK_ProjScreen_Base"], 160]);
        true
    };
    case "fx": {
        // Lueur chaude sur l'objectif (allumé, à moins de 40 m) ; faisceau faible vers la toile, de nuit seulement.
        _a params ["_p", "_glow", "_t", "_eye"];
        private _fx = _p getVariable ["COMSPEC_ATAK_ProjFx", []];
        _fx params [["_pt", objNull], ["_beam", objNull], ["_ft", objNull]];
        if (!_glow || {(_eye distance _p) > 40}) exitWith {
            if (_fx isNotEqualTo []) then { deleteVehicle _pt; deleteVehicle _beam; _p setVariable ["COMSPEC_ATAK_ProjFx", []]; };
            false
        };
        ([_p] call _lens) params ["_o", "", "_m0"];
        if (isNull _pt) then {
            _pt = "#lightpoint" createVehicleLocal (ASLToAGL _o);
            _pt setLightColor [1, 0.8, 0.55];
            _pt setLightAmbient [0.06, 0.045, 0.03];
            _pt setLightIntensity 6;
            _pt setLightAttenuation [0.05, 4, 4, 0, 0.3, 1.2];
            _pt setLightUseFlare true;
            _pt setLightFlareSize 0.18;
            _pt setLightFlareMaxDistance 40;
            _pt setLightDayLight true;
            _pt attachTo [_p, _m0 vectorAdd [0, 0.015, 0]];
        };
        private _want = !isNull _t && {sunOrMoon < 0.5};
        if (!_want || {_ft isNotEqualTo _t}) then { deleteVehicle _beam; _beam = objNull; };
        if (_want) then {
            private _c = [_t] call _center;
            private _d = (_o distance _c) max 0.5;
            if (isNull _beam) then {
                _beam = "#lightreflector" createVehicleLocal (ASLToAGL _o);
                _beam setLightColor [1, 0.96, 0.9];
                _beam setLightAmbient [0, 0, 0];
                _beam setLightIntensity 150;
                _beam setLightAttenuation [0, 0, 0, 1, _range, _range + 2];
                _beam setLightDayLight false;
                _beam attachTo [_p, _m0 vectorAdd [0, 0.01, 0]];
            };
            // Cône sur la diagonale de la toile (2,3 m), orienté de l'objectif vers son centre (repère du projecteur).
            private _cone = 2 * (atan (1.15 / _d));
            // setLightConeParams (Arma 2.06) est inconnue de l'analyseur de HEMTT : compilée une fois au premier faisceau.
            private _coneFn = missionNamespace getVariable "COMSPEC_ATAK_ProjConeFn";
            if (isNil "_coneFn") then {
                _coneFn = compile "(_this select 0) setLightConeParams [_this select 1, _this select 2, 1]";
                missionNamespace setVariable ["COMSPEC_ATAK_ProjConeFn", _coneFn];
            };
            [_beam, _cone, _cone * 0.8] call _coneFn;
            private _dirM = vectorNormalized (_p vectorWorldToModel ((ASLToAGL _c) vectorDiff (ASLToAGL _o)));
            private _side = _dirM vectorCrossProduct [0, 0, 1];
            if ((vectorMagnitude _side) < 0.01) then { _side = [1, 0, 0]; };
            _beam setVectorDirAndUp [_dirM, vectorNormalized (_side vectorCrossProduct _dirM)];
        };
        _p setVariable ["COMSPEC_ATAK_ProjFx", [_pt, _beam, [objNull, _t] select _want]];
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_ProjFxOn", []];
        private _i = _list findIf { (_x select 0) isEqualTo _p };
        if (_i < 0) then { _list pushBack [_p, [_pt, _beam]]; } else { _list set [_i, [_p, [_pt, _beam]]]; };
        missionNamespace setVariable ["COMSPEC_ATAK_ProjFxOn", _list];
        true
    };
    case "aceInit": {
        if (!hasInterface) exitWith { false };
        if (missionNamespace getVariable ["COMSPEC_ATAK_ProjAceInit", false]) exitWith { true };
        missionNamespace setVariable ["COMSPEC_ATAK_ProjAceInit", true];
        if (isNil "ace_interact_menu_fnc_createAction") exitWith { false };
        // Mêmes sous-menus que l'écran COMSPEC (Diffusion, Affichage TOC, Carte), appliqués à la toile associée.
        ["aceInit"] call comspec_atak_native_fnc_tocScreen;
        private _kids = {
            private _p = _target;
            (_p getVariable ["COMSPEC_ATAK_Proj", []]) params [["_on", false], ["_t", objNull]];
            private _out = [[["COMSPEC_ATAK_ProjPower", ["Allumer", "Éteindre"] select _on, "", {
                ["power", [_target, !((_target getVariable ["COMSPEC_ATAK_Proj", []]) param [0, false])]] call comspec_atak_native_fnc_projector;
            }, { true }] call comspec_atak_native_fnc_aceAction, [], _p]];
            private _sub = [];
            {
                _x params ["_tt", "_d"];
                _sub pushBack [[format ["COMSPEC_ATAK_ProjTo%1", _forEachIndex], format [["Toile à %1 m", "Toile à %1 m (associée)"] select (_tt isEqualTo _t), round _d], "", {
                    ["link", [_target, _this select 2]] call comspec_atak_native_fnc_projector;
                }, { true }, {}, _tt] call comspec_atak_native_fnc_aceAction, [], _p];
            } forEach ((["candidates", _p] call comspec_atak_native_fnc_projector) select [0, 8]);
            if (_sub isEqualTo []) then {
                _sub pushBack [["COMSPEC_ATAK_ProjToNone", "Aucune toile à moins de 15 m en vue", "", {}, { true }] call comspec_atak_native_fnc_aceAction, [], _p];
            };
            _out pushBack [["COMSPEC_ATAK_ProjTo", "Projeter sur…", "", {}, { true }] call comspec_atak_native_fnc_aceAction, _sub, _p];
            if (!isNull _t && {alive _t}) then {
                private _target = _t;
                _out append (call (missionNamespace getVariable ["COMSPEC_ATAK_WallKids", { [] }]));
            };
            _out
        };
        private _root = ["COMSPEC_ATAK_Proj", "Projecteur COMSPEC", "\z\comspec_atak_native\addons\main\data\app_briefing.paa", {}, {
            alive _target && {(_player distance _target) < 5}
        }, _kids, [], [0, 0, 0], 4] call comspec_atak_native_fnc_aceAction;
        { [_x, 0, ["ACE_MainActions"], _root] call ace_interact_menu_fnc_addActionToClass; } forEach ["COMSPEC_ATAK_Projector", "COMSPEC_ATAK_Projector_Ceiling"];
        private _roll = ["COMSPEC_ATAK_ProjRoll", "Enrouler la toile", "", { ["roll", [_target, true]] call comspec_atak_native_fnc_projector; }, {
            !(_target getVariable ["COMSPEC_ATAK_ProjRolled", false])
        }] call comspec_atak_native_fnc_aceAction;
        private _unroll = ["COMSPEC_ATAK_ProjUnroll", "Dérouler la toile", "", { ["roll", [_target, false]] call comspec_atak_native_fnc_projector; }, {
            _target getVariable ["COMSPEC_ATAK_ProjRolled", false]
        }] call comspec_atak_native_fnc_aceAction;
        {
            [_x, 0, ["ACE_MainActions"], _roll] call ace_interact_menu_fnc_addActionToClass;
            [_x, 0, ["ACE_MainActions"], _unroll] call ace_interact_menu_fnc_addActionToClass;
        } forEach ["COMSPEC_ATAK_ProjScreen", "COMSPEC_ATAK_ProjScreen_Wall"];
        true
    };
    case "wheel": {
        // Sans ACE : actions de la molette, une fois par objet.
        private _o = _a;
        if (isNull _o || {_o getVariable ["COMSPEC_ATAK_ProjWheel", false]}) exitWith { false };
        _o setVariable ["COMSPEC_ATAK_ProjWheel", true];
        private _add = {
            params ["_lbl", "_code", "_cond", "_prio"];
            _o addAction [format ["<t color='#7fd0b0'>%1</t>", _lbl call comspec_atak_native_fnc_tr], _code, nil, _prio, false, true, "", _cond, 4];
        };
        if (_o isKindOf "COMSPEC_ATAK_ProjScreen_Base") exitWith {
            ["Toile : enrouler / dérouler", { ["roll", [_this select 0, !((_this select 0) getVariable ["COMSPEC_ATAK_ProjRolled", false])]] call comspec_atak_native_fnc_projector; }, "true", 1.3] call _add;
            true
        };
        ["Projecteur : allumer / éteindre", { ["power", [_this select 0, !(((_this select 0) getVariable ["COMSPEC_ATAK_Proj", []]) param [0, false])]] call comspec_atak_native_fnc_projector; }, "alive _target", 1.3] call _add;
        private _hasT = "private _t = (_target getVariable ['COMSPEC_ATAK_Proj', []]) param [1, objNull]; !isNull _t";
        ["Projecteur : diffuser mes diapositives", { ["castOn", ((_this select 0) getVariable ["COMSPEC_ATAK_Proj", []]) param [1, objNull]] call comspec_atak_native_fnc_screenCast; }, _hasT, 1.29] call _add;
        ["Projecteur : afficher le réseau TOC", { ["ensure", [((_this select 0) getVariable ["COMSPEC_ATAK_Proj", []]) param [1, objNull], "SPLIT"]] call comspec_atak_native_fnc_tocScreen; }, _hasT, 1.28] call _add;
        true
    };
    default { false };
};
