/*
    Météo et réseau ATAK (bande C 4,9 GHz) : pertes en dB ajoutées au bilan radio (fn_radioLink) et pertes de paquets
    en rafales (fn_linkQuality). Condition « Météo » (fn_netCond "weather", intensité "weatherK" en %).
      - pluie sur le trajet : affaiblissement spécifique ITU-R P.838 à 4,9 GHz (k = 0,00025, alpha = 1,65 ;
        environ 0,05 dB/km sous 25 mm/h, 0,5 dB/km sous 100 mm/h), intensité tirée de rain (0-1 -> 0-100 mm/h) ;
      - antennes et radômes mouillés : film d'eau sur les deux antennes, 0,5 à 2 dB dès qu'il pleut ;
      - orage (lightnings, ciel couvert) : bruit impulsif des éclairs, le plancher de bruit monte jusqu'à 6 dB et les
        paquets se perdent par rafales ;
      - vent fort (au-delà de 8 m/s) : mâts et feuillage en mouvement, évanouissements rapides jusqu'à 4 dB ;
      - brouillard : aucun effet à 4,9 GHz (ITU-R P.840, moins de 0,05 dB/km), affiché comme tel.
    Le feuillage mouillé (+20 %) reste compté par fn_radioLink.
    Params : ["global"] -> [[[cause, dB]...], pertes de paquets en plus (%), gigue en plus (ms)] (cache 5 s)
             ["path", distance m] -> dB de pluie sur ce trajet
*/
params [["_mode", "global"], ["_d", 0]];
if (!(["weather"] call comspec_atak_native_fnc_netCond) || {(["realism"] call comspec_atak_native_fnc_netCond) isEqualTo 0}) exitWith {
    [[[], 0, 0], 0] select (_mode isEqualTo "path")
};
private _k = ((["weatherK"] call comspec_atak_native_fnc_netCond) max 0) / 100;
if (_mode isEqualTo "path") exitWith {
    if (rain < 0.05) exitWith { 0 };
    private _rate = 100 * (rain ^ 1.6);
    0.00025 * (_rate ^ 1.65) * (_d / 1000) * _k
};
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_NetWx", []];
if ((count _cache) isEqualTo 2 && {diag_tickTime - (_cache select 0) < 5}) exitWith { _cache select 1 };
private _out = [];
private _pl = 0;
private _jit = 0;
if (rain >= 0.05) then {
    _out pushBack [format ["Pluie (%1 mm/h) : antennes mouillées", round (100 * (rain ^ 1.6))], (0.5 + 1.5 * rain) * _k];
};
private _storm = if (overcast > 0.6) then { lightnings } else { 0 };
if (_storm > 0.15) then {
    _out pushBack ["Orage : bruit des éclairs", 6 * _storm * _k];
    _pl = _pl + 10 * _storm * _k;
    _jit = _jit + 40 * _storm * _k;
};
private _w = vectorMagnitude wind;
if (_w > 8) then {
    _out pushBack [format ["Vent fort (%1 km/h) : mâts et feuillage en mouvement", round (_w * 3.6)], ((_w - 8) * 0.3 min 4) * _k];
    _pl = _pl + ((_w - 8) * 0.5 min 6) * _k;
    _jit = _jit + ((_w - 8) * 3 min 30) * _k;
};
private _res = [_out, round _pl, round _jit];
missionNamespace setVariable ["COMSPEC_ATAK_NetWx", [diag_tickTime, _res]];
_res
