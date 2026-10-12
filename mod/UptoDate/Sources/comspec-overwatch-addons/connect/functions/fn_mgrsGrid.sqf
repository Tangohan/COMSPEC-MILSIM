/*
    Grille militaire MGRS (format OTAN), par exemple « 35S LC 12345 67890 » (précision 1 m).
    Le terrain est placé sur le globe par sa latitude et sa longitude (CfgWorlds ; dans Arma, une latitude négative
    veut dire hémisphère nord). Le centre de la carte est converti en UTM (WGS84), puis la position est décalée en
    mètres depuis ce centre : zone, bande, carré de 100 km et coordonnées sont ceux d'un vrai GPS militaire.
    Params : [position monde ou objet]
    Retour : texte MGRS, "" si la position est invalide.
*/
params [["_pos", [0, 0, 0], [[], objNull]]];
if (_pos isEqualType objNull) then {
    if (isNull _pos) exitWith { "" };
    _pos = getPosWorld _pos;
};
if (!(_pos isEqualType []) || {(count _pos) < 2}) exitWith { "" };

private _cfg = configFile >> "CfgWorlds" >> worldName;
private _lat = -(getNumber (_cfg >> "latitude"));
private _lon = getNumber (_cfg >> "longitude");
_lat = (_lat max -79.9) min 83.9;
_lon = ((_lon + 180) mod 360) - 180;

// Centre de la carte en UTM (formules de Snyder, WGS84). sin/cos/tan d'Arma travaillent en degrés.
private _a = 6378137;
private _f = 1 / 298.257223563;
private _e2 = _f * (2 - _f);
private _ep2 = _e2 / (1 - _e2);
private _k0 = 0.9996;
private _zone = (floor ((_lon + 180) / 6) + 1) min 60;
private _lon0 = (_zone - 1) * 6 - 180 + 3;
private _rad = pi / 180;
private _n = _a / sqrt (1 - _e2 * (sin _lat) ^ 2);
private _t = (tan _lat) ^ 2;
private _c = _ep2 * (cos _lat) ^ 2;
private _ang = (cos _lat) * (_lon - _lon0) * _rad;
private _m = _a * ((1 - _e2 / 4 - 3 * _e2 ^ 2 / 64 - 5 * _e2 ^ 3 / 256) * _lat * _rad
    - (3 * _e2 / 8 + 3 * _e2 ^ 2 / 32 + 45 * _e2 ^ 3 / 1024) * sin (2 * _lat)
    + (15 * _e2 ^ 2 / 256 + 45 * _e2 ^ 3 / 1024) * sin (4 * _lat)
    - (35 * _e2 ^ 3 / 3072) * sin (6 * _lat));
private _east = _k0 * _n * (_ang + (1 - _t + _c) * _ang ^ 3 / 6 + (5 - 18 * _t + _t ^ 2 + 72 * _c - 58 * _ep2) * _ang ^ 5 / 120) + 500000;
private _north = _k0 * (_m + _n * (tan _lat) * (_ang ^ 2 / 2 + (5 - _t + 9 * _c + 4 * _c ^ 2) * _ang ^ 4 / 24 + (61 - 58 * _t + _t ^ 2 + 600 * _c - 330 * _ep2) * _ang ^ 6 / 720));
if (_lat < 0) then { _north = _north + 10000000; };

// Position : décalage en mètres depuis le centre de la carte.
private _half = worldSize / 2;
_east = _east + (_pos select 0) - _half;
_north = _north + (_pos select 1) - _half;
if (_north < 0) then { _north = _north + 10000000; };

// Bande de latitude, puis carré de 100 km (lettres de colonne selon la zone, de ligne décalées les zones paires).
private _band = "CDEFGHJKLMNPQRSTUVWX" select [((floor ((_lat + 80) / 8)) max 0) min 19, 1];
private _cols = ["STUVWXYZ", "ABCDEFGH", "JKLMNPQR"] select (_zone mod 3);
private _col = _cols select [(((floor (_east / 100000)) - 1) max 0) min 7, 1];
private _rows = "ABCDEFGHJKLMNPQRSTUV";
private _row = _rows select [((floor (_north / 100000)) + ([0, 5] select ((_zone mod 2) isEqualTo 0))) mod 20, 1];
private _pad = { params ["_v"]; private _s = str (floor _v); while { (count _s) < 5 } do { _s = "0" + _s; }; _s };
format ["%1%2 %3%4 %5 %6", _zone, _band, _col, _row, [(floor _east) mod 100000] call _pad, [(floor _north) mod 100000] call _pad]
