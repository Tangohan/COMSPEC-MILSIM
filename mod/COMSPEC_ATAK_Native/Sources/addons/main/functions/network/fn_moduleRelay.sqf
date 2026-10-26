/*
    Module « ATAK · Relais » (Eden et Zeus) : pose un relais du réseau ATAK natif.
    Eden : le cercle du module est la portée (étirez-le) ; objets synchronisés = ce qui porte l'antenne (mât, bâtiment,
    véhicule qui devient relais mobile). Sans objet synchronisé, le module crée l'objet du type choisi à sa place.
    Zeus : formulaire à la pose, module lâché sur un objet ou un véhicule pour l'équiper. Lâché sur un relais existant
    (pylône compris), le formulaire reprend ses valeurs : c'est la modification du relais (aussi par l'éditeur
    d'attributs ZEN, fn_relayZeus).
    Types (préréglages, chaque valeur à 0 ou « préréglage » prend celle du type) :
      portable  : radio relais au sol, 1,5 km, 4 Mbit/s, antenne à 2,5 m, 8 places
      mat       : mât tactique, 3 km, 8 Mbit/s, antenne à 10 m, 24 places ;
                  sans objet synchronisé, crée le mât relais 3D de 12 m (COMSPEC_ATAK_RelayTower_M12)
      tour      : tour fixe, 6 km, 20 Mbit/s, antenne à 25 m (hauteur réelle sur un pylône COMSPEC), 64 places ;
                  sans objet synchronisé, crée la tour relais 3D de 40 m (COMSPEC_ATAK_RelayTower_40)
      satellite : terminal satellite, 1 km, 10 Mbit/s, antenne à 2 m, 16 places
    Alimentation : secteur par défaut (relais réel posé sur la carte) ; batterie seulement si la mission la choisit.
    Liaison : « raccordé » (satellite, fibre : porte le réseau seul) ou « répéteur » (doit voir un relais raccordé,
    de proche en proche, fn_relayNet). Variables posées sur l'objet : voir fn_relayNet "list".
    Condition d'activation (fn_relayCond) : toujours, mât ou antenne déployé(e), véhicule à l'arrêt, moteur coupé, ou code
    SQF (Eden : champ « Condition (code) », _this = l'objet). Exemple : GAZ-66 radio de RHS relais seulement mât dressé.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _kinds = [["Radio relais portable", "portable"], ["Mât tactique", "mat"], ["Tour fixe", "tour"], ["Terminal satellite", "satellite"]];
private _sides = [["Tous les camps", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendants", "GUER"], ["Civils", "CIV"]];
private _auts = [["Secteur / groupe électrogène", 0], ["Batterie 1 h", 60], ["Batterie 2 h", 120], ["Batterie 4 h", 240], ["Batterie 8 h", 480], ["Batterie 12 h", 720], ["Batterie 24 h", 1440]];
private _ups = [["Raccordé (satellite ou fibre)", "root"], ["Répéteur (passe par un relais raccordé)", "repeater"]];
// Fréquence (COMSPEC_AtakRelayFreq, MHz, 0 = automatique) : deux relais voisins sur le même canal se brouillent (fn_relayNet).
// Condition d'activation (COMSPEC_AtakRelayCond, fn_relayCond).
private _conds = [["Toujours", ""], ["Mât ou antenne déployé(e)", "mast"], ["Véhicule à l'arrêt", "stopped"], ["Moteur coupé", "engineoff"]];
private _freqs = [["Automatique", 0], ["UHF 400 MHz", 400], ["L 1,4 GHz", 1400], ["S 2,4 GHz", 2400], ["C 4,9 GHz", 4900]]
    + ([4940, 4945, 4950, 4955, 4960, 4965, 4970, 4975] apply { [format ["C %1 MHz", _x], _x] });
// Portée Eden : le cercle du module (le plus grand des deux axes).
private _area = _logic getVariable ["objectArea", []];
private _eden = if ((count _area) >= 2) then { (_area select 0) max (_area select 1) } else { 0 };

private _cur = [
    _logic getVariable ["Name", ""], _logic getVariable ["Kind", "mat"], _logic getVariable ["Side", ""],
    [_logic getVariable ["Range", 0], _eden] select (_eden > 0),
    _logic getVariable ["Throughput", 0], _logic getVariable ["Height", 0], _logic getVariable ["Slots", 0],
    _logic getVariable ["Autonomy", 0], _logic getVariable ["Uplink", "root"],
    _logic getVariable ["StartOn", true], _logic getVariable ["Indestructible", false], _logic getVariable ["Required", false],
    _logic getVariable ["Freq", 0], _logic getVariable ["RelayOnly", false],
    [_logic getVariable ["Cond", ""], _logic getVariable ["CondCode", ""]] select ((trim (_logic getVariable ["CondCode", ""])) isNotEqualTo "")
];
// Module lâché sur un relais existant (Zeus) : le formulaire part de ses valeurs.
private _edit = [(_ctx get "target"), _ctx getOrDefault ["near", objNull]] select (isNull (_ctx get "target"));
if ((_ctx get "zeus") && {!isNull _edit} && {_edit getVariable ["COMSPEC_AtakRelay", false]}) then {
    _cur = [
        _edit getVariable ["COMSPEC_AtakRelayName", ""], _edit getVariable ["COMSPEC_AtakRelayKind", "tour"], _edit getVariable ["COMSPEC_AtakRelaySide", ""],
        _edit getVariable ["COMSPEC_AtakRelayRange", 0], _edit getVariable ["COMSPEC_AtakRelayThroughput", 0], _edit getVariable ["COMSPEC_AtakRelayHeight", 0],
        _edit getVariable ["COMSPEC_AtakRelaySlots", 0], _edit getVariable ["COMSPEC_AtakRelayAutonomy", 0],
        ["repeater", "root"] select (_edit getVariable ["COMSPEC_AtakRelayRoot", true]),
        _edit getVariable ["COMSPEC_AtakRelayOn", true], _edit getVariable ["COMSPEC_AtakRelayIndestructible", !(isDamageAllowed _edit)], missionNamespace getVariable ["COMSPEC_ATAK_RelaysRequired", false],
        _edit getVariable ["COMSPEC_AtakRelayFreq", 0], missionNamespace getVariable ["COMSPEC_ATAK_RelayOnly", false],
        _edit getVariable ["COMSPEC_AtakRelayCond", ""]
    ];
    // Condition en code (posée dans Eden ou par script) : gardée telle quelle dans la liste du formulaire.
    if !((_cur select 14) in (_conds apply { _x select 1 })) then { _conds pushBack [format ["Code : %1", (_cur select 14) select [0, 40]], _cur select 14]; };
    if !((_cur select 7) in (_auts apply { _x select 1 })) then { _auts pushBack [format ["Batterie %1 min", _cur select 7], _cur select 7]; };
};

private _apply = {
    params ["_vals", "_args"];
    _args params ["_ctx", "_logicPos", "_logicDir"];
    _vals params ["_name", "_kind", "_side", "_range", "_mb", "_h", "_slots", "_aut", "_up", "_on", "_indes", "_req", ["_freq", 0], ["_only", false], ["_cond", ""]];
    private _preset = createHashMapFromArray [
        ["portable", [1500, 4, 2.5, 8, 0, ["Land_PortableLongRangeRadio_F", "Land_SurvivalRadio_F"]]],
        ["mat", [3000, 8, 10, 24, 0, ["COMSPEC_ATAK_RelayTower_M12", "Land_TTowerSmall_1_F", "Land_Antenna_01_F"]]],
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
    // Zeus : l'objet proche (mur, bâtiment de la carte) n'est repris que si c'est déjà un relais ; sinon le module crée son
    // objet 3D (lâcher le module sur un objet pour l'équiper).
    private _near = _ctx getOrDefault ["near", objNull];
    if (_targets isEqualTo [] && {!isNull _near} && {!(_ctx get "zeus") || {_near getVariable ["COMSPEC_AtakRelay", false]}}) then { _targets pushBack _near; };
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
        if (_ctx get "zeus") then { ["feedback", ["Relais : aucun objet pour porter l'antenne."]] call comspec_atak_native_fnc_zeusMenu; };
    };
    private _list = (missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x };
    {
        private _p = getPosATL _x;
        private _n = [_name, format ["%1 %2", _name, _forEachIndex + 1]] select (_forEachIndex > 0);
        // Modification d'un relais existant : identifiant et batterie gardés (batterie remise à 100 % seulement si l'autonomie change).
        private _was = _x getVariable ["COMSPEC_AtakRelay", false];
        private _keepBatt = _was && {(_x getVariable ["COMSPEC_AtakRelayAutonomy", 0]) isEqualTo _aut};
        _x setVariable ["COMSPEC_AtakRelay", true, true];
        _x setVariable ["COMSPEC_AtakRelayName", _n, true];
        _x setVariable ["COMSPEC_AtakRelayIdentity", _n, true];
        if (!_was || {(_x getVariable ["COMSPEC_AtakRelayUid", ""]) isEqualTo ""}) then {
            _x setVariable ["COMSPEC_AtakRelayUid", format ["relay_%1_%2_%3", round (_p select 0), round (_p select 1), round random 9999], true];
        };
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
        _x setVariable ["COMSPEC_AtakRelayCond", trim _cond, true];
        // Condition en code : copie locale du serveur, seule évaluée (fn_relayCond ne compile jamais la variable publique).
        // Un Zeus client ne peut que garder le code déjà posé sur le serveur (Eden ou script serveur).
        if (isServer && {!((toLower trim _cond) in ["", "stopped", "engineoff", "mast"])}) then { _x setVariable ["COMSPEC_AtakRelayCondSrv", trim _cond]; };
        _x setVariable ["COMSPEC_AtakRelayAutonomy", _aut, true];
        if (!_keepBatt) then { _x setVariable ["COMSPEC_AtakRelayBatt", [-1, 100] select (_aut > 0), true]; };
        _x setVariable ["COMSPEC_AtakRelayIp", format ["10.%1.%2.1", ((round abs (_p select 0)) mod 220) + 10, ((round abs (_p select 1)) mod 220) + 10], true];
        _x setVariable ["COMSPEC_AtakRelayReliability", 92, true];
        if (_indes || {_was}) then {
            [_x, !_indes] remoteExec ["allowDamage", _x];
            // allowDamage ne vaut que chez le propriétaire : perdu quand l'objet change de main (véhicule relais pris par un
            // joueur). Drapeau public et événement « Local » sur tous les postes (JIP compris) qui le réapplique au nouveau
            // propriétaire.
            _x setVariable ["COMSPEC_AtakRelayIndestructible", _indes, true];
            if (_indes && {!(_x getVariable ["COMSPEC_AtakRelayIndesEh", false])}) then {
                _x setVariable ["COMSPEC_AtakRelayIndesEh", true, true];
                [_x, ["Local", {
                    params ["_o", "_isLocal"];
                    private _f = _o getVariable "COMSPEC_AtakRelayIndestructible";
                    if (_isLocal && {!isNil "_f"} && {_f isEqualType true}) then { _o allowDamage !_f; };
                }]] remoteExec ["addEventHandler", 0, _x];
            };
        };
        _list pushBackUnique _x;
        // Fiche du relais remontée à Athena quand l'addon Overwatch est chargé (serveur).
        if (isServer && {!isNil "comspec_overwatch_connect_fnc_syncAtakRelay"}) then { [_x, true] call comspec_overwatch_connect_fnc_syncAtakRelay; };
    } forEach _targets;
    missionNamespace setVariable ["COMSPEC_AtakRelays", _list, true];
    if (_req) then { missionNamespace setVariable ["COMSPEC_ATAK_RelaysRequired", true, true]; };
    if (_only) then { missionNamespace setVariable ["COMSPEC_ATAK_RelayOnly", true, true]; };
    missionNamespace setVariable ["COMSPEC_ATAK_RelayCache", nil, true];
    private _msg = format ["Relais %1 posé : %2 m, %3 Mbit/s, %4%5.", _name, round _range, _mb, ["répéteur", "raccordé"] select (_up isNotEqualTo "repeater"),
        ["", format [", %1 MHz", _freq]] select (_freq > 0)];
    ["comspec_atak_native_dataNote", [_msg]] call CBA_fnc_localEvent;
    if (_ctx get "zeus") then { ["feedback", [_msg]] call comspec_atak_native_fnc_zeusMenu; };
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
            ["COMBO", "Fréquence", _freqs, _cur select 12],
            ["CHECK", "Réseau uniquement par les relais", _cur select 13],
            ["COMBO", "Actif seulement si", _conds, _cur select 14]
        ],
        _apply, _args,
        "Lâchez le module sur un objet ou un véhicule pour l'équiper, sinon l'objet du type est créé. Répéteur : doit voir un relais raccordé. Relais obligatoires : sans relais, presque plus de réseau. Réseau exclusivement par relais : sans relais, aucun réseau. Lâché sur un relais existant : modifie ce relais. Actif seulement si : par exemple mât déployé, pour un véhicule radio qui ne relaie que mât dressé."
    ] call comspec_atak_native_fnc_zeusForm;
} else {
    [_cur, _args] call _apply;
};
deleteVehicle _logic;
true
