/*
    IA RP : ordres suivis par une IA à qui l'on parle (fn_iarpServer), exécutés là où elle est locale (serveur, hôte, HC).
      surrender  reddition (ACE, sinon geste du jeu)       stop  s'arrête et attend       free  repart (circulez)
      pos [UP | MIDDLE | DOWN]  à genoux, couché           follow [joueur]  suit le joueur (4 min, 80 m au plus)
      out  descend du véhicule                             unlock  (véhicule) déverrouillé pour la fouille
*/
["comspec_sse_iarpAiDo", {
    params [["_u", objNull], ["_cmd", ""], ["_a", []]];
    if (isNull _u || {!local _u} || {!alive _u}) exitWith {};
    if (_cmd isEqualTo "unlock") exitWith { _u lock 0; };
    _u setVariable ["comspec_sse_iarpFollow", objNull];
    switch (_cmd) do {
        case "surrender": {
            if (isClass (configFile >> "CfgPatches" >> "ace_captives")) then { ["ace_captives_setSurrendered", [_u, true], _u] call CBA_fnc_targetEvent; } else { _u action ["Surrender", _u]; };
        };
        case "stop": { doStop _u; _u disableAI "PATH"; };
        case "free": { _u enableAI "PATH"; _u setUnitPos "AUTO"; _u doFollow (leader group _u); };
        case "pos": { _u setUnitPos (_a param [0, "MIDDLE"]); };
        case "out": { [_u] allowGetIn false; unassignVehicle _u; moveOut _u; };
        case "follow": {
            private _l = _a param [0, objNull];
            if (isNull _l) exitWith {};
            _u enableAI "PATH";
            _u setUnitPos "UP";
            _u setVariable ["comspec_sse_iarpFollow", _l];
            [{
                params ["_args", "_h"];
                _args params ["_u", "_l", "_end"];
                if (!alive _u || {isNull _l} || {time > _end} || {(_u getVariable ["comspec_sse_iarpFollow", objNull]) isNotEqualTo _l} || {(_u distance _l) > 80}) exitWith {
                    [_h] call CBA_fnc_removePerFrameHandler;
                    if ((_u getVariable ["comspec_sse_iarpFollow", objNull]) isEqualTo _l) then { _u setVariable ["comspec_sse_iarpFollow", objNull]; doStop _u; };
                };
                if ((_u distance _l) > 3) then { _u doMove ((getPosATL _l) getPos [2, _l getDir _u]); };
            }, 2, [_u, _l, time + 240]] call CBA_fnc_addPerFrameHandler;
        };
    };
}] call CBA_fnc_addEventHandler;
