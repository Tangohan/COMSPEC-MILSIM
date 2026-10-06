/*
    Bluetooth et mode avion : événements et publication de l'état (fonction postInit, clients seulement).
      comspec_atak_native_bt     : messages entre téléphones (appairage, demandes de son, réponses) → fn_btAction "recv" ;
      comspec_atak_native_btPlay : ["play", [source, kind, ref, portée, id]] / ["stop", [id]], reçu par les joueurs proches
                                   du téléphone qui joue : le son part de son unité (say3D ou playSound3D local).
    Toutes les 5 s, et après une réapparition : Bluetooth publié sur l'unité (éteint si le téléphone est inutilisable).
*/
if (!hasInterface) exitWith {};
["comspec_atak_native_bt", { ["recv", _this] call comspec_atak_native_fnc_btAction; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_btPlay", {
    params [["_type", ""], ["_a", []]];
    private _h = missionNamespace getVariable ["COMSPEC_ATAK_BtPlaying", createHashMap];
    missionNamespace setVariable ["COMSPEC_ATAK_BtPlaying", _h];
    if (_type isEqualTo "stop") exitWith {
        private _s = _h getOrDefault [_a param [0, ""], objNull];
        _h deleteAt (_a param [0, ""]);
        if (_s isEqualType objNull) then { if (!isNull _s) then { deleteVehicle _s; }; } else { if (_s isEqualType 0) then { stopSound _s; }; };
    };
    _a params [["_src", objNull], ["_kind", ""], ["_ref", ""], ["_range", 20], ["_id", ""]];
    if (isNull _src || {_ref isEqualTo ""}) exitWith {};
    if (_kind isEqualTo "snd") exitWith {
        private _o = _src say3D [_ref, _range];
        _h set [_id, _o];
    };
    if (_kind isEqualTo "mus") then {
        private _inMission = isClass (missionConfigFile >> "CfgMusic" >> _ref);
        private _cfg = if (_inMission) then { missionConfigFile >> "CfgMusic" >> _ref } else { configFile >> "CfgMusic" >> _ref };
        private _path = (getArray (_cfg >> "sound")) param [0, ""];
        if !(_path isEqualType "") exitWith {};
        if (_path isEqualTo "" || {(_path select [0, 1]) isEqualTo "@"}) exitWith {};
        if ((_path select [0, 1]) isEqualTo "\") then { _path = _path select [1]; };
        if (_inMission) then { _path = getMissionPath _path; };
        // Lecture locale sur chaque client proche (dernier paramètre) : pas de diffusion réseau du son.
        private _r = playSound3D [_path, _src, false, getPosASL _src, 2, 1, _range, 0, true];
        if (!isNil "_r") then { _h set [_id, _r]; };
    };
}] call CBA_fnc_addEventHandler;
[{ ["publish"] call comspec_atak_native_fnc_btAction; }, 5] call CBA_fnc_addPerFrameHandler;
[{ !isNull player }, {
    player addEventHandler ["Respawn", {
        player setVariable ["COMSPEC_ATAK_Airplane", missionNamespace getVariable ["COMSPEC_ATAK_Airplane", false], true];
        ["publish"] call comspec_atak_native_fnc_btAction;
    }];
}] call CBA_fnc_waitUntilAndExecute;
