/*
    Saleté de l'écran et de la coque (état de l'appareil, missionNamespace COMSPEC_ATAK_Device, valeurs 0-1) :
      dust (poussière), prints (traces de doigts), blood (sang sur l'écran), frameBlood (sang sur la coque),
      drops (gouttes de pluie). Mains en sang : missionNamespace COMSPEC_ATAK_HandsBlood (0-1).
    Params : [mode, valeur]
      "enabled" : la saleté est-elle active ? (réglage serveur comspec_atak_native_dirt_sim et réglage Réalisme du joueur)
      "tick"    : toutes les 5 s (XEH) : poussière dehors (plus vite couché, sur un sol sableux, en véhicule ouvert,
                  écran sorti), pluie (gouttes, la poussière part un peu, le sang s'étale), mains en sang ou sales qui
                  touchent l'écran tenu en main, saignement de ses propres bras ;
      "blast"   : poussière soulevée par une explosion (valeur : dégâts du souffle) ;
      "water"   : immersion (la poussière part, le sang s'étale en traînées) ;
      "treat"   : soin ACE donné à un blessé qui saigne (valeur : patient) : mains en sang ;
      "clean"   : nettoyage terminé (fn_screenClean) : la poussière et les gouttes partent, le sang demande deux passages ;
                  valeur true : nettoyage complet (tout part, même avec des mains en sang) ;
      "tap"     : [x, y] appui sur l'écran (fn_displayLoad) : des mains en sang laissent une empreinte sous le doigt ;
      "hit"     : coups reçus (file de fn_deviceImpact « hits ») : téléphone en main ou sur le gilet, le sang gicle dessus ;
      "nearby"  : [distance] un homme tué tout près, téléphone sorti : projections fines ;
      "zeus"    : [intensité 1-100] écran couvert de sang par le Zeus ;
      "stainsCount" : nombre de taches (fn_screenClean).
    Taches de sang (1.97.0) : liste COMSPEC_ATAK_Device "stains", chaque tache gardée à sa place (plus de sang qui
    change de place quand une autre tache apparaît) : [genre, variante, x 0-1, y 0-1, taille (part de la hauteur de
    l'écran), angle, opacité 0-1, heure de la tache (time : le sang sèche et brunit, fn_screenSurface)]. Genres : print (empreinte de doigt), smear (traînée), splat, hand (paume), drip
    (coulure), spatter (projection fine), pool (nappe, gore). « blood » reste la couverture globale (0-1).
    Style (réglage serveur comspec_atak_native_blood_style) : 0 discret, 1 réaliste, 2 gore (plus de taches, plus
    grandes, nappes et mains entières).
    Rendu : fn_screenSurface. Rien n'est envoyé sur le réseau.
*/
params [["_mode", "tick"], ["_v", 0]];
private _on = (missionNamespace getVariable ["comspec_atak_native_dirt_sim", true]) && {(["COMSPEC_ATAK_DirtFx", true, "native_dirt"] call comspec_atak_native_fnc_pref) select 0};
if (_mode isEqualTo "enabled") exitWith { _on };
if (_mode isEqualTo "stainsCount") exitWith { count ((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["stains", []]) };
if (!_on || {!hasInterface} || {!alive player}) exitWith { false };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _add = { params ["_k", "_d"]; _n set [_k, (((_n getOrDefault [_k, 0]) + _d) max 0) min 1]; };
private _hands = missionNamespace getVariable ["COMSPEC_ATAK_HandsBlood", 0];
if !("dirtSeed" in _n) then { _n set ["dirtSeed", floor random 100000]; };
private _stains = _n getOrDefault ["stains", []];
private _style = (missionNamespace getVariable ["comspec_atak_native_blood_style", 1]) max 0 min 2;
private _open = !isNull ([] call comspec_atak_native_fnc_display);
// Nouvelle tache, posée une fois pour toutes. x / y négatifs : au hasard (zones touchées : bords, bas, milieu).
private _stain = {
    params ["_kind", ["_sx", -1], ["_sy", -1], ["_size", 0.1], ["_alpha", 0.8], ["_ang", -1]];
    private _vars = createHashMapFromArray [["print", 4], ["smear", 3], ["splat", 3], ["hand", 2], ["drip", 2], ["spatter", 2], ["pool", 2]];
    if (_sx < 0) then { _sx = selectRandomWeighted [0.08, 3, 0.5, 2, 0.9, 3, random 1, 2]; _sx = (_sx + (random 0.16) - 0.08) max 0.02 min 0.98; };
    if (_sy < 0) then { _sy = 0.15 + random 0.8; };
    if (_ang < 0) then { _ang = [random 360, (random 30) - 15] select (_kind isEqualTo "drip"); };
    _size = _size * ([0.75, 1, 1.35] select _style);
    _stains pushBack [_kind, floor random (_vars getOrDefault [_kind, 1]), _sx, _sy, _size, _ang, _alpha max 0.05 min 1, time];
    // Au plus 40 taches : les plus pâles partent d'abord.
    if ((count _stains) > 40) then {
        private _low = 0;
        { if ((_x select 6) < ((_stains select _low) select 6)) then { _low = _forEachIndex; }; } forEach _stains;
        _stains deleteAt _low;
    };
};
switch (_mode) do {
    case "tick": {
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        private _carry = ["carry"] call comspec_atak_native_fnc_deviceImpact;
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _open = !isNull ([] call comspec_atak_native_fnc_display);
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
        // Pluie : gouttes sur l'écran sorti, qui sèchent ensuite ; la pluie rince un peu la poussière et étale le sang.
        if (!_roof && {_veh isEqualTo player || _openVeh} && {rain > 0.1}) then {
            if (_open) then { ["drops", 0.25 * rain] call _add; };
            ["dust", -0.004 * rain] call _add;
            if (_stains isNotEqualTo []) then { { _x set [6, (_x select 6) * (1 - 0.015 * rain)]; } forEach _stains; ["prints", 0.003 * rain] call _add; };
        } else {
            ["drops", -0.08] call _add;
        };
        // Mes bras saignent : mes mains aussi.
        private _bleed = player getVariable ["ace_medical_woundBleeding", 0];
        if (_bleed isEqualType 0 && {_bleed > 0}) then {
            private _arms = false;
            private _ow = player getVariable ["ace_medical_openWounds", createHashMap];
            if (_ow isEqualType createHashMap) then { _arms = ((_ow getOrDefault ["leftarm", []]) + (_ow getOrDefault ["rightarm", []])) isNotEqualTo []; };
            if (_arms || {random 1 < 0.3}) then { _hands = (_hands + 0.05 + (_bleed * 2 min 0.2)) min 1; };
        };
        // Téléphone en main : les doigts laissent leurs traces (et le sang de mains ensanglantées, sur l'écran et la coque).
        if (_inHand) then {
            ["prints", 0.0008 + 0.003 * ((_n getOrDefault ["dust", 0]) min 1) * (parseNumber ((stance player) isEqualTo "PRONE"))] call _add;
            if (_hands > 0.05) then {
                // Mains en sang : empreintes de doigts là où l'on touche, parfois une traînée, la coque se tache.
                if (random 1 < 0.6) then { ["print", -1, -1, 0.11 + random 0.05, 0.35 + 0.55 * _hands] call _stain; };
                if (random 1 < 0.12 * _hands) then { ["smear", -1, -1, 0.1 + random 0.05, 0.4 + 0.5 * _hands] call _stain; };
                if (_style isEqualTo 2 && {_hands > 0.6} && {random 1 < 0.08}) then { ["hand", -1, 0.5 + random 0.4, 0.3 + random 0.1, 0.75] call _stain; };
                ["frameBlood", 0.04 * _hands] call _add;
                ["prints", 0.01] call _add;
                _hands = _hands * 0.95;
            };
        };
        // Le sang sèche sur les mains ou part en se frottant : ~10 minutes.
        _hands = (_hands - 0.008) max 0;
    };
    case "blast": {
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        ["dust", (0.05 + 0.3 * ((_v * 3) min 1)) * ([0.5, 1] select (!isNull ([] call comspec_atak_native_fnc_display)))] call _add;
    };
    case "water": {
        _n set ["dust", (_n getOrDefault ["dust", 0]) * 0.5];
        _n set ["drops", 1];
        if (_stains isNotEqualTo []) then { { _x set [6, (_x select 6) * 0.6]; } forEach _stains; ["prints", 0.1] call _add; };
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
    };
    case "clean": {
        // Nettoyage complet (valeur true) : tout part, coque comprise, quel que soit l'état des mains.
        if (_v isEqualTo true) exitWith {
            { _n set [_x, 0]; } forEach ["dust", "drops", "prints", "frameBlood"];
            _stains = [];
            _hands = _hands * 0.5;
        };
        // Un vrai coup de chiffon (retour de jeu : l'ancien passage laissait un voile visible). Poussière, gouttes et
        // traces partent ; le sang demande deux passages ; mains en sang : on étale autant qu'on essuie.
        private _dirty = _hands > 0.3;
        _n set ["dust", [0, (_n getOrDefault ["dust", 0]) * 0.3] select _dirty];
        _n set ["drops", 0];
        _n set ["prints", [0, (_n getOrDefault ["prints", 0]) * 0.6] select _dirty];
        { _x set [6, (_x select 6) * ([0.25, 0.8] select _dirty)]; } forEach _stains;
        _stains = _stains select { (_x select 6) >= 0.08 };
        if (_dirty) then { ["smear", -1, -1, 0.12, 0.3 + 0.4 * _hands] call _stain; };
        _n set ["frameBlood", (_n getOrDefault ["frameBlood", 0]) * ([0.3, 0.9] select _dirty)];
        if ((_n getOrDefault ["frameBlood", 0]) < 0.04) then { _n set ["frameBlood", 0]; };
    };
    case "tap": {
        // Empreinte sous le doigt : mains en sang, téléphone en main, au plus une toutes les 0,4 s.
        if (_hands < 0.08 || {!_open}) exitWith {};
        if (diag_tickTime < (uiNamespace getVariable ["COMSPEC_ATAK_BloodTapAt", 0])) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_BloodTapAt", diag_tickTime + 0.4];
        ((([] call comspec_atak_native_fnc_layoutGet) get "device")) params ["_rx", "_ry", "_rw", "_rh"];
        _v params [["_mx", -10], ["_my", -10]];
        if (_rw <= 0 || {_rh <= 0}) exitWith {};
        private _fx = (_mx - _rx) / _rw;
        private _fy = (_my - _ry) / _rh;
        if (_fx < 0 || {_fx > 1} || {_fy < 0} || {_fy > 1}) exitWith {};
        ["print", _fx, _fy, 0.09 + random 0.04, 0.3 + 0.6 * _hands] call _stain;
        _hands = _hands * 0.97;
    };
    case "hit": {
        // Coups reçus ce tour-ci : [[partie, dégâts, dégâts bruts, munition]...]. Chutes et accidents ne saignent pas sur l'écran.
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        private _carry = ["carry"] call comspec_atak_native_fnc_deviceImpact;
        private _sum = 0;
        private _near = 0;
        {
            _x params [["_part", ""], ["_d", 0], ["_raw", 0], ["_ammo", ""]];
            if !(_ammo isEqualType "") then { _ammo = ""; };
            if ((["falling", "vehiclecrash", "collision", "drowning", "ropeburn"] findIf { (toLower _ammo find _x) >= 0 }) >= 0) then { continue };
            private _w = (_d max _raw) min 1.5;
            _sum = _sum + _w;
            // Le sang part surtout des bras et du torse quand le téléphone est tenu, du torse s'il est sur le gilet.
            if (_part in ["leftarm", "rightarm", "hand_l", "hand_r", "head", "body"]) then { _near = _near + _w; };
        } forEach _v;
        if (_sum < 0.05) exitWith {};
        private _exposed = _open || {_carry in ["hand", "vest"]};
        if (!_exposed) exitWith {};
        private _k = (_near max (_sum * 0.4)) * ([0.6, 1, 1.8] select _style);
        if (_open) then {
            ["spatter", -1, -1, 0.3 + random 0.25, (0.5 + _k) min 1] call _stain;
            for "_i" from 1 to (1 + floor (_k * 3)) do { ["splat", -1, -1, 0.08 + random 0.08, (0.6 + _k) min 1] call _stain; };
            if (_k > 0.6) then { ["drip", -1, random 0.3, 0.25 + random 0.15, 0.85] call _stain; };
            if (_style isEqualTo 2 && {_k > 0.8}) then { ["pool", -1, -1, 0.35 + random 0.2, 0.9] call _stain; };
        } else {
            // Rangé sur le gilet : l'écran est en partie protégé, la coque prend le plus gros.
            ["splat", -1, -1, 0.07 + random 0.06, (0.4 + _k * 0.5) min 0.9] call _stain;
        };
        ["frameBlood", 0.15 + 0.3 * _k] call _add;
        _hands = (_hands + 0.2 * _k) min 1;
    };
    case "nearby": {
        // Un homme tué à quelques mètres, téléphone sorti : projections fines, plus fortes de près.
        if (!_open || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {};
        private _dist = (_v param [0, 3]) max 0.5;
        private _k = (((3.5 - _dist) / 3) max 0) * ([0.5, 1, 1.7] select _style);
        if (_k <= 0.05) exitWith {};
        ["spatter", -1, -1, 0.25 + 0.2 * _k, (0.35 + 0.5 * _k) min 1] call _stain;
        if (_k > 0.5 || {_style isEqualTo 2}) then { ["splat", -1, -1, 0.08 + random 0.06, 0.75] call _stain; };
        if (_style isEqualTo 2 && {_k > 0.7}) then { ["drip", -1, random 0.3, 0.3, 0.85] call _stain; };
        ["frameBlood", 0.1 * _k] call _add;
    };
    case "zeus": {
        // Zeus : écran couvert de sang (intensité 1-100), même rangé.
        private _i = ((_v param [0, 50]) max 1 min 100) / 100;
        for "_j" from 1 to (2 + floor (_i * 8)) do {
            private _kind = selectRandomWeighted ["splat", 3, "smear", 3, "print", 2, "spatter", 2, "drip", 1, "hand", [0, 1] select (_i > 0.5), "pool", [0, 1] select (_i > 0.7 && {_style >= 1})];
            private _size = createHashMapFromArray [["splat", 0.12], ["smear", 0.12], ["print", 0.12], ["spatter", 0.4], ["drip", 0.3], ["hand", 0.35], ["pool", 0.4]];
            [_kind, -1, -1, (_size getOrDefault [_kind, 0.12]) * (0.8 + random 0.4), 0.6 + 0.4 * _i] call _stain;
        };
        ["frameBlood", 0.2 + 0.6 * _i] call _add;
    };
};
// Couverture globale de sang (diagnostic, nettoyage) : somme des taches pondérée par leur taille.
private _cov = 0;
{ _cov = _cov + (_x select 6) * ((_x select 4) * 4 min 1.5); } forEach _stains;
_n set ["blood", (_cov / 3) min 1];
_n set ["stains", _stains];
missionNamespace setVariable ["COMSPEC_ATAK_HandsBlood", _hands];
missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
true
