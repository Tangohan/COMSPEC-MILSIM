/*
    Calque Wave Relay sur la carte (appelé depuis mapOnDraw), réglage profil COMSPEC_ATAK_MeshOnMap :
    - couverture réseau calculée (fn_relayNet "coverage") : vert débit fort, jaune, orange, rouge faible, gris zone blanche ;
    - liens du maillage entre téléphones, vert au-dessus de 60 %, orange au-dessus de 30 %, rouge en dessous.
    Calque « Relais » (réglage profil COMSPEC_ATAK_RelaysLayer, affiché par défaut, masquable dans CALQUES) : relais et
    passerelles de véhicule ou PC mobiles en pastille (anneau de couleur sur fond sombre, antenne), nom puis
    « portée · débit · places » en dessous ; vert : relais utilisé par mon téléphone (trait pointillé vers moi, portée
    tracée), bleu : disponible, orange : saturé ou batterie faible, gris : hors service avec la raison, rouge barré :
    détruit ; répéteurs reliés en pointillé au relais qui les raccorde ; portées des autres relais en léger quand on zoome,
    textes masqués en vue très large. L'étiquette donne aussi la fréquence, la perte et la santé si elle n'est pas nominale ;
    troisième anneau : relais préféré. Clic gauche sur une pastille : fiche du relais (fn_mapCtxMenu).
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
if (profileNamespace getVariable ["COMSPEC_ATAK_RelaysLayer", true]) then {
    private _scale = ctrlMapScale _map;
    private _txt = _scale < 0.25;
    private _mine = uiNamespace getVariable ["COMSPEC_ATAK_RelayLast", objNull];
    private _font = COMSPEC_ATAK_FontSet select 0;
    private _fontB = COMSPEC_ATAK_FontSet select 1;
    // Ligne sous l'étiquette : même point décalé d'une hauteur de texte à l'écran.
    private _below = { params ["_p", "_k"]; _map ctrlMapScreenToWorld ((_map ctrlMapWorldToScreen _p) vectorAdd [0, 0.024 * _ts * _k]) };
    {
        _x params ["_o", "_ap", "_name", "_kind", "_rng", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "", "_up", "_fq", "", "_loss", "", "_hl", "", "_co"];
        private _p = getPosASLVisual _o;
        private _use = _ok && {_o isEqualTo _mine};
        private _sat = _sl > 0 && {_u > _sl};
        private _col = switch (true) do {
            case (_st isEqualTo "détruit"): { [0.92, 0.30, 0.25, 1] };
            case (!_ok): { [0.62, 0.66, 0.64, 1] };
            case (_use): { [0.36, 0.85, 0.42, 1] };
            case (_sat || {_bt >= 0 && {_bt < 20}} || {_hl isEqualTo "critique"}): { [1, 0.65, 0.2, 1] };
            default { [0.28, 0.70, 1, 1] };
        };
        // Portée : celle du relais utilisé toujours, les autres en léger quand on zoome.
        if (_ok && {_use || {_scale < 0.12}}) then {
            _map drawEllipse [_p, _rng, _rng, 0, [_col select 0, _col select 1, _col select 2, [0.18, 0.45] select _use], ""];
        };
        if (_st in ["éteint", "batterie vide", "brouillé", "sans liaison"] && {_scale < 0.12}) then {
            _map drawEllipse [_p, _rng, _rng, 0, [0.5, 0.5, 0.5, 0.25], ""];
        };
        // Répéteur : pointillé vers le relais qui le raccorde.
        if (_ok && {!isNull _up}) then {
            private _q = getPosASLVisual _up;
            private _len = _p distance2D _q;
            private _dir = _p getDir _q;
            for "_k" from 0 to (floor (_len / (_r * 3))) step 2 do {
                _map drawLine [_p getPos [_k * _r * 3, _dir], _p getPos [((_k + 1) * _r * 3) min _len, _dir], [0.28, 0.70, 1, 0.7]];
            };
        };
        // Relais utilisé : pointillé de mon téléphone à son antenne.
        if (_use) then {
            private _me = getPosASLVisual (vehicle player);
            private _len = _me distance2D _p;
            private _dir = _me getDir _p;
            for "_k" from 0 to (floor (_len / (_r * 2))) step 2 do {
                _map drawLine [_me getPos [_k * _r * 2, _dir], _me getPos [((_k + 1) * _r * 2) min _len, _dir], [0.36, 0.85, 0.42, 0.75]];
            };
        };
        // Pastille : fond sombre, anneau de couleur (double si utilisé), antenne ou symbole OTAN du véhicule / PC.
        _map drawEllipse [_p, _r * 1.6, _r * 1.6, 0, [0.04, 0.06, 0.05, 0.82], _fill];
        _map drawEllipse [_p, _r * 1.6, _r * 1.6, 0, _col, ""];
        if (_use) then { _map drawEllipse [_p, _r * 2.1, _r * 2.1, 0, _col, ""]; };
        // Relais préféré (fiche du relais) : troisième anneau.
        if (_o isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_RelayPin", objNull])) then { _map drawEllipse [_p, _r * 2.6, _r * 2.6, 0, [_col select 0, _col select 1, _col select 2, 0.6], ""]; };
        private _icon = switch (_kind) do {
            case "cp": { "\A3\ui_f\data\map\markers\nato\b_hq.paa" };
            case "gw": { "\A3\ui_f\data\map\markers\nato\b_motor_inf.paa" };
            default { "\A3\ui_f\data\map\mapcontrol\transmitter_CA.paa" };
        };
        _map drawIcon [_icon, _col, _p, 15, 15, 0, "", 0, 0, _font, "center"];
        if (_st isEqualTo "détruit") then {
            _map drawLine [_p getPos [_r * 2.2, 45], _p getPos [_r * 2.2, 225], _col];
            _map drawLine [_p getPos [_r * 2.2, 135], _p getPos [_r * 2.2, 315], _col];
        };
        if (!_txt) then { continue };
        // Étiquette : nom (gras), puis « portée · débit · places » ou la raison de la panne.
        private _lp = _p getPos [_r * 2.4, 90];
        _map drawIcon [_none, [1, 1, 1, [0.75, 1] select _ok], _lp, 0, 0, 0, _name + (["", "  (utilisé)"] select _use), 2, 0.026 * _ts, _fontB, "right"];
        private _sub = if (_ok) then {
            private _e = format ["%1 km · %2 Mbit/s", (_rng / 1000) toFixed 1, _mb toFixed 1];
            if (_sl > 0) then { _e = _e + format [" · %1/%2", _u, _sl]; };
            if (_bt >= 0) then { _e = _e + format [" · batt. %1 %%", round _bt]; };
            _e = _e + format [" · %1 MHz · perte %2 %%", _fq, _loss];
            if (_co > 0) then { _e = _e + " · " + ("canal partagé" call comspec_atak_native_fnc_tr); };
            if (_hl in ["dégradé", "critique"]) then { _e = _e + " · " + (_hl call comspec_atak_native_fnc_tr); };
            _e
        } else { _st };
        _map drawIcon [_none, [_col select 0, _col select 1, _col select 2, 0.95], [_lp, 1] call _below, 0, 0, 0, _sub, 2, 0.022 * _ts, _font, "right"];
    } forEach (["list"] call comspec_atak_native_fnc_relayNet);
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
