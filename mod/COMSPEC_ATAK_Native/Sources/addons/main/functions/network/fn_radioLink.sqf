/*
    Bilan radio d'un lien (bande C 4,9 GHz du réseau ATAK) entre deux antennes : marge en dB au-dessus du seuil de réception.
    La portée donnée par la mission (relais, maillage) est celle d'un lien dégagé : marge 0 dB à cette distance, +6 dB
    à mi-distance (affaiblissement en espace libre, 20 log10 (portée / distance)). On retire ensuite :
      - relief et courbure de la Terre : profil du terrain entre les deux antennes (rayon terrestre équivalent 4/3),
        dégagement de la première zone de Fresnel et diffraction en lame de couteau (ITU-R P.526) sur l'arête principale,
        +2 dB par crête secondaire. Une crête qui effleure coûte environ 6 dB, une colline franche 15 à 30 dB ;
      - feuillage (mode "full") : là où le rayon passe à moins de 20 m du sol, arbres comptés autour de points du trajet,
        profondeur de feuillage traversée et affaiblissement de Weissberger à 4,9 GHz ; feuillage mouillé par la pluie +20 % ;
      - bâtiments (mode "full") : chaque bâtiment traversé sur les derniers 1 500 m avant la cible, 12 dB (deux murs) ;
        ceux à moins de 15 m de chaque antenne ne comptent pas (abri du joueur : fn_linkQuality ; support du relais).
    Réglage serveur comspec_atak_native_net_realism : 0 simplifié (ancien modèle : relief coupé = portée x 0,35, ni
    feuillage ni bâtiments), 1 réaliste (par défaut : feuillage et bâtiments x 0,6), 2 exigeant (pertes complètes).
    Params : [antenne A ASL, cible B ASL, portée m, mode ("fast" : relief seul | "coarse" : relief, profil grossier |
              "full" : relief, feuillage, bâtiments), objets ignorés []]
    Renvoie [marge dB, [[cause, dB perdus]...], distance m].
*/
params ["_a", "_b", "_range", ["_mode", "fast"], ["_ign", []]];
private _d = (_a distance _b) max 1;
private _real = missionNamespace getVariable ["comspec_atak_native_net_realism", 1];
if (!(_real isEqualType 0)) then { _real = 1; };
private _fs = 20 * log ((_range max 1) / _d);
// Au-delà du double de la portée, rien ne peut plus passer (aucun gain n'atteint +6 dB) : pas de profil à calculer.
if (_d > 2 * _range) exitWith { [_fs, [], _d] };
private _losses = [];
if (_real isEqualTo 0) exitWith {
    if (terrainIntersectASL [_a, _b]) then { _losses pushBack ["Relief entre les antennes", 9.1]; };
    [_fs - 9.1 * (count _losses), _losses, _d]
};
private _envK = [0.6, 1] select (_real >= 2);
private _lambda = 0.0606;
private _d2 = (_a distance2D _b) max 1;
private _za = _a select 2;
private _zb = _b select 2;
// Points du profil : "full" un tous les 60 m, "fast" tous les 150 m, "coarse" (maillage entre téléphones) tous les 300 m.
private _mi = ["full", "fast", "coarse"] find _mode;
if (_mi < 0) then { _mi = 1; };
([[60, 6, 24], [150, 6, 24], [300, 4, 10]] select _mi) params ["_step", "_nMin", "_nMax"];
private _n = round (((_d2 / _step) max _nMin) min _nMax);
private _seg = _d2 / (_n + 1);
private _vmax = -99;
private _edges = 0;
private _prevV = -99;
private _canopy = [];
for "_i" from 1 to _n do {
    private _t = _i / (_n + 1);
    private _p = [(_a select 0) + ((_b select 0) - (_a select 0)) * _t, (_a select 1) + ((_b select 1) - (_a select 1)) * _t];
    private _g = (getTerrainHeightASL _p) max 0;
    private _d1 = _t * _d2;
    private _dd = _d2 - _d1;
    private _line = _za + (_zb - _za) * _t;
    private _h = _g + (_d1 * _dd) / 17000000 - _line;
    private _v = _h * sqrt (2 * _d2 / (_lambda * _d1 * _dd));
    if (_v > _vmax) then { _vmax = _v; };
    // Crêtes secondaires : chaque passage au-dessus de la ligne (v > 0) distinct du précédent.
    if (_v > 0 && {_prevV <= 0}) then { _edges = _edges + 1; };
    _prevV = _v;
    if ((_line - _g) < 20) then { _canopy pushBack _p; };
};
if (_vmax > -0.78) then {
    private _j = 6.9 + 20 * log ((sqrt ((_vmax - 0.1) ^ 2 + 1)) + _vmax - 0.1);
    _j = (_j + 2 * ((_edges - 1) max 0)) min 40;
    if (_j > 0.5) then { _losses pushBack [["Zone de Fresnel partiellement masquée", "Relief entre les antennes"] select (_vmax > 0), _j]; };
};
if (_mode isEqualTo "full") then {
    // Feuillage : au plus 12 points du trajet interrogés, répartis sur la partie du rayon qui passe dans la canopée.
    if (_canopy isNotEqualTo []) then {
        private _step = ((count _canopy) / 12) max 1;
        private _depth = 0;
        for "_k" from 0 to (count _canopy) - 1 step _step do {
            private _p = _canopy select (floor _k);
            private _trees = count (nearestTerrainObjects [_p, ["TREE", "SMALL TREE", "FOREST"], 10, false, true]);
            _depth = _depth + _seg * _step * ((_trees / 4) min 1);
        };
        if (_depth >= 3) then {
            private _w = if (_depth < 14) then { 0.71 * _depth } else { 2.1 * (_depth ^ 0.588) };
            _w = (_w * _envK * (1 + 0.2 * rain)) min 35;
            _losses pushBack [format ["Feuillage traversé (%1 m)", round _depth], _w];
        };
    };
    // Bâtiments entre les deux antennes, près de la cible (lineIntersectsSurfaces porte à 5 km au plus).
    private _from = if (_d > 1500) then { _b vectorAdd ((_a vectorDiff _b) vectorMultiply (1500 / _d)) } else { _a };
    private _hits = lineIntersectsSurfaces [_from, _b, _ign param [0, objNull], _ign param [1, objNull], true, 8, "GEOM", "NONE"];
    private _blds = [];
    {
        private _o = _x select 2;
        if (isNull _o || {_o in _blds} || {typeOf _o isEqualTo ""} || {_o isKindOf "AllVehicles"}) then { continue };
        private _ip = _x select 0;
        if ((_ip distance _b) < 15 || {(_ip distance _a) < 15}) then { continue };
        _blds pushBack _o;
    } forEach _hits;
    if (_blds isNotEqualTo []) then {
        _losses pushBack [format ["Bâtiments sur le trajet (%1)", count _blds], ((count _blds) * 12 * _envK) min 30];
    };
};
private _sum = 0;
{ _sum = _sum + (_x select 1); } forEach _losses;
[_fs - _sum, _losses, _d]
