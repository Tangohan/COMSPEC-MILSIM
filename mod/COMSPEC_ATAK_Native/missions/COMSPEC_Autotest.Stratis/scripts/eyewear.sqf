// SSE lunettes de sport (addon eyewear) : classes, trois slots (lunettes, JVN, slot virtuel), teinte des verres, captures
// en gros plan du visage pour chaque coloris (face et profil à 45°). Groupe « eyewear » (aussi lancé avec « sse »).
if !(("eyewear" call AT_fnc_groupe) || {"sse" call AT_fnc_groupe}) exitWith {};

AT_EwIds = ["radar_white", "radar_black", "hurricane_black", "wind_rainbow", "wind_orange"];
AT_fnc_ewCat = { (([] call comspec_sse_fnc_eyewearCatalog) select 0) getOrDefault [_this, createHashMap] };

// Caméra : par défaut à 0,6 m du visage, un peu au-dessus, visant les yeux ; _az : 0 de face, 45 de trois quarts,
// 180 de dos. _cible : position ASL visée (défaut : les yeux) ; _dz : hauteur de la caméra par rapport à la cible.
AT_fnc_ewCamera = {
    params ["_u", "_az", ["_dist", 0.6], ["_dz", 0.07], ["_cible", []], ["_fov", 0.30]];
    private _tgt = if (_cible isEqualTo []) then { eyePos _u } else { _cible };
    private _d = (getDir _u) + _az;
    private _pos = _tgt vectorAdd [_dist * sin _d, _dist * cos _d, _dz];
    private _cam = missionNamespace getVariable ["AT_EwCam", objNull];
    if (isNull _cam) then {
        _cam = "camera" camCreate (ASLToAGL _pos);
        _cam cameraEffect ["internal", "back"];
        showCinemaBorder false;
        missionNamespace setVariable ["AT_EwCam", _cam];
    };
    _cam camPrepareTarget (ASLToAGL _tgt);
    _cam camPreparePos (ASLToAGL _pos);
    _cam camPrepareFov _fov;
    _cam camCommitPrepared 0;
    uiSleep 0.6;
};
// Plein corps de face et de dos (où tombe un maillage mal pondéré), caméra à 2,5 m à hauteur de poitrine.
AT_fnc_ewCorps = {
    params ["_tag"];
    private _poitrine = (eyePos player) vectorAdd [0, 0, -0.45];
    [player, 0, 2.5, 0, _poitrine, 0.55] call AT_fnc_ewCamera;
    [format ["eyewear_%1_corps", _tag]] call AT_fnc_shot;
    [player, 180, 2.5, 0, _poitrine, 0.55] call AT_fnc_ewCamera;
    [format ["eyewear_%1_dos", _tag]] call AT_fnc_shot;
};
AT_fnc_ewLog = { diag_log format ["AUTOTEST|EYEWEAR|%1|%2", AT_Nom, _this]; };
// Réglage CBA côté client (comme le menu des réglages), et la variable elle-même.
AT_fnc_ewSet = {
    params ["_nom", "_val"];
    if (!isNil "CBA_settings_fnc_set") then { [_nom, _val, 0, "client"] call CBA_settings_fnc_set; };
    missionNamespace setVariable [_nom, _val];
};
AT_fnc_ewCameraEnd = {
    private _cam = missionNamespace getVariable ["AT_EwCam", objNull];
    if (!isNull _cam) then {
        _cam cameraEffect ["terminate", "back"];
        camDestroy _cam;
    };
    missionNamespace setVariable ["AT_EwCam", objNull];
};
// Joueur immobile, posture neutre, tête droite.
AT_fnc_ewPose = {
    player switchMove (["AmovPercMstpSnonWnonDnon", "AmovPercMstpSlowWrflDnon"] select (primaryWeapon player isNotEqualTo ""));
    player setDir 0;
    uiSleep 0.8;
};

