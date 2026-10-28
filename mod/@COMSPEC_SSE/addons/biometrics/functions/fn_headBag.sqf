/*
    Sac de tête (capture).

    Un prisonnier (menotté, rendu, inconscient ou mort) reçoit un sac sur la tête :
    il ne voit plus rien (écran noir pour un joueur), entend étouffé, et son visage
    comme ses iris ne peuvent plus être relevés tant que le sac est en place.

    [_op, _target, _operator] call comspec_sse_fnc_headBag
      "is"     : le sujet porte-t-il un sac ?
      "canOn"  : l'opérateur peut-il en poser un ? (sac dans l'inventaire, sujet maîtrisé)
      "canOff" : l'opérateur peut-il le retirer ?
      "on"     : pose (là où l'opérateur est local)
      "off"    : retire et rend le sac à l'opérateur
      "local"  : effets sur le poste du sujet (appelé là où il est local)
*/
params [["_op", "is", [""]], ["_t", objNull, [objNull]], ["_by", objNull, [objNull]]];
if (isNull _t) exitWith { false };

private _model = missionNamespace getVariable ["comspec_sse_headBagGoggles", ""];
if (!(_model isEqualType "") || {(trim _model) isEqualTo ""} || {!isClass (configFile >> "CfgGlasses" >> trim _model)}) then {
    _model = "COMSPEC_SSE_G_HeadBag";
};
private _bagged = (_t getVariable ["COMSPEC_SSE_HeadBagged", false]) || {(goggles _t) isEqualTo _model};

switch (toLower _op) do {
    case "is": { _bagged };
    case "canon": {
        !_bagged && {_t != _by} && {"COMSPEC_SSE_HeadBag" in (items _by)}
        && {!alive _t || {_t getVariable ["ace_captives_isHandcuffed", false]} || {_t getVariable ["ace_captives_isSurrendering", false]} || {_t getVariable ["ACE_isUnconscious", false]} || {(lifeState _t) isEqualTo "INCAPACITATED"}}
    };
    case "canoff": { _bagged && {_t != _by} };
    case "on": {
        if (_bagged) exitWith { false };
        _by removeItem "COMSPEC_SSE_HeadBag";
        _t setVariable ["COMSPEC_SSE_HeadBagPrev", goggles _t, true];
        _t setVariable ["COMSPEC_SSE_HeadBagged", true, true];
        ["local", _t] remoteExecCall ["comspec_sse_fnc_headBag", _t];
        _by playActionNow "PutDown";
        true
    };
    case "off": {
        if (!_bagged) exitWith { false };
        _t setVariable ["COMSPEC_SSE_HeadBagged", false, true];
        ["local", _t] remoteExecCall ["comspec_sse_fnc_headBag", _t];
        if (_by canAdd "COMSPEC_SSE_HeadBag") then { _by addItem "COMSPEC_SSE_HeadBag"; } else {
            private _h = "GroundWeaponHolder" createVehicle (getPosATL _by);
            _h addItemCargoGlobal ["COMSPEC_SSE_HeadBag", 1];
        };
        _by playActionNow "PutDown";
        true
    };
    case "local": {
        if (!local _t) exitWith { false };
        // Lunettes changées là où l'unité est locale (addGoggles est local).
        if (_t getVariable ["COMSPEC_SSE_HeadBagged", false]) then {
            removeGoggles _t;
            _t addGoggles _model;
        } else {
            if ((goggles _t) isEqualTo _model) then { removeGoggles _t; };
            private _prev = _t getVariable ["COMSPEC_SSE_HeadBagPrev", ""];
            if (_prev isNotEqualTo "") then { _t addGoggles _prev; };
        };
        if (!hasInterface || {_t != player}) exitWith { true };
        if (_t getVariable ["COMSPEC_SSE_HeadBagged", false]) then {
            "comspec_sse_headBag" cutText ["", "BLACK OUT", 1.5, true];
            0.5 fadeSound 0.45;
            // Le sac reste noir tant qu'il est porté (mort, réapparition : levé).
            [{ !((_this select 0) getVariable ["COMSPEC_SSE_HeadBagged", false]) || {!alive (_this select 0)} || {(_this select 0) != player} }, {
                "comspec_sse_headBag" cutText ["", "BLACK IN", 1.5, true];
                1 fadeSound 1;
            }, [_t]] call CBA_fnc_waitUntilAndExecute;
        };
        true
    };
    default { false };
};
