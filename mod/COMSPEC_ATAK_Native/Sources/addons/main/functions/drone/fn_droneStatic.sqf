/*
    Neige de télévision « NO DATA » posée sur l'image de la caméra (app Drone, vue drone de la carte) quand il n'y a pas
    de flux : liaison perdue, drone détruit ou en veille, caméra pas prête, porteur de la caméra casque mort, PiP coupée.
      ["build", [x, y, w, h], dans le groupe de page]   pose le fond gris, trois couches de bruit et l'étiquette (masqués) ;
      ["reason"]   → "" si le flux passe, sinon la raison affichée sous NO DATA ;
      ["frame"]    gestionnaire par image (environ 15 fois par seconde) : montre ou masque, anime la neige ;
                   retiré seul quand les contrôles disparaissent.
    Neige : textures procédurales perlinNoise (aucun fichier), une seule couche visible à la fois, tournée d'un demi-tour
    une fois sur deux ; sous elle un fond gris, pour que l'image reste grise même si la texture n'est pas créée.
    État : uiNamespace COMSPEC_ATAK_DroneStatic ([[fond, couches, étiquette]...]), missionNamespace COMSPEC_ATAK_DroneStaticPfh.
*/
params [["_act", "frame"], ["_arg", []], ["_inGroup", true]];
disableSerialization;
switch (_act) do {
    case "reason": {
        if !(isPiPEnabled) exitWith { "IMAGE DANS L'IMAGE (PIP) COUPÉE" };
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        private _src = missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedSrc", "DRONE"];
        if (_src isEqualTo "HELMET") exitWith {
            private _u = missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedUnit", objNull];
            [["", "CAMÉRA CASQUE HORS LIGNE"] select (!alive _u), "PORTEUR TROP LOIN"] select (alive _u && {(_u distance player) > 3000})
        };
        if (_src isEqualTo "PHONE") exitWith { "" };
        if (isNull _d || {!alive _d}) exitWith { "DRONE PERDU" };
        if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull])) exitWith { "CAMÉRA PAS PRÊTE" };
        if ((_d getVariable ["COMSPEC_DroneMode", ""]) isEqualTo "STANDBY") exitWith { "DRONE EN VEILLE" };
        (["link"] call comspec_atak_native_fnc_droneAction) params ["", "_dist", "_ok"];
        if (!_ok) exitWith { format ["LIAISON PERDUE · %1 M", _dist] };
        ""
    };
    case "build": {
        _arg params ["_px", "_py", "_pw", "_ph"];
        private _fs = ([] call comspec_atak_native_fnc_layoutGet) get "fontSmall";
        private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, _inGroup] call comspec_atak_native_fnc_pageCtrl };
        private _bg = ["COMSPEC_RscText", [_px, _py, _pw, _ph]] call _mk;
        _bg ctrlSetBackgroundColor [0.2, 0.2, 0.2, 1];
        _bg ctrlShow false;
        private _layers = ["#(ai,128,64,1)perlinNoise(2,2,0,1)", "#(ai,128,64,1)perlinNoise(3,2,0,1)", "#(ai,128,64,1)perlinNoise(2,3,0,1)"] apply {
            private _p = ["RscPicture", [_px, _py, _pw, _ph], _x] call _mk;
            _p ctrlSetTextColor [0.85, 0.85, 0.85, 1];
            _p ctrlShow false;
            _p
        };
        private _lb = ["COMSPEC_RscStructuredText", [_px, _py + _ph * 0.42, _pw, _fs * 2.4]] call _mk;
        _lb ctrlShow false;
        _lb ctrlEnable false;
        private _all = (uiNamespace getVariable ["COMSPEC_ATAK_DroneStatic", []]) select { !isNull (_x select 0) };
        _all pushBack [_bg, _layers, _lb, "§"];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneStatic", _all];
        if ((missionNamespace getVariable ["COMSPEC_ATAK_DroneStaticPfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneStaticPfh", [{ ["frame"] call comspec_atak_native_fnc_droneStatic; }, 0.066] call CBA_fnc_addPerFrameHandler];
        };
        ["frame"] call comspec_atak_native_fnc_droneStatic;
    };
    case "frame": {
        private _all = (uiNamespace getVariable ["COMSPEC_ATAK_DroneStatic", []]) select { !isNull (_x select 0) };
        uiNamespace setVariable ["COMSPEC_ATAK_DroneStatic", _all];
        if ((count _all) isEqualTo 0) exitWith {
            [missionNamespace getVariable ["COMSPEC_ATAK_DroneStaticPfh", -1]] call CBA_fnc_removePerFrameHandler;
            missionNamespace setVariable ["COMSPEC_ATAK_DroneStaticPfh", -1];
        };
        // Raison relue deux fois par seconde (calcul de liaison), neige animée à chaque passage.
        if (diag_tickTime >= (uiNamespace getVariable ["COMSPEC_ATAK_DroneStaticNext", 0])) then {
            uiNamespace setVariable ["COMSPEC_ATAK_DroneStaticNext", diag_tickTime + 0.5];
            uiNamespace setVariable ["COMSPEC_ATAK_DroneStaticWhy", ["reason"] call comspec_atak_native_fnc_droneStatic];
        };
        private _why = uiNamespace getVariable ["COMSPEC_ATAK_DroneStaticWhy", ""];
        private _on = _why isNotEqualTo "" && {!(missionNamespace getVariable ["COMSPEC_ATAK_DroneShotBusy", false])};
        private _pick = floor random 3;
        private _flip = (random 1) < 0.5;
        {
            private _entry = _x;
            _entry params ["_bg", "_layers", "_lb", "_last"];
            if (_on isNotEqualTo (ctrlShown _bg)) then {
                _bg ctrlShow _on;
                _lb ctrlShow _on;
                if (!_on) then { { _x ctrlShow false; } forEach _layers; };
            };
            if (_on) then {
                {
                    _x ctrlShow (_forEachIndex isEqualTo _pick);
                    if (_forEachIndex isEqualTo _pick) then {
                        _x ctrlSetAngle [[0, 180] select _flip, 0.5, 0.5];
                        _x ctrlSetTextColor [1, 1, 1, 0.75 + random 0.25];
                    };
                } forEach _layers;
                if (_last isNotEqualTo _why) then {
                    _entry set [3, _why];
                    _lb ctrlSetStructuredText parseText ((format ["<t align='center' font='EtelkaMonospacePro' shadow='2' shadowColor='#000000' color='#f2f2f2'><t size='1.4'>NO DATA</t><br/><t size='0.75'>%1</t></t>", _why]) call comspec_atak_native_fnc_tr);
                };
            };
        } forEach _all;
    };
};
true