["eyewear_classes", {
    if (["comspec_sse_fnc_eyewearCatalog", "comspec_sse_fnc_eyewearWear", "comspec_sse_fnc_eyewearTint"] call AT_fnc_nil) exitWith {};
    [isClass (configFile >> "CfgPatches" >> "comspec_sse_eyewear"), "CfgPatches comspec_sse_eyewear absent"] call AT_fnc_check;
    [isClass (configFile >> "CfgVehicles" >> "COMSPEC_SSE_Box_Eyewear"), "caisse COMSPEC_SSE_Box_Eyewear absente"] call AT_fnc_check;
    {
        private _e = _x call AT_fnc_ewCat;
        [count _e > 0, format ["%1 absent du catalogue", _x]] call AT_fnc_check;
        if (count _e > 0) then {
            [isClass (configFile >> "CfgGlasses" >> (_e get "glasses")), format ["CfgGlasses %1 absente", _e get "glasses"]] call AT_fnc_check;
            [isClass (configFile >> "CfgWeapons" >> (_e get "nvg")), format ["CfgWeapons %1 absente", _e get "nvg"]] call AT_fnc_check;
            [isClass (configFile >> "CfgWeapons" >> (_e get "item")), format ["CfgWeapons %1 absente", _e get "item"]] call AT_fnc_check;
            [isClass (configFile >> "CfgVehicles" >> (_e get "head")), format ["CfgVehicles %1 absente", _e get "head"]] call AT_fnc_check;
            [getNumber (configFile >> "CfgWeapons" >> (_e get "nvg") >> "scope") isEqualTo 2, format ["%1 : scope différent de 2", _e get "nvg"]] call AT_fnc_check;
            [(getText (configFile >> "CfgWeapons" >> (_e get "nvg") >> "picture")) isNotEqualTo "", format ["%1 sans icône", _e get "nvg"]] call AT_fnc_check;
            {
                private _m = getText (configFile >> (_x select 0) >> (_e get (_x select 1)) >> (_x select 2));
                [fileExists _m, format ["modèle introuvable : %1", _m]] call AT_fnc_check;
            } forEach [["CfgGlasses", "glasses", "model"], ["CfgVehicles", "head", "model"]];
            private _nm = getText (configFile >> "CfgWeapons" >> (_e get "nvg") >> "ItemInfo" >> "uniformModel");
            [fileExists _nm, format ["modèle JVN introuvable : %1", _nm]] call AT_fnc_check;
            [fileExists (_e get "picture"), format ["icône introuvable : %1", _e get "picture"]] call AT_fnc_check;
        };
    } forEach AT_EwIds;
    call AT_fnc_done;
}, 30] call AT_fnc_run;

["eyewear_slots_lunettes_jvn", {
    if (["comspec_sse_fnc_eyewearOf"] call AT_fnc_nil) exitWith {};
    private _g0 = goggles player;
    private _h0 = hmd player;
    {
        private _e = _x call AT_fnc_ewCat;
        player addGoggles (_e get "glasses");
        uiSleep 0.2;
        [(goggles player) isEqualTo (_e get "glasses"), format ["addGoggles %1 : goggles = %2", _e get "glasses", goggles player]] call AT_fnc_check;
        [(([player] call comspec_sse_fnc_eyewearOf) select 0) isEqualTo _x, format ["eyewearOf ne voit pas %1 (slot lunettes)", _x]] call AT_fnc_check;
        removeGoggles player;
        player linkItem (_e get "nvg");
        uiSleep 0.2;
        [(hmd player) isEqualTo (_e get "nvg"), format ["linkItem %1 : hmd = %2", _e get "nvg", hmd player]] call AT_fnc_check;
        [(([player] call comspec_sse_fnc_eyewearOf) select 1) isEqualTo "nvg", format ["eyewearOf ne voit pas %1 (slot JVN)", _x]] call AT_fnc_check;
        // « Allumer » les JVN : visionMode « Normal » seul, et l'addon les éteint aussitôt (XEH_postInitClient,
        // événement visionMode) : ce ne sont pas des JVN, ni image verte ni effets de JVN d'ACE.
        if (_forEachIndex isEqualTo 0) then { player switchCamera "INTERNAL"; uiSleep 0.3; };
        player action ["nvGoggles", player];
        uiSleep 0.05;
        (format ["%1 : currentVisionMode juste après la touche JVN = %2", _e get "nvg", currentVisionMode player]) call AT_fnc_ewLog;
        if (_forEachIndex isEqualTo 0) then { ["eyewear_jvn_nv_on"] call AT_fnc_shot; player switchCamera "EXTERNAL"; };
        [[{ (currentVisionMode player) isEqualTo 0 }, 2] call AT_fnc_wait, format ["%1 : vision nocturne restée active (%2)", _e get "nvg", currentVisionMode player]] call AT_fnc_check;
        player action ["nvGogglesOff", player];
        player unlinkItem (_e get "nvg");
    } forEach AT_EwIds;
    if (_g0 isNotEqualTo "") then { player addGoggles _g0; };
    if (_h0 isNotEqualTo "") then { player linkItem _h0; };
    call AT_fnc_done;
}, 40] call AT_fnc_run;

