/*
    App Explosifs : charges posées par le joueur, mise à feu une par une ou en séquence.
    Sécurité à lever avant tout tir ; délai avant la première charge et intervalle choisis ;
    pendant la séquence, compte à rebours de chaque charge et durée totale. Minuteries ACE affichées
    avec leur temps restant ; chaque charge se montre sur la carte (numéro et état).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Explo", _s];
private _grey = "#8a9a93";
private _armed = _s getOrDefault ["armed", false];
private _sel = _s getOrDefault ["sel", []];
private _queue = _s getOrDefault ["queue", []];
private _delay = _s getOrDefault ["delay", 5];
private _gap = _s getOrDefault ["gap", 1];
private _list = [true] call comspec_atak_native_fnc_exploList;
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _dist = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _mmss = { params ["_t"]; _t = (ceil _t) max 0; format ["%1:%2", floor (_t / 60), [str (_t mod 60), "0" + str (_t mod 60)] select ((_t mod 60) < 10)] };
private _kinds = createHashMapFromArray [["atak", ["ATAK", "#f2ab33"]], ["command", ["déclencheur", "#e5c84a"]], ["timer", ["minuterie", "#e5483a"]], ["mine", ["mine", "#c9d4cf"]]];
private _rows = [];

// Bandeau : séquence en cours (compte à rebours) ou état de la sécurité.
if ((count _queue) > 0) then {
    private _end = _s getOrDefault ["seqEnd", diag_tickTime];
    private _next = (_queue apply { _x select 0 }) call BIS_fnc_lowestNum;
    _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_explo.paa", format ["<t size='1.3' font='RobotoCondensedBold' color='#e5483a'>MISE À FEU EN COURS</t><br/><t size='0.9'>Prochaine charge dans <t font='RobotoCondensedBold'>%1 s</t> · fin dans %2 s · %3 restante(s)</t>",
        ((_next - diag_tickTime) max 0) toFixed 1, ((_end - diag_tickTime) max 0) toFixed 1, count _queue]];
    {
        _x params ["_at", "_c"];
        _rows pushBack ["info", format ["<t color='#e5483a'>T-%1 s</t>", ((_at - diag_tickTime) max 0) toFixed 1], format ["%1  <t color='%2'>%3</t>", _c get "label", _grey, _c get "grid"]];
    } forEach _queue;
    _rows pushBack ["buttons", [["ANNULER LA SÉQUENCE", { ['cancel'] call comspec_atak_native_fnc_exploAction; }, true]]];
} else {
    _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_explo.paa", format ["<t size='1.3' font='RobotoCondensedBold'>EXPLOSIFS</t>  <t font='RobotoCondensedBold' color='%1'>● %2</t><br/><t size='0.85' color='%3'>%4 charge(s) posée(s) · mise à feu par le réseau ATAK</t>",
        ["#5cc76b", "#e5483a"] select _armed, ["SÉCURITÉ", "PRÊT AU TIR"] select _armed, _grey, count _list]];
    _rows pushBack ["switch", "Lever la sécurité de mise à feu", _armed, { ['safety'] call comspec_atak_native_fnc_exploAction; }, "Remise automatiquement après chaque séquence"];
};

// Séquence : délai, intervalle, durée prévue.
private _nSel = { (_x get "key") in _sel } count _list;
_rows pushBack ["section", "Séquence", format ["%1 charge(s) cochée(s) · durée prévue %2", _nSel, [(_delay + ((_nSel - 1) max 0) * _gap)] call _mmss]];
_rows pushBack ["segment", "Délai avant la première", [0, 3, 5, 10, 30] apply { [format ["%1 s", _x], compile format ["['delay', %1] call comspec_atak_native_fnc_exploAction;", _x], _x isEqualTo _delay] }];
_rows pushBack ["segment", "Intervalle entre charges", [0, 0.5, 1, 2, 5] apply { [format ["%1 s", _x], compile format ["['gap', %1] call comspec_atak_native_fnc_exploAction;", _x], _x isEqualTo _gap] }];
_rows pushBack ["buttons", [
    ["TOUT COCHER", { ['selAll'] call comspec_atak_native_fnc_exploAction; }],
    ["DÉCOCHER", { ['selNone'] call comspec_atak_native_fnc_exploAction; }],
    [format ["FEU (%1)", _nSel], { ['seq'] call comspec_atak_native_fnc_exploAction; }, true, _armed && {_nSel > 0} && {(count _queue) isEqualTo 0}]
]];

// Charges, dans l'ordre de pose.
_rows pushBack ["section", "Charges posées", ["Aucune charge : posez un explosif (ACE) puis revenez ici", "C1 = première posée · cochez pour la séquence"] select ((count _list) > 0)];
{
    private _k = _x get "key";
    private _e = _x get "obj";
    (_kinds getOrDefault [_x get "kind", ["charge", "#c9d4cf"]]) params ["_kt", "_kc"];
    private _times = if ((_x get "placedAt") >= 0) then { format ["posée il y a %1", [time - (_x get "placedAt")] call _mmss] } else { "" };
    if ((_x get "kind") isEqualTo "timer") then {
        _times = if ((_x get "remaining") >= 0) then { format ["<t color='#e5483a' font='RobotoCondensedBold'>saute dans %1</t> (minuterie %2)", [_x get "remaining"] call _mmss, [_x get "fuse"] call _mmss] } else { "minuterie lancée" };
    };
    _rows pushBack ["text", format ["<t font='RobotoCondensedBold'>C%1 · %2</t>  <t color='%3' size='0.85'>%4</t>%5<br/><t size='0.85' color='%6'>%7 · %8 · %9° %10%11</t>",
        _forEachIndex + 1, _x get "label", _kc, _kt, ["", "  <t color='#5cc76b' size='0.85'>✓ séquence</t>"] select (_k in _sel), _grey,
        _x get "grid", [player distance2D _e] call _dist, round (player getDir _e), [player getDir _e] call _card, ["", " · " + _times] select (_times isNotEqualTo "")]];
    private _btns = [["CARTE", compile format ["['map', %1] call comspec_atak_native_fnc_exploAction;", str _k]]];
    if ((_x get "kind") isNotEqualTo "timer") then {
        _btns pushBack [["COCHER", "DÉCOCHER"] select (_k in _sel), compile format ["['sel', %1] call comspec_atak_native_fnc_exploAction;", str _k]];
        _btns pushBack ["FEU", compile format ["['fire', %1] call comspec_atak_native_fnc_exploAction;", str _k], true, _armed];
    };
    _rows pushBack ["buttons", _btns];
} forEach _list;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
