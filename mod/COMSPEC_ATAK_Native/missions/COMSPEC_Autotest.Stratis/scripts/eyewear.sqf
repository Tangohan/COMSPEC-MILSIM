// SSE lunettes de sport (addon eyewear) : classes, trois slots (lunettes, JVN, slot virtuel), teinte des verres, captures
// en gros plan du visage pour chaque coloris (face et profil à 45°). Groupe « eyewear » (aussi lancé avec « sse »).
if !(("eyewear" call AT_fnc_groupe) || {"sse" call AT_fnc_groupe}) exitWith {};

AT_EwIds = ["radar_white", "radar_black", "hurricane_black", "wind_rainbow", "wind_orange"];
AT_fnc_ewCat = { (([] call comspec_sse_fnc_eyewearCatalog) select 0) getOrDefault [_this, createHashMap] };

// Caméra à 0,6 m du visage, un peu au-dessus, visant les yeux ; _az : 0 de face, 45 de trois quarts.
AT_fnc_ewCamera = {
    params ["_u", "_az"];
    private _eye = eyePos _u;
    private _d = (getDir _u) + _az;
    private _pos = _eye vectorAdd [0.6 * sin _d, 0.6 * cos _d, 0.07];
    private _cam = missionNamespace getVariable ["AT_EwCam", objNull];
    if (isNull _cam) then {
        _cam = "camera" camCreate (ASLToAGL _pos);
        _cam cameraEffect ["internal", "back"];
        showCinemaBorder false;
        missionNamespace setVariable ["AT_EwCam", _cam];
    };
    _cam camPrepareTarget (ASLToAGL _eye);
    _cam camPreparePos (ASLToAGL _pos);
    _cam camPrepareFov 0.30;
    _cam camCommitPrepared 0;
    uiSleep 0.6;
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
        // « Allumer » les JVN : la vision reste normale (visionMode « Normal » seul).
        player action ["nvGoggles", player];
        uiSleep 0.4;
        [(currentVisionMode player) isEqualTo 0, format ["%1 : vision nocturne active (%2)", _e get "nvg", currentVisionMode player]] call AT_fnc_check;
        player action ["nvGogglesOff", player];
        player unlinkItem (_e get "nvg");
    } forEach AT_EwIds;
    if (_g0 isNotEqualTo "") then { player addGoggles _g0; };
    if (_h0 isNotEqualTo "") then { player linkItem _h0; };
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
    if (!isNull _o) then {
        [(attachedTo _o) isEqualTo player, "objet 3D non accroché au joueur"] call AT_fnc_check;
        [(typeOf _o) isEqualTo (_e get "head"), format ["objet 3D de classe %1", typeOf _o]] call AT_fnc_check;
        (format ["objet à %1 m des yeux (eyePos), « pilot » %2, « head » %3",
            ((getPosASL _o) vectorDistance (eyePos player)) toFixed 3,
            player selectionPosition "pilot", player selectionPosition "head"]) call AT_fnc_info;
    };
    // Gros plan, vue extérieure (l'objet est caché en vue subjective pour le joueur).
    player switchCamera "EXTERNAL";
    call AT_fnc_ewPose;
    [player, 0] call AT_fnc_ewCamera;
    ["eyewear_virtuel_face"] call AT_fnc_shot;
    [player, 45] call AT_fnc_ewCamera;
    ["eyewear_virtuel_profil"] call AT_fnc_shot;
    call AT_fnc_ewCameraEnd;
    "lunettes du slot virtuel posées sur les yeux (corriger comspec_sse_eyewearAttachOffset sinon)" call AT_fnc_visuel;
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
    private _g0 = goggles player;
    private _h0 = hmd player;
    if (_h0 isNotEqualTo "") then { player unlinkItem _h0; };
    private _e = "wind_orange" call AT_fnc_ewCat;
    player addGoggles (_e get "glasses");
    player switchCamera "INTERNAL";
    uiSleep 0.5;
    missionNamespace setVariable ["comspec_sse_eyewear_tint", false];
    [([] call comspec_sse_fnc_eyewearTint) isEqualTo "", "teinte posée alors que le réglage est coupé"] call AT_fnc_check;
    missionNamespace setVariable ["comspec_sse_eyewear_tint", true];
    [([] call comspec_sse_fnc_eyewearTint) isEqualTo "wind_orange", "teinte du verre wind_orange non posée"] call AT_fnc_check;
    private _pp = missionNamespace getVariable ["comspec_sse_eyewear_tintPP", -1];
    [_pp >= 0 && {ppEffectEnabled _pp}, "ppEffect ColorCorrections non actif"] call AT_fnc_check;
    uiSleep 1;
    ["eyewear_teinte_on"] call AT_fnc_shot;
    // JVN allumées : teinte coupée.
    removeGoggles player;
    player linkItem "NVGoggles";
    player addGoggles (_e get "glasses");
    player action ["nvGoggles", player];
    uiSleep 0.6;
    [([] call comspec_sse_fnc_eyewearTint) isEqualTo "", "teinte gardée avec les JVN allumées"] call AT_fnc_check;
    player action ["nvGogglesOff", player];
    player unlinkItem "NVGoggles";
    missionNamespace setVariable ["comspec_sse_eyewear_tint", false];
    [([] call comspec_sse_fnc_eyewearTint) isEqualTo "", "teinte gardée après coupure du réglage"] call AT_fnc_check;
    uiSleep 1;
    [!(ppEffectEnabled _pp), "ppEffect resté actif après coupure"] call AT_fnc_check;
    ["eyewear_teinte_off"] call AT_fnc_shot;
    missionNamespace setVariable ["comspec_sse_eyewear_tint", _t0];
    removeGoggles player;
    if (_g0 isNotEqualTo "") then { player addGoggles _g0; };
    if (_h0 isNotEqualTo "") then { player linkItem _h0; };
    player switchCamera "EXTERNAL";
    call AT_fnc_done;
}, 40] call AT_fnc_run;

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
    {
        private _e = _x call AT_fnc_ewCat;
        player addGoggles (_e get "glasses");
        uiSleep 0.4;
        [player, 0] call AT_fnc_ewCamera;
        [format ["eyewear_%1_face", _x]] call AT_fnc_shot;
        [player, 45] call AT_fnc_ewCamera;
        [format ["eyewear_%1_profil", _x]] call AT_fnc_shot;
    } forEach AT_EwIds;
    removeGoggles player;
    private _e = "hurricane_black" call AT_fnc_ewCat;
    player linkItem (_e get "nvg");
    uiSleep 0.4;
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
}, 150] call AT_fnc_run;
call AT_fnc_ewCameraEnd;