// Mesures en jeu (objets simples locaux) : boîtes englobantes des lunettes et JVN vanilla et des nôtres, et une capture
// des modèles posés devant la caméra, hors personnage (le maillage complet est-il dessiné ?).
["eyewear_mesures", {
    private _modeles = [
        "a3\characters_f_beta\heads\glasses\g_sport_red.p3d",
        "a3\weapons_f\binocular\nvg_proxy.p3d",
        "z\comspec_sse\addons\eyewear\data\models\ew_radar_white.p3d",
        "z\comspec_sse\addons\eyewear\data\models\ew_radar_white_nvg.p3d",
        "z\comspec_sse\addons\eyewear\data\models\ew_radar_white_head.p3d"
    ];
    private _dir = getDir player;
    private _fwd = [sin _dir, cos _dir, 0];
    private _right = [cos _dir, -(sin _dir), 0];
    private _centre = (eyePos player) vectorAdd (_fwd vectorMultiply 1.6);
    private _objs = [];
    {
        // Les modèles du repère personnage ont les yeux à ~0,7 m au-dessus de leur origine ; le modèle « _head » à 0.
        private _dz = [-0.70, 0] select ((_x find "_head") >= 0);
        private _pos = _centre vectorAdd (_right vectorMultiply ((_forEachIndex - 2) * 0.2)) vectorAdd [0, 0, _dz];
        private _o = createSimpleObject [_x, _pos, true];
        if (isNull _o) then {
            (format ["bbox|%1|createSimpleObject a échoué", _x]) call AT_fnc_ewLog;
        } else {
            _o setDir (_dir + 180);
            _o setPosASL _pos;
            _objs pushBack _o;
            (format ["bbox|%1|boundingBoxReal=%2|boundingCenter=%3|getModelInfo=%4|origine posée=%5|origine modèle=%6",
                _x, boundingBoxReal _o, boundingCenter _o, getModelInfo _o, _pos, _o modelToWorldVisualWorld [0, 0, 0]]) call AT_fnc_ewLog;
        };
    } forEach _modeles;
    uiSleep 0.5;
    // Caméra entre le joueur et la rangée, à hauteur des verres, de face puis de trois quarts.
    private _cible = _centre;
    [player, 0, -1.0, 0.05, _cible, 0.75] call AT_fnc_ewCamera;
    ["eyewear_objets_simples"] call AT_fnc_shot;
    [player, 30, -0.9, 0.12, _cible, 0.75] call AT_fnc_ewCamera;
    ["eyewear_objets_simples_34"] call AT_fnc_shot;
    call AT_fnc_ewCameraEnd;
    "ordre de gauche à droite (vu du joueur) : g_sport_red, nvg_proxy, ew_radar_white, ew_radar_white_nvg, ew_radar_white_head ; maillage complet ?" call AT_fnc_visuel;
    { deleteVehicle _x; } forEach _objs;
    call AT_fnc_done;
}, 40] call AT_fnc_run;

