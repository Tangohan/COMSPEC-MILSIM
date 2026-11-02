/*
    Vidéoprojecteur et tableaux de projection COMSPEC (demande de TTRD, 02/11) : le vidéoprojecteur du jeu
    (COMSPEC_ATAK_Projector, modèle Land_Projector_01_F) projette sur un tableau blanc du jeu (COMSPEC_ATAK_ProjScreen sur
    trépied, COMSPEC_ATAK_ProjScreen_Wall mural) à moins de 15 m. Allumé, il montre sur le tableau ce qu'on y diffuse,
    avec les mêmes sources que les écrans COMSPEC : diapositives du briefing ou leçon Academy (fn_screenCast),
    affichage TOC, carte, caméra en direct (fn_tocScreen, fn_tocCam). Le tableau est un écran comme les autres pour ces
    fonctions ; l'image va sur une surface 16:9 (COMSPEC_ATAK_ProjSurface) que chaque joueur pose devant la face blanche,
    du côté du projecteur, mesurée une fois par des rayons sur la géométrie du tableau (taille, hauteur, inclinaison).
    L'image n'apparaît que si le projecteur est allumé, intact, tourné vers le tableau (axe de l'objectif, ± 53°) et que
    rien ne coupe la ligne objectif - tableau (personne, mur, véhicule) : sinon tableau blanc.

    Variables publiques (posées par le joueur qui agit, gardées pour ceux qui rejoignent) :
      projecteur COMSPEC_ATAK_Proj        [allumé, tableau associé (objNull : aucun)]
    Variables locales (chaque poste, contrôle toutes les 2 s, seulement à moins de 160 m) :
      tableau    COMSPEC_ATAK_ProjLit     éclairé (lu par fn_tocScreen, fn_screenCast, fn_tocCam)
      tableau    COMSPEC_ATAK_ProjPlane   surface de projection posée devant lui ; ProjFace1 / ProjFace-1 : mesures
      projecteur COMSPEC_ATAK_ProjFx      [lueur de l'objectif, faisceau, tableau du faisceau] (lumières, à moins de 40 m)
    Rien par image : tout se décide dans « tick » (2 s) et à chaque action.

    Modes :
      ["power", [projecteur, allumé]]   allume / éteint (sans tableau valable : le plus proche dans l'axe de l'objectif)
      ["link", [projecteur, tableau]]   associe un tableau
      ["candidates", projecteur]        [[tableau, distance, alignement]...] à moins de 15 m en ligne de vue, dans l'axe d'abord
      ["lit", [projecteur, tableau]]    le tableau reçoit l'image de ce projecteur (contrôle local)
      ["face", [tableau, côté]]         face blanche côté ±Y du modèle : [centre, hauteur de l'image, pente] (mémorisée)
      ["tick"]                          client, toutes les 2 s (XEH_postInitClient)
      ["fx", [projecteur, allumé, tableau éclairé, œil]]   lumières locales
      ["aceInit"]                       menus ACE (ou molette sans ACE) des projecteurs
    Exemple de mission : ["power", [projo1, true]] call comspec_atak_native_fnc_projector;
*/
params [["_mode", "tick"], ["_a", objNull]];
private _fn = comspec_atak_native_fnc_projector;
private _range = 15;
private _boards = ["COMSPEC_ATAK_ProjScreen", "COMSPEC_ATAK_ProjScreen_Wall"];
// Objectif : le modèle du jeu n'a pas de point mémoire ; on prend le milieu de la face (avant ou arrière, selon le côté
// tourné vers la cible) de sa boîte englobante, et l'axe du boîtier. Renvoie [position ASL, direction, position modèle].
private _axis = {
    params ["_p", "_tgt"];
    (boundingBoxReal _p) params ["_mn", "_mx"];
    private _s = [1, -1] select (((_p worldToModel (ASLToAGL _tgt)) select 1) < 0);
    private _m = [((_mn select 0) + (_mx select 0)) / 2, ([_mn, _mx] select (_s > 0)) select 1, ((_mn select 2) + (_mx select 2)) / 2];
    _m set [1, (_m select 1) + _s * 0.01];
    private _o = _p modelToWorldWorld _m;
    [_o, vectorNormalized ((_p modelToWorldWorld (_m vectorAdd [0, _s, 0])) vectorDiff _o), _m]
};
// Côté du tableau tourné vers un point (+1 : +Y du modèle).
private _sideTo = {
    params ["_b", "_posASL"];
    [1, -1] select (((_b worldToModel (ASLToAGL _posASL)) select 1) < 0)
};
// Centre de l'image sur le tableau, côté tourné vers le projecteur.
private _center = {
    params ["_b", "_p"];
    private _pl = _b getVariable ["COMSPEC_ATAK_ProjPlane", objNull];
    if (!isNull _pl) exitWith { _pl modelToWorldWorld [0, 0, 0] };
    (["face", [_b, [_b, getPosASL _p] call _sideTo]] call _fn) params [["_c", [0, 0, 1]]];
    _b modelToWorldWorld _c
};
private _say = {
    params ["_txt", ["_t", 3]];
    if (hasInterface) then { ["INFO", _txt, _t, 20] call comspec_atak_native_fnc_notify; };
};

