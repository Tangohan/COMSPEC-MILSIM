/*
    Carte de l'écran 3D du JPC (fn_jpcScreen). Une carte (contrôle de type carte) ne se dessine pas dans une texture UI :
    l'écran restait noir à la place de la carte. On y met donc une vue aérienne en direct, nord en haut, centrée sur
    le porteur : caméra locale rendue dans une texture (r2t « comspec_atak_jpcmap »), positions amies par-dessus
    (flèche du porteur au centre, points bleus pour les alliés à pied proches).
    La vue prend la place et la taille du contrôle carte et n'est visible que quand il l'est (page Carte).
    Actions :
      ["init", _display]  displayLoad (texture) : crée l'image et les icônes, avant les panneaux de la page ;
      ["update", _display] fn_jpcScreen, à chaque rafraîchissement de l'écran : caméra, image, icônes ;
      ["close"]           détruit la caméra.
    Réglages fins (missionNamespace) : COMSPEC_ATAK_JpcMapAlt (hauteur de la caméra, 300 m), COMSPEC_ATAK_JpcMapFov (0.75).
    Nécessite l'image dans l'image (Options vidéo > Qualité de l'image dans l'image) : sinon la vue reste noire.
*/
params [["_act", "update"], ["_display", displayNull]];
disableSerialization;
private _name = "comspec_atak_jpcmap";
private _iconsMax = 16;

