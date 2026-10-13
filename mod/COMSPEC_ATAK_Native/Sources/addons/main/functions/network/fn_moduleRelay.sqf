/*
    Module « ATAK · Relais » (Eden et Zeus) : pose un relais du réseau ATAK natif.
    Eden : le cercle du module est la portée (étirez-le) ; objets synchronisés = ce qui porte l'antenne (mât, bâtiment,
    véhicule qui devient relais mobile). Sans objet synchronisé, le module crée l'objet du type choisi à sa place.
    Zeus : formulaire à la pose, module lâché sur un objet ou un véhicule pour l'équiper.
    Types (préréglages, chaque valeur à 0 ou « préréglage » prend celle du type) :
      portable  : radio relais au sol, 1,5 km, 4 Mbit/s, antenne à 2,5 m, 8 places
      mat       : mât tactique, 3 km, 8 Mbit/s, antenne à 10 m, 24 places
      tour      : tour fixe, 6 km, 20 Mbit/s, antenne à 25 m (hauteur réelle sur un pylône COMSPEC), 64 places ;
                  sans objet synchronisé, crée la tour relais 3D de 40 m (COMSPEC_ATAK_RelayTower_40)
      satellite : terminal satellite, 1 km, 10 Mbit/s, antenne à 2 m, 16 places
    Alimentation : secteur par défaut (relais réel posé sur la carte) ; batterie seulement si la mission la choisit.
    Liaison : « raccordé » (satellite, fibre : porte le réseau seul) ou « répéteur » (doit voir un relais raccordé,
    de proche en proche, fn_relayNet). Variables posées sur l'objet : voir fn_relayNet "list".
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _kinds = [["Radio relais portable", "portable"], ["Mât tactique", "mat"], ["Tour fixe", "tour"], ["Terminal satellite", "satellite"]];
private _sides = [["Tous les camps", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendants", "GUER"], ["Civils", "CIV"]];
private _auts = [["Secteur / groupe électrogène", 0], ["Batterie 1 h", 60], ["Batterie 2 h", 120], ["Batterie 4 h", 240], ["Batterie 8 h", 480], ["Batterie 12 h", 720], ["Batterie 24 h", 1440]];
private _ups = [["Raccordé (satellite ou fibre)", "root"], ["Répéteur (passe par un relais raccordé)", "repeater"]];
// Bande de fréquence : retenue sur le relais (COMSPEC_AtakRelayFreq, MHz, 0 = automatique) pour les fréquences à venir.
private _freqs = [["Automatique", 0], ["UHF 400 MHz", 400], ["L 1,4 GHz", 1400], ["S 2,4 GHz", 2400], ["C 4,9 GHz", 4900]];
// Portée Eden : le cercle du module (le plus grand des deux axes).
private _area = _logic getVariable ["objectArea", []];
private _eden = if ((count _area) >= 2) then { (_area select 0) max (_area select 1) } else { 0 };

private _cur = [
    _logic getVariable ["Name", ""], _logic getVariable ["Kind", "mat"], _logic getVariable ["Side", ""],
    [_logic getVariable ["Range", 0], _eden] select (_eden > 0),
    _logic getVariable ["Throughput", 0], _logic getVariable ["Height", 0], _logic getVariable ["Slots", 0],
    _logic getVariable ["Autonomy", 0], _logic getVariable ["Uplink", "root"],
    _logic getVariable ["StartOn", true], _logic getVariable ["Indestructible", false], _logic getVariable ["Required", false],
    _logic getVariable ["Freq", 0]
];

private _apply = {
    params ["_vals", "_args"];
    _args params ["_ctx", "_logicPos", "_logicDir"];
    _vals params ["_name", "_kind", "_side", "_range", "_mb", "_h", "_slots", "_aut", "_up", "_on", "_indes", "_req", ["_freq", 0]];
    private _preset = createHashMapFromArray [
        ["portable", [1500, 4, 2.5, 8, 0, ["Land_PortableLongRangeRadio_F", "Land_SurvivalRadio_F"]]],
        ["mat", [3000, 8, 10, 24, 0, ["Land_Antenna_01_F", "Land_TTowerSmall_1_F"]]],
        ["tour", [6000, 20, 25, 64, 0, ["COMSPEC_ATAK_RelayTower_40", "Land_TTowerBig_2_F", "Land_TTowerBig_1_F", "Land_TTowerSmall_2_F"]]],
        ["satellite", [1000, 10, 2, 16, 0, ["Land_SatelliteAntenna_01_F", "Land_Antenna_01_F"]]]
    ] getOrDefault [_kind, [3000, 8, 10, 24, 0, ["Land_Antenna_01_F"]]];
    _preset params ["_pR", "_pMb", "_pH", "_pSl", "_pAut", "_cls"];
    private _hUser = _h;
    if (_range <= 0) then { _range = _pR; };
    if (_mb <= 0) then { _mb = _pMb; };
    if (_h <= 0) then { _h = _pH; };
    if (_slots <= 0) then { _slots = _pSl; };
    if (_aut < 0) then { _aut = _pAut; };
    _range = (_range max 100) min 30000;
    if (_name isEqualTo "") then { _name = format ["Relais %1", mapGridPosition _logicPos]; };
    private _targets = +(_ctx get "targets");
    if (_targets isEqualTo [] && {!isNull (_ctx get "near")}) then { _targets pushBack (_ctx get "near"); };
    if (_targets isEqualTo []) then {
        _cls = _cls select { isClass (configFile >> "CfgVehicles" >> _x) };
        if (_cls isEqualTo []) exitWith {};
        private _o = createVehicle [_cls select 0, _logicPos, [], 0, "CAN_COLLIDE"];
        _o setDir _logicDir;
        _o setPosATL _logicPos;
        { [_x, [[_o], true]] remoteExec ["addCuratorEditableObjects", 2]; } forEach allCurators;
        _targets pushBack _o;
    };
    if (_targets isEqualTo []) exitWith {
        ["comspec_atak_native_dataNote", ["Relais : aucun objet pour porter l'antenne."]] call CBA_fnc_localEvent;
    };
    private _list = (missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x };
    {
        private _p = getPosATL _x;
        private _n = [_name, format ["%1 %2", _name, _forEachIndex + 1]] select (_forEachIndex > 0);
        _x setVariable ["COMSPEC_AtakRelay", true, true];
        _x setVariable ["COMSPEC_AtakRelayName", _n, true];
        _x setVariable ["COMSPEC_AtakRelayIdentity", _n, true];
        _x setVariable ["COMSPEC_AtakRelayUid", format ["relay_%1_%2_%3", round (_p select 0), round (_p select 1), round random 9999], true];
        _x setVariable ["COMSPEC_AtakRelayKind", _kind, true];
        _x setVariable ["COMSPEC_AtakRelayRange", _range, true];
        _x setVariable ["COMSPEC_AtakRelayThroughput", _mb, true];
        // Pylône relais 3D : hauteur réelle de ses antennes quand le module laisse la hauteur au type.
        private _hc = getNumber (configOf _x >> "comspec_atak_relayHeight");
        _x setVariable ["COMSPEC_AtakRelayHeight", [_h, _hc] select (_hUser <= 0 && {_hc > 0}), true];
        _x setVariable ["COMSPEC_AtakRelaySlots", round _slots, true];
        _x setVariable ["COMSPEC_AtakRelaySide", _side, true];
        _x setVariable ["COMSPEC_AtakRelayRoot", _up isNotEqualTo "repeater", true];
        _x setVariable ["COMSPEC_AtakRelayOn", _on, true];
        _x setVariable ["COMSPEC_AtakRelayFreq", _freq, true];
        _x setVariable ["COMSPEC_AtakRelayAutonomy", _aut, true];
        _x setVariable ["COMSPEC_AtakRelayBatt", [-1, 100] select (_aut > 0), true];
        _x setVariable ["COMSPEC_AtakRelayIp", format ["10.%1.%2.1", ((round abs (_p select 0)) mod 220) + 10, ((round abs (_p select 1)) mod 220) + 10], true];
        _x setVariable ["COMSPEC_AtakRelayReliability", 92, true];
        if (_indes) then { [_x, false] remoteExec ["allowDamage", _x]; };
        _list pushBackUnique _x;
        // Fiche du relais remontée à Athena quand l'addon Overwatch est chargé (serveur).
        if (isServer && {!isNil "comspec_overwatch_connect_fnc_syncAtakRelay"}) then { [_x, true] call comspec_overwatch_connect_fnc_syncAtakRelay; };
    } forEach _targets;
    missionNamespace setVariable ["COMSPEC_AtakRelays", _list, true];
    if (_req) then { missionNamespace setVariable ["COMSPEC_ATAK_RelaysRequired", true, true]; };
    ["comspec_atak_native_dataNote", [format ["Relais %1 posé : %2 m, %3 Mbit/s, %4.", _name, round _range, _mb, ["répéteur", "raccordé"] select (_up isNotEqualTo "repeater")]]] call CBA_fnc_localEvent;
};

private _args = [_ctx, getPosATL _logic, getDir _logic];
if (_ctx get "zeus") then {
    [
        "Relais ATAK",
        [
            ["EDIT", "Nom", _cur select 0],
            ["COMBO", "Type", _kinds, _cur select 1],
            ["COMBO", "Réseau du camp", _sides, _cur select 2],
            ["SLIDER", "Portée (m, 0 = type)", [0, 15000, _cur select 3]],
            ["SLIDER", "Débit Mbit/s (0 = type)", [0, 50, _cur select 4]],
            ["SLIDER", "Hauteur d'antenne m (0 = type)", [0, 60, _cur select 5]],
            ["SLIDER", "Places (0 = type)", [0, 128, _cur select 6]],
            ["COMBO", "Alimentation", _auts, _cur select 7],
            ["COMBO", "Liaison", _ups, _cur select 8],
            ["CHECK", "Allumé", _cur select 9],
            ["CHECK", "Indestructible", _cur select 10],
            ["CHECK", "Relais obligatoires", _cur select 11],
            ["COMBO", "Bande de fréquence", _freqs, _cur select 12]
        ],
        _apply, _args,
        "Lâchez le module sur un objet ou un véhicule pour l'équiper, sinon l'objet du type est créé. Répéteur : doit voir un relais raccordé. Relais obligatoires : sans relais, presque plus de réseau."
    ] call comspec_atak_native_fnc_zeusForm;
} else {
    [_cur, _args] call _apply;
};
deleteVehicle _logic;
true
