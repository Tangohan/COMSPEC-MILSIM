/*
    Dégâts réalistes du téléphone : ce qui arrive dépend de l'endroit où il est porté, de ce qui l'atteint et de l'énergie.
    Params : [type, données]
      "hits"  : [[partie ACE (head, body, leftarm, rightarm, leftleg, rightleg), dégâts reçus (après gilet),
                  dégâts bruts (sans gilet), classe de munition]...] d'un même coup (balle, éclat, chute, accident) ;
      "blast" : [dégâts du souffle sur le joueur, distance (m, -1 inconnue)] (événement Explosion) ;
      "water" : secondes passées sous l'eau.
    Où est le téléphone (fn_deviceImpact "carry") :
      "hand"     en main (téléphone pris en main) : bras et mains surtout, écran exposé ;
      "vest"     sur le gilet (emplacement GPS, poche du gilet, ou porté en miniature) : torse surtout, devant la plaque ;
      "uniform"  dans une poche de la tenue : jambes (poche de cuisse) et torse, sous le gilet ;
      "backpack" dans le sac : seulement les coups dans le dos, protégé du souffle et des éclats.
    Gilet : une balle arrêtée par la plaque ne blesse pas, mais un téléphone fixé devant la plaque peut la prendre
    de plein fouet ou encaisser le choc ; un téléphone dans la poche de tenue, sous le gilet, est protégé d'autant
    (part des dégâts qui passe le gilet = dégâts reçus / dégâts bruts) ; le sac n'est pas concerné.
    Énergie : valeur « hit » de la munition (5,56 ≈ 8, 7,62 ≈ 11, 12,7 ≈ 25…), dégâts du souffle, hauteur de chute.
    Tirage pondéré par l'énergie : rien, rayures, écran fêlé (léger / grave), pixels morts et lignes, batterie
    endommagée, haut-parleur et micro, GPS, antenne, extinction, destruction (fn_deviceDamage applique et prévient).
*/
params [["_kind", "hits"], ["_data", []]];
// Où est le téléphone ? (aussi utilisé seul : ["carry"] call ...)
private _carryOf = {
    private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
    private _has = { params ["_list"]; (_list findIf { (toLower _x) in _cat }) >= 0 };
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _open = !isNull ([] call comspec_atak_native_fnc_display);
    switch (true) do {
        case (_open && {_s getOrDefault ["interactive", false]}): { "hand" };
        case (_open): { "vest" };
        case ([assignedItems player] call _has): { "vest" };
        case ([vestItems player] call _has): { "vest" };
        case ([uniformItems player] call _has): { "uniform" };
        case ([backpackItems player] call _has): { "backpack" };
        // Objet non obligatoire (réglage serveur) : téléphone supposé sur le gilet. Tablette de bord seule : rien à casser.
        case ([player] call comspec_atak_native_fnc_aircrewTerminal): { "" };
        default { "vest" };
    }
};
if (_kind isEqualTo "carry") exitWith { call _carryOf };
if !(missionNamespace getVariable ["comspec_atak_native_damage_sim", true]) exitWith { "" };
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { "" };
if (((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["damage", 0]) >= 1) exitWith { "" };
private _carry = call _carryOf;
if (_carry isEqualTo "") exitWith { "" };
private _where = createHashMapFromArray [["hand", "en main"], ["vest", "sur le gilet"], ["uniform", "dans la poche"], ["backpack", "dans le sac"]] get _carry;
private _exposed = !isNull ([] call comspec_atak_native_fnc_display);

// Tirage pondéré : [[issue, poids]...] -> issue.
private _roll = {
    params ["_w"];
    private _tot = 0;
    { _tot = _tot + ((_x select 1) max 0); } forEach _w;
    private _r = random _tot;
    private _out = "none";
    { _r = _r - ((_x select 1) max 0); if (_r <= 0) exitWith { _out = _x select 0; }; } forEach _w;
    _out
};
// Écran sorti (en main ou en miniature) : verre plus exposé.
private _expose = {
    params ["_w"];
    if (!_exposed) exitWith { _w };
    _w apply { [_x select 0, (_x select 1) * ([1, 1.5] select ((_x select 0) in ["scratch", "crackLight", "crackHeavy", "pixels"]))] }
};
// Issue -> [écran ajouté, extinction (s), composants]
private _effect = {
    params ["_o", "_e"];
    private _p = createHashMap;
    private _scr = 0; private _off = 0;
    switch (_o) do {
        case "scratch": { _p set ["scratch", 0.12 + 0.15 * _e]; };
        case "crackLight": { _scr = 0.2 + 0.1 * _e; _p set ["scratch", 0.1]; };
        case "crackHeavy": { _scr = 0.45 + 0.15 * _e; _p set ["pixels", 0.15]; };
        case "pixels": { _scr = 0.05; _p set ["pixels", 0.3 + 0.2 * _e]; };
        case "battery": { _p set ["battery", 0.25 + 0.2 * _e]; };
        case "audio": { _p set ["audio", 0.5 + 0.3 * _e]; };
        case "gps": { _p set ["gps", 0.3 + 0.3 * _e]; };
        case "antenna": { _p set ["antenna", 0.3 + 0.3 * _e]; };
        case "off": { _off = round (10 + 12 * _e); };
        case "destroy": { _p set ["destroy", true]; };
    };
    [_scr, _off, _p]
};
private _apply = {
    params ["_outs", "_e", "_why"];
    private _scr = 0; private _off = 0; private _p = createHashMap;
    {
        ([_x, _e] call _effect) params ["_s", "_o", "_pp"];
        _scr = _scr + _s; _off = _off max _o;
        { if (_y isEqualType true) then { _p set [_x, _y]; } else { _p set [_x, (_p getOrDefault [_x, 0]) + _y]; }; } forEach _pp;
    } forEach (_outs select { _x isNotEqualTo "none" });
    if (_scr <= 0 && {_off <= 0} && {(count _p) isEqualTo 0}) exitWith {
        ["INFO", format ["%1 (%2) : le téléphone a encaissé, rien de cassé", _why, _where], 3, 15] call comspec_atak_native_fnc_notify;
        false
    };
    [_scr, _why, _off, _p, _where] call comspec_atak_native_fnc_deviceDamage
};

switch (_kind) do {
    case "hits": {
        if ((count _data) isEqualTo 0) exitWith {};
        private _ammo = (_data select 0) param [3, ""];
        private _al = toLower _ammo;
        private _cfg = configFile >> "CfgAmmo" >> _ammo;
        private _type = switch (true) do {
            case (_al isEqualTo "falling"): { "fall" };
            case (_al in ["vehiclecrash", "collision"]): { "crash" };
            case (_al isEqualTo ""): { ["fall", "crash"] select ((vehicle player) isNotEqualTo player) };
            case (_al in ["drowning", "burning", "ropeburn", "backblast", "stab", "punch", "unknown"]): { "skip" };
            case (!isClass _cfg): { "bullet" };
            case ((getNumber (_cfg >> "explosive")) > 0.3): { "frag" };
            default { "bullet" };
        };
        if (_type isEqualTo "skip") exitWith {};
        private _total = 0;
        { _total = _total + ((_x param [2, 0]) max (_x param [1, 0])); } forEach _data;
        // Chute ou accident : tout le corps encaisse, le téléphone suit (en main, il peut aussi échapper).
        if (_type in ["fall", "crash"]) exitWith {
            private _e = (_total * 1.5) min 2;
            private _p = ((0.2 + 0.5 * (_total min 1)) * (createHashMapFromArray [["hand", 1.5], ["vest", 1], ["uniform", 0.9], ["backpack", 0.5]] get _carry)) min 0.95;
            if (random 1 > _p) exitWith {};
            private _w = if (_type isEqualTo "fall") then {
                [["none", 0.45], ["scratch", 0.25], ["crackLight", 0.12 * _e], ["off", 0.08], ["battery", 0.05], ["gps", 0.03], ["pixels", 0.04 * _e]]
            } else {
                [["none", 0.3], ["scratch", 0.2], ["crackLight", 0.18], ["crackHeavy", 0.08 * _e], ["off", 0.14], ["battery", 0.06], ["antenna", 0.05], ["gps", 0.04], ["destroy", 0.03 * _e]]
            };
            [[[[_w] call _expose] call _roll], _e, ["Chute", "Accident"] select (_type isEqualTo "crash")] call _apply;
        };
        // Balle ou éclat : chaque partie touchée, de la plus forte à la plus faible ; le téléphone n'est touché qu'une fois.
        private _hitVal = [getNumber (_cfg >> "hit"), 0] select !(isClass _cfg);
        // Chance [directe, choc] qu'un coup sur cette partie concerne le téléphone, selon où il est porté.
        private _table = createHashMapFromArray [
            ["hand", createHashMapFromArray [["head", [0.03, 0.02]], ["body", [0.14, 0.15]], ["leftarm", [0.3, 0.3]], ["rightarm", [0.3, 0.3]]]],
            ["vest", createHashMapFromArray [["body", [0.16, 0.35]], ["leftarm", [0.05, 0.05]], ["rightarm", [0.05, 0.05]], ["leftleg", [0.02, 0]], ["rightleg", [0.02, 0]]]],
            ["uniform", createHashMapFromArray [["body", [0.08, 0.12]], ["leftarm", [0.03, 0.02]], ["rightarm", [0.03, 0.02]], ["leftleg", [0.14, 0.15]], ["rightleg", [0.14, 0.15]]]],
            ["backpack", createHashMapFromArray [["body", [0.08, 0.05]]]]
        ] get _carry;
        private _sorted = _data apply { [(_x param [2, 0]) max (_x param [1, 0]), _x] };
        _sorted sort false;
        {
            (_x select 1) params [["_part", ""], ["_dmg", 0], ["_raw", 0]];
            _part = toLower _part;
            if (_part isEqualTo "hitface" || {_part isEqualTo "hitneck"}) then { _part = "head"; };
            (_table getOrDefault [_part, [0, 0]]) params ["_pd", "_ps"];
            _raw = _raw max _dmg;
            // Part qui traverse le gilet (1 = rien ne l'arrête).
            private _pass = if (_raw > 0.001) then { (_dmg / _raw) min 1 max 0 } else { 1 };
            private _e = if (_hitVal > 0) then { (_hitVal / 11) max 0.2 min 2.5 } else { (_raw * 2) max 0.2 min 2 };
            if (_type isEqualTo "frag") then { _pd = _pd * 1.6; _e = (_raw * 1.5) max 0.1 min 2; };
            // Sous le gilet (poche de tenue, torse) : la balle doit d'abord passer la plaque.
            if (_carry isEqualTo "uniform" && {_part isEqualTo "body"}) then { _pd = _pd * _pass; _ps = _ps * (0.4 + 0.6 * _pass); };
            // Énergie du choc reçue sans impact direct : plus forte devant une plaque qui a arrêté la balle.
            private _es = _e * (createHashMapFromArray [["hand", 0.5], ["vest", 0.5 + 0.5 * (1 - _pass)], ["uniform", 0.6 * _pass], ["backpack", 0.3]] get _carry);
            private _r = random 1;
            if (_r < _pd) exitWith {
                private _w = if (_type isEqualTo "frag") then {
                    [["scratch", 0.3], ["crackLight", 0.22], ["crackHeavy", 0.1 * _e], ["pixels", 0.1], ["antenna", 0.08], ["audio", 0.05], ["battery", 0.05], ["destroy", 0.06 * _e]]
                } else {
                    [["destroy", 0.15 + 0.4 * _e], ["crackHeavy", 0.25], ["crackLight", 0.12], ["battery", 0.12], ["antenna", 0.1], ["gps", 0.06], ["audio", 0.06], ["pixels", 0.1], ["scratch", 0.08 * ((2 - _e) max 0)]]
                };
                private _outs = [[[_w] call _expose] call _roll];
                // Gros calibre : un second composant peut lâcher.
                if (_e > 0.8 && {random 1 < 0.35}) then { _outs pushBack ([[["battery", 1], ["antenna", 1], ["gps", 0.6], ["audio", 0.6], ["pixels", 1]]] call _roll); };
                [_outs, _e, ["Balle dans le téléphone", "Éclat dans le téléphone"] select (_type isEqualTo "frag")] call _apply;
            };
            if (_r < _pd + _ps) exitWith {
                private _w = [["none", 0.5], ["scratch", 0.18], ["off", 0.14], ["crackLight", 0.12 * _es], ["pixels", 0.06 * _es], ["battery", 0.04]];
                [[[[_w] call _expose] call _roll], _es, ["Impact près du téléphone", "Impact sur le gilet, près du téléphone"] select (_carry isEqualTo "vest" && {_part isEqualTo "body"} && {_pass < 0.6})] call _apply;
            };
        } forEach _sorted;
    };
    case "blast": {
        _data params [["_d", 0], ["_dist", -1]];
        if (_d < 0.02) exitWith {};
        private _e = (_d * 3) max 0.05 min 2;
        private _p = ((0.15 + 0.7 * ((_d * 3) min 1)) * (createHashMapFromArray [["hand", 1.3], ["vest", 1], ["uniform", 0.8], ["backpack", 0.5]] get _carry)) min 0.95;
        if (random 1 > _p) exitWith {};
        private _w = [["none", 0.35], ["off", 0.22], ["audio", 0.1], ["battery", 0.08], ["gps", 0.06], ["pixels", 0.08], ["scratch", 0.12], ["crackLight", 0.1 * _e], ["crackHeavy", 0.05 * _e], ["destroy", 0.03 * _e * _e]];
        [[[[_w] call _expose] call _roll], _e, ["Explosion", format ["Explosion à %1 m", round _dist]] select (_dist >= 0)] call _apply;
    };
    case "water": {
        private _sec = _data;
        if !(_sec isEqualType 0) exitWith {};
        // Étanche quelques dizaines de secondes, puis l'eau s'infiltre ; détruit seulement après une longue immersion.
        private _e = (_sec / 60) min 2;
        private _w = [["none", 0.3], ["audio", 0.25], ["off", 0.25], ["battery", 0.15], ["pixels", 0.06], ["gps", 0.04], ["destroy", 0.06 * ((_e - 1.5) max 0)]];
        [[[_w] call _roll], _e, "Eau dans le téléphone"] call _apply;
    };
};
_carry
