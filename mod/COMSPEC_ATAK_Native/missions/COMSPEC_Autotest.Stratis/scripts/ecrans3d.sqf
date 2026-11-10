// Écrans de salle d'opérations du mod (tools/model_toc_screens.py) : chaque modèle posé contre un mur, image TOC
// dessus (dalle 0), autres dalles peintes une à une, de nuit (salle sombre) puis de jour. Captures de face et de biais.
if !("ecrans3d" call AT_fnc_groupe) exitWith {};
["ecrans3d_salle", {
    if (["comspec_atak_native_fnc_tocScreen"] call AT_fnc_nil) exitWith {};
    private _classes = ["COMSPEC_ATAK_Screen_Wall2x2", "COMSPEC_ATAK_Screen_Wall2x2_Quad", "COMSPEC_ATAK_Screen_Wall3x2", "COMSPEC_ATAK_Screen_Wall3x2_Mixed",
        "COMSPEC_ATAK_Screen_98", "COMSPEC_ATAK_Screen_98_Stand", "COMSPEC_ATAK_Screen_65_Ceiling", "COMSPEC_ATAK_Screen_ClockStrip", "COMSPEC_ATAK_Screen_NamePlate"];
    private _manque = _classes select { !isClass (configFile >> "CfgVehicles" >> _x) };
    if (_manque isNotEqualTo []) exitWith { (format ["classes absentes : %1", _manque]) call AT_fnc_skip; };
    // Repère de la salle : 45 m au nord du joueur, écrans tournés vers le sud (direction 180) ; x vers la droite de
    // qui regarde le mur, y vers la salle.
    private _dir = 180;
    private _o0 = (AT_Base getPos [45, 0]);
    private _f = [sin _dir, cos _dir, 0];
    private _r = [cos _dir, -(sin _dir), 0];
    private _sol = getTerrainHeightASL _o0;
    private _w = {
        params ["_dx", "_dy", "_dz"];
        private _p = _o0 vectorAdd (_r vectorMultiply _dx) vectorAdd (_f vectorMultiply _dy);
        _p set [2, _sol + _dz];
        _p
    };
    private _objs = [];
    private _pose = {
        params ["_cls", "_dx", ["_dy", 0], ["_dz", 0]];
        private _p = [_dx, _dy, _dz] call _w;
        private _o = createVehicle [_cls, ASLToATL _p, [], 0, "CAN_COLLIDE"];
        _o setDir _dir;
        _o setPosWorld _p;
        _o enableSimulation false;
        _objs pushBack _o;
        _o
    };
    // Place nette : objets du terrain cachés autour de la salle (remis à la fin), mur du fond en béton du jeu.
    private _caches = (nearestTerrainObjects [[9.5, 6, 0] call _w, [], 22, false, true]) select { !isObjectHidden _x };
    { _x hideObject true; } forEach _caches;
    private _murCls = ["Land_Mil_WallBig_4m_F", "Land_CncWall4_F"] select { isClass (configFile >> "CfgVehicles" >> _x) };
    if (_murCls isNotEqualTo []) then {
        for "_i" from -1 to 7 do {
            private _p = [_i * 4 + 2, -0.75, 0] call _w;
            private _m = createVehicle [_murCls select 0, ASLToATL _p, [], 0, "CAN_COLLIDE"];
            _m setDir _dir; _m setPosASL _p; _objs pushBack _m;
        };
    };
    // Murs 2x2 accolés (largeur hors tout 2,4572 m : aucun jour), bandeau horloge au-dessus ; 3x2 mixte avec sa plaque ; 3x2 ; 98" mural ; 98" sur pied ; 65" suspendu.
    private _w22 = ["COMSPEC_ATAK_Screen_Wall2x2", 0] call _pose;
    private _w22q = ["COMSPEC_ATAK_Screen_Wall2x2_Quad", 2.4572] call _pose;
    private _clk = ["COMSPEC_ATAK_Screen_ClockStrip", 1.2286] call _pose;
    private _w32m = ["COMSPEC_ATAK_Screen_Wall3x2_Mixed", 5.8] call _pose;
    private _plt = ["COMSPEC_ATAK_Screen_NamePlate", 5.8] call _pose;
    private _w32 = ["COMSPEC_ATAK_Screen_Wall3x2", 10.0] call _pose;
    private _s98 = ["COMSPEC_ATAK_Screen_98", 13.6] call _pose;
    private _s98p = ["COMSPEC_ATAK_Screen_98_Stand", 16.4, 1.2] call _pose;
    private _s65 = ["COMSPEC_ATAK_Screen_65_Ceiling", 19.2, 1.0, 3.1] call _pose;
    { [!isNull _x && {(count getObjectTextures _x) isEqualTo (count getArray (configOf _x >> "hiddenSelections"))}, format ["%1 : dalles %2", typeOf _x, getObjectTextures _x]] call AT_fnc_check; } forEach [_w22, _w22q, _clk, _w32m, _plt, _w32, _s98, _s98p, _s65];
    // Repère : l'origine du modèle est bien au sol, contre le mur.
    (format ["2x2 : posWorld %1, sol %2, boîte %3", getPosWorld _w22, _sol, boundingBoxReal _w22]) call AT_fnc_info;
    // Nuit sans lune, couvert : salle sombre.
    private _date = date;
    setDate [2035, 6, 4, 23, 30];
    0 setOvercast 1; 0 setFog 0; forceWeatherChange;
    // Image TOC sur la dalle 0 de chaque écran 16:9.
    { ["add", [_x, format ["TEST %1", _forEachIndex + 1], "", 0, ["SPLIT", "MAP"] select (_forEachIndex mod 2), false, false]] call comspec_atak_native_fnc_tocScreen; } forEach [_w22, _w22q, _w32m, _w32, _s98, _s98p, _s65];
    // Autres dalles : une image opaque numérotée par dalle (index de sélection), chacune garde sa matière d'écran.
    private _num = { params ["_i", "_c"]; format ["#(rgb,1024,512,3)text(1,1,""PuristaBold"",0.3,""%1"",""#ffffff"",""DALLE %2"")", _c, _i + 1] };
    private _coul = ["#1d4f7a", "#7a3a1d", "#2f6b2f", "#6b2f6b", "#7a6a1d", "#1d6b6b"];
    private _peint = {
        params ["_o", "_i", "_t"];
        ["paint", [_o, _i, _t]] call comspec_atak_native_fnc_tocScreen;
        ["surface", [_o, _i, true]] call comspec_atak_native_fnc_tocScreen;
    };
    { [_w22q, _x, [_x, _coul select _x] call _num] call _peint; } forEach [1, 2, 3];
    { [_w32, _x, [_x, _coul select _x] call _num] call _peint; } forEach [1, 2, 3, 4, 5];
    { [_w32m, _x, [_x, _coul select _x] call _num] call _peint; } forEach [1, 2];
    [_clk, 0, "#(rgb,1024,256,3)text(1,1,""PuristaBold"",0.4,""#000000"",""#ff2a10"",""23:30:00Z"")"] call _peint;
    [_plt, 0, "#(rgb,1024,256,3)text(1,1,""PuristaBold"",0.45,""#101214"",""#e8e8e0"",""ARCHANGEL"")"] call _peint;
    // Attente des images TOC (une texture UI à la fois).
    private _toc = [_w22, _w22q, _w32m, _w32, _s98, _s98p, _s65];
    [{ (_toc findIf { (_x getVariable ["COMSPEC_ATAK_TocDraws", 0]) < 1 }) < 0 }, 90] call AT_fnc_wait;
    { [(_x getVariable ["COMSPEC_ATAK_TocDraws", 0]) > 0, format ["%1 : image TOC jamais dessinée", typeOf _x]] call AT_fnc_check; } forEach _toc;
    // Dalle 0 du mur 3x2 restée allumée après la préparation des dalles 1 à 5 (une préparation par dalle).
    private _mats = getObjectMaterials _w32;
    [((_mats param [0, ""]) find "comspec_screen_") >= 0 && {((_mats param [5, ""]) find "comspec_screen_") >= 0}, format ["matières du 3x2 : %1", _mats]] call AT_fnc_check;
    private _cam = "camera" camCreate (ASLToAGL ([1.2, 8, 1.7] call _w));
    _cam cameraEffect ["internal", "back"];
    showCinemaBorder false;
    private _vue = {
        params ["_nom", "_de", "_vers", ["_fov", 0.7]];
        _cam camPreparePos (ASLToAGL (_de call _w));
        _cam camPrepareTarget (ASLToAGL (_vers call _w));
        _cam camPrepareFOV _fov;
        _cam camCommitPrepared 0;
        uiSleep 2.5;
        [_nom] call AT_fnc_shot;
    };
    // Nuit.
    ["nuit_ensemble", [9.5, 15, 1.8], [9.5, 0, 1.6], 0.9] call _vue;
    ["nuit_2x2_accoles", [2.4, 4.6, 1.75], [1.23, 0, 1.75]] call _vue;
    ["nuit_2x2_biais", [-1.2, 2.2, 1.6], [1.8, 0, 1.6]] call _vue;
    ["nuit_jointure_proche", [2.1, 0.9, 1.75], [2.45, 0, 1.6], 0.45] call _vue;
    ["nuit_3x2_mixte", [5.8, 5.5, 1.8], [5.8, 0, 1.8]] call _vue;
    ["nuit_3x2", [10.0, 5.5, 1.7], [10.0, 0, 1.6]] call _vue;
    ["nuit_98_mural_et_pied", [15.0, 6.5, 1.7], [15.0, 0.5, 1.5]] call _vue;
    ["nuit_98_pied_biais", [18.2, 3.6, 1.2], [16.4, 1.2, 1.2]] call _vue;
    ["nuit_65_suspendu", [18.4, 3.6, 1.3], [19.2, 1.0, 2.3]] call _vue;
    ["nuit_pied_dos", [17.6, -0.2, 1.4], [16.4, 1.4, 1.3], 0.8] call _vue;
    // Jour.
    setDate [2035, 6, 4, 12, 0];
    0 setOvercast 0.2; forceWeatherChange;
    uiSleep 3;
    ["jour_ensemble", [9.5, 15, 1.8], [9.5, 0, 1.6], 0.9] call _vue;
    ["jour_2x2_accoles", [2.4, 4.6, 1.75], [1.23, 0, 1.75]] call _vue;
    ["jour_98_pied_biais", [18.2, 3.6, 1.2], [16.4, 1.2, 1.2]] call _vue;
    ["jour_3x2_mixte", [5.8, 5.5, 1.8], [5.8, 0, 1.8]] call _vue;
    ["jour_3x2", [10.0, 5.5, 1.7], [10.0, 0, 1.6]] call _vue;
    ["jour_65_suspendu", [18.0, 4.0, 1.5], [19.2, 1.0, 2.2]] call _vue;
    ["jour_pied_dos", [18.0, -0.3, 1.5], [16.4, 1.4, 1.3], 0.8] call _vue;
    ["jour_bord_proche", [-0.9, 0.7, 2.0], [-1.25, 0, 2.2], 0.5] call _vue;
    "Écrans 3D : bords fins, proportions, image lisible de nuit et de jour, aucun jour entre les deux 2x2" call AT_fnc_visuel;
    _cam cameraEffect ["terminate", "back"];
    camDestroy _cam;
    { ["remove", _x] call comspec_atak_native_fnc_tocScreen; } forEach _toc;
    uiSleep 1;
    { deleteVehicle _x; } forEach _objs;
    { _x hideObject false; } forEach _caches;
    setDate _date;
    0 setOvercast 0; forceWeatherChange;
    call AT_fnc_done;
}, 400] call AT_fnc_run;
