/*
    Dégâts réalistes de l'écran du téléphone : un seul modèle par appareil, gardé avec lui (COMSPEC_ATAK_Device, clés
    « scr », « scrSeed », « scrN » : il suit donc l'appareil partout où son état voyage : socle de charge, sauvegarde,
    lecture NFC d'un équipier). Trois familles :
      - verre (« glass ») : fêlure fine, impact en toile, œil de bœuf, coin écrasé, coin éclaté, longue fracture, fêlure de
        pression, impact de balle, dalle écrasée, verre rayé, film décollé, eau sous le verre, brûlure de chaleur ;
      - dalle (« panel »), verre intact : pixels morts, pixels bloqués, amas, ligne morte, plusieurs lignes, ligne qui
        clignote, bande de colonnes, tache d'encre qui grandit, image fantôme, fuite de rétroéclairage, zone sombre,
        dominante de couleur, zone tactile morte ;
      - bugs (« glitch ») : passagers, joués par fn_screenGlitch, jamais gardés.
    Une panne : [genre, variante, x, y (repère du verre en portrait, 0-1), gravité 1-3, angle, heure (time), tirage].
    La position est celle de l'impact sur le verre : le téléphone tourné, le dégât reste au même endroit (fn_screenFaults).
    Tirages : suite propre à l'appareil (graine « scrSeed », compteur « scrN ») ; la liste elle-même voyage avec
    l'appareil, celui qui le reprend voit donc exactement le même écran.
    Params : [mode, valeur]
      "layers"  : ordre des calques, du fond vers l'œil (constante lue par fn_screenSurface et par les tests) ;
      "catalog" : [[genre, famille, libellé, description, réparation, gabarit, variantes]...] ;
      "causes"  : HashMap cause -> [[genres de verre], [genres de dalle], [bugs]] ;
      "level"   : réglage d'intensité 0-3 (0 : ancien rendu seul) ; "on" : [famille] active ? ; "impact" : 0-2 ;
      "list"    : pannes affichées (filtrées par les réglages) ;
      "add"     : [genre, gravité, x, y] (x, y négatifs : tirés) ; "cause" : [cause, énergie 0-2, composants] ;
      "tick"    : toutes les 10 s (usure, eau qui sèche, bugs de fond) ; "repair" : ["screen" | "clean" | "film" |
                  "reboot" | "all"] ; "export" / "import" : état en tableau ; "rows" : lignes du diagnostic ;
      "deadAt"  : [x, y écran 0-1, paysage] l'appui tombe-t-il dans une zone tactile morte ? ;
      "selftest": tirages reproductibles et aller-retour export / import (app Debug, autotest) -> [ok, détail].
*/
params [["_mode", "list"], ["_a", []]];
if (_mode isEqualTo "layers") exitWith { ["panel", "glass", "liquid", "blood", "glare", "corner"] };
if (_mode isEqualTo "catalog") exitWith {
    private _c = missionNamespace getVariable "COMSPEC_ATAK_ScrCatalog";
    if (!isNil "_c") exitWith { _c };
    _c = [
        ["hair", "glass", "Fêlure fine", "Un seul trait fin dans le verre, à peine visible de face.", "screen", "point", 1],
        ["web", "glass", "Impact en toile", "Un choc franc : des rayons partent de l'impact, reliés par des anneaux brisés.", "screen", "point", 2],
        ["bull", "glass", "Impact « œil de bœuf »", "Petit impact dur : anneaux serrés et rayons courts autour d'un point de verre pilé.", "screen", "point", 1],
        ["corner", "glass", "Coin écrasé", "Le téléphone est tombé sur un coin : éventail de fêlures depuis ce coin.", "screen", "corner", 2],
        ["chip", "glass", "Coin éclaté", "Des éclats de verre manquent dans un coin, la dalle nue apparaît.", "screen", "corner", 1],
        ["diag", "glass", "Longue fracture", "Une fracture traverse le verre en biais, d'un bord à l'autre.", "screen", "strip", 2],
        ["press", "glass", "Fêlure de pression", "Châssis plié : longues lignes courbes, sans point d'impact.", "screen", "free", 1],
        ["bullet", "glass", "Impact de balle", "Trou sombre, couronne de verre pilé, rayons serrés et dalle morte autour.", "screen", "point", 2],
        ["crush", "glass", "Dalle écrasée", "Grande tache d'encre noire partie du choc : les cristaux liquides ont fui.", "screen", "point", 1],
        ["abrade", "glass", "Verre rayé", "Fines rayures, visibles surtout dans le reflet du verre.", "screen", "free", 2],
        ["peel", "glass", "Film de protection décollé", "Le film se décolle depuis un coin : poche d'air, bulles et poussières piégées.", "film", "corner", 1],
        ["water", "glass", "Eau sous le verre", "Buée et perles d'eau sous le verre, auréole qui bouge lentement ; elle sèche en quelques minutes hors de l'eau.", "dry", "free", 2],
        ["heat", "glass", "Brûlure de chaleur", "Plage jaunie puis brunie par la chaleur.", "screen", "free", 1],
        ["deadpx", "panel", "Pixels morts", "Quelques points noirs fixes, verre intact.", "screen", "panel", 1],
        ["stuckpx", "panel", "Pixels bloqués", "Points rouges, verts, bleus ou blancs toujours allumés ; un redémarrage en débloque parfois.", "reboot", "panel", 1],
        ["cluster", "panel", "Amas de pixels morts", "Un petit nuage de pixels éteints autour d'un point de choc.", "screen", "panel", 1],
        ["deadline", "panel", "Ligne morte", "Une fine ligne de couleur sur toute la hauteur ou la largeur de la dalle.", "screen", "panel", 1],
        ["lines", "panel", "Plusieurs lignes", "Plusieurs lignes de couleur ou noires, colonnes et rangées.", "screen", "panel", 1],
        ["flickline", "panel", "Ligne qui clignote", "Une rangée qui s'allume et s'éteint.", "screen", "panel", 1],
        ["band", "panel", "Bande de colonnes", "Une bande de colonnes aux couleurs fausses.", "screen", "panel", 2],
        ["blot", "panel", "Tache d'encre qui grandit", "Tache noire sous le verre, qui s'étend lentement en quelques minutes.", "screen", "panel", 2],
        ["burnin", "panel", "Image fantôme", "La barre d'état et le clavier restent marqués en très pâle.", "screen", "panel", 1],
        ["edgeleak", "panel", "Fuite de rétroéclairage", "Halos clairs contre les bords de la dalle.", "screen", "panel", 2],
        ["dimzone", "panel", "Zone sombre", "Une partie du rétroéclairage a lâché : plage plus sombre.", "screen", "panel", 1],
        ["tint", "panel", "Dominante de couleur", "Toute l'image tire vers le vert, le magenta ou le jaune.", "screen", "panel", 3],
        ["touchdead", "panel", "Zone tactile morte", "Les appuis ne passent plus dans un rectangle de l'écran.", "screen", "panel", 1],
        ["tear", "glitch", "Image déchirée", "Des bandes de l'image glissent sur le côté pendant un instant.", "reboot", "", 2],
        ["roll", "glitch", "Barre de balayage", "Une barre claire traverse l'écran de haut en bas.", "reboot", "", 1],
        ["rgb", "glitch", "Couleurs décalées", "Franges rouges et bleues pendant un instant.", "reboot", "", 1],
        ["blocks", "glitch", "Pavés de compression", "Des pavés de couleur recouvrent une partie de l'image.", "reboot", "", 2],
        ["freeze", "glitch", "Image figée", "L'écran ne répond plus pendant une fraction de seconde.", "reboot", "", 1],
        ["flicker", "glitch", "Scintillement", "L'image s'assombrit par à-coups.", "reboot", "", 1],
        ["noise", "glitch", "Neige", "Une rafale de parasites.", "reboot", "", 2],
        ["pump", "glitch", "Luminosité qui pompe", "La luminosité monte et descend lentement.", "reboot", "", 1],
        ["partial", "glitch", "Demi-écran en retard", "Une moitié de l'écran se rafraîchit après l'autre.", "reboot", "", 1],
        ["reboot", "glitch", "Écran noir puis logo", "Écran noir, logo de démarrage, puis tout revient.", "reboot", "", 1],
        ["ghosttap", "glitch", "Appuis fantômes", "Des témoins d'appui apparaissent sans qu'on touche l'écran (visuel seulement).", "reboot", "", 1]
    ];
    missionNamespace setVariable ["COMSPEC_ATAK_ScrCatalog", _c];
    _c
};
if (_mode isEqualTo "causes") exitWith {
    // Cause -> [verre [genre, poids]..., dalle [genre, poids]..., bugs]. Chaque genre a au moins une cause (test).
    createHashMapFromArray [
        ["bullet", [[["bullet", 6], ["web", 2], ["crush", 2]], [["blot", 3], ["lines", 2], ["cluster", 2], ["touchdead", 1]], ["noise", "tear", "reboot"]]],
        ["frag", [[["bull", 4], ["web", 3], ["chip", 2], ["abrade", 2], ["bullet", 1]], [["cluster", 3], ["deadpx", 2], ["deadline", 2]], ["noise", "blocks"]]],
        ["fall", [[["corner", 5], ["hair", 3], ["chip", 2], ["diag", 2], ["peel", 1]], [["deadline", 2], ["flickline", 2], ["deadpx", 2], ["dimzone", 1]], ["flicker", "freeze", "reboot"]]],
        ["crash", [[["diag", 4], ["press", 4], ["corner", 2], ["crush", 2], ["web", 1]], [["band", 2], ["lines", 2], ["edgeleak", 2], ["touchdead", 1]], ["tear", "freeze", "reboot"]]],
        ["blast", [[["web", 3], ["bull", 2], ["hair", 2], ["abrade", 3], ["press", 1]], [["lines", 2], ["tint", 2], ["flickline", 2], ["dimzone", 1]], ["noise", "rgb", "flicker", "partial"]]],
        ["shock", [[["hair", 1]], [["deadpx", 4], ["stuckpx", 4], ["deadline", 1], ["cluster", 1]], ["flicker", "freeze"]]],
        ["water", [[["water", 6]], [["blot", 2], ["tint", 2], ["dimzone", 2], ["touchdead", 2], ["stuckpx", 1]], ["ghosttap", "flicker", "pump", "reboot"]]],
        ["heat", [[["heat", 5], ["peel", 2]], [["burnin", 3], ["tint", 2], ["dimzone", 2], ["edgeleak", 1]], ["pump", "freeze", "partial", "blocks"]]],
        ["emp", [[], [["lines", 3], ["band", 2], ["stuckpx", 3], ["tint", 2], ["flickline", 2]], ["noise", "rgb", "tear", "roll", "reboot", "blocks"]]],
        ["jam", [[], [], ["noise", "roll", "rgb", "tear"]]],
        ["wear", [[["abrade", 3], ["peel", 2], ["hair", 1]], [["burnin", 3], ["stuckpx", 2], ["deadpx", 2], ["edgeleak", 1]], ["pump", "partial"]]],
        ["cold", [[], [["dimzone", 1]], ["partial", "pump", "freeze"]]],
        ["lowbat", [[], [], ["pump", "flicker", "reboot"]]],
        ["pressure", [[["press", 5], ["hair", 2]], [["blot", 2], ["edgeleak", 2]], ["flicker"]]]
    ]
};
// Réglages : rien sans les dégâts du téléphone (réglage serveur) ni en profil Arcade.
private _simOn = (missionNamespace getVariable ["comspec_atak_native_damage_sim", true]) && {(["profile"] call comspec_atak_native_fnc_netCond) isNotEqualTo 1};
private _level = if (_simOn) then { (missionNamespace getVariable ["comspec_atak_native_screen_fx_level", 2]) max 0 min 3 } else { 0 };
if (_mode isEqualTo "level") exitWith { _level };
if (_mode isEqualTo "impact") exitWith { [0, (missionNamespace getVariable ["comspec_atak_native_screen_impact", 1]) max 0 min 2] select (_level > 0) };
private _famOn = {
    params ["_fam"];
    _level > 0 && {switch (_fam) do {
        case "panel": { (missionNamespace getVariable ["comspec_atak_native_panel_faults", true]) isEqualTo true };
        case "glitch": { (missionNamespace getVariable ["comspec_atak_native_screen_glitch", true]) isEqualTo true };
        default { true };
    }}
};
if (_mode isEqualTo "on") exitWith { [_a param [0, "glass"]] call _famOn };
private _cat = ["catalog"] call comspec_atak_native_fnc_screenDamage;
private _famOf = { params ["_id"]; private _i = _cat findIf { (_x select 0) isEqualTo _id }; if (_i < 0) then { "" } else { (_cat select _i) select 1 } };
private _rowOf = { params ["_id"]; private _i = _cat findIf { (_x select 0) isEqualTo _id }; if (_i < 0) then { [] } else { _cat select _i } };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _list = _n getOrDefault ["scr", []];
if !(_list isEqualType []) then { _list = []; };
if (_mode isEqualTo "list") exitWith { _list select { [[_x select 0] call _famOf] call _famOn } };
if (_mode isEqualTo "export") exitWith { [_n getOrDefault ["scrSeed", 0], _n getOrDefault ["scrN", 0], +_list] };
if (_mode isEqualTo "deadAt") exitWith {
    _a params [["_sx", -1], ["_sy", -1], ["_land", false]];
    if ((["impact"] call comspec_atak_native_fnc_screenDamage) < 1 || {!(["panel"] call _famOn)}) exitWith { false };
    // Écran -> repère du verre (portrait).
    private _gx = [_sx, 1 - _sy] select _land;
    private _gy = [_sy, _sx] select _land;
    (_list findIf { (_x select 0) isEqualTo "touchdead" && {abs (_gx - (_x select 2)) < 0.22} && {abs (_gy - (_x select 3)) < (0.05 + 0.03 * (_x select 4))} }) >= 0
};
if (_mode isEqualTo "rows") exitWith {
    // Diagnostic (app Profil, lecture NFC) : chaque panne en clair, avec ce qui la répare. _a : état exporté (vide : le mien).
    private _src = if (_a isEqualType [] && {(count _a) >= 3}) then { _a select 2 } else { _list };
    private _fix = createHashMapFromArray [["screen", "remplacer l'écran (kit de réparation)"], ["film", "nettoyage complet (on retire le film)"], ["dry", "sèche seule hors de l'eau"], ["reboot", "redémarrer"]];
    private _rows = [];
    {
        private _r = [_x select 0] call _rowOf;
        if (_r isNotEqualTo []) then {
            private _sev = (["légère", "moyenne", "grave"] select (((_x select 4) max 1 min 3) - 1)) call comspec_atak_native_fnc_tr;
            _rows pushBack ["info", _r select 2, format ["<t color='%1'>%2</t>  <t color='#8a9a93'>%3</t>", ["#f2ab33", "#f2ab33", "#e5483a"] select (((_x select 4) max 1 min 3) - 1), _sev, (_fix getOrDefault [_r select 4, ""]) call comspec_atak_native_fnc_tr]]; // i18n:brut
        };
    } forEach _src;
    _rows
};
// Suite de tirages de l'appareil (même graine, mêmes tirages, quel que soit le client).
private _seed = _n getOrDefault ["scrSeed", -1];
if (_seed < 0) then { _seed = 1 + floor random 99999; _n set ["scrSeed", _seed]; };
private _k = _n getOrDefault ["scrN", 0];
private _rnd = { _k = _k + 1; private _v = (sin (_seed * 0.731 + _k * 97.13)) * 4375.85; _v - floor _v };
private _pickW = {
    // [[valeur, poids]...] -> valeur (tirage de l'appareil).
    params ["_w"];
    private _tot = 0;
    { _tot = _tot + (_x select 1); } forEach _w;
    private _r = (call _rnd) * _tot;
    private _out = (_w select 0) select 0;
    { _r = _r - (_x select 1); if (_r <= 0) exitWith { _out = _x select 0; }; } forEach _w;
    _out
};
private _add = {
    params ["_id", ["_sev", 1], ["_gx", -1], ["_gy", -1]];
    private _r = [_id] call _rowOf;
    if (_r isEqualTo []) exitWith { false };
    private _fam = _r select 1;
    if (_fam isEqualTo "glitch") exitWith { [_id] call comspec_atak_native_fnc_screenGlitch; true };
    _sev = (round _sev) max 1 min ([3, 2] select (_level isEqualTo 1));
    // Même genre déjà là : la cassure grandit depuis le même impact (jamais deux fois le même dessin).
    private _i = _list findIf { (_x select 0) isEqualTo _id };
    if (_i >= 0) exitWith { private _f = _list select _i; _f set [4, ((_f select 4) + 1) max _sev min 3]; true };
    // Plafonds : 6 bris de verre, 8 pannes de dalle.
    if (({ ([_x select 0] call _famOf) isEqualTo _fam } count _list) >= ([6, 8] select (_fam isEqualTo "panel"))) exitWith { false };
    if (_gx < 0) then { _gx = 0.1 + 0.8 * call _rnd; };
    if (_gy < 0) then { _gy = 0.08 + 0.84 * call _rnd; };
    _list pushBack [_id, floor ((call _rnd) * (_r select 6)), _gx, _gy, _sev, floor ((call _rnd) * 360), time, _k];
    true
};
private _save = {
    _n set ["scr", _list];
    _n set ["scrN", _k];
    missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
};
private _rate = (missionNamespace getVariable ["comspec_atak_native_screen_fault_rate", 1]) max 0 min 3;
switch (_mode) do {
    case "import": {
        _a params [["_s", 0], ["_c", 0], ["_l", []]];
        _n set ["scrSeed", _s];
        _k = _c;
        _list = +_l;
        call _save;
        true
    };
    case "add": {
        if (_level <= 0) exitWith { false };
        private _ok = _a call _add;
        call _save;
        _ok
    };
    case "cause": {
        _a params [["_cause", "shock"], ["_e", 0.3], ["_parts", createHashMap]];
        if (_level <= 0) exitWith { false };
        private _map = (["causes"] call comspec_atak_native_fnc_screenDamage) getOrDefault [_cause, []];
        if (_map isEqualTo []) exitWith { false };
        _map params ["_glass", "_panel", "_bugs"];
        private _did = false;
        // Verre : seulement si le choc a vraiment abîmé l'écran (énergie = dégâts ajoutés par fn_deviceDamage).
        if (_e >= 0.15 && {_glass isNotEqualTo []}) then {
            private _sev = [1, 2, 3] select ((parseNumber (_e >= 0.3)) + (parseNumber (_e >= 0.55)));
            _did = [[_glass] call _pickW, _sev] call _add;
            if (_e >= 0.4 && {(call _rnd) < 0.35}) then { [[_glass] call _pickW, _sev - 1] call _add; };
        } else {
            // Sous le seuil : au plus une rayure, une fêlure fine ou de l'eau (causes sans casse).
            if (_glass isNotEqualTo [] && {(call _rnd) < ([0.12, 0.6] select (_cause in ["water", "heat", "wear"])) * _rate}) then { _did = [[_glass] call _pickW, 1] call _add; };
        };
        // Dalle : pannes sans verre cassé, plus probables si un composant « pixels » a lâché.
        if (_panel isNotEqualTo [] && {["panel"] call _famOn}) then {
            private _p = (0.18 + 0.3 * (_e min 1) + 0.4 * (parseNumber ((_parts getOrDefault ["pixels", 0]) > 0))) * _rate * ([1, 0.5, 1, 1.3] select _level);
            if ((call _rnd) < _p) then { _did = ([[_panel] call _pickW, 1 + floor ((call _rnd) * (1 + (_e min 1.99)))] call _add) || _did; };
        };
        call _save;
        // Bug passager : presque toujours après un vrai choc.
        if (_bugs isNotEqualTo [] && {(call _rnd) < 0.8}) then { [selectRandom _bugs] call comspec_atak_native_fnc_screenGlitch; };
        _did
    };
    case "tick": {
        if (_level <= 0 || {!hasInterface} || {!alive player} || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith { false };
        private _changed = false;
        // Eau sous le verre : sèche hors de l'eau (une gravité de moins toutes les 4 min, puis disparaît).
        private _wet = (_n getOrDefault ["wet", 0]) > 0;
        {
            if ((_x select 0) isEqualTo "water" && {!_wet} && {(time - (_x select 6)) > 240}) then {
                _x set [4, (_x select 4) - 1];
                _x set [6, time];
                _changed = true;
            };
        } forEach _list;
        if (_changed) then { _list = _list select { (_x select 4) >= 1 }; };
        // Usure (réaliste seulement) : très rare, une panne toutes les quelques heures d'écran allumé.
        if (_level >= 3 && {(random 1) < 0.0006 * _rate}) then { ["cause", ["wear", 0.05]] call comspec_atak_native_fnc_screenDamage; _list = (missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["scr", _list]; _k = _n getOrDefault ["scrN", _k]; };
        if (_changed) then { call _save; };
        _changed
    };
    case "repair": {
        private _how = _a param [0, "screen"];
        private _before = count _list;
        switch (_how) do {
            // Écran remplacé : verre et dalle neufs.
            case "screen"; case "all": { _list = []; };
            // Nettoyage complet : on retire le film décollé. Le reste est dans le verre ou sous lui.
            case "film": { _list = _list select { (_x select 0) isNotEqualTo "peel" }; };
            // Redémarrage : les bugs passagers s'arrêtent ; un pixel bloqué sur deux se débloque.
            case "reboot": {
                ["stop"] call comspec_atak_native_fnc_screenGlitch;
                _list = _list select { (_x select 0) isNotEqualTo "stuckpx" || {(call _rnd) < 0.5} };
            };
        };
        call _save;
        (count _list) < _before
    };
    case "selftest": {
        // Tirages reproductibles (même graine, même suite) et aller-retour export / import, sans toucher à l'appareil.
        private _draw = { params ["_s"]; private _kk = 0; private _o = []; for "_i" from 1 to 12 do { _kk = _kk + 1; private _v = (sin (_s * 0.731 + _kk * 97.13)) * 4375.85; _o pushBack (_v - floor _v); }; _o };
        private _same = ([4242] call _draw) isEqualTo ([4242] call _draw);
        private _diff = ([4242] call _draw) isNotEqualTo ([4243] call _draw);
        private _state = [4242, 7, [["web", 1, 0.3, 0.4, 2, 120, 15, 3], ["deadpx", 0, 0.6, 0.7, 1, 10, 20, 5]]];
        private _back = parseSimpleArray (str _state);
        private _round = _back isEqualTo _state;
        private _ids = (_cat apply { _x select 0 });
        private _covered = createHashMap;
        { _y params ["_g", "_p", "_b"]; { _covered set [_x select 0, true]; } forEach (_g + _p); { _covered set [_x, true]; } forEach _b; } forEach (["causes"] call comspec_atak_native_fnc_screenDamage);
        private _missing = _ids select { !(_x in _covered) };
        [_same && _diff && _round && {_missing isEqualTo []}, format ["tirages %1/%2, aller-retour %3, sans cause : %4", _same, _diff, _round, _missing]]
    };
    default { false };
}