switch (_act) do {
    case "init": {
        if (isNull _display) exitWith {};
        private _pic = _display ctrlCreate ["COMSPEC_RscSlide", -1];
        _pic ctrlSetPosition [0, 0, 0, 0];
        _pic ctrlCommit 0;
        private _icons = [];
        for "_i" from 1 to _iconsMax do {
            private _c = _display ctrlCreate ["COMSPEC_RscSlide", -1];
            _c ctrlSetText (("\A3\ui_f\data\map\markers\military\dot_CA.paa") call comspec_atak_native_fnc_tr);
            _c ctrlSetTextColor [0.25, 0.55, 1, 1];
            _c ctrlShow false;
            _icons pushBack _c;
        };
        private _me = _display ctrlCreate ["COMSPEC_RscSlide", -1];
        _me ctrlSetText (("\A3\ui_f\data\igui\cfg\islandmap\iconplayer_ca.paa") call comspec_atak_native_fnc_tr);
        _me ctrlSetTextColor [0.36, 0.78, 0.42, 1];
        uiNamespace setVariable ["COMSPEC_ATAK_JpcMapCtrls", [_pic, _me, _icons, []]];
    };
    case "close": {
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_JpcMapCam", objNull];
        if (!isNull _cam) then {
            _cam cameraEffect ["Terminate", "Back", _name];
            camDestroy _cam;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_JpcMapCam", objNull];
        uiNamespace setVariable ["COMSPEC_ATAK_JpcMapCtrls", nil];
    };
    default {
        (uiNamespace getVariable ["COMSPEC_ATAK_JpcMapCtrls", []]) params [["_pic", controlNull], ["_me", controlNull], ["_icons", []], ["_last", []]];
        if (isNull _display || {isNull _pic}) exitWith {};
        private _map = _display displayCtrl 88530;
        private _show = ctrlShown _map;
        (ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_JpcMapCam", objNull];
        if (!_show || {_mw <= 0} || {_mh <= 0}) exitWith {
            { _x ctrlShow false; } forEach ([_pic, _me] + _icons);
            // Hors page Carte : la caméra ne rend plus rien (pas de coût).
            if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back", _name]; camDestroy _cam; missionNamespace setVariable ["COMSPEC_ATAK_JpcMapCam", objNull]; };
        };
        // Image dans l'image coupée (Options vidéo) : la vue aérienne serait noire, on montre la carte d'ensemble du
        // terrain (pictureMap) avec un rappel, sans caméra.
        if !(isPiPEnabled) exitWith {
            if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back", _name]; camDestroy _cam; missionNamespace setVariable ["COMSPEC_ATAK_JpcMapCam", objNull]; };
            private _pm = getText (configFile >> "CfgWorlds" >> worldName >> "pictureMap");
            if (_last isNotEqualTo ["pip", _mx, _my, _mw, _mh]) then {
                _pic ctrlSetText ((_pm) call comspec_atak_native_fnc_tr);
                _pic ctrlSetPosition [_mx, _my, _mw, _mh];
                _pic ctrlCommit 0;
                (uiNamespace getVariable ["COMSPEC_ATAK_JpcMapCtrls", []]) set [3, ["pip", _mx, _my, _mw, _mh]];
                ["WARNING", "Écran du JPC : activez « Image dans l'image » (Options > Vidéo) pour la vue aérienne", 6, 120] call comspec_atak_native_fnc_notify;
            };
            _pic ctrlShow (_pm isNotEqualTo "");
            { _x ctrlShow false; } forEach ([_me] + _icons);
        };
        // Proportions de la vue en pixels de la texture (1024 x 576).
        private _aspect = (_mw * 1024) / (_mh * 576);
        if (isNull _cam) then {
            _cam = "camera" camCreate (getPosATL player);
            _cam cameraEffect ["Internal", "Back", _name];
            _name setPiPEffect [0];
            missionNamespace setVariable ["COMSPEC_ATAK_JpcMapCam", _cam];
            _last = [];
        };
        private _alt = (missionNamespace getVariable ["COMSPEC_ATAK_JpcMapAlt", 300]) max 50;
        private _fov = (missionNamespace getVariable ["COMSPEC_ATAK_JpcMapFov", 0.75]) max 0.1;
        private _p = getPosASL player;
        _cam setPosASL (_p vectorAdd [0, 0, _alt]);
        _cam setVectorDirAndUp [[0, 0, -1], [0, 1, 0]];
        _cam camSetFov _fov;
        _cam camCommit 0;
        private _pos = [_mx, _my, _mw, _mh];
        if (_last isNotEqualTo [_pos, _aspect]) then {
            _pic ctrlSetText ((format ["#(argb,512,512,1)r2t(%1,%2)", _name, _aspect]) call comspec_atak_native_fnc_tr);
            _pic ctrlSetPosition _pos;
            _pic ctrlCommit 0;
            (uiNamespace getVariable ["COMSPEC_ATAK_JpcMapCtrls", []]) set [3, [_pos, _aspect]];
        };
        _pic ctrlShow true;
        // Échelle : demi-largeur au sol = hauteur × fov (fov = tangente du demi-angle horizontal).
        private _halfW = _alt * _fov;
        private _halfH = _halfW / _aspect;
        private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", 1024 / 576];
        private _s = _mh * 0.07;
        private _place = {
            params ["_c", "_dx", "_dy", "_size"];
            _c ctrlSetPosition [_mx + _mw * (0.5 + _dx / (2 * _halfW)) - _size / _ratio / 2, _my + _mh * (0.5 - _dy / (2 * _halfH)) - _size / 2, _size / _ratio, _size];
            _c ctrlCommit 0;
        };
        [_me, 0, 0, _s * 1.4] call _place;
        _me ctrlSetAngle [getDir player, 0.5, 0.5];
        _me ctrlShow true;
        private _side = side group player;
        private _near = ((_p nearEntities ["CAManBase", _halfW * 1.5]) select { _x isNotEqualTo player && {alive _x} && {side group _x isEqualTo _side} });
        {
            private _u = _near param [_forEachIndex, objNull];
            if (isNull _u) then { _x ctrlShow false; } else {
                private _d = (getPosASL _u) vectorDiff _p;
                if (abs (_d select 0) > _halfW || {abs (_d select 1) > _halfH}) then { _x ctrlShow false; } else {
                    [_x, _d select 0, _d select 1, _s] call _place;
                    _x ctrlShow true;
                };
            };
        } forEach _icons;
    };
};
