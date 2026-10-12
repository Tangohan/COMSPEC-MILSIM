/*
    Animations d'un relevé biométrique : l'opérateur, son appareil et le sujet.

    [_operator, _target, _modality, _phase] call comspec_sse_fnc_bioAnimate
      _modality : empreintes | iris | visage | adn | latente | complet
      _phase    : "start" (au début de la barre de progression) | "stop" (fin ou interruption)

    Ce que l'on voit :
      - sujet au sol (mort, inconscient, couché) : l'opérateur s'agenouille et travaille
        sur le corps (soins ACE, mains au sol) ;
      - sujet debout : l'opérateur reste debout face à lui, appareil en main ; le sujet
        coopérant tend la main (empreintes) ou regarde l'appareil (iris, visage) ;
      - un appareil apparaît dans la main droite de l'opérateur (SEEK, kit ADN,
        poudre à latentes) et disparaît à la fin ;
      - bips de lecture pendant le relevé d'empreintes et d'iris.
*/
params [["_op", objNull, [objNull]], ["_t", objNull, [objNull]], ["_mod", "empreintes", [""]], ["_phase", "start", [""]]];
if (isNull _op || {!local _op}) exitWith { false };

if (isNull _t) then { _t = _op getVariable ["comspec_sse_bioAnimTarget", objNull]; };
private _stop = {
    private _dev = _op getVariable ["comspec_sse_bioAnimDevice", objNull];
    if (!isNull _dev) then { deleteVehicle _dev; };
    _op setVariable ["comspec_sse_bioAnimDevice", nil];
    private _h = _op getVariable ["comspec_sse_bioAnimBeep", -1];
    if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; };
    _op setVariable ["comspec_sse_bioAnimBeep", nil];
    // Seulement si un relevé animé est en cours (pas pour les autres actions SSE).
    if (!isNil { _op getVariable "comspec_sse_bioAnimKneel" } && {vehicle _op == _op} && {alive _op}) then {
        private _kneel = _op getVariable ["comspec_sse_bioAnimKneel", false];
        _op playMoveNow (["AmovPercMstpSnonWnonDnon", "AmovPknlMstpSnonWnonDnon"] select _kneel);
    };
    _op setVariable ["comspec_sse_bioAnimKneel", nil];
    _op setVariable ["comspec_sse_bioAnimTarget", nil];
    if (!isNull _t && {alive _t} && {!isPlayer _t}) then { ["comspec_sse_bioAnimSubject", [_t, objNull, ""], _t] call CBA_fnc_targetEvent; };
};

if (_phase isEqualTo "stop") exitWith { call _stop; true };
call _stop;
if (vehicle _op != _op) exitWith { false };

private _down = isNull _t
    || {!alive _t}
    || {_t getVariable ["ACE_isUnconscious", false]}
    || {(lifeState _t) isEqualTo "INCAPACITATED"}
    || {((_t selectionPosition "head") select 2) < 0.8}
    || {_mod isEqualTo "latente"};
_op setVariable ["comspec_sse_bioAnimKneel", _down];
_op setVariable ["comspec_sse_bioAnimTarget", _t];

// Face au sujet.
if (!isNull _t) then { _op setDir (_op getDir _t); };

// Opérateur.
if (_down) then {
    _op playMoveNow (selectRandom ["AinvPknlMstpSnonWnonDnon_medic0", "AinvPknlMstpSnonWnonDnon_medic1", "AinvPknlMstpSnonWnonDnon_medic3", "AinvPknlMstpSnonWnonDnon_medic4"]);
} else {
    // Debout, arme à la bretelle : appareil tenu devant soi.
    _op action ["SwitchWeapon", _op, _op, 299];
    if (_mod isEqualTo "adn") then { _op playActionNow "PutDown"; };
};

// Appareil en main.
private _devClass = switch (_mod) do {
    case "adn": { "Land_PencilBlue_F" };
    case "latente": { "Land_PencilYellow_F" };
    default { "Land_MobilePhone_smart_F" };
};
if (isClass (configFile >> "CfgVehicles" >> _devClass)) then {
    private _dev = createSimpleObject [getText (configFile >> "CfgVehicles" >> _devClass >> "model"), [0, 0, 0], false];
    if (isNull _dev) exitWith {};
    _dev attachTo [_op, [-0.02, 0.06, 0.02], "righthand", true];
    _dev setVectorDirAndUp [[0, 0, 1], [0, 1, 0]];
    _op setVariable ["comspec_sse_bioAnimDevice", _dev];
};

// Sujet vivant et debout : il présente la main ou regarde l'appareil.
if (!_down && {!isNull _t} && {alive _t} && {!isPlayer _t}) then {
    private _restrained = (_t getVariable ["ace_captives_isHandcuffed", false]) || {_t getVariable ["ace_captives_isSurrendering", false]};
    private _gesture = ["", "GestureGo"] select (!_restrained && {_mod in ["empreintes", "complet"]});
    // Événement CBA vers la machine du sujet (les commandes distantes sont en liste blanche).
    ["comspec_sse_bioAnimSubject", [_t, _op, _gesture], _t] call CBA_fnc_targetEvent;
};

// Bips de lecture (empreintes, iris).
if (_mod in ["empreintes", "iris", "complet"]) then {
    private _h = [{
        params ["_args"];
        _args params ["_op"];
        playSound3D ["a3\sounds_f\sfx\beep_target.wss", _op, false, getPosASL _op, 0.6, 1.6, 15];
    }, 1.4, [_op]] call CBA_fnc_addPerFrameHandler;
    _op setVariable ["comspec_sse_bioAnimBeep", _h];
};
true
