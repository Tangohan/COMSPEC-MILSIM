/*
    Calque Wave Relay sur la carte (appelé depuis mapOnDraw) : liens du maillage entre téléphones,
    vert au-dessus de 60 %, orange au-dessus de 30 %, rouge en dessous ; passerelle cerclée de bleu.
    Activé par le réglage profil COMSPEC_ATAK_MeshOnMap (interrupteur de l'app Wave Relay).
    Params : [contrôle carte]
*/
params ["_map"];
if !(profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false]) exitWith {};
// Le calcul (lignes de vue entre antennes) est mis en cache 3 s par l'action « scan ».
private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
private _nodes = _mesh getOrDefault ["nodes", []];
private _hops = _mesh getOrDefault ["hops", []];
// Cercles de taille constante à l'écran, quel que soit le zoom.
private _r = (_map ctrlMapScreenToWorld [0, 0]) distance2D (_map ctrlMapScreenToWorld [0.008, 0]);
// Positions relues à chaque image : les traits suivent les joueurs entre deux calculs.
private _pos = _nodes apply { [_x select 1, getPosWorld (_x select 0)] select (alive (_x select 0)) };
{
    _x params ["_a", "_b", "_q"];
    private _col = switch (true) do { case (_q >= 60): { [0.36, 0.85, 0.42, 0.85] }; case (_q >= 30): { [1, 0.65, 0.2, 0.85] }; default { [0.9, 0.25, 0.2, 0.85] }; };
    _map drawLine [_pos select _a, _pos select _b, _col];
} forEach (_mesh getOrDefault ["links", []]);
{
    _x params ["", "", "_name", "_kind"];
    private _p = _pos select _forEachIndex;
    private _isolated = (_hops param [_forEachIndex, -1]) < 0;
    _map drawEllipse [_p, _r, _r, 0, [[0.36, 0.85, 0.42, 0.9], [0.9, 0.25, 0.2, 0.9]] select _isolated, ""];
    if (_kind isEqualTo "gw" || {_forEachIndex isEqualTo (_mesh getOrDefault ["gw", -1])}) then {
        _map drawEllipse [_p, _r * 2.2, _r * 2.2, 0, [0.28, 0.70, 1, 1], ""];
        if (_kind isEqualTo "gw") then {
            _map drawIcon ["\A3\ui_f\data\map\markers\nato\b_hq.paa", [0.28, 0.70, 1, 1], _p, 20, 20, 0, _name, 1, 0.028 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1]), "RobotoCondensedBold", "right"];
        };
    };
} forEach _nodes;