["eyewear_slot_virtuel", {
    if (["comspec_sse_fnc_eyewearWear", "comspec_sse_fnc_eyewearRemove", "comspec_sse_fnc_eyewearRender"] call AT_fnc_nil) exitWith {};
    private _e = "radar_white" call AT_fnc_ewCat;
    private _item = _e get "item";
    player addItem _item;
    [_item in (items player), format ["addItem %1 raté (place ?)", _item]] call AT_fnc_check;
    [[player, "radar_white"] call comspec_sse_fnc_eyewearWear, "eyewearWear a refusé"] call AT_fnc_check;
    [(player getVariable ["comspec_sse_eyewear_worn", ""]) isEqualTo "radar_white", "variable comspec_sse_eyewear_worn non posée"] call AT_fnc_check;
    [!(_item in (items player)), "l'objet est resté dans l'inventaire"] call AT_fnc_check;
    private _obj = {
        private _l = (missionNamespace getVariable ["comspec_sse_eyewear_drawn", []]) select { (_x select 0) isEqualTo player };
        if (_l isEqualTo []) then { objNull } else { (_l select 0) select 1 }
    };
    [[{ !isNull (call _obj) }, 3] call AT_fnc_wait, "objet 3D non créé"] call AT_fnc_check;
    private _o = call _obj;
    private _hg0 = headgear player;
    private _hmd0 = hmd player;
    removeHeadgear player;
    if (_hmd0 isNotEqualTo "") then { player unlinkItem _hmd0; };
    player switchCamera "EXTERNAL";
    call AT_fnc_ewPose;
    uiSleep 0.5;
    // Suivi de la tête à chaque image (fn_eyewearFollow) : position contrôlée au moment même des captures.
    AT_EwControle = {
        params ["_o", "_quand"];
        if (isNull _o) exitWith {};
        private _orig = _o modelToWorldVisualWorld [0, 0, 0];
        private _pilot = player modelToWorldVisualWorld (player selectionPosition ["pilot", "Memory"]);
        // Origine attendue : « pilot » + décalage de la config (repère de l'objet : x droite, y avant, z haut).
        private _tune = getArray (configFile >> "CfgVehicles" >> typeOf _o >> "comspec_sse_eyewearAttachOffset");
        _tune params [["_tx", 0], ["_ty", 0], ["_tz", 0]];
        private _d = vectorDirVisual _o;
        private _up = vectorUpVisual _o;
        private _attendu = _pilot vectorAdd ((_d vectorCrossProduct _up) vectorMultiply _tx) vectorAdd (_d vectorMultiply _ty) vectorAdd (_up vectorMultiply _tz);
        private _ecart = _attendu vectorDistance _orig;
        (format ["%1 : origine modèle=%2 pilot (monde)=%3 attendu=%4 écart=%5 m distance origine/pilot=%6 m eyePos=%7 dir=%8 up=%9", _quand, _orig, _pilot, _attendu,
            _ecart toFixed 4, (_orig vectorDistance _pilot) toFixed 4, eyePos player, _d, _up]) call AT_fnc_ewLog;
        [_ecart < 0.02, format ["%1 : origine du modèle à %2 m de « pilot » + décalage", _quand, _ecart toFixed 3]] call AT_fnc_check;
    };
    if (!isNull _o) then {
        [(attachedTo _o) isEqualTo player, "objet 3D non accroché au joueur"] call AT_fnc_check;
        [(typeOf _o) isEqualTo (_e get "head"), format ["objet 3D de classe %1", typeOf _o]] call AT_fnc_check;
        (format ["objet %1 (%2) attachedTo=%3 attachedObjects=%4 cache=%5 posASL=%6 eyePos=%7 distance=%8 pilot=%9 head=%10 modele=%11",
            _o, typeOf _o, attachedTo _o, attachedObjects player, isObjectHidden _o, getPosASL _o, eyePos player,
            ((getPosASL _o) vectorDistance (eyePos player)) toFixed 3,
            player selectionPosition "pilot", player selectionPosition "head", getModelInfo _o]) call AT_fnc_ewLog;
    } else {
        (format ["aucun objet ; attachedObjects=%1 ; liste=%2", attachedObjects player, missionNamespace getVariable ["comspec_sse_eyewear_drawn", []]]) call AT_fnc_ewLog;
    };
    // Gros plan sur le visage : l'objet n'est caché que si la caméra est aux yeux du porteur.
    [player, 0] call AT_fnc_ewCamera;
    if (!isNull _o) then { (format ["caméra de script : objet caché = %1", isObjectHidden _o]) call AT_fnc_ewLog; };
    [_o, "capture face"] call AT_EwControle;
    ["eyewear_virtuel_face"] call AT_fnc_shot;
    [player, 45] call AT_fnc_ewCamera;
    [_o, "capture profil"] call AT_EwControle;
    ["eyewear_virtuel_profil"] call AT_fnc_shot;
    // La caméra vise l'objet lui-même : il existe là où il est accroché.
    if (!isNull _o) then {
        [player, 90, 0.5, 0.05, getPosASL _o, 0.35] call AT_fnc_ewCamera;
        ["eyewear_virtuel_objet"] call AT_fnc_shot;
    };
    ["virtuel"] call AT_fnc_ewCorps;
    call AT_fnc_ewCameraEnd;
    if (_hg0 isNotEqualTo "") then { player addHeadgear _hg0; };
    if (_hmd0 isNotEqualTo "") then { player linkItem _hmd0; };
    "lunettes du slot virtuel posées sur les yeux (réglage fin : comspec_sse_eyewearAttachOffset)" call AT_fnc_visuel;
    [(([player, true] call comspec_sse_fnc_eyewearRemove)) isEqualTo "radar_white", "eyewearRemove n'a rien retiré"] call AT_fnc_check;
    [(player getVariable ["comspec_sse_eyewear_worn", ""]) isEqualTo "", "variable comspec_sse_eyewear_worn restée posée"] call AT_fnc_check;
    [_item in (items player), "objet non rendu à l'inventaire"] call AT_fnc_check;
    [[{ isNull (call _obj) }, 3] call AT_fnc_wait, "objet 3D non supprimé"] call AT_fnc_check;
    player removeItem _item;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["eyewear_teinte", {
    if (["comspec_sse_fnc_eyewearTint"] call AT_fnc_nil) exitWith {};
    private _t0 = missionNamespace getVariable ["comspec_sse_eyewear_tint", false];
    private _f0 = missionNamespace getVariable ["comspec_sse_eyewear_tint_force", 0.6];
    private _g0 = goggles player;
    private _h0 = hmd player;
    if (_h0 isNotEqualTo "") then { player unlinkItem _h0; };
    private _e = "wind_orange" call AT_fnc_ewCat;
    player addGoggles (_e get "glasses");
    player switchCamera "INTERNAL";
    uiSleep 0.5;
    ["comspec_sse_eyewear_tint", false] call AT_fnc_ewSet;
    [([] call comspec_sse_fnc_eyewearTint) isEqualTo "", "teinte posée alors que le réglage est coupé"] call AT_fnc_check;
    uiSleep 1;
    ["eyewear_teinte_off"] call AT_fnc_shot;
    // Force 1 pour une différence nette sur la capture.
    ["comspec_sse_eyewear_tint_force", 1] call AT_fnc_ewSet;
    ["comspec_sse_eyewear_tint", true] call AT_fnc_ewSet;
    [([] call comspec_sse_fnc_eyewearTint) isEqualTo "wind_orange", "teinte du verre wind_orange non posée"] call AT_fnc_check;
    private _pp = missionNamespace getVariable ["comspec_sse_eyewear_tintPP", -1];
    [_pp >= 0 && {ppEffectEnabled _pp}, "ppEffect ColorCorrections non actif"] call AT_fnc_check;
    (format ["ppEffect %1 actif=%2 reglage=%3 force=%4 vue=%5 params=%6", _pp, ppEffectEnabled _pp,
        missionNamespace getVariable ["comspec_sse_eyewear_tint", false], missionNamespace getVariable ["comspec_sse_eyewear_tint_force", -1],
        cameraView, missionNamespace getVariable ["comspec_sse_eyewear_tintParams", []]]) call AT_fnc_ewLog;
    uiSleep 1.5;
    (format ["avant capture : actif=%1 transition finie=%2 lunettes=%3", ppEffectEnabled _pp, ppEffectCommitted _pp, [player] call comspec_sse_fnc_eyewearOf]) call AT_fnc_ewLog;
    ["eyewear_teinte_on"] call AT_fnc_shot;
    uiSleep 0.5;
    // Vraies JVN allumées : teinte coupée.
    removeGoggles player;
    player linkItem "NVGoggles";
    player addGoggles (_e get "glasses");
    player action ["nvGoggles", player];
    uiSleep 0.6;
    [([] call comspec_sse_fnc_eyewearTint) isEqualTo "", "teinte gardée avec les JVN allumées"] call AT_fnc_check;
    player action ["nvGogglesOff", player];
    player unlinkItem "NVGoggles";
    ["comspec_sse_eyewear_tint", false] call AT_fnc_ewSet;
    [([] call comspec_sse_fnc_eyewearTint) isEqualTo "", "teinte gardée après coupure du réglage"] call AT_fnc_check;
    uiSleep 1;
    [!(ppEffectEnabled _pp), "ppEffect resté actif après coupure"] call AT_fnc_check;
    ["comspec_sse_eyewear_tint", _t0] call AT_fnc_ewSet;
    ["comspec_sse_eyewear_tint_force", _f0] call AT_fnc_ewSet;
    removeGoggles player;
    if (_g0 isNotEqualTo "") then { player addGoggles _g0; };
    if (_h0 isNotEqualTo "") then { player linkItem _h0; };
    player switchCamera "EXTERNAL";
    call AT_fnc_done;
}, 50] call AT_fnc_run;

// Captures en gros plan : chaque coloris au slot lunettes, de face puis de profil à 45° ; un coloris au slot JVN.
["eyewear_captures", {
    if (["comspec_sse_fnc_eyewearCatalog"] call AT_fnc_nil) exitWith {};
    private _date0 = date;
    setDate [_date0 select 0, _date0 select 1, _date0 select 2, 12, 0];
    private _g0 = goggles player;
    private _h0 = hmd player;
    private _hg0 = headgear player;
    removeHeadgear player;
    if (_h0 isNotEqualTo "") then { player unlinkItem _h0; };
    player switchCamera "EXTERNAL";
    call AT_fnc_ewPose;
    // Référence : lunettes de sport vanilla, mêmes caméras.
    player addGoggles "G_Sport_Red";
    uiSleep 0.4;
    [player, 0] call AT_fnc_ewCamera;
    ["eyewear_ref_vanilla_face"] call AT_fnc_shot;
    [player, 45] call AT_fnc_ewCamera;
    ["eyewear_ref_vanilla_profil"] call AT_fnc_shot;
    // Calibration TEMPORAIRE : Radar blanche tournée autour d'un axe à 5,6 et 7,2 cm derrière les yeux (le modèle
    // retenu, ew_radar_white, autour d'un axe à 6,4 cm).
    {
        private _cls = "COMSPEC_SSE_G_Cal_" + toUpper _x;
        if (isClass (configFile >> "CfgGlasses" >> _cls)) then {
            player addGoggles _cls;
            uiSleep 0.4;
            [player, 0] call AT_fnc_ewCamera;
            [format ["eyewear_cal_%1_face", _x]] call AT_fnc_shot;
            [player, 45] call AT_fnc_ewCamera;
            [format ["eyewear_cal_%1_profil", _x]] call AT_fnc_shot;
        };
    } forEach ["p056", "p072"];
    {
        private _e = _x call AT_fnc_ewCat;
        player addGoggles (_e get "glasses");
        uiSleep 0.4;
        [_x] call AT_fnc_ewCorps;
        [player, 0] call AT_fnc_ewCamera;
        [format ["eyewear_%1_face", _x]] call AT_fnc_shot;
        [player, 45] call AT_fnc_ewCamera;
        [format ["eyewear_%1_profil", _x]] call AT_fnc_shot;
    } forEach AT_EwIds;
    removeGoggles player;
    private _e = "hurricane_black" call AT_fnc_ewCat;
    player linkItem (_e get "nvg");
    uiSleep 0.4;
    ["jvn"] call AT_fnc_ewCorps;
    [player, 0] call AT_fnc_ewCamera;
    ["eyewear_jvn_face"] call AT_fnc_shot;
    [player, 45] call AT_fnc_ewCamera;
    ["eyewear_jvn_profil"] call AT_fnc_shot;
    player unlinkItem (_e get "nvg");
    call AT_fnc_ewCameraEnd;
    "lunettes posées sur les yeux, ni dans la tête ni devant le visage ; JVN au même endroit" call AT_fnc_visuel;
    if (_g0 isNotEqualTo "") then { player addGoggles _g0; };
    if (_h0 isNotEqualTo "") then { player linkItem _h0; };
    if (_hg0 isNotEqualTo "") then { player addHeadgear _hg0; };
    setDate _date0;
    call AT_fnc_done;
}, 360] call AT_fnc_run;
call AT_fnc_ewCameraEnd;