switch (_mode) do {
    case "face": {
        // Face blanche du tableau mesurée par des rayons (géométrie de vue / de tir), du côté _s : colonne au milieu
        // (hauteur, pente des tableaux sur chevalet), puis ligne à mi-hauteur (largeur). Image 16:9 dans 92 % x 90 %.
        _a params ["_b", ["_s", 1]];
        private _key = format ["COMSPEC_ATAK_ProjFace%1", _s];
        private _f = _b getVariable [_key, []];
        if (_f isNotEqualTo []) exitWith { _f };
        (boundingBoxReal _b) params ["_mn", "_mx"];
        private _far = ((abs (_mn select 1)) max (abs (_mx select 1))) + 0.5;
        private _hit = {
            params ["_px", "_pz"];
            private _r = (lineIntersectsSurfaces [_b modelToWorldWorld [_px, _s * _far, _pz], _b modelToWorldWorld [_px, -_s * _far, _pz], objNull, objNull, true, -1, "VIEW", "FIRE"])
                select { (_x select 2) isEqualTo _b || {(_x select 3) isEqualTo _b} };
            if (_r isEqualTo []) exitWith { [] };
            [(_b worldToModel (ASLToAGL ((_r select 0) select 0))) select 1]
        };
        private _xc = ((_mn select 0) + (_mx select 0)) / 2;
        private _z0 = _mn select 2; private _z1 = _mx select 2;
        private _col = [];
        for "_i" from 0 to 40 do {
            private _z = _z0 + (_z1 - _z0) * _i / 40;
            if (_z > _z0 + 0.3 * (_z1 - _z0)) then {
                private _h = [_xc, _z] call _hit;
                if (_h isNotEqualTo []) then { _col pushBack [_z, _h select 0]; };
            };
        };
        private _ys = _col apply { _x select 1 };
        _ys sort true;
        private _med = _ys param [floor ((count _ys) / 2), 0];
        _col = _col select { abs ((_x select 1) - _med) < 0.1 };
        // Droite y = a + b z (moindres carrés), puis écarts de plus de 2 cm écartés (pieds, plumier).
        private _fit = {
            private _n = count _col;
            private _sz = 0; private _sy = 0; private _szz = 0; private _szy = 0;
            { _sz = _sz + (_x select 0); _sy = _sy + (_x select 1); _szz = _szz + (_x select 0) ^ 2; _szy = _szy + (_x select 0) * (_x select 1); } forEach _col;
            private _den = _n * _szz - _sz ^ 2;
            private _bb = [0, (_n * _szy - _sz * _sy) / _den] select (abs _den > 1e-6);
            [(_sy - _bb * _sz) / _n, _bb]
        };
        private _res = [];
        if ((count _col) >= 4) then {
            ([] call _fit) params ["_ca", "_cb"];
            _col = _col select { abs ((_x select 1) - (_ca + _cb * (_x select 0))) < 0.02 };
            if ((count _col) >= 4) then {
                ([] call _fit) params ["_fa", "_fb"];
                private _zs = _col apply { _x select 0 };
                private _zb0 = selectMin _zs; private _zb1 = selectMax _zs;
                private _zc = (_zb0 + _zb1) / 2;
                private _xs = [];
                for "_i" from 0 to 40 do {
                    private _x0 = (_mn select 0) + ((_mx select 0) - (_mn select 0)) * _i / 40;
                    private _h = [_x0, _zc] call _hit;
                    if (_h isNotEqualTo [] && {abs ((_h select 0) - (_fa + _fb * _zc)) < 0.03}) then { _xs pushBack _x0; };
                };
                if ((count _xs) >= 3) then {
                    private _w = ((selectMax _xs) - (selectMin _xs)) * 0.92;
                    private _hh = (_zb1 - _zb0) * (sqrt (1 + _fb ^ 2)) * 0.9;
                    private _h = _hh min (_w * 9 / 16);
                    private _n = vectorNormalized [0, _s, -_s * _fb];
                    _res = [[((selectMax _xs) + (selectMin _xs)) / 2, _fa + _fb * _zc, _zc] vectorAdd (_n vectorMultiply 0.006), _h, _fb];
                };
            };
        };
        if (_res isEqualTo []) then {
            // Mesure impossible : boîte englobante (partie haute), face plane.
            private _h = (((_mx select 0) - (_mn select 0)) * 0.9 * 9 / 16) min (((_z1 - _z0) * 0.45) * 0.9);
            _res = [[_xc, ([_mn, _mx] select (_s > 0)) select 1, _z1 - (_z1 - _z0) * 0.25] vectorAdd [0, _s * 0.006, 0], _h, 0];
            ["WARNING", "TOC", format ["Projection : face de %1 non mesurée, boîte englobante utilisée", typeOf _b]] call comspec_atak_native_fnc_log;
        };
        _b setVariable [_key, _res];
        _res
    };
    case "candidates": {
        private _p = _a;
        if (isNull _p) exitWith { [] };
        private _out = [];
        {
            private _c = [_x, _p] call _center;
            ([_p, _c] call _axis) params ["_o", "_dir"];
            private _d = _o distance _c;
            if (_d <= _range && {alive _x} && {(lineIntersectsSurfaces [_o, _c, _p, _x, true, 1, "VIEW", "FIRE"]) isEqualTo []}) then {
                _out pushBack [_x, _d, _dir vectorDotProduct (vectorNormalized (_c vectorDiff _o))];
            };
        } forEach (nearestObjects [_p, _boards, _range + 3]);
        // Dans l'axe de l'objectif d'abord (± 53°), puis les plus proches.
        [_out, [], { (_x select 1) + ([0, 100] select ((_x select 2) < 0.6)) }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "lit": {
        _a params [["_p", objNull], ["_t", objNull]];
        if (isNull _p || {isNull _t} || {!alive _p} || {(damage _p) > 0.9} || {!alive _t}) exitWith { false };
        (_p getVariable ["COMSPEC_ATAK_Proj", []]) params [["_on", false], ["_lt", objNull]];
        if (!_on || {_lt isNotEqualTo _t}) exitWith { false };
        private _pl = _t getVariable ["COMSPEC_ATAK_ProjPlane", objNull];
        if (isNull _pl) exitWith { false };
        private _c = _pl modelToWorldWorld [0, 0, 0];
        ([_p, _c] call _axis) params ["_o", "_dir"];
        if ((_o distance _c) > _range) exitWith { false };
        // Projecteur tourné ailleurs : rien. Puis ligne de vue libre (le tableau et le projecteur eux-mêmes exceptés).
        if ((_dir vectorDotProduct (vectorNormalized (_c vectorDiff _o))) < 0.6) exitWith { false };
        (lineIntersectsSurfaces [_o, _c, _p, _t, true, 1, "VIEW", "FIRE"]) isEqualTo []
    };
    case "power": {
        _a params [["_p", objNull], ["_on", true]];
        if (isNull _p) exitWith { false };
        if (_on && {!alive _p || {(damage _p) > 0.9}}) exitWith { ["Projecteur hors d'usage"] call _say; false };
        (_p getVariable ["COMSPEC_ATAK_Proj", []]) params [["_was", false], ["_t", objNull]];
        if (_on && {isNull _t || {!alive _t} || {(_t distance _p) > _range + 3}}) then {
            // Association automatique : le tableau le plus proche dans l'axe de l'objectif, en ligne de vue.
            private _c = (["candidates", _p] call _fn) select { (_x select 2) >= 0.6 };
            _t = (_c param [0, []]) param [0, objNull];
        };
        _p setVariable ["COMSPEC_ATAK_Proj", [_on, _t], true];
        if (!_on) then { ["Projecteur éteint", 2] call _say; } else {
            if (isNull _t) then {
                ["Projecteur allumé : aucun tableau en vue à moins de 15 m (Projeter sur…)", 4] call _say;
            } else {
                [format ["Projecteur allumé : image sur le tableau à %1 m", round (_p distance _t)], 3] call _say;
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
            [[format ["Projecteur associé au tableau à %1 m", round (_p distance _t)], format ["Projecteur associé au tableau à %1 m (éteint : Allumer)", round (_p distance _t)]] select !_on, 3] call _say;
        };
        if (hasInterface) then { ["tick"] call _fn; };
        true
    };
    case "tick": {
        if (!hasInterface) exitWith { false };
        private _eye = positionCameraToWorld [0, 0, 0];
        private _projs = nearestObjects [_eye, ["COMSPEC_ATAK_Projector"], 160];
        private _bds = nearestObjects [_eye, _boards, 160];
        // Surface de projection devant chaque tableau proche, côté du projecteur qui le vise (sinon +Y du modèle).
        private _planes = (missionNamespace getVariable ["COMSPEC_ATAK_ProjPlanes", []]) select {
            _x params ["_b", "_pl"];
            private _keep = !isNull _b && {!isNull _pl} && {_b in _bds};
            if (!_keep) then { deleteVehicle _pl; if (!isNull _b) then { _b setVariable ["COMSPEC_ATAK_ProjPlane", objNull]; }; };
            _keep
        };
        private _fresh = [];
        {
            private _b = _x;
            private _pi = _projs findIf { ((_x getVariable ["COMSPEC_ATAK_Proj", []]) param [1, objNull]) isEqualTo _b };
            private _s = if (_pi >= 0) then { [_b, getPosASL (_projs select _pi)] call _sideTo } else { _b getVariable ["COMSPEC_ATAK_ProjSide", 1] };
            private _pl = _b getVariable ["COMSPEC_ATAK_ProjPlane", objNull];
            if (isNull _pl || {(_b getVariable ["COMSPEC_ATAK_ProjSide", 0]) isNotEqualTo _s}) then {
                if (!isNull _pl) then { deleteVehicle _pl; };
                (["face", [_b, _s]] call _fn) params ["_c", "_h", "_tb"];
                _pl = "COMSPEC_ATAK_ProjSurface" createVehicleLocal [0, 0, 0];
                _pl attachTo [_b, _c];
                _pl setVectorDirAndUp [vectorNormalized [0, _s, -_s * _tb], vectorNormalized [0, _tb, 1]];
                _pl setObjectScale _h;
                _b setVariable ["COMSPEC_ATAK_ProjPlane", _pl];
                _b setVariable ["COMSPEC_ATAK_ProjSide", _s];
                _planes = _planes select { (_x select 0) isNotEqualTo _b };
                _planes pushBack [_b, _pl];
                // Nouvelle surface : l'image sera reposée au prochain passage (éclairage remis à zéro).
                _fresh pushBack _b;
            };
        } forEach _bds;
        missionNamespace setVariable ["COMSPEC_ATAK_ProjPlanes", _planes];
        private _litList = [];
        {
            private _p = _x;
            (_p getVariable ["COMSPEC_ATAK_Proj", []]) params [["_on", false], ["_t", objNull]];
            private _ok = !(_t in _fresh) && {["lit", [_p, _t]] call _fn};
            if (_ok) then { _litList pushBackUnique _t; };
            private _glow = _on && {alive _p} && {(damage _p) <= 0.9};
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
        // Tableaux : éclairés ou non (lu par les écrans) ; éteint : surface vide (tableau blanc).
        {
            private _t = _x;
            private _on = _t in _litList;
            if ((_t getVariable ["COMSPEC_ATAK_ProjLit", false]) isNotEqualTo _on) then {
                _t setVariable ["COMSPEC_ATAK_ProjLit", _on];
                // Écran TOC : image tout de suite (sans attendre les 2 s de rafraîchissement).
                _t setVariable ["COMSPEC_ATAK_TocAt", -10];
                if (!_on) then { (_t getVariable ["COMSPEC_ATAK_ProjPlane", objNull]) setObjectTexture [0, ""]; };
            };
        } forEach _bds;
        true
    };
    case "fx": {
        // Lueur chaude devant l'objectif (allumé, à moins de 40 m) ; faisceau faible vers le tableau, de nuit seulement.
        _a params ["_p", "_glow", "_t", "_eye"];
        private _fx = _p getVariable ["COMSPEC_ATAK_ProjFx", []];
        _fx params [["_pt", objNull], ["_beam", objNull], ["_ft", objNull]];
        if (!_glow || {(_eye distance _p) > 40}) exitWith {
            if (_fx isNotEqualTo []) then { deleteVehicle _pt; deleteVehicle _beam; _p setVariable ["COMSPEC_ATAK_ProjFx", []]; };
            false
        };
        (_p getVariable ["COMSPEC_ATAK_Proj", []]) params ["", ["_lt", objNull]];
        private _aim = if (isNull _lt) then { _p modelToWorldWorld [0, 5, 0] } else { [_lt, _p] call _center };
        ([_p, _aim] call _axis) params ["_o", "", "_m0"];
        if (isNull _pt || {(_pt getVariable ["COMSPEC_ATAK_ProjAt", []]) isNotEqualTo _m0}) then {
            deleteVehicle _pt;
            _pt = "#lightpoint" createVehicleLocal (ASLToAGL _o);
            _pt setLightColor [1, 0.8, 0.55];
            _pt setLightAmbient [0.06, 0.045, 0.03];
            _pt setLightIntensity 6;
            _pt setLightAttenuation [0.05, 4, 4, 0, 0.3, 1.2];
            _pt setLightUseFlare true;
            _pt setLightFlareSize 0.18;
            _pt setLightFlareMaxDistance 40;
            _pt setLightDayLight true;
            _pt attachTo [_p, _m0];
            _pt setVariable ["COMSPEC_ATAK_ProjAt", _m0];
        };
        private _want = !isNull _t && {sunOrMoon < 0.5};
        if (!_want || {_ft isNotEqualTo _t}) then { deleteVehicle _beam; _beam = objNull; };
        if (_want) then {
            private _c = [_t, _p] call _center;
            private _d = (_o distance _c) max 0.5;
            if (isNull _beam) then {
                _beam = "#lightreflector" createVehicleLocal (ASLToAGL _o);
                _beam setLightColor [1, 0.96, 0.9];
                _beam setLightAmbient [0, 0, 0];
                _beam setLightIntensity 150;
                _beam setLightAttenuation [0, 0, 0, 1, _range, _range + 2];
                _beam setLightDayLight false;
                _beam attachTo [_p, _m0];
            };
            // Cône sur la diagonale de l'image, orienté de l'objectif vers son centre (repère du projecteur).
            private _h = ((_t getVariable [format ["COMSPEC_ATAK_ProjFace%1", _t getVariable ["COMSPEC_ATAK_ProjSide", 1]], []]) param [1, 1]);
            private _cone = 2 * (atan ((_h * 1.02) / _d));
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
        // Mêmes sous-menus que l'écran COMSPEC (Diffusion, Affichage TOC, Carte), appliqués au tableau associé.
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
                _sub pushBack [[format ["COMSPEC_ATAK_ProjTo%1", _forEachIndex], format [["Tableau à %1 m", "Tableau à %1 m (associé)"] select (_tt isEqualTo _t), round _d], "", {
                    ["link", [_target, _this select 2]] call comspec_atak_native_fnc_projector;
                }, { true }, {}, _tt] call comspec_atak_native_fnc_aceAction, [], _p];
            } forEach ((["candidates", _p] call comspec_atak_native_fnc_projector) select [0, 8]);
            if (_sub isEqualTo []) then {
                _sub pushBack [["COMSPEC_ATAK_ProjToNone", "Aucun tableau à moins de 15 m en vue", "", {}, { true }] call comspec_atak_native_fnc_aceAction, [], _p];
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
        ["COMSPEC_ATAK_Projector", 0, ["ACE_MainActions"], _root] call ace_interact_menu_fnc_addActionToClass;
        true
    };
    case "wheel": {
        // Sans ACE : actions de la molette, une fois par projecteur.
        private _o = _a;
        if (isNull _o || {_o getVariable ["COMSPEC_ATAK_ProjWheel", false]}) exitWith { false };
        _o setVariable ["COMSPEC_ATAK_ProjWheel", true];
        private _add = {
            params ["_lbl", "_code", "_cond", "_prio"];
            _o addAction [format ["<t color='#7fd0b0'>%1</t>", _lbl call comspec_atak_native_fnc_tr], _code, nil, _prio, false, true, "", _cond, 4];
        };
        ["Projecteur : allumer / éteindre", { ["power", [_this select 0, !(((_this select 0) getVariable ["COMSPEC_ATAK_Proj", []]) param [0, false])]] call comspec_atak_native_fnc_projector; }, "alive _target", 1.3] call _add;
        private _hasT = "private _t = (_target getVariable ['COMSPEC_ATAK_Proj', []]) param [1, objNull]; !isNull _t";
        ["Projecteur : diffuser mes diapositives", { ["castOn", ((_this select 0) getVariable ["COMSPEC_ATAK_Proj", []]) param [1, objNull]] call comspec_atak_native_fnc_screenCast; }, _hasT, 1.29] call _add;
        ["Projecteur : afficher le réseau TOC", { ["ensure", [((_this select 0) getVariable ["COMSPEC_ATAK_Proj", []]) param [1, objNull], "SPLIT"]] call comspec_atak_native_fnc_tocScreen; }, _hasT, 1.28] call _add;
        true
    };
    default { false };
};
