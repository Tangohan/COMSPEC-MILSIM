/*
    Calque Wave Relay sur la carte (appelé depuis mapOnDraw), réglage profil COMSPEC_ATAK_MeshOnMap :
    - couverture réseau calculée (fn_relayNet "coverage") : vert débit fort, jaune, orange, rouge faible, gris zone blanche ;
    - relais (antenne, nom, portée, débit, saturation, batterie) et passerelles de véhicule ou PC mobiles ; relais hors
      service en gris avec la raison (détruit barré) ; répéteurs reliés en pointillé au relais qui les raccorde ;
    - liens du maillage entre téléphones, vert au-dessus de 60 %, orange au-dessus de 30 %, rouge en dessous.
    Toujours affichées (même calque masqué) : les triangulations récentes (fn_relayNet "locate"), cercles de distance
    estimée depuis chaque source et zone d'incertitude autour de la position trouvée, pâlissant sur 10 min.
    Params : [contrôle carte]
*/
params ["_map"];
private _ts = uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1];
private _none = "#(argb,8,8,3)color(0,0,0,0)";
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
// Cercles de taille constante à l'écran, quel que soit le zoom.
private _r = (_map ctrlMapScreenToWorld [0, 0]) distance2D (_map ctrlMapScreenToWorld [0.008, 0]);
if (profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false]) then {
    private _cv = missionNamespace getVariable ["COMSPEC_ATAK_Coverage", []];
    if ((count _cv) >= 8) then {
        _cv params ["", "_cell", "_cells"];
        private _a = _cell / 2;
        (ctrlPosition _map) params ["_cx", "_cy", "_cw", "_ch"];
        private _tl = _map ctrlMapScreenToWorld [_cx, _cy];
        private _br = _map ctrlMapScreenToWorld [_cx + _cw, _cy + _ch];
        private _x0 = ((_tl select 0) min (_br select 0)) - _cell;
        private _x1 = ((_tl select 0) max (_br select 0)) + _cell;
        private _y0 = ((_tl select 1) min (_br select 1)) - _cell;
        private _y1 = ((_tl select 1) max (_br select 1)) + _cell;
        {
            _x params ["_p", "_mb"];
            if ((_p select 0) < _x0 || {(_p select 0) > _x1} || {(_p select 1) < _y0} || {(_p select 1) > _y1}) then { continue };
            private _col = switch (true) do {
                case (_mb >= 4): { [0.2, 0.85, 0.3, 0.28] };
                case (_mb >= 1.5): { [0.75, 0.85, 0.2, 0.28] };
                case (_mb >= 0.5): { [1, 0.6, 0.15, 0.28] };
                case (_mb > 0): { [0.9, 0.2, 0.15, 0.3] };
                default { [0.15, 0.15, 0.15, 0.3] };
            };
            _map drawRectangle [_p, _a, _a, 0, _col, _fill];
        } forEach _cells;
    };
    // Relais et passerelles, même hors du maillage (aucun téléphone à portée) : icône, nom, portée, état.
    // Hors service (détruit, éteint, batterie vide, brouillé, sans liaison) : gris, raison en clair, détruit barré.
    // Répéteur : trait pointillé vers le relais qui le raccorde au réseau.
    private _rl = ["list"] call comspec_atak_native_fnc_relayNet;
    {
        _x params ["_o", "_ap", "_name", "_kind", "_rng", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "", "_up"];
        private _p = getPosASLVisual _o;
        if (!_ok) then {
            _map drawIcon ["\A3\ui_f\data\map\mapcontrol\transmitter_CA.paa", [0.5, 0.5, 0.5, 0.9], _p, 20, 20, 0, format ["%1 (%2)", _name, _st], 1, 0.026 * _ts, (COMSPEC_ATAK_FontSet select 0), "right"];
            if (_st isEqualTo "détruit") then { _map drawLine [_p getPos [_r * 1.5, 45], _p getPos [_r * 1.5, 225], [0.9, 0.25, 0.2, 0.9]]; };
            if (_st in ["éteint", "batterie vide", "brouillé", "sans liaison"]) then { _map drawEllipse [_p, _rng, _rng, 0, [0.5, 0.5, 0.5, 0.35], ""]; };
            continue;
        };
        if (!isNull _up) then {
            private _q = getPosASLVisual _up;
            private _len = _p distance2D _q;
            private _dir = _p getDir _q;
            for "_k" from 0 to (floor (_len / (_r * 3))) step 2 do {
                _map drawLine [_p getPos [_k * _r * 3, _dir], _p getPos [((_k + 1) * _r * 3) min _len, _dir], [0.28, 0.70, 1, 0.8]];
            };
        };
        private _sat = _sl > 0 && {_u > _sl};
        private _col = [[0.28, 0.70, 1, 1], [1, 0.65, 0.2, 1]] select (_sat || {_bt >= 0 && {_bt < 20}});
        _map drawEllipse [_p, _rng, _rng, 0, [0.28, 0.70, 1, 0.45], ""];
        private _icon = switch (_kind) do {
            case "cp": { "\A3\ui_f\data\map\markers\nato\b_hq.paa" };
            case "gw": { "\A3\ui_f\data\map\markers\nato\b_motor_inf.paa" };
            default { "\A3\ui_f\data\map\mapcontrol\transmitter_CA.paa" };
        };
        private _extra = "";
        if (_sat) then { _extra = _extra + format [" · saturé %1/%2", _u, _sl]; };
        if (_bt >= 0) then { _extra = _extra + format [" · batt. %1 %%", round _bt]; };
        _map drawIcon [_icon, _col, _p, 22, 22, 0, format ["%1 · %2 km · %3 Mbit/s%4", _name, (_rng / 1000) toFixed 1, _mb toFixed 1, _extra], 1, 0.026 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
    } forEach _rl;
    // Le calcul (lignes de vue entre antennes) est mis en cache 3 s par l'action « scan ».
    private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
    private _nodes = _mesh getOrDefault ["nodes", []];
    private _hops = _mesh getOrDefault ["hops", []];
    // Positions relues à chaque image : les traits suivent les joueurs entre deux calculs.
    private _pos = _nodes apply { [_x select 1, getPosWorld (_x select 0)] select (alive (_x select 0)) };
    {
        _x params ["_a", "_b", "_q"];
        private _col = switch (true) do { case (_q >= 60): { [0.36, 0.85, 0.42, 0.85] }; case (_q >= 30): { [1, 0.65, 0.2, 0.85] }; default { [0.9, 0.25, 0.2, 0.85] }; };
        _map drawLine [_pos select _a, _pos select _b, _col];
    } forEach (_mesh getOrDefault ["links", []]);
    {
        _x params ["", "", "_name", "_kind"];
        if (_kind isEqualTo "gw") then { continue };
        private _p = _pos select _forEachIndex;
        private _isolated = (_hops param [_forEachIndex, -1]) < 0;
        _map drawEllipse [_p, _r, _r, 0, [[0.36, 0.85, 0.42, 0.9], [0.9, 0.25, 0.2, 0.9]] select _isolated, ""];
        // Chef de groupe servant de passerelle faute de relais.
        if (_forEachIndex isEqualTo (_mesh getOrDefault ["gw", -1])) then { _map drawEllipse [_p, _r * 2.2, _r * 2.2, 0, [0.28, 0.70, 1, 1], ""]; };
    } forEach _nodes;
};
private _now = [time, serverTime] select isMultiplayer;
{
    _x params ["_name", "_est", "_rad", "_src", "_t"];
    private _age = _now - _t;
    if (_age > 600) then { continue };
    private _al = 1 - (_age / 600) * 0.7;
    {
        _x params ["_sp", "_d"];
        _map drawEllipse [_sp, _d, _d, 0, [1, 0.75, 0.2, 0.55 * _al], ""];
        if (_est isNotEqualTo []) then { _map drawLine [_sp, _est, [1, 0.75, 0.2, 0.25 * _al]]; };
    } forEach _src;
    if (_est isNotEqualTo []) then {
        _map drawEllipse [_est, _rad, _rad, 0, [1, 0.55, 0.1, 0.25 * _al], _fill];
        _map drawEllipse [_est, _rad, _rad, 0, [1, 0.55, 0.1, _al], ""];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\unknown_CA.paa", [1, 0.55, 0.1, _al], _est, 20, 20, 0, format ["%1 ± %2 m", _name, _rad], 1, 0.028 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
    } else {
        _map drawIcon [_none, [1, 0.75, 0.2, _al], (_src select 0) select 0, 0, 0, 0, format ["%1 : cercle %2 m", _name, round ((_src select 0) select 1)], 1, 0.026 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
    };
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_Trilat", []]);
