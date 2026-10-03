/*
    App Guerre électronique : onglets BROUILLAGE (brouilleur à ma position), GONIO (relèvements d'émissions ennemies),
    GÉOLOC (localiser un téléphone par numéro, IMEI ou MAC) et ÉTAT (effets du brouillage subi : GPS, suivi des forces, débit).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _f = uiNamespace getVariable ["COMSPEC_ATAK_Ew", createHashMap];
private _tab = _s getOrDefault ["ewTab", "JAM"];
private _now = [time, serverTime] select isMultiplayer;
private _allowed = ["allowed"] call comspec_atak_native_fnc_ewAction;
private _mine = missionNamespace getVariable ["COMSPEC_ATAK_EwMine", []];
private _active = (count _mine) > 0 && {(_mine select 4) >= _now};
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _km = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['ewTab', '%1']; [{ ['EW'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _rows = [["segment", "", [["BROUILLAGE", "JAM"] call _tabBtn, ["GONIO", "DF"] call _tabBtn, ["GÉOLOC", "GEO"] call _tabBtn, ["ÉTAT", "FX"] call _tabBtn]], ["gap"]];
private _seg = {
    params ["_label", "_key", "_def", "_opts"];
    private _v = _f getOrDefault [_key, _def];
    ["segment", _label, _opts apply { [_x select 0, compile format ["['set', '%1', '%2'] call comspec_atak_native_fnc_ewAction;", _key, _x select 1], (_x select 1) isEqualTo _v] }]
};
private _locked = "<t color='#f2ab33'>Réservé aux opérateurs de guerre électronique sur cette mission.</t>";

switch (_tab) do {
    case "DF": {
        _rows pushBack ["section", "Goniométrie", "Téléphones ennemis et brouilleurs à moins de 3 km"];
        if !(_allowed) exitWith { _rows pushBack ["text", _locked]; };
        private _next = (uiNamespace getVariable ["COMSPEC_ATAK_EwScanNext", 0]) - diag_tickTime;
        _rows append [
            ["text", "<t size='0.8' color='#8a9a93'>Relèvement approximatif (erreur plus grande au loin) et bande de distance, jamais de position exacte. Balayer émet aussi : la goniométrie ennemie vous voit pendant 20 s.</t>"],
            ["buttons", [[[format ["RÉCEPTEUR EN RECHARGE (%1 s)", ceil _next], "BALAYER"] select (_next <= 0), { ['scan'] call comspec_atak_native_fnc_ewAction; }, true], ["EFFACER", { ['clear'] call comspec_atak_native_fnc_ewAction; }]]]
        ];
        private _b = missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []];
        for "_i" from ((count _b) - 1) to 0 step -1 do {
            (_b select _i) params ["_from", "_brg", "_b0", "_b1", "_kind", "_t", "_err", ["_hour", ""]];
            private _col = createHashMapFromArray [["BROUILLEUR", "#d940f2"], ["GONIO", "#e5483a"]] getOrDefault [_kind, "#f2ab33"];
            _rows pushBack ["text", format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t size='1.1'>%3° %4</t>  <t color='#8a9a93'>à %5° près</t><br/><t size='0.8' color='#8a9a93'>%6 · relevé à %7 · il y a %8 min</t>",
                _col, _kind, round _brg, [_brg] call _card, round _err,
                [format ["moins de %1", [_b1] call _km], format ["entre %1 et %2", [_b0] call _km, [_b1] call _km]] select (_b0 > 0),
                _hour, floor ((time - _t) / 60)]];
            _rows pushBack ["buttons", [["VOIR SUR LA CARTE", compile format ["['locate', %1] call comspec_atak_native_fnc_ewAction;", _i]]]];
        };
        if ((count _b) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun relèvement.</t>"]; };
    };
    case "GEO": {
        _rows pushBack ["section", "Géolocalisation", "Numéro, IMEI ou adresse MAC"];
        (["geoAllowed"] call comspec_atak_native_fnc_ewAction) params ["_ok", "_why"];
        if !(_ok) exitWith { _rows pushBack ["text", format ["<t color='#f2ab33'>%1</t>", _why]]; };
        _rows append [
            ["text", "<t size='0.8' color='#8a9a93'>Requête à l'opérateur : position par triangulation des antennes, avec une marge d'erreur. Un téléphone éteint, sans réseau ou changé ne répond plus.</t>"],
            ["edit", "geoq", "Numéro (06 12 34 56 78), IMEI (35-…) ou MAC (A4:5E:…)", _f getOrDefault ["geoq", ""]],
            ["buttons", [["LOCALISER", { ['geoLocate'] call comspec_atak_native_fnc_ewAction; }, true]]]
        ];
        private _every = round ((missionNamespace getVariable ["comspec_atak_native_geoloc_refresh", 30]) max 10);
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []];
        if ((count _list) > 0) then { _rows pushBack ["section", "Résultats", format ["Suivi actif : nouvelle position toutes les %1 s", _every]]; };
        for "_i" from ((count _list) - 1) to 0 step -1 do {
            private _e = _list select _i;
            private _p = _e getOrDefault ["pos", []];
            private _st = _e getOrDefault ["status", ""];
            private _stTxt = switch (_st) do {
                case "OK": { "<t color='#5cc76b'>en ligne</t>" };
                case "OFF": { "<t color='#f2ab33'>éteint ou hors réseau</t>" };
                default { "<t color='#e5483a'>abonné introuvable</t>" };
            };
            private _where = if ((count _p) < 2) then { "<t color='#8a9a93'>aucune position</t>" } else {
                format ["%1 · à %2 m près · %3 · <t color='#8a9a93'>relevé à %4, il y a %5 min</t>", [_p, 8] call comspec_atak_native_fnc_gridRef, _e get "rad",
                    [format ["%1 m", round (player distance2D _p)], format ["%1 km", ((player distance2D _p) / 1000) toFixed 1]] select ((player distance2D _p) >= 1000),
                    _e get "hour", floor ((time - (_e get "t")) / 60)]
            };
            _rows pushBack ["text", format ["<t font='RobotoCondensedBold' color='#f2ab33'>%1</t>  <t size='1.05'>%2</t>  %3%4<br/><t size='0.85'>%5</t>",
                _e get "kind", _e get "q", _stTxt, ["", "  <t color='#5cc76b'>● SUIVI</t>"] select (_e getOrDefault ["track", false]), _where]];
            _rows pushBack ["buttons", [
                ["CARTE", compile format ["['geoMap', %1] call comspec_atak_native_fnc_ewAction;", _i]],
                [["SUIVRE", "ARRÊTER LE SUIVI"] select (_e getOrDefault ["track", false]), compile format ["['geoTrack', %1] call comspec_atak_native_fnc_ewAction;", _i]],
                ["EFFACER", compile format ["['geoDel', %1] call comspec_atak_native_fnc_ewAction;", _i]]
            ]];
        };
        ([player] call comspec_atak_native_fnc_phoneIdent) params ["_num", "_imei", "_mac"];
        _rows append [
            ["section", "Mon téléphone", "À donner (ou à cacher) en roleplay"],
            ["info", "Numéro", _num], ["info", "IMEI", _imei], ["info", "Adresse MAC", _mac]
        ];
    };
    case "FX": {
        ([] call comspec_atak_native_fnc_ewEffects) params ["_err", "_bft", "", "_k"];
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        _rows append [
            ["section", "Effets subis", "Brouillage à votre position"],
            ["info", "Brouillage", [format ["<t color='#e5483a'>%1 %%</t>", round (_k * 100)], "<t color='#5cc76b'>aucun</t>"] select (_err isEqualTo 0)],
            ["info", "Erreur GPS", [format ["environ %1 m", _err], "nominale"] select (_err isEqualTo 0)],
            ["info", "Suivi des forces (BFT)", ["<t color='#5cc76b'>normal</t>", "<t color='#f2ab33'>dégradé, positions périmées</t>"] select _bft],
            ["info", "Réseau", format ["%1 · %2 kbit/s · perte %3 %%", _q get "label", _q get "kbps", _q get "loss"]]
        ];
        // Brouilleurs connus de mon camp.
        private _side = str side group player;
        private _own = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { (_x param [3, ""]) isEqualTo _side && {(_x param [4, 1e9]) >= _now} };
        _rows pushBack ["section", "Brouilleurs amis", format ["%1 actif(s)", count _own]];
        {
            _x params ["_pos", "_rad", "", "", ["_until", 1e9], ["_exempt", false]];
            if (_pos isEqualType objNull) then { _pos = getPosATL _pos; };
            _rows pushBack ["text", format ["%1 · rayon %2 m · %3 · <t color='#8a9a93'>reste %4 min%5</t>",
                [_pos, 6] call comspec_atak_native_fnc_gridRef, _rad, [player distance2D _pos] call _km, ceil ((_until - _now) / 60), ["", ", alliés filtrés"] select _exempt]];
        } forEach _own;
    };
    default {
        _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_ew.paa", "<t size='1.2' font='RobotoCondensedBold'>Brouilleur</t><br/><t color='#8a9a93'>Coupe le réseau des téléphones dans le rayon et fausse leur GPS. Visible à la goniométrie ennemie tant qu'il émet.</t>"];
        if !(_allowed) exitWith { _rows pushBack ["text", _locked]; };
        if (_active) then {
            _mine params ["_pos", "_rad", "", "", "_until", "_exempt"];
            private _left = (_until - _now) max 0;
            _rows append [
                ["section", "Brouilleur actif", format ["%1 · rayon %2 m", [_pos, 6] call comspec_atak_native_fnc_gridRef, _rad]],
                ["info", "Temps restant", format ["%1 min %2 s", floor (_left / 60), floor (_left mod 60)]],
                ["info", "Distance", [player distance2D _pos] call _km],
                ["info", "Filtrage ami", ["non : tout le monde est brouillé", "oui : votre camp garde le réseau"] select _exempt],
                ["buttons", [["ARRÊTER LE BROUILLEUR", { ['stop'] call comspec_atak_native_fnc_ewAction; }, true]]]
            ];
        } else {
            private _ex = _f getOrDefault ["exempt", true];
            _rows append [
                ["Rayon", "rad", "600", [["300 m", "300"], ["600 m", "600"], ["1000 m", "1000"]]] call _seg,
                ["Durée", "dur", "10", [["5 min", "5"], ["10 min", "10"], ["20 min", "20"]]] call _seg,
                ["switch", "Filtrage ami", _ex, compile format ["['set', 'exempt', %1] call comspec_atak_native_fnc_ewAction;", !_ex], "Votre camp garde le réseau et le GPS dans la zone"],
                ["buttons", [["DÉMARRER LE BROUILLAGE", { ['start'] call comspec_atak_native_fnc_ewAction; }, true]]]
            ];
        };
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
