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
      "clean"   : nettoyage terminé (fn_screenClean) : la poussière et les gouttes partent, le sang demande deux passages.
    Rendu : fn_screenSurface. Rien n'est envoyé sur le réseau.
*/
params [["_mode", "tick"], ["_v", 0]];
private _on = (missionNamespace getVariable ["comspec_atak_native_dirt_sim", true]) && {(["COMSPEC_ATAK_DirtFx", true, "native_dirt"] call comspec_atak_native_fnc_pref) select 0};
if (_mode isEqualTo "enabled") exitWith { _on };
if (!_on || {!hasInterface} || {!alive player}) exitWith { false };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _add = { params ["_k", "_d"]; _n set [_k, (((_n getOrDefault [_k, 0]) + _d) max 0) min 1]; };
private _hands = missionNamespace getVariable ["COMSPEC_ATAK_HandsBlood", 0];
if !("dirtSeed" in _n) then { _n set ["dirtSeed", floor random 100000]; };
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
            private _r = 0.0015;
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
            if ((_n getOrDefault ["blood", 0]) > 0) then { ["blood", -0.004 * rain] call _add; ["prints", 0.003 * rain] call _add; };
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
            ["prints", 0.004 + 0.01 * ((_n getOrDefault ["dust", 0]) min 1) * (parseNumber ((stance player) isEqualTo "PRONE"))] call _add;
            if (_hands > 0.05) then {
                ["blood", 0.05 * _hands] call _add;
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
        private _b = _n getOrDefault ["blood", 0];
        if (_b > 0) then { _n set ["blood", _b * 0.75]; ["prints", _b * 0.15] call _add; };
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
        // Mains en sang : on étale autant qu'on essuie.
        private _dirty = _hands > 0.3;
        _n set ["dust", (_n getOrDefault ["dust", 0]) * 0.12];
        _n set ["drops", 0];
        _n set ["prints", (_n getOrDefault ["prints", 0]) * ([0.15, 0.6] select _dirty)];
        private _b = _n getOrDefault ["blood", 0];
        _n set ["blood", _b * ([0.4, 0.8] select _dirty)];
        if (_b > 0.05) then { ["prints", _b * 0.2] call _add; };
        _n set ["frameBlood", (_n getOrDefault ["frameBlood", 0]) * ([0.5, 0.9] select _dirty)];
    };
};
missionNamespace setVariable ["COMSPEC_ATAK_HandsBlood", _hands];
missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
true
