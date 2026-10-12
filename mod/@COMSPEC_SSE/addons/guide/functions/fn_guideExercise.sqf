/*
    Tutoriel Zeus : pose une cellule d'entraînement complète à l'endroit choisi (serveur).

    Cinq civils d'une même cellule (organisation générée, rien de connu des joueurs) :
    un qui se rend, un mort, trois debout ; le téléphone et les documents du chef
    à côté de lui, une caisse d'armes rattachée à la cellule. Tout est exploitable
    SSE et éditable par les Zeus. De quoi dérouler un drill complet : contrôle,
    relevés, sac de tête, saisie et scellés, exploitation, tableau des liens.

    [_pos, _requester] call comspec_sse_fnc_guideExercise
*/
params [["_pos", [0, 0, 0], [[]]], ["_req", objNull, [objNull]]];
if (!isServer) exitWith { ["comspec_sse_guideExercise", [_pos, player]] call CBA_fnc_serverEvent; false };

private _classes = ["C_man_polo_1_F", "C_man_polo_2_F", "C_man_polo_4_F", "C_man_1_1_F", "C_man_p_fugitive_F", "C_Man_casual_1_F"] select { isClass (configFile >> "CfgVehicles" >> _x) };
if (_classes isEqualTo []) exitWith { false };
private _grp = createGroup [civilian, true];
private _units = [];
for "_i" from 0 to 4 do {
    private _p = _pos getPos [4 + random 8, _i * 72 + random 30];
    private _u = _grp createUnit [selectRandom _classes, _p, [], 0, "NONE"];
    _u disableAI "PATH";
    _u setDir (random 360);
    _u setVariable ["comspec_sse_enabled", true, true];
    [_u, "PERSON", "INSURGENT", "STANDARD"] call comspec_sse_fnc_makeSearchable;
    _units pushBack _u;
};

private _name = format ["Cellule d'exercice %1", selectRandom (([] call comspec_sse_fnc_orgTables) get "codes")];
[_units, _name, "cellule", -1, 0, "elevee"] call comspec_sse_fnc_orgGenerate;
private _leader = _units select 0;
{ if (((_x getVariable ["COMSPEC_SSE_Org", []]) param [1, ""]) isEqualTo "chef") exitWith { _leader = _x; }; } forEach _units;

// Un sujet se rend, un autre est mort (relevés sur un corps).
private _others = _units - [_leader];
private _surr = _others select 0;
private _dead = _others select 1;
["ace_captives_setSurrendered", [_surr, true], _surr] call CBA_fnc_targetEvent;
_dead setDamage 1;

// Objets du chef, et une cache rattachée à la cellule.
private _objs = [];
{
    _x params ["_cls", "_type", "_off"];
    if (isClass (configFile >> "CfgVehicles" >> _cls)) then {
        private _o = createVehicle [_cls, (getPosATL _leader) vectorAdd _off, [], 0, "CAN_COLLIDE"];
        _o setVariable ["comspec_sse_forcedType", _type, true];
        [_o, _type, "INSURGENT", "STANDARD"] call comspec_sse_fnc_makeSearchable;
        _objs pushBack _o;
    };
} forEach [
    ["Land_MobilePhone_smart_F", "PHONE", [0.6, 0.4, 0]],
    ["Land_File1_F", "DOCUMENT", [-0.5, 0.6, 0]],
    ["Box_East_Wps_F", "CONTAINER", [3, -2, 0]]
];
if ((count _objs) >= 2) then {
    [_objs select 0, _leader, "OWNER", 0.9, "EXERCICE"] call comspec_sse_fnc_linkEntities;
    [_objs select 1, _leader, "OWNER", 0.9, "EXERCICE"] call comspec_sse_fnc_linkEntities;
};
if ((count _objs) >= 3) then { (_objs select 2) setVariable ["COMSPEC_SSE_OrgLinked", _name, true]; };

{ _x addCuratorEditableObjects [_units + _objs, true]; } forEach allCurators;

private _msg = format ["Exercice prêt : %1 (5 personnes, rien de connu des joueurs). Consignes : contrôler et relever le sujet qui se rend (sac de tête ensuite), relever le corps, saisir et sceller le téléphone et les documents, les exploiter, ouvrir le tableau des liens.", _name];
if (!isNull _req) then { ["comspec_sse_campNote", [_msg], _req] call CBA_fnc_targetEvent; };
true
