/*
    App Guerre électronique : onglets BROUILLAGE (brouilleur à ma position), GONIO (relèvements d'émissions ennemies),
    TRAÇAGE (tracer un numéro, un IMEI ou une MAC : requête au serveur, fn_traceAction et fn_traceServer) et ÉTAT
    (effets du brouillage subi : GPS, suivi des forces, débit). Clé d'onglet de TRAÇAGE : "GEO" (ancien nom GÉOLOC).
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
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['ewTab', '%1']; [{ ['EW'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _k], _tab isEqualTo _k] };
private _rows = [["segment", "", [["BROUILLAGE", "JAM"] call _tabBtn, ["GONIO", "DF"] call _tabBtn, ["TRAÇAGE", "GEO"] call _tabBtn, ["ÉTAT", "FX"] call _tabBtn]], ["gap"]];
private _seg = {
    params ["_label", "_key", "_def", "_opts"];
    private _v = _f getOrDefault [_key, _def];
    ["segment", _label, _opts apply { [_x select 0, compile format ["['set', '%1', '%2'] call comspec_atak_native_fnc_ewAction;", _key, _x select 1], (_x select 1) isEqualTo _v] }]
};
private _locked = "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Réservé aux opérateurs de guerre électronique sur cette mission.</t>";

switch (_tab) do {
    case "DF": {
        _rows pushBack ["section", "Goniométrie", "Téléphones ennemis et brouilleurs à moins de 3 km"];
        if !(_allowed) exitWith { _rows pushBack ["text", _locked]; };
        private _next = (uiNamespace getVariable ["COMSPEC_ATAK_EwScanNext", 0]) - diag_tickTime;
        _rows append [
            ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Relèvement approximatif (erreur plus grande au loin) et bande de distance, jamais de position exacte. Balayer émet aussi : la goniométrie ennemie vous voit pendant 20 s.</t>"],
            ["buttons", [[[format ["RÉCEPTEUR EN RECHARGE (%1 s)", ceil _next], "BALAYER"] select (_next <= 0), { ['scan'] call comspec_atak_native_fnc_ewAction; }, true], ["EFFACER", { ['clear'] call comspec_atak_native_fnc_ewAction; }]]]
        ];
        private _b = missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []];
        // Recoupement (fn_ewAction "fixes") : point estimé là où se croisent des relèvements pris de points différents.
        private _fx = ["fixes"] call comspec_atak_native_fnc_ewAction;
        if ((count _fx) > 0) then {
            _rows pushBack ["section", "Points estimés", "Recoupement de relèvements pris depuis des points différents"];
            {
                _x params ["_fp", "_fr", "_fk", "_ft", "_fn", ["_fc", 0]];
                private _fcol = createHashMapFromArray [["BROUILLEUR", "#d940f2"], ["GONIO", (COMSPEC_ATAK_Th get "hexAlert")]] getOrDefault [_fk, (COMSPEC_ATAK_Th get "hexWarn")];
                _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t size='1.1'>%3</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>à %4 près · %5 recoupement(s) · il y a %6 min</t>",
                    _fcol, _fk + ([""] + [format [" n°%1", _fc]] select (_fc > 0)), mapGridPosition _fp, [_fr] call _km, _fn, floor ((time - _ft) / 60)]];
                _rows pushBack ["buttons", [["VOIR LE POINT SUR LA CARTE", compile format ["['locateFix', %1] call comspec_atak_native_fnc_ewAction;", _forEachIndex], true]]];
            } forEach _fx;
            _rows pushBack ["section", "Relèvements", ""];
        } else {
            if ((count _b) > 0) then { _rows pushBack ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Pour un point estimé : relevez la même émission depuis un second endroit, à plus de 150 m, dans les 10 minutes.</t>"]; };
        };
        for "_i" from ((count _b) - 1) to 0 step -1 do {
            (_b select _i) params ["_from", "_brg", "_b0", "_b1", "_kind", "_t", "_err", ["_hour", ""]];
            private _col = createHashMapFromArray [["BROUILLEUR", "#d940f2"], ["GONIO", (COMSPEC_ATAK_Th get "hexAlert")]] getOrDefault [_kind, (COMSPEC_ATAK_Th get "hexWarn")];
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t size='1.1'>%3° %4</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>à %5° près</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%6 · relevé à %7 · il y a %8 min</t>",
                _col, _kind, round _brg, [_brg] call _card, round _err,
                [format ["moins de %1", [_b1] call _km], format ["entre %1 et %2", [_b0] call _km, [_b1] call _km]] select (_b0 > 0),
                _hour, floor ((time - _t) / 60)]];
            _rows pushBack ["buttons", [["VOIR SUR LA CARTE", compile format ["['locate', %1] call comspec_atak_native_fnc_ewAction;", _i]]]];
        };
        if ((count _b) isEqualTo 0) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aucun relèvement.</t>"]; };
    };
    case "GEO": {
        // Traçage de numéro : formulaire, numéros tracés et journal (fn_traceAction) ; la position vient du serveur.
        _rows append (["rows"] call comspec_atak_native_fnc_traceAction);
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
            ["info", "Brouillage", [format ["<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>%1 %%</t>", round (_k * 100)], "<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>aucun</t>"] select (_err isEqualTo 0)],
            ["info", "Erreur GPS", [format ["environ %1 m", _err], "nominale"] select (_err isEqualTo 0)],
            ["info", "Suivi des forces (BFT)", ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>normal</t>", "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>dégradé, positions périmées</t>"] select _bft],
            ["info", "Réseau", format ["%1 · %2 kbit/s · perte %3 %%", _q get "label", _q get "kbps", _q get "loss"]]
        ];
        // Brouilleurs connus de mon camp.
        private _side = str side group player;
        private _own = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { (_x param [3, ""]) isEqualTo _side && {(_x param [4, 1e9]) >= _now} };
        _rows pushBack ["section", "Brouilleurs amis", format ["%1 actif(s)", count _own]];
        {
            _x params ["_pos", "_rad", "", "", ["_until", 1e9], ["_exempt", false]];
            if (_pos isEqualType objNull) then { _pos = getPosATL _pos; };
            _rows pushBack ["text", format ["%1 · rayon %2 m · %3 · <t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>reste %4 min%5</t>",
                [_pos, 6] call comspec_atak_native_fnc_gridRef, _rad, [player distance2D _pos] call _km, ceil ((_until - _now) / 60), ["", ", alliés filtrés"] select _exempt]];
        } forEach _own;
    };
    default {
        _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_ew.paa", "<t size='1.2' font='COMSPEC_BarlowBold'>Brouilleur</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Coupe le réseau des téléphones dans le rayon et fausse leur GPS. Visible à la goniométrie ennemie tant qu'il émet.</t>"];
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
// GONIO : interceptions du trafic du maillage (fn_meshKeys, réglage « Interception du trafic en clair ») sous les relèvements.
if (_tab isEqualTo "DF") then { _rows append (["interceptRows"] call comspec_atak_native_fnc_meshKeys); };
// GONIO : spectre et boussole en tête, le formulaire dessous.
if (_tab isEqualTo "DF" && {_allowed}) then {
    private _vh = _bh * 0.42;
    [[0, 0, _bw, _vh]] call comspec_atak_native_fnc_vizSpectrum;
    [_rows, [0, _vh, _bw, _bh - _vh]] call comspec_atak_native_fnc_formRender;
} else {
    if (_tab isEqualTo "DF") then { _rows append (["rows"] call comspec_atak_native_fnc_meshEW); };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
};
true
