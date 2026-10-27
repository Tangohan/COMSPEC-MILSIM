/*
    Antennes du jeu converties en relais du réseau ATAK (réglage serveur comspec_atak_native_relay_convert) :
      0 : rien, par défaut (TTRD 2026-10-26 : une antenne n'est relais que désignée, module « ATAK · Relais » en Eden ou Zeus) ;
      1 : objets posés dans la mission (Eden, Zeus, script) ;
      2 : objets de la mission et antennes du décor de la carte (type de carte « TRANSMITTER » : pylônes et tours de
          transmission, antennes de toit), 60 au plus, les plus hautes d'abord.
    Objets reconnus : pylônes et mâts de télécommunication (Land_TTowerBig_*, Land_TTowerSmall_*, Land_Communication_*,
    tours Vysilac / Telek de Chernarus), antennes omnidirectionnelles (OmniDirectionalAntenna_*), antennes et paraboles
    (…Antenna…, …SatelliteAntenna…), et tout objet dont la classe contient l'un de ces mots. Pas nos pylônes COMSPEC
    (déjà relais, fn_relayTower), ni ce qui est déjà relais (module « ATAK · Relais »), ni les véhicules.
    Réglages du relais selon la hauteur de l'objet (antenne à 90 % de la hauteur) :
      30 m et plus : tour fixe, 6 km, 20 Mbit/s, 64 places ; 12 à 30 m : mât, 3 à 5 km, 8 Mbit/s, 24 places ;
      moins de 12 m : antenne, 1,5 km, 4 Mbit/s, 8 places ; parabole : terminal satellite, 1 km, 10 Mbit/s, 16 places.
    Décor de la carte : un objet invisible (Land_HelipadEmpty_F) au pied de l'antenne porte le relais ; il est retiré
    quand l'antenne du décor est détruite (vérifié toutes les 15 s), les téléphones reçoivent « Relais perdu ».
    Objets de mission : mêmes variables que fn_relayTower, posées sur l'objet ; détruit = relais « détruit » (fn_relayNet).
    Serveur seulement.
      ["sweep"] call comspec_atak_native_fnc_relayConvert   (début de mission, puis toutes les 60 s : objets créés depuis)
      ["check"] call ...                                     (antennes du décor détruites)
*/
params [["_mode", "sweep"]];
if (!isServer) exitWith {};
private _level = (missionNamespace getVariable ["comspec_atak_native_relay_convert", 0]) max 0 min 2;
private _keys = ["ttower", "communication", "vysilac", "telek", "antenna", "transmitter", "radiotower", "radio_tower", "radiomast"];
private _match = {
    params ["_cls"];
    private _l = toLower _cls;
    !("comspec" in _l) && {(_keys findIf { _x in _l }) >= 0}
};
// Préréglage d'un relais d'après la classe et la hauteur : [type, portée m, Mbit/s, hauteur antenne m, places].
private _preset = {
    params ["_cls", "_h"];
    private _l = toLower _cls;
    // Portée et débit de base : réglages addon par type (comspec_atak_native_relay_<type>_range / _mbps).
    private _rg = { params ["_k", "_d"]; missionNamespace getVariable [format ["comspec_atak_native_relay_%1_range", _k], _d] };
    private _db = { params ["_k", "_d"]; missionNamespace getVariable [format ["comspec_atak_native_relay_%1_mbps", _k], _d] };
    if ("satellite" in _l || {"dish" in _l}) exitWith { ["satellite", ["satellite", 1000] call _rg, ["satellite", 10] call _db, (_h * 0.9) max 2, 16] };
    if (_h >= 30) exitWith { ["tour", ["tour", 6000] call _rg, ["tour", 20] call _db, _h * 0.9, 64] };
    if (_h >= 12) exitWith { ["mat", round ((["mat", 3000] call _rg) + (_h - 12) * 110), ["mat", 8] call _db, _h * 0.9, 24] };
    ["antenne", ["portable", 1500] call _rg, ["portable", 4] call _db, (_h * 0.9) max 2.5, 8]
};
private _register = {
    params ["_o", "_src", "_cls", "_h"];
    ([_cls, _h] call _preset) params ["_kind", "_range", "_mb", "_ant", "_slots"];
    private _p = getPosATL _src;
    private _n = format ["%1 %2", ["Antenne", "Tour", "Mât", "Parabole"] select (["antenne", "tour", "mat", "satellite"] find _kind), mapGridPosition _p];
    _o setVariable ["COMSPEC_AtakRelay", true, true];
    _o setVariable ["COMSPEC_AtakRelayName", _n, true];
    _o setVariable ["COMSPEC_AtakRelayIdentity", _n, true];
    _o setVariable ["COMSPEC_AtakRelayUid", format ["relay_conv_%1_%2", round (_p select 0), round (_p select 1)], true];
    _o setVariable ["COMSPEC_AtakRelayKind", [_kind, "mat"] select (_kind isEqualTo "antenne"), true];
    _o setVariable ["COMSPEC_AtakRelayRange", _range, true];
    _o setVariable ["COMSPEC_AtakRelayThroughput", _mb, true];
    _o setVariable ["COMSPEC_AtakRelayHeight", _ant, true];
    _o setVariable ["COMSPEC_AtakRelaySlots", _slots, true];
    _o setVariable ["COMSPEC_AtakRelaySide", "", true];
    _o setVariable ["COMSPEC_AtakRelayRoot", true, true];
    _o setVariable ["COMSPEC_AtakRelayOn", true, true];
    _o setVariable ["COMSPEC_AtakRelayFreq", 0, true];
    // Antenne existante : sur le secteur, pas de batterie.
    _o setVariable ["COMSPEC_AtakRelayAutonomy", 0, true];
    _o setVariable ["COMSPEC_AtakRelayBatt", -1, true];
    _o setVariable ["COMSPEC_AtakRelayIp", format ["10.%1.%2.1", ((round abs (_p select 0)) mod 220) + 10, ((round abs (_p select 1)) mod 220) + 10], true];
    _o setVariable ["COMSPEC_AtakRelayReliability", 92, true];
    _o setVariable ["COMSPEC_AtakRelayConverted", _cls, true];
    private _list = (missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x };
    _list pushBackUnique _o;
    // Liste locale ici : diffusée une seule fois après le balayage (pas une fois par objet converti).
    missionNamespace setVariable ["COMSPEC_AtakRelays", _list];
    if (!isNil "comspec_overwatch_connect_fnc_syncAtakRelay") then { [_o, true] call comspec_overwatch_connect_fnc_syncAtakRelay; };
};
private _height = {
    params ["_o"];
    (boundingBoxReal _o) params ["_a", "_b"];
    ((_b select 2) - (_a select 2)) max 1
};
switch (_mode) do {
    case "sweep": {
        if (_level < 1) exitWith {};
        // Objets de la mission (Eden, Zeus, script) : immobiles, pas des véhicules. allMissionObjects (lent sur les grosses
        // missions) une seule fois ; ensuite seuls les objets créés depuis, notés par EntityCreated (Zeus, scripts).
        private _new = missionNamespace getVariable "COMSPEC_ATAK_ConvNew";
        if (isNil "_new") then {
            _new = (allMissionObjects "Static") + (allMissionObjects "Thing");
            missionNamespace setVariable ["COMSPEC_ATAK_ConvNew", []];
            addMissionEventHandler ["EntityCreated", {
                params ["_e"];
                if ((_e isKindOf "Static") || {_e isKindOf "Thing"}) then { (missionNamespace getVariable ["COMSPEC_ATAK_ConvNew", []]) pushBack _e; };
            }];
        } else {
            missionNamespace setVariable ["COMSPEC_ATAK_ConvNew", []];
            _new = _new select { !isNull _x };
        };
        private _n = 0;
        {
            if (_x getVariable ["COMSPEC_AtakRelay", false] || {_x getVariable ["COMSPEC_ConvChecked", false]}) then { continue };
            _x setVariable ["COMSPEC_ConvChecked", true];
            if (_x isKindOf "AllVehicles" || {!([typeOf _x] call _match)}) then { continue };
            [_x, _x, typeOf _x, [_x] call _height] call _register;
            _n = _n + 1;
        } forEach _new;
        if (_n > 0) then {
            missionNamespace setVariable ["COMSPEC_AtakRelays", missionNamespace getVariable ["COMSPEC_AtakRelays", []], true];
            ["INFO", "RELAIS", format ["%1 antenne(s) de la mission converties en relais ATAK", _n]] call comspec_atak_native_fnc_log;
        };
        // Décor de la carte : une seule fois par mission.
        if (_level < 2 || {missionNamespace getVariable ["COMSPEC_ATAK_ConvTerrainDone", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_ConvTerrainDone", true];
        private _half = worldSize / 2;
        private _found = (nearestTerrainObjects [[_half, _half, 0], ["TRANSMITTER"], _half * 1.42, false, true]) select { alive _x };
        // Les plus hautes d'abord, 60 au plus (coût de la carte de couverture et du calcul de liaison).
        private _sorted = [_found, [], { -([_x] call _height) }, "ASCEND"] call BIS_fnc_sortBy;
        private _proxies = [];
        {
            private _t = _x;
            private _cls = typeOf _t;
            if (_cls isEqualTo "") then { _cls = (getModelInfo _t) select 0; };
            private _pr = createVehicle ["Land_HelipadEmpty_F", getPosATL _t, [], 0, "CAN_COLLIDE"];
            _pr setPosATL [(getPosATL _t) select 0, (getPosATL _t) select 1, 0];
            [_pr, _t, _cls, [_t] call _height] call _register;
            _proxies pushBack [_pr, _t];
        } forEach (_sorted select [0, 60]);
        missionNamespace setVariable ["COMSPEC_ATAK_ConvProxies", _proxies];
        if (_proxies isNotEqualTo []) then { missionNamespace setVariable ["COMSPEC_AtakRelays", missionNamespace getVariable ["COMSPEC_AtakRelays", []], true]; };
        ["INFO", "RELAIS", format ["%1 antenne(s) du décor converties en relais ATAK", count _proxies]] call comspec_atak_native_fnc_log;
    };
    case "check": {
        private _proxies = missionNamespace getVariable ["COMSPEC_ATAK_ConvProxies", []];
        if (_proxies isEqualTo []) exitWith {};
        private _keep = [];
        {
            _x params ["_pr", "_t"];
            if (isNull _t || {!alive _t}) then {
                if (!isNull _pr) then { deleteVehicle _pr; };
            } else { _keep pushBack _x; };
        } forEach _proxies;
        if ((count _keep) < (count _proxies)) then {
            missionNamespace setVariable ["COMSPEC_ATAK_ConvProxies", _keep];
            missionNamespace setVariable ["COMSPEC_AtakRelays", (missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x }, true];
        };
    };
};
