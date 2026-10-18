/*
    Studio noir des photos de profil (Tinder, profils HVT / SSE, fn_datingCam) : la personne affichée est recopiée
    (tenue, équipement, visage) dans un studio local au fond et au sol noirs, loin au-dessus d'un coin de la carte,
    éclairé par deux lampes. Seul ce poste voit la copie et le studio ; rien n'est créé chez les autres joueurs.
    Réglage CBA « ATAK · Photo » : comspec_atak_native_dating_black (activé par défaut).
    Params :
      ["unit", unité] → copie à filmer (objNull : studio coupé ou copie impossible, on filme la vraie personne)
      ["stop"]        → studio et copie supprimés
*/
params [["_act", "unit"], ["_u", objNull]];
private _st = missionNamespace getVariable ["COMSPEC_ATAK_Stage", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_Stage", _st];
private _clear = {
    { deleteVehicle _x; } forEach ((_st getOrDefault ["objects", []]) + [_st getOrDefault ["clone", objNull]]);
    _st set ["objects", []];
    _st set ["clone", objNull];
    _st set ["source", objNull];
};
switch (_act) do {
    case "stop": { call _clear; true };
    case "unit": {
        if (isNull _u || {!(missionNamespace getVariable ["comspec_atak_native_dating_black", true])}) exitWith { call _clear; objNull };
        private _clone = _st getOrDefault ["clone", objNull];
        // Studio : au-dessus du coin sud-ouest de la carte, hors de toute scène (ni décor, ni joueurs, ni ciel derrière).
        private _origin = [-600, -600, 1800];
        if (isNull _clone || {(_st getOrDefault ["source", objNull]) isNotEqualTo _u}) then {
            call _clear;
            // Copie locale sans IA (createVehicleLocal) ; refusée par le moteur : on filme la vraie personne.
            _clone = (typeOf _u) createVehicleLocal [0, 0, 0];
            if (isNull _clone || {!(_clone isKindOf "CAManBase")}) exitWith { if (!isNull _clone) then { deleteVehicle _clone; }; _clone = objNull; };
            _clone setPosASL _origin;
            _clone setDir 0;
            _clone enableSimulation false;
            _clone allowDamage false;
            _st set ["clone", _clone];
            _st set ["source", _u];
            _st set ["sync", 0];
            // Fond noir : murs à double face derrière et autour, sol noir ; 10 m de large, la vue en pied tient dedans.
            private _black = "#(argb,8,8,3)color(0,0,0,1)";
            private _objs = [];
            private _plane = {
                params ["_pos", "_dir", "_up"];
                {
                    private _p = "UserTexture10m_F" createVehicleLocal [0, 0, 0];
                    _p setObjectTexture [0, _black];
                    _p setPosASL _pos;
                    _p setVectorDirAndUp [_dir vectorMultiply _x, _up];
                    _p enableSimulation false;
                    _objs pushBack _p;
                } forEach [1, -1];
            };
            // Derrière (la copie regarde le nord, la caméra est devant), sur les côtés, au sol et au-dessus.
            [_origin vectorAdd [0, -1.6, 1], [0, 1, 0], [0, 0, 1]] call _plane;
            [_origin vectorAdd [-5, 3, 1], [1, 0, 0], [0, 0, 1]] call _plane;
            [_origin vectorAdd [5, 3, 1], [1, 0, 0], [0, 0, 1]] call _plane;
            [_origin vectorAdd [0, 3, -0.02], [0, 0, 1], [0, -1, 0]] call _plane;
            [_origin vectorAdd [0, 3, 4], [0, 0, 1], [0, -1, 0]] call _plane;
            [_origin vectorAdd [0, 8, 1], [0, 1, 0], [0, 0, 1]] call _plane;
            // Lumière de face (clé) et de côté (douce), le jour comme la nuit.
            {
                _x params ["_off", "_bright", "_col"];
                private _l = "#lightpoint" createVehicleLocal [0, 0, 0];
                _l setPosASL (_origin vectorAdd _off);
                _l setLightColor _col;
                _l setLightAmbient (_col vectorMultiply 0.25);
                _l setLightBrightness _bright;
                _l setLightAttenuation [0.5, 0, 0, 1.2, 4, 6];
                _l setLightDayLight true;
                _objs pushBack _l;
            } forEach [[[0.6, 2.2, 2.1], 1.6, [1, 0.96, 0.9]], [[-1.8, 1, 1.4], 0.7, [0.85, 0.9, 1]]];
            _st set ["objects", _objs];
        };
        if (isNull _clone) exitWith { objNull };
        // Tenue et visage recopiés à l'ouverture puis toutes les 5 s (la personne peut se changer).
        if (diag_tickTime >= (_st getOrDefault ["sync", 0])) then {
            _st set ["sync", diag_tickTime + 5];
            _clone setUnitLoadout (getUnitLoadout _u);
            _clone setFace (face _u);
            _clone switchMove (["AmovPercMstpSnonWnonDnon", "AmovPercMstpSlowWrflDnon"] select ((primaryWeapon _u) isNotEqualTo ""));
            _clone setPosASL _origin;
            _clone setDir 0;
        };
        _clone
    };
    default { objNull };
};
