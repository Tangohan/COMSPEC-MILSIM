/*
    Vent pour l'app CAS : [texte « VENT 0563 / 6 M/S », vitesse m/s, direction d'où il vient (millièmes), vecteur].
*/
private _w = wind;
private _spd = vectorMagnitude [_w select 0, _w select 1, 0];
// wind : vers où souffle l'air ; on affiche d'où il vient, comme une station météo.
private _from = ((((_w select 0) atan2 (_w select 1)) + 180) mod 360 + 360) mod 360;
private _mils = round (_from * 6400 / 360) mod 6400;
[format [("VENT %1 / %2 M/S") call comspec_atak_native_fnc_tr, [_mils, 4] call CBA_fnc_formatNumber, round _spd], _spd, _mils, _w]
