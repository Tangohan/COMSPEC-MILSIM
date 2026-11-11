/*
    Saleté de l'écran et de la coque (état de l'appareil, missionNamespace COMSPEC_ATAK_Device, valeurs 0-1) :
      dust (poussière), prints (traces de doigts), blood (part de l'écran couverte de sang), frameBlood (sang sur la coque),
      drops (gouttes de pluie). Mains en sang : missionNamespace COMSPEC_ATAK_HandsBlood (0-1).
    Params : [mode, valeur]
      "enabled" : la saleté est-elle active ? (réglage serveur comspec_atak_native_dirt_sim et réglage Réalisme du joueur)
      "tick"    : toutes les 5 s (XEH) : poussière dehors (plus vite couché, sur un sol sableux, en véhicule ouvert,
                  écran sorti), pluie (gouttes, la poussière part un peu, le sang s'étale), mains en sang ou sales qui
                  touchent l'écran tenu en main, son propre saignement (ACE : débit, volume de sang, plaies aux bras,
                  inconscience ; sans ACE : saignement et dégâts du jeu), blessé qui saigne porté ou traîné (ACE),
                  coulures fraîches qui descendent l'écran ;
      "blast"   : poussière soulevée par une explosion (valeur : dégâts du souffle) ;
      "water"   : immersion (la poussière part, le sang s'étale et redevient humide) ;
      "treat"   : soin ACE donné à un blessé qui saigne (valeur : patient) : mains en sang ;
      "clean"   : nettoyage terminé (fn_screenClean). valeur false : coup de chiffon (le sang frais s'étale en traînées,
                  le sang séché tient) ; true : nettoyage complet (il peut rester un voile de sang séché) ;
                  "kit" : nettoyage avec un kit (tout part) ;
      "tap"     : [x, y] appui sur l'écran (fn_displayLoad) : des mains en sang laissent une empreinte sous le doigt ;
      "hit"     : coups reçus (file de fn_deviceImpact « hits ») : téléphone en main ou sur le gilet, le sang gicle dessus ;
      "nearby"  : [distance] un homme tué tout près, téléphone sorti ou sur le gilet : projections, plus fortes de près ;
      "zeus"    : [intensité 1-100] écran couvert de sang par le Zeus ;
      "force"   : [niveau 1-4] taches d'un niveau posées tout de suite (autotest) ;
      "age"     : [secondes] vieillit toutes les taches (autotest : sang séché) ;
      "residue" : [taches humides, taches séchées, traînées d'essuyage] (fn_screenClean) ;
      "maxLevel": niveau maximal permis (réglage comspec_atak_native_blood_max, profil de réalisme, style) ;
      "stainsCount" : nombre de taches (fn_screenClean).
    Niveaux de sang (4.4) : 1 léger (gouttes, empreintes), 2 moyen (traînées, projections), 3 lourd (grandes nappes,
    paumes, coulures, jet artériel), 4 extrême (écran presque couvert, voile de sang, on lit entre les taches). Chaque
    cause donne un niveau (débit du saignement, volume de sang, inconscience, coup reçu, mort à bout portant, blessé
    porté) ; le niveau est borné par le réglage et par l'exposition du téléphone (en main : plein niveau ; miniature
    portée ou sur le gilet : un niveau de moins ; dans le sac : rien). Un passage pose des taches tant que l'écran n'est
    pas couvert à la mesure du niveau (cibles _target).
    Taches de sang (1.97.0) : liste COMSPEC_ATAK_Device "stains", chaque tache gardée à sa place : [genre, variante,
    x 0-1, y 0-1, taille (part du petit côté de l'écran), angle, opacité 0-1, heure de la tache (time : le sang sèche,
    fn_screenSurface), paysage (orientation où elle a été posée : la tache tourne avec le verre), débit (coulures : 0
    ou nombre de segments qui restent à couler)]. Genres : print, smear, splat, hand, drip, spatter, pool, run (coulure
    épaisse, segments bout à bout vers le bas de l'écran), arterial (jet), drag (main qui a glissé), wipe (traînée
    d'essuyage), cover et veil (plein écran). « blood » : part couverte (union approchée des taches).
    Style (réglage serveur comspec_atak_native_blood_style) : 0 discret (au plus le niveau 2), 1 réaliste, 2 gore.
    Rendu : fn_screenSurface. Rien n'est envoyé sur le réseau.
*/
params [["_mode", "tick"], ["_v", 0]];
private _on = (missionNamespace getVariable ["comspec_atak_native_dirt_sim", true]) && {(["profile"] call comspec_atak_native_fnc_netCond) isNotEqualTo 1} && {(["COMSPEC_ATAK_DirtFx", true, "native_dirt"] call comspec_atak_native_fnc_pref) select 0};
if (_mode isEqualTo "enabled") exitWith { _on };
if (_mode isEqualTo "stainsCount") exitWith { count ((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["stains", []]) };
private _style = (missionNamespace getVariable ["comspec_atak_native_blood_style", 1]) max 0 min 2;
// Niveau maximal : réglage (0 = selon le profil : extrême en Milsim, fort sinon), style discret : au plus moyen.
private _maxSet = missionNamespace getVariable ["comspec_atak_native_blood_max", 0];
private _maxLvl = if (_maxSet isEqualTo 0) then { [3, 4] select ((["profile"] call comspec_atak_native_fnc_netCond) isEqualTo 3) } else { _maxSet max 1 min 4 };
if (_style isEqualTo 0) then { _maxLvl = _maxLvl min 2; };
if (_mode isEqualTo "maxLevel") exitWith { _maxLvl };
// Séchage 0-1 : frais la première minute, sec vers 6 min (même calcul que fn_screenSurface).
private _dryOf = { (((time - (_this param [7, time])) - 60) / 300) max 0 min 1 };
if (_mode isEqualTo "residue") exitWith {
    private _st = (missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["stains", []];
    [{ !((_x select 0) in ["wipe"]) && {(_x call _dryOf) < 0.6} } count _st, { !((_x select 0) in ["wipe"]) && {(_x call _dryOf) >= 0.6} } count _st, { (_x select 0) isEqualTo "wipe" } count _st]
};
if (!_on || {!hasInterface} || {!alive player}) exitWith { false };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _add = { params ["_k", "_d"]; _n set [_k, (((_n getOrDefault [_k, 0]) + _d) max 0) min 1]; };
private _hands = missionNamespace getVariable ["COMSPEC_ATAK_HandsBlood", 0];
if !("dirtSeed" in _n) then { _n set ["dirtSeed", floor random 100000]; };
private _stains = _n getOrDefault ["stains", []];
private _open = !isNull ([] call comspec_atak_native_fnc_display);
// Orientation et proportions de l'écran (les tailles sont en part du petit côté ; positions en part de chaque côté).
private _lay = [] call comspec_atak_native_fnc_layoutGet;
private _land = _lay getOrDefault ["landscape", true];
((_lay getOrDefault ["device", [0, 0, 1, 1]])) params ["", "", ["_lw", 1], ["_lh", 1]];
private _asp = ((((_lw * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW])) / (_lh max 0.001)) max (_lh / ((_lw * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW])) max 0.001))) max 1) min 3;
// Part de l'écran couverte par une tache de cette taille (union approchée plus bas).
private _fill = createHashMapFromArray [["print", 0.06], ["splat", 0.3], ["smear", 0.7], ["spatter", 0.2], ["drip", 0.1], ["hand", 0.4], ["drag", 0.18], ["pool", 0.5], ["arterial", 0.3], ["run", 0.05], ["wipe", 0.5]];
private _area = {
    params ["_kind", "_size"];
    if (_kind isEqualTo "cover") exitWith { 0.78 };
    if (_kind isEqualTo "veil") exitWith { 0.4 };
    (_size * _size * (_fill getOrDefault [_kind, 0.2]) / _asp) min 1
};
private _covOf = { private _p = 1; { _p = _p * (1 - (([_x select 0, _x select 4] call _area) * (_x select 6)) min 1); } forEach _stains; 1 - _p };
// Nouvelle tache, posée une fois pour toutes. x / y négatifs : au hasard (zones touchées : bords, bas, milieu).
private _vars = createHashMapFromArray [["print", 4], ["smear", 3], ["splat", 4], ["hand", 4], ["drip", 2], ["spatter", 3], ["pool", 4], ["run", 3], ["arterial", 2], ["drag", 2], ["wipe", 3], ["cover", 2], ["veil", 1]];
private _stain = {
    params ["_kind", ["_sx", -1], ["_sy", -1], ["_size", 0.1], ["_alpha", 0.8], ["_ang", -1], ["_flow", 0]];
    if (_sx < 0) then { _sx = selectRandomWeighted [0.08, 3, 0.5, 2, 0.9, 3, random 1, 2]; _sx = (_sx + (random 0.16) - 0.08) max 0.02 min 0.98; };
    if (_sy < 0) then { _sy = 0.15 + random 0.8; };
    if (_ang < 0) then { _ang = [random 360, (random 16) - 8] select (_kind in ["drip", "run", "drag"]); };
    if !(_kind in ["cover", "veil"]) then { _size = _size * ([0.75, 1, 1.2] select _style); };
    _stains pushBack [_kind, floor random (_vars getOrDefault [_kind, 1]), _sx, _sy, _size, _ang, _alpha max 0.05 min 1, time];
    (_stains select ((count _stains) - 1)) append [_land, _flow];
    // Au plus 48 taches : celles qui comptent le moins (pâles et petites) partent d'abord, jamais les 6 dernières.
    while { (count _stains) > 48 } do {
        private _low = 0;
        private _lv = 9;
        for "_i" from 0 to ((count _stains) - 7) do {
            private _s = _stains select _i;
            private _w = (_s select 6) * ((([_s select 0, _s select 4] call _area) * 4) max 0.05);
            if (_w < _lv) then { _lv = _w; _low = _i; };
        };
        _stains deleteAt _low;
    };
};
// Coulure qui part du bas d'une tache (x, y du centre, demi-hauteur en part du petit côté).
private _runFrom = {
    params ["_x0", "_y0", "_half", ["_flow", 2]];
    private _len = 0.35 + random 0.3;
    private _fy = [1 / _asp, 1] select _land;
    private _y = _y0 + (_half * 0.6 + _len / 2) * _fy;
    if (_y - _len * _fy / 2 > 0.95) exitWith {};
    ["run", (_x0 + (random 0.06) - 0.03) max 0.02 min 0.98, _y, _len, 0.9, -1, _flow] call _stain;
};
// Un passage de taches d'un niveau. Source : "bleed" (son sang ou celui d'un blessé), "hit" (impact), "spray"
// (mort à bout portant), "hands" (mains en sang).
private _target = [0, 0.06, 0.2, 0.45, 0.85];
private _burst = {
    params ["_lvl", ["_src", "bleed"]];
    _lvl = _lvl min _maxLvl;
    if (_lvl <= 0) exitWith {};
    private _cov = call _covOf;
    switch (_lvl) do {
        case 1: {
            for "_i" from 1 to (1 + floor random 2) do { [selectRandom ["splat", "print", "splat"], -1, -1, 0.1 + random 0.08, 0.55 + random 0.3] call _stain; };
            if (_src isEqualTo "spray") then { ["spatter", -1, -1, 0.3 + random 0.15, 0.6] call _stain; };
        };
        case 2: {
            ["spatter", -1, -1, 0.4 + random 0.25, 0.75 + random 0.2] call _stain;
            for "_i" from 1 to (1 + floor random 2) do { ["splat", -1, -1, 0.14 + random 0.12, 0.75 + random 0.2] call _stain; };
            if (_src in ["bleed", "hands"] || {random 1 < 0.5}) then { ["smear", -1, -1, 0.22 + random 0.12, 0.65 + random 0.25] call _stain; };
            if (_src isEqualTo "bleed" && {random 1 < 0.5}) then { ["drip", -1, random 0.4, 0.3 + random 0.15, 0.85] call _stain; };
        };
        case 3: {
            // Grandes nappes, paume ou main qui glisse, coulures, jet artériel sur un impact.
            for "_i" from 1 to (1 + floor random 2) do {
                private _x0 = 0.15 + random 0.7;
                private _y0 = 0.15 + random 0.55;
                private _s = 0.5 + random 0.3;
                ["pool", _x0, _y0, _s, 0.88 + random 0.12] call _stain;
                if (random 1 < 0.7) then { [_x0, _y0, _s / 2, 1 + floor random 3] call _runFrom; };
            };
            if (_src in ["hit", "spray"]) then { ["arterial", 0.3 + random 0.4, 0.2 + random 0.5, 0.4 + random 0.2, 0.9] call _stain; };
            if (_src in ["bleed", "hands"] || {random 1 < 0.4}) then { [selectRandom ["hand", "drag"], 0.15 + random 0.7, 0.3 + random 0.4, 0.55 + random 0.3, 0.85] call _stain; };
            ["spatter", -1, -1, 0.5 + random 0.25, 0.85] call _stain;
            ["splat", -1, -1, 0.22 + random 0.15, 0.9] call _stain;
        };
        default {
            // Extrême : d'abord de grandes nappes et des coulures ; une fois l'écran bien pris, le voile et la
            // couche presque continue (un seul de chaque, rafraîchi s'il existe déjà).
            if (_cov < 0.4 && {_src isNotEqualTo "spray"}) then {
                for "_i" from 1 to 3 do {
                    private _x0 = 0.1 + random 0.8;
                    private _y0 = 0.1 + random 0.5;
                    private _s = 0.7 + random 0.3;
                    ["pool", _x0, _y0, _s, 0.92 + random 0.08] call _stain;
                    [_x0, _y0, _s / 2, 2 + floor random 3] call _runFrom;
                };
                ["arterial", 0.3 + random 0.4, 0.2 + random 0.4, 0.5 + random 0.15, 0.95] call _stain;
                [selectRandom ["hand", "drag"], 0.15 + random 0.7, 0.3 + random 0.4, 0.7 + random 0.3, 0.9] call _stain;
            } else {
                {
                    _x params ["_k", "_a"];
                    private _i = _stains findIf { (_x select 0) isEqualTo _k };
                    if (_i < 0) then { [_k, 0.5, 0.5, 1, _a] call _stain; } else {
                        private _s = _stains select _i;
                        _s set [6, ((_s select 6) max _a)];
                        // Sang neuf par-dessus : redevient frais.
                        _s set [7, time];
                    };
                } forEach [["veil", 0.85], ["cover", 0.95]];
                ["arterial", 0.25 + random 0.5, 0.15 + random 0.5, 0.5 + random 0.15, 0.95] call _stain;
                for "_i" from 1 to (2 + floor random 3) do { ["run", 0.05 + random 0.9, 0.2 + random 0.3, 0.4 + random 0.25, 0.92, -1, 2 + floor random 3] call _stain; };
                ["pool", 0.15 + random 0.7, 0.2 + random 0.5, 0.8 + random 0.2, 0.95] call _stain;
            };
            ["frameBlood", 0.4] call _add;
        };
    };
    ["frameBlood", 0.04 * _lvl] call _add;
};
// Exposition : en main, plein niveau ; miniature portée ou téléphone sur le gilet / à la main, un niveau de moins ;
// dans l'uniforme ou le sac, rien sur l'écran.
private _expose = {
    params ["_lvl"];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if (_open && {_s getOrDefault ["interactive", false]}) exitWith { _lvl };
    if (_open || {(["carry"] call comspec_atak_native_fnc_deviceImpact) in ["hand", "vest"]}) exitWith { _lvl - 1 };
    0
};
// Niveau de sang d'une unité qui saigne (ACE : débit et volume ; sinon saignement et dégâts du jeu).
private _bleedLvl = {
    params ["_u"];
    if (isNull _u) exitWith { 0 };
    private _b = _u getVariable ["ace_medical_woundBleeding", -1];
    private _l = 0;
    if (_b isEqualType 0 && {_b >= 0}) then {
        if (_b > 0) then {
            private _vol = _u getVariable ["ace_medical_bloodVolume", 6];
            if !(_vol isEqualType 0) then { _vol = 6; };
            _l = 1 + parseNumber (_b > 0.02) + parseNumber (_b > 0.05) + parseNumber (_b > 0.1);
            if (_vol < 5) then { _l = _l max 3; };
            if (_vol < 4 || {(_u getVariable ["ACE_isUnconscious", false]) isEqualTo true && {_b > 0.02}}) then { _l = 4; };
        };
    } else {
        if (isBleeding _u) then {
            _l = 1 + parseNumber ((damage _u) > 0.35) + parseNumber ((damage _u) > 0.65) + parseNumber ((lifeState _u) isEqualTo "INCAPACITATED");
        };
    };
    _l min 4
};
switch (_mode) do {
    case "tick": {
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        private _carry = ["carry"] call comspec_atak_native_fnc_deviceImpact;
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _inHand = _open && {_s getOrDefault ["interactive", false]};
        private _veh = vehicle player;
        private _eye = eyePos player;
        private _roof = (count (lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 20], player, _veh, true, 1, "GEOM", "NONE"])) > 0;
        private _openVeh = _veh isNotEqualTo player && {isTurnedOut player || {_veh isKindOf "Motorcycle"} || {_veh isKindOf "Quadbike_01_base_F"} || {(_veh emptyPositions "cargo") > 0 && {_veh isKindOf "Helicopter"} && {(speed _veh) > 20}}};
        // Poussière : dehors, à pied ou en véhicule ouvert ; écran sorti, elle se dépose beaucoup plus vite.
        if (!_roof && {_veh isEqualTo player || _openVeh}) then {
            // Base lente : couche complète en ~2 h écran sorti debout, ~20 min couché sur du sable (retours de jeu : trop rapide).
            private _r = 0.0004;
            if (_veh isEqualTo player) then {
                if ((stance player) isEqualTo "PRONE") then { _r = _r * 4; };
                if ((speed player) > 10) then { _r = _r * 1.5; };
                private _surf = toLower surfaceType getPosATL player;
                if ((["sand", "dirt", "desert", "dust", "soil", "gravel"] findIf { (_surf find _x) >= 0 }) >= 0) then { _r = _r * 1.6; };
            } else {
                _r = _r * (2 + ((speed _veh) / 40) min 3);
            };
            _r = _r * ([0.4, 2] select _open) * (createHashMapFromArray [["hand", 1], ["vest", 1], ["uniform", 0.5], ["backpack", 0.2]] getOrDefault [_carry, 1]);
            _r = _r * (1 - rain * 0.7);
            ["dust", _r] call _add;
        };
        // Pluie : gouttes sur l'écran sorti, qui sèchent ensuite ; la pluie rince un peu la poussière, étale le sang
        // et le garde humide.
        if (!_roof && {_veh isEqualTo player || _openVeh} && {rain > 0.1}) then {
            if (_open) then { ["drops", 0.25 * rain] call _add; };
            ["dust", -0.004 * rain] call _add;
            if (_stains isNotEqualTo [] && {_open}) then { { _x set [6, (_x select 6) * (1 - 0.015 * rain)]; _x set [7, (_x select 7) max (time - 90)]; } forEach _stains; ["prints", 0.003 * rain] call _add; };
        } else {
            ["drops", -0.08] call _add;
        };
        // Son propre saignement : les mains d'abord (bras blessés : toujours), puis l'écran selon l'exposition.
        private _self = [player] call _bleedLvl;
        if (_self > 0) then {
            private _bleed = player getVariable ["ace_medical_woundBleeding", 0];
            if !(_bleed isEqualType 0) then { _bleed = 0.03; };
            private _arms = false;
            private _ow = player getVariable ["ace_medical_openWounds", createHashMap];
            if (_ow isEqualType createHashMap) then { _arms = ((_ow getOrDefault ["leftarm", []]) + (_ow getOrDefault ["rightarm", []])) isNotEqualTo []; };
            if (_arms || {random 1 < 0.3}) then { _hands = (_hands + 0.05 + (_bleed * 2 min 0.2)) min 1; };
            // Bras qui saignent et téléphone en main : le sang coule directement dessus (un niveau de plus).
            private _l = ([_self + parseNumber (_arms && _inHand)] call _expose) min 4;
            if (_l > 0 && {(call _covOf) < (_target select _l)} && {random 1 < 0.75}) then { [_l, "bleed"] call _burst; };
        };
        // Blessé qui saigne porté ou traîné (ACE) : mains en sang, et son sang sur le téléphone porté ou en main.
        private _cas = player getVariable ["ace_dragging_carriedObject", objNull];
        private _drag = false;
        if (isNull _cas) then { _cas = player getVariable ["ace_dragging_draggedObject", objNull]; _drag = true; };
        if (!isNull _cas && {_cas isKindOf "CAManBase"}) then {
            private _cl = [_cas] call _bleedLvl;
            if (_cl > 0) then {
                _hands = (_hands + 0.1 + 0.05 * _cl) min 1;
                private _l = [_cl - parseNumber _drag] call _expose;
                if (_l > 0 && {(call _covOf) < (_target select _l)} && {random 1 < 0.6}) then { [_l, "bleed"] call _burst; };
            };
        };
        // Téléphone en main : les doigts laissent leurs traces (et le sang de mains ensanglantées, sur l'écran et la coque).
        if (_inHand) then {
            ["prints", 0.0008 + 0.003 * ((_n getOrDefault ["dust", 0]) min 1) * (parseNumber ((stance player) isEqualTo "PRONE"))] call _add;
            if (_hands > 0.05) then {
                // Mains en sang : empreintes de doigts là où l'on touche, traînées, paume et main qui glisse quand
                // elles sont couvertes de sang ; la coque se tache.
                for "_i" from 1 to (1 + floor (_hands * 2)) do {
                    if (random 1 < 0.6) then { ["print", -1, -1, 0.18 + random 0.08, 0.35 + 0.55 * _hands] call _stain; };
                };
                if (random 1 < 0.3 * _hands) then { ["smear", -1, -1, 0.22 + random 0.12, 0.4 + 0.5 * _hands] call _stain; };
                if (_maxLvl >= 3 && {_hands > 0.6} && {random 1 < 0.15}) then { ["hand", 0.5 + random 0.4, 0.3 + random 0.3, 0.5 + random 0.2, 0.8] call _stain; };
                if (_maxLvl >= 3 && {_hands > 0.8} && {random 1 < 0.08}) then { ["drag", 0.15 + random 0.7, 0.45, 0.8 + random 0.2, 0.8] call _stain; };
                ["frameBlood", 0.04 * _hands] call _add;
                ["prints", 0.01] call _add;
                _hands = _hands * 0.95;
            };
        };
        // Coulures fraîches : un segment de plus vers le bas de l'écran à chaque passage (suit l'orientation
        // actuelle : téléphone tourné, la coulure change de sens), tant que le sang est frais et qu'il en reste.
        // (liste des coulures prise d'abord : mêmes tableaux, les segments ajoutés ne coulent qu'au passage suivant).
        {
            private _flow = _x select 9;
            _x set [9, 0];
            if ((time - (_x select 7)) <= 150) then {
                _x params ["", "", "_sx", "_sy", "_size", "_ang", "_alpha", "", ["_sl", _land]];
                // Centre et angle dans l'orientation actuelle (la tache tourne avec le verre).
                if (_sl isNotEqualTo _land) then {
                    if (_land) then { private _t = _sx; _sx = _sy; _sy = 1 - _t; _ang = _ang - 90; } else { private _t = _sx; _sx = 1 - _sy; _sy = _t; _ang = _ang + 90; };
                };
                private _fx = [1, 1 / _asp] select _land;
                private _fy = [1 / _asp, 1] select _land;
                private _ex = _sx - (sin _ang) * (_size / 2) * _fx;
                private _ey = _sy + (cos _ang) * (_size / 2) * _fy;
                private _len = (_size * (0.75 + random 0.2)) max 0.2;
                private _ny = _ey + (_len / 2) * _fy * 0.92;
                if (_ey < 0.94 && {_ex > 0.01} && {_ex < 0.99}) then {
                    ["run", _ex, _ny, _len, (_alpha * 0.95) max 0.5, -1, _flow - 1] call _stain;
                };
            };
        } forEach (_stains select { (_x select 0) isEqualTo "run" && {(_x param [9, 0]) > 0} });
        // Le sang sèche sur les mains ou part en se frottant : ~10 minutes.
        _hands = (_hands - 0.008) max 0;
    };
    case "blast": {
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        ["dust", (0.05 + 0.3 * ((_v * 3) min 1)) * ([0.5, 1] select _open)] call _add;
    };
    case "water": {
        _n set ["dust", (_n getOrDefault ["dust", 0]) * 0.5];
        _n set ["drops", 1];
        // Le sang redevient humide (il part ensuite au chiffon) et s'étale.
        if (_stains isNotEqualTo []) then { { _x set [6, (_x select 6) * 0.6]; _x set [7, (_x select 7) max (time - 60)]; } forEach _stains; ["prints", 0.1] call _add; };
        _hands = _hands * 0.3;
    };
    case "treat": {
        private _u = _v;
        if !(_u isEqualType objNull) exitWith {};
        if (isNull _u) exitWith {};
        private _b = _u getVariable ["ace_medical_woundBleeding", 0];
        if !(_b isEqualType 0) then { _b = 0; };
        private _ow = _u getVariable ["ace_medical_openWounds", createHashMap];
        private _wounds = if (_ow isEqualType createHashMap) then { (values _ow) findIf { _x isNotEqualTo [] } >= 0 } else { _ow isNotEqualTo [] };
        if (_b > 0 || _wounds || {!alive _u}) then { _hands = (_hands + 0.2 + (_b * 4 min 0.5)) min 1; };
        // Hémorragie massive : mains couvertes de sang.
        if (([_u] call _bleedLvl) >= 4) then { _hands = 1; };
    };
    case "clean": {
        private _kit = _v isEqualTo "kit";
        // Kit de nettoyage : tout part, coque comprise, quel que soit l'état des mains.
        if (_kit) exitWith {
            { _n set [_x, 0]; } forEach ["dust", "drops", "prints", "frameBlood"];
            _stains = [];
            _hands = _hands * 0.5;
        };
        // Nettoyage complet sans kit : tout le sang frais part ; le sang séché laisse un voile qu'il faut repasser.
        if (_v isEqualTo true) exitWith {
            { _n set [_x, 0]; } forEach ["dust", "drops", "prints", "frameBlood"];
            _stains = _stains select { !((_x select 0) isEqualTo "wipe") && {(_x call _dryOf) >= 0.6} };
            { _x set [6, (_x select 6) * 0.3]; } forEach _stains;
            _stains = _stains select { (_x select 6) >= 0.08 };
            _hands = _hands * 0.5;
        };
        // Un coup de chiffon (retour de jeu : l'ancien passage laissait un voile visible). Poussière, gouttes et
        // traces partent. Sang frais : il pâlit mais s'étale en traînées (beaucoup de sang : plusieurs passages) ;
        // sang séché : il tient (plusieurs passages ou un nettoyage complet) ; mains en sang : on étale autant qu'on essuie.
        private _dirty = _hands > 0.3;
        _n set ["dust", [0, (_n getOrDefault ["dust", 0]) * 0.3] select _dirty];
        _n set ["drops", 0];
        _n set ["prints", [0, (_n getOrDefault ["prints", 0]) * 0.6] select _dirty];
        private _heavy = (call _covOf) > 0.3;
        private _wang = (random 50) - 25 + ([0, 180] select (random 1 < 0.5));
        private _new = [];
        {
            private _k = _x select 0;
            private _a = _x select 6;
            private _d = _x call _dryOf;
            switch (true) do {
                case (_k isEqualTo "wipe"): { _x set [6, _a * ([0.4, 0.65] select _dirty)]; };
                case (_d >= 0.6): { _x set [6, _a * 0.72]; };
                default {
                    _x set [6, _a * ([0.3, 0.75] select _dirty)];
                    // Le sang frais part avec le chiffon mais laisse une traînée là où il y en avait beaucoup.
                    private _big = ([_k, _x select 4] call _area) * _a;
                    if ((_heavy || _dirty || {_big > 0.05}) && {(count _new) < 5} && {_big > 0.01}) then {
                        private _cx = [_x select 2, 0.5] select (_k in ["cover", "veil"]);
                        private _cy = [_x select 3, 0.3 + random 0.4] select (_k in ["cover", "veil"]);
                        private _sz = [((_x select 4) * 1.1) max 0.45 min 0.9, 0.9] select (_k in ["cover", "veil"]);
                        _new pushBack [_cx + (random 0.1) - 0.05, _cy + (random 0.1) - 0.05, _sz, (0.35 + _a * 0.5) min 0.85, _x select 7];
                    };
                };
            };
        } forEach _stains;
        _stains = _stains select { (_x select 6) >= 0.08 };
        {
            _x params ["_cx", "_cy", "_sz", "_a", "_t"];
            ["wipe", _cx, _cy, _sz, _a, (_wang + (random 16) - 8) mod 360] call _stain;
            // Traînée aussi humide que le sang qu'elle a étalé.
            (_stains select ((count _stains) - 1)) set [7, _t];
        } forEach _new;
        if (_dirty) then { ["smear", -1, -1, 0.22, 0.3 + 0.4 * _hands] call _stain; };
        _n set ["frameBlood", (_n getOrDefault ["frameBlood", 0]) * ([0.3, 0.9] select _dirty)];
        if ((_n getOrDefault ["frameBlood", 0]) < 0.04) then { _n set ["frameBlood", 0]; };
    };
    case "tap": {
        // Empreinte sous le doigt : mains en sang, téléphone en main, au plus une toutes les 0,4 s.
        if (_hands < 0.08 || {!_open}) exitWith {};
        if (diag_tickTime < (uiNamespace getVariable ["COMSPEC_ATAK_BloodTapAt", 0])) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_BloodTapAt", diag_tickTime + 0.4];
        (_lay getOrDefault ["device", [0, 0, 0, 0]]) params ["_rx", "_ry", "_rw", "_rh"];
        _v params [["_mx", -10], ["_my", -10]];
        if (_rw <= 0 || {_rh <= 0}) exitWith {};
        private _fx = (_mx - _rx) / _rw;
        private _fy = (_my - _ry) / _rh;
        if (_fx < 0 || {_fx > 1} || {_fy < 0} || {_fy > 1}) exitWith {};
        ["print", _fx, _fy, 0.16 + random 0.06, 0.3 + 0.6 * _hands] call _stain;
        _hands = _hands * 0.97;
    };
    case "hit": {
        // Coups reçus ce tour-ci : [[partie, dégâts, dégâts bruts, munition]...]. Chutes et accidents ne saignent pas sur l'écran.
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        private _carry = ["carry"] call comspec_atak_native_fnc_deviceImpact;
        private _sum = 0;
        private _near = 0;
        private _cnt = 0;
        {
            _x params [["_part", ""], ["_d", 0], ["_raw", 0], ["_ammo", ""]];
            if !(_ammo isEqualType "") then { _ammo = ""; };
            if ((["falling", "vehiclecrash", "collision", "drowning", "ropeburn"] findIf { (toLower _ammo find _x) >= 0 }) >= 0) then { continue };
            private _w = (_d max _raw) min 1.5;
            _sum = _sum + _w;
            _cnt = _cnt + 1;
            // Le sang part surtout des bras et du torse quand le téléphone est tenu, du torse s'il est sur le gilet.
            if (_part in ["leftarm", "rightarm", "hand_l", "hand_r", "head", "body"]) then { _near = _near + _w; };
        } forEach _v;
        if (_sum < 0.05) exitWith {};
        private _exposed = _open || {_carry in ["hand", "vest"]};
        if (!_exposed) exitWith {};
        private _k = (_near max (_sum * 0.4)) * ([0.6, 1, 1.5] select _style);
        // Niveau du coup : léger, moyen, lourd ; extrême pour un coup très grave ou plusieurs blessures proches.
        private _lvl = 1 + parseNumber (_k > 0.25) + parseNumber (_k > 0.6) + parseNumber (_k > 1.1 || {_cnt > 1 && {_near > 0.8}});
        [[_lvl min 4] call _expose, "hit"] call _burst;
        // Rangé sur le gilet : l'écran est en partie protégé, la coque prend le plus gros.
        if (!_open) then { ["splat", -1, -1, 0.12 + random 0.08, (0.4 + _k * 0.5) min 0.9] call _stain; };
        ["frameBlood", 0.15 + 0.3 * _k] call _add;
        _hands = (_hands + 0.2 * _k) min 1;
    };
    case "nearby": {
        // Un homme tué à quelques mètres, téléphone sorti ou sur le gilet : projections, plus fortes de près.
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        private _dist = (_v param [0, 3]) max 0.3;
        // À bout portant (moins de 1,2 m) : extrême, le jet couvre l'écran d'un coup (voile et couche continue).
        private _lvl = switch (true) do { case (_dist < 1.2): { 4 }; case (_dist < 2): { 3 }; case (_dist < 2.8): { 2 }; default { 1 }; };
        if (_style isEqualTo 2) then { _lvl = (_lvl + 1) min 4; };
        _lvl = [_lvl] call _expose;
        if (_lvl <= 0) exitWith {};
        [_lvl, "spray"] call _burst;
        ["frameBlood", 0.1 * _lvl] call _add;
    };
    case "zeus": {
        // Zeus : écran couvert de sang (intensité 1-100 : léger à extrême), même rangé.
        private _lvl = ceil (((_v param [0, 50]) max 1 min 100) / 25);
        for "_j" from 1 to 6 do {
            if ((call _covOf) >= (_target select (_lvl min _maxLvl))) exitWith {};
            [_lvl, "bleed"] call _burst;
        };
        ["frameBlood", 0.2 + 0.15 * _lvl] call _add;
    };
    case "force": {
        // Autotest : un niveau posé tout de suite, comme un saignement continu qui a eu le temps de s'installer.
        private _lvl = (_v param [0, 1]) max 1 min 4;
        for "_j" from 1 to 6 do {
            if ((call _covOf) >= (_target select (_lvl min _maxLvl))) exitWith {};
            [_lvl, ["bleed", "hit"] select (_j isEqualTo 2)] call _burst;
        };
    };
    case "age": {
        private _sec = _v param [0, 600];
        { _x set [7, (_x select 7) - _sec]; } forEach _stains;
    };
};
// Part de l'écran couverte (diagnostic, nettoyage, rapport de l'appareil).
_n set ["blood", (call _covOf) min 1];
_n set ["stains", _stains];
missionNamespace setVariable ["COMSPEC_ATAK_HandsBlood", _hands];
missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
true
