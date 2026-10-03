/*
    App Météo : onglets ACTUEL (conditions, soleil, lune, visibilité), PRÉVISIONS (prochain changement,
    couverture et brouillard prévus, lumière des prochaines heures) et AVIATION / TIR
    (altitude-densité, dérive sous voile, dérive au vent pour le tir à longue distance).
    ACE Weather est utilisé s'il est chargé (température, humidité, pression), sinon estimations.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["wxTab", "NOW"];
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['wxTab', '%1']; [{ ['WEATHER'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _card = { params ["_deg"]; ["N", "NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S", "SSO", "SO", "OSO", "O", "ONO", "NO", "NNO"] select ((round (_deg / 22.5)) mod 16) };
private _pct = { params ["_v"]; round ((0 max (_v min 1)) * 100) };
private _hhmm = { params ["_h"]; [(_h + 24) mod 24, "HH:MM"] call BIS_fnc_timeToString };
private _trend = {
    params ["_now", "_next"];
    switch (true) do {
        case (_next - _now > 0.08): { "<t color='#f2ab33'>en hausse</t>" };
        case (_now - _next > 0.08): { "<t color='#5cc76b'>en baisse</t>" };
        default { "<t color='#8a9a93'>stable</t>" };
    }
};

// Mesures communes à tous les onglets.
([] call comspec_atak_native_fnc_weather) params ["_temp"];
private _w = wind;
private _spd = vectorMagnitude [_w select 0, _w select 1, 0];
private _from = (((_w select 0) atan2 (_w select 1)) + 180) mod 360;
private _gust = _spd * (1 + gusts);
private _hum = if (!isNil "ace_weather_currentHumidity") then { ace_weather_currentHumidity } else { humidity };
private _alt = (getPosASL player) select 2;
private _press = if (!isNil "ace_weather_fnc_calculateBarometricPressure") then { [_alt] call ace_weather_fnc_calculateBarometricPressure } else { 1013.25 * (1 - 0.0065 * _alt / 288.15) ^ 5.255 };
private _aceWx = !isNil "ace_weather_fnc_calculateBarometricPressure";
private _sun = date call BIS_fnc_sunriseSunsetTime;
_sun params [["_rise", -1], ["_set", -1]];
private _night = sunOrMoon < 0.5;

private _rows = [["segment", "", [["ACTUEL", "NOW"] call _tabBtn, ["PRÉVISIONS", "FCST"] call _tabBtn, ["AVIATION / TIR", "AVIA"] call _tabBtn]]];
switch (_tab) do {
    case "FCST": {
        private _next = nextWeatherChange;
        _rows append [
            ["section", "Prochain changement", "Le temps évolue progressivement jusqu'à cette échéance"],
            ["info", "Échéance", if (_next > 0) then { format ["dans %1 min (vers %2)", round (_next / 60), [dayTime + _next / 3600] call _hhmm] } else { "aucun prévu" }],
            ["info", "Couverture nuageuse", format ["%1 %% puis %2 %% · %3", [overcast] call _pct, [overcastForecast] call _pct, [overcast, overcastForecast] call _trend]],
            ["info", "Brouillard", format ["%1 %% puis %2 %% · %3", [fog] call _pct, [fogForecast] call _pct, [fog, fogForecast] call _trend]],
            ["info", "Pluie", switch (true) do {
                case (overcastForecast > 0.75): { "<t color='#f2ab33'>probable</t>" };
                case (overcastForecast > 0.55): { "possible" };
                default { "peu probable" };
            }],
            ["text", format ["<t size='0.8' color='#8a9a93'>%1</t>", switch (true) do {
                case (fogForecast > 0.4): { "Brouillard attendu : prévoir des points de rendez-vous rapprochés et un appui aérien limité." };
                case (overcastForecast > 0.75): { "Dégradation attendue : plafond bas, visibilité réduite, sols glissants." };
                case (overcastForecast < overcast - 0.1): { "Amélioration attendue : éclaircies et meilleure visibilité." };
                default { "Pas d'évolution marquée attendue." };
            }]],
            ["section", "Lumière des prochaines heures", ""]
        ];
        for "_i" from 1 to 6 do {
            private _h = (dayTime + _i) mod 24;
            private _lab = switch (true) do {
                case (_rise < 0 || {_set < 0}): { ["Nuit polaire", "Jour polaire"] select (_rise isEqualTo 0) };
                case (_h >= _rise + 0.5 && {_h <= _set - 0.5}): { "<t color='#e8b84a'>Jour</t>" };
                case (_h >= _rise - 0.5 && {_h <= _set + 0.5}): { "<t color='#f2ab33'>Crépuscule</t>" };
                default { "<t color='#7fb6e6'>Nuit</t>" };
            };
            _rows pushBack ["info", [_h] call _hhmm, _lab];
        };
    };
    case "AVIA": {
        // Altitude pression (atmosphère standard) puis altitude-densité : règle des 120 ft par °C d'écart à l'ISA.
        private _paFt = 145366.45 * (1 - (_press / 1013.25) ^ 0.190284);
        private _isa = 15 - 1.98 * _paFt / 1000;
        private _daFt = _paFt + 118.8 * (_temp - _isa);
        private _rho = (_press * 100) / (287.05 * (_temp + 273.15));
        _rows append [
            ["section", "Aviation", format ["Altitude du téléphone : %1 m", round _alt]],
            ["info", "Pression", format ["%1 hPa%2", round _press, ["  <t color='#8a9a93'>(estimée)</t>", ""] select _aceWx]],
            ["info", "Altitude pression", format ["%1 m (%2 ft)", round (_paFt * 0.3048), round _paFt]],
            ["info", "Altitude-densité", format ["<t color='%1'>%2 m (%3 ft)</t>", ["#5cc76b", "#f2ab33", "#e5483a"] select ((floor (_daFt / 4000)) min 2 max 0), round (_daFt * 0.3048), round _daFt]],
            ["info", "Densité de l'air", format ["%1 kg/m3", _rho toFixed 3]],
            ["text", format ["<t size='0.8' color='#8a9a93'>%1</t>", switch (true) do {
                case (_daFt > 8000): { "Altitude-densité très élevée : hélicoptères en limite de puissance, réduire la charge et préférer les décollages roulés." };
                case (_daFt > 4000): { "Altitude-densité élevée : vol stationnaire plus difficile, charge utile réduite." };
                default { "Air dense : performances nominales." };
            }]],
            ["info", "Vent au sol", format ["%1 m/s du %2 (%3°), rafales %4 m/s", _spd toFixed 1, [_from] call _card, round _from, _gust toFixed 1]]
        ];
        // Dérive sous voile : le parachutiste dérive avec le vent pendant toute la descente (vers où souffle le vent).
        private _to = (_from + 180) mod 360;
        private _hAgl = (getPosATL vehicle player) select 2;
        _rows pushBack ["section", "Parachutage", format ["Dérive vers le %1 (%2°), à anticiper en sens inverse", [_to] call _card, round _to]];
        {
            _x params ["_lab", "_free", "_canopy"];
            // _free : hauteur en chute libre (~55 m/s) ; _canopy : hauteur sous voile (~5 m/s).
            private _t = _free / 55 + _canopy / 5;
            _rows pushBack ["info", _lab, format ["%1 s · dérive %2 m", round _t, round (_spd * _t)]];
        } forEach ([
            ["SOA 300 m (ouverture auto)", 0, 300],
            ["HAHO 3 000 m (ouverture immédiate)", 0, 3000],
            ["HALO 4 000 m (ouverture à 300 m)", 3700, 300]
        ] + ([[], [[format ["Depuis ma hauteur (%1 m)", round _hAgl], 0 max (_hAgl - 300), _hAgl min 300]]] select (_hAgl > 50)));
        // Tir longue distance : composante traversière selon la direction où je regarde.
        private _aim = getDir player;
        private _rel = _from - _aim;
        private _cross = _spd * sin _rel;
        private _head = _spd * cos _rel;
        _rows pushBack ["section", "Tir à longue distance", format ["Direction de tir %1° · vent traversier %2 m/s %3 · %4 %5 m/s",
            round _aim, (abs _cross) toFixed 1, ["de gauche", "de droite"] select (_cross > 0), ["vent arrière", "vent de face"] select (_head > 0), (abs _head) toFixed 1]];
        _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>Dérive approchée (vitesse décroissante exponentielle, air dense corrigé). Visez %1 du point d'impact voulu.</t>", ["à gauche", "à droite"] select (_cross > 0)]];
        private _densK = 1.225 / (_rho max 0.5);
        {
            _x params ["_cal", "_v0", "_len"];
            // Longueur de décroissance plus grande quand l'air est moins dense : la balle freine moins.
            private _lenEff = _len * _densK;
            private _cells = [];
            {
                private _tof = (_lenEff / _v0) * ((exp (_x / _lenEff)) - 1);
                private _drift = abs _cross * (_tof - _x / _v0);
                _cells pushBack format ["%1 m : %2 m (%3 mrad)", _x, _drift toFixed 2, ((_drift / _x) * 1000) toFixed 1];
            } forEach [300, 600, 1000];
            _rows pushBack ["text", format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.85'>%2</t>", _cal, _cells joinString "  ·  "]];
        } forEach [["5,56 mm (V0 940 m/s)", 940, 1080], ["7,62 mm (V0 840 m/s)", 840, 1230], [".338 LM (V0 900 m/s)", 900, 2000], ["12,7 mm (V0 890 m/s)", 890, 2600]];
    };
    default {
        private _sky = switch (true) do {
            case (rain > 0.6): { "Forte pluie" };
            case (rain > 0.2): { "Pluie" };
            case (rain > 0.02): { "Bruine" };
            case (overcast > 0.8): { "Couvert" };
            case (overcast > 0.5): { "Nuageux" };
            case (overcast > 0.25): { "Éclaircies" };
            default { ["Dégagé", "Nuit claire"] select _night };
        };
        // Visibilité : brouillard atténué au-dessus de sa base (fogParams), pluie, limite de vue du jeu.
        fogParams params ["_fv", "_fdecay", "_fbase"];
        private _fogEff = _fv * (exp (-(_fdecay max 0) * ((_alt - _fbase) max 0)));
        private _vis = ((4000 * (1 - _fogEff) ^ 3 + 30) * (1 - 0.5 * rain)) min viewDistance;
        private _visTxt = [format ["%1 m", round (_vis / 10) * 10], format ["%1 km", (_vis / 1000) toFixed 1]] select (_vis >= 1000);
        private _moon = moonIntensity;
        private _moonTxt = switch (true) do {
            case (_moon > 0.7): { "Lune brillante" };
            case (_moon > 0.35): { "Lune moyenne" };
            case (_moon > 0.05): { "Lune faible" };
            default { "Pas de lune (nuit noire)" };
        };
        _rows append [
            ["hero", "\z\comspec_atak_native\addons\main\data\app_weather.paa", format ["<t size='1.6' font='RobotoCondensedBold'>%1 °C</t>  <t size='1.1'>%2</t><br/><t color='#8a9a93'>Vent du %3 (%4°) · %5 m/s (%6 km/h)</t><br/><t size='0.85' color='#8a9a93'>%7 · %8</t>",
                round _temp, _sky, [_from] call _card, round _from, _spd toFixed 1, round (_spd * 3.6),
                [getPosASL player] call comspec_atak_native_fnc_gridRef, [dayTime] call _hhmm]],
            ["section", "Conditions", ["Valeurs estimées (ACE Weather absent)", "ACE Weather"] select _aceWx],
            ["info", "Couverture nuageuse", format ["%1 %%", [overcast] call _pct]],
            ["info", "Pluie", format ["%1 %%", [rain] call _pct]],
            ["info", "Brouillard", format ["%1 %%", [_fogEff] call _pct]],
            ["info", "Rafales", format ["%1 m/s (%2 km/h)", _gust toFixed 1, round (_gust * 3.6)]],
            ["info", "Humidité", format ["%1 %%", [_hum] call _pct]],
            ["info", "Pression", format ["%1 hPa%2", round _press, ["  <t color='#8a9a93'>(estimée)</t>", ""] select _aceWx]],
            ["info", "Visibilité", format ["%1%2", _visTxt, ["", "  <t color='#7fb6e6'>(de nuit)</t>"] select _night]],
            ["section", "Soleil et lune", ""],
            ["info", "Lever du soleil", if (_rise < 0 || {_set < 0}) then { "aucun aujourd'hui" } else { [_rise] call _hhmm }],
            ["info", "Coucher du soleil", if (_rise < 0 || {_set < 0}) then { "aucun aujourd'hui" } else { [_set] call _hhmm }],
            ["info", "Lune", format ["%1 (%2 %%)", _moonTxt, [_moon] call _pct]]
        ];
        if (_rise >= 0 && {_set >= 0}) then {
            private _nextEvt = switch (true) do {
                case (dayTime < _rise): { format ["Lever dans %1", [_rise - dayTime, "HH:MM"] call BIS_fnc_timeToString] };
                case (dayTime < _set): { format ["Coucher dans %1", [_set - dayTime, "HH:MM"] call BIS_fnc_timeToString] };
                default { format ["Lever dans %1", [24 - dayTime + _rise, "HH:MM"] call BIS_fnc_timeToString] };
            };
            _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>%1%2</t>", _nextEvt, ["", " · prévoir les jumelles de vision nocturne"] select (_night && {_moon < 0.35})]];
        };
    };
};
_rows pushBack ["buttons", [["ACTUALISER", { [{ ['WEATHER'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
